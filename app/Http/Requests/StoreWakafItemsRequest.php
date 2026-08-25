<?php

namespace App\Http\Requests;

use App\Enums\PermissionEnum;
use App\Models\Donatur;
use Illuminate\Foundation\Http\FormRequest;

class StoreWakafItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::DONATUR_UPDATE->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'], // Increased limit but will be validated in business logic
            'items.*.wakaf_type' => ['required', 'string', 'in:A5,A6,IQRA'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'], // Increased per-item limit
            'items.*.wakif_name' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s\.\'\-]+$/u'], // Enhanced validation
            'items.*.doa_request' => ['nullable', 'string', 'max:1000'],
            'items.*.relationship_to_donatur' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s\.\'\-]+$/u'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Data item wakaf harus diisi.',
            'items.array' => 'Data item wakaf harus berupa array.',
            'items.min' => 'Minimal harus ada 1 item wakaf.',
            'items.max' => 'Maksimal 20 jenis item wakaf dapat ditambahkan sekaligus.',
            'items.*.wakaf_type.required' => 'Jenis wakaf harus dipilih.',
            'items.*.wakaf_type.in' => 'Jenis wakaf tidak valid. Pilihan: A5, A6, IQRA.',
            'items.*.quantity.required' => 'Jumlah item harus diisi.',
            'items.*.quantity.integer' => 'Jumlah item harus berupa angka.',
            'items.*.quantity.min' => 'Jumlah item minimal 1.',
            'items.*.quantity.max' => 'Jumlah item maksimal 100 per jenis dalam satu permintaan.',
            'items.*.wakif_name.max' => 'Nama wakif maksimal 255 karakter.',
            'items.*.wakif_name.regex' => 'Nama wakif hanya boleh mengandung huruf, spasi, titik, apostrof, dan tanda hubung.',
            'items.*.doa_request.max' => 'Permintaan doa maksimal 1000 karakter.',
            'items.*.relationship_to_donatur.max' => 'Hubungan dengan donatur maksimal 100 karakter.',
            'items.*.relationship_to_donatur.regex' => 'Hubungan dengan donatur hanya boleh mengandung huruf, spasi, titik, apostrof, dan tanda hubung.',
        ];
    }

    /**
     * Get custom attributes for validation error messages.
     */
    public function attributes(): array
    {
        return [
            'items.*.wakaf_type' => 'jenis wakaf',
            'items.*.quantity' => 'jumlah',
            'items.*.wakif_name' => 'nama wakif',
            'items.*.doa_request' => 'permintaan doa',
            'items.*.relationship_to_donatur' => 'hubungan dengan donatur',
        ];
    }

    /**
     * Perform additional validation after basic rules pass with enhanced business rules.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $donatur = $this->route('donatur');

            if (! $donatur instanceof Donatur) {
                $validator->errors()->add('donatur', 'Donatur tidak ditemukan.');

                return;
            }

            // ENHANCED: Address validation - allow empty addresses with fallback
            // Note: Empty addresses will use default fallback in controller
            if (! empty($donatur->alamat_donatur) && strlen(trim($donatur->alamat_donatur)) < 10) {
                $validator->errors()->add('donatur',
                    'Alamat donatur terlalu singkat. Harap lengkapi alamat untuk pengiriman yang akurat.');
                // Don't return - allow processing with warning
            }

            // ENHANCED: Validate donatur has contact information - allow flexible phone format
            if (empty($donatur->no_hp)) {
                $validator->errors()->add('donatur',
                    'Donatur harus memiliki nomor HP sebelum dapat menambah item wakaf.');

                return;
            }

            // Optional validation for phone format - warn but don't block
            try {
                $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                $numberProto = $phoneUtil->parse($donatur->no_hp, null);
                if (! $phoneUtil->isValidNumber($numberProto)) {
                    $validator->errors()->add('phone_format_warning',
                        'Format nomor telepon tidak standar. Pastikan nomor telepon valid untuk notifikasi pengiriman.');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('phone_format_warning',
                    'Format nomor telepon tidak standar. Pastikan nomor telepon valid untuk notifikasi pengiriman.');
                // Don't return - allow processing with warning
            }

            // Validate total quantity limits per request
            $totalQuantityPerType = [];
            $totalRequestQuantity = 0;

            foreach ($this->items as $index => $item) {
                $type = $item['wakaf_type'];
                $quantity = (int) $item['quantity'];

                $totalQuantityPerType[$type] = ($totalQuantityPerType[$type] ?? 0) + $quantity;
                $totalRequestQuantity += $quantity;
            }

            // ENHANCED: Check individual type limits per request
            foreach ($totalQuantityPerType as $type => $totalQuantity) {
                if ($totalQuantity > 100) {
                    $validator->errors()->add('items',
                        "Total jumlah {$type} tidak boleh lebih dari 100 item dalam satu permintaan.");
                }
            }

            // ENHANCED: Check total request limit
            if ($totalRequestQuantity > 200) {
                $validator->errors()->add('items',
                    "Total item dalam satu permintaan tidak boleh lebih dari 200. Saat ini: {$totalRequestQuantity}.");
            }

            // ENHANCED: Business logic validation with current totals
            $currentItemCount = $donatur->wakafItems()->count();
            $newItemsCount = $totalRequestQuantity;

            // Check maximum items per donatur
            if (($currentItemCount + $newItemsCount) > 1000) {
                $validator->errors()->add('items',
                    'Total item wakaf untuk donatur ini akan melebihi batas maksimal (1000 item). '.
                    "Saat ini: {$currentItemCount}, akan ditambah: {$newItemsCount}.");
            }

            // ENHANCED: Validate per-type limits for the donatur
            foreach ($totalQuantityPerType as $type => $additionalQuantity) {
                $currentTypeCount = $donatur->wakafItems()->where('wakaf_type', $type)->count();
                $maxPerType = match ($type) {
                    'A5' => 500,
                    'A6' => 300,
                    'IQRA' => 200,
                    default => 100
                };

                if (($currentTypeCount + $additionalQuantity) > $maxPerType) {
                    $validator->errors()->add('items',
                        "Total {$type} untuk donatur ini akan melebihi batas maksimal ({$maxPerType} item). ".
                        "Saat ini: {$currentTypeCount}, akan ditambah: {$additionalQuantity}.");
                }
            }

            // ENHANCED: Validate prayer mode compatibility - allow empty wakif names with fallback to donatur name
            if ($donatur->prayer_mode === 'customize_individual') {
                foreach ($this->items as $index => $item) {
                    $wakifName = trim($item['wakif_name'] ?? '');
                    // Only warn if wakif name is empty - controller will use donatur name as fallback
                    if (empty($wakifName)) {
                        // Don't add error - just log info that fallback will be used
                        logger()->info('Wakif name empty for customize_individual mode, will use donatur name as fallback', [
                            'donatur_id' => $donatur->id,
                            'item_index' => $index,
                            'fallback_name' => $donatur->nama_donatur,
                        ]);
                    }
                }
            }

            // ENHANCED: Validate request frequency (prevent spam/abuse)
            $recentRequestsCount = cache()->get("wakaf_requests_count_{$donatur->id}", 0);
            if ($recentRequestsCount >= 10) {
                $validator->errors()->add('items',
                    'Terlalu banyak permintaan dalam waktu singkat. Silakan tunggu beberapa menit.');
            } else {
                // Increment request counter with 1 minute expiry
                cache()->put("wakaf_requests_count_{$donatur->id}", $recentRequestsCount + 1, 60);
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure items is an array
        if (! is_array($this->items)) {
            $this->merge(['items' => []]);
        }

        // Clean and prepare data
        $items = [];
        foreach ($this->items as $item) {
            $items[] = [
                'wakaf_type' => trim($item['wakaf_type'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'wakif_name' => trim($item['wakif_name'] ?? ''),
                'doa_request' => trim($item['doa_request'] ?? ''),
                'relationship_to_donatur' => trim($item['relationship_to_donatur'] ?? '') ?: 'Diri sendiri',
            ];
        }

        $this->merge(['items' => $items]);
    }
}
