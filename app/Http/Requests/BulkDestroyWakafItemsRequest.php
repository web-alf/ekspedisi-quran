<?php

namespace App\Http\Requests;

use App\Enums\PermissionEnum;
use App\Models\Donatur;
use App\Models\WakafItem;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyWakafItemsRequest extends FormRequest
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
            'wakaf_item_ids' => ['required', 'array', 'min:1', 'max:50'], // Reduced for safety
            'wakaf_item_ids.*' => ['required', 'integer', 'exists:wakaf_items,id'],
            'confirm_deletion' => ['required', 'boolean', 'accepted'], // Enhanced: Require explicit confirmation
            'deletion_reason' => ['nullable', 'string', 'max:255'], // Enhanced: Optional deletion reason
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'wakaf_item_ids.required' => 'Item wakaf yang akan dihapus harus dipilih.',
            'wakaf_item_ids.array' => 'Data item wakaf harus berupa array.',
            'wakaf_item_ids.min' => 'Minimal pilih 1 item wakaf untuk dihapus.',
            'wakaf_item_ids.max' => 'Maksimal 50 item wakaf dapat dihapus sekaligus untuk keamanan data.',
            'wakaf_item_ids.*.required' => 'ID item wakaf harus diisi.',
            'wakaf_item_ids.*.integer' => 'ID item wakaf harus berupa angka.',
            'wakaf_item_ids.*.exists' => 'Item wakaf tidak ditemukan.',
            'confirm_deletion.required' => 'Konfirmasi penghapusan diperlukan.',
            'confirm_deletion.accepted' => 'Anda harus mengonfirmasi penghapusan item.',
            'deletion_reason.max' => 'Alasan penghapusan maksimal 255 karakter.',
        ];
    }

    /**
     * Get custom attributes for validation error messages.
     */
    public function attributes(): array
    {
        return [
            'wakaf_item_ids' => 'item wakaf',
            'wakaf_item_ids.*' => 'ID item wakaf',
        ];
    }

    /**
     * Perform additional validation after basic rules pass with enhanced business logic.
     */
    public function withValidator($validator): void
    {
        // Debug logging
        logger()->info('=== BULK DELETE VALIDATION STARTED ===', [
            'request_data' => $this->all(),
            'basic_validation_passed' => true,
        ]);

        $validator->after(function ($validator) {
            $donatur = $this->route('donatur');

            if (! $donatur instanceof Donatur) {
                $validator->errors()->add('donatur', 'Donatur tidak ditemukan.');

                return;
            }

            // Validate that all items belong to the donatur and can be deleted
            $wakafItems = WakafItem::whereIn('id', $this->wakaf_item_ids)
                ->where('donatur_id', $donatur->id)
                ->with(['pengiriman.status'])
                ->get();

            // Check if all items exist and belong to donatur
            if ($wakafItems->count() !== count($this->wakaf_item_ids)) {
                $foundIds = $wakafItems->pluck('id')->toArray();
                $missingIds = array_diff($this->wakaf_item_ids, $foundIds);

                $validator->errors()->add('wakaf_item_ids',
                    'Beberapa item wakaf tidak ditemukan untuk donatur ini: '.implode(', ', $missingIds));

                return;
            }

            // ENHANCED: Check if all items can be deleted with detailed reasons
            $nonDeletableItems = [];
            $deletableItems = [];
            $statusCounts = ['pending' => 0, 'processed' => 0, 'shipped' => 0, 'delivered' => 0];

            foreach ($wakafItems as $item) {
                $statusCounts[$item->status] = ($statusCounts[$item->status] ?? 0) + 1;

                if (! $item->canBeDeleted()) {
                    $reason = match (true) {
                        $item->status !== 'pending' => "status {$item->status}",
                        $item->pengiriman && ! in_array($item->pengiriman->status->nama ?? '', ['Pending', 'Proses Pemesanan', 'Batal']) => "pengiriman {$item->pengiriman->status->nama}",
                        default => 'tidak dapat dihapus'
                    };
                    $nonDeletableItems[] = "#{$item->global_sequence} ({$item->wakaf_type}) - {$reason}";
                } else {
                    $deletableItems[] = $item;
                }
            }

            if (! empty($nonDeletableItems)) {
                $validator->errors()->add('wakaf_item_ids',
                    'Beberapa item wakaf tidak dapat dihapus: '.implode(', ', array_slice($nonDeletableItems, 0, 5)).
                    (count($nonDeletableItems) > 5 ? ' dan '.(count($nonDeletableItems) - 5).' lainnya.' : ''));

                return;
            }

            // ENHANCED: Business rules validation
            $remainingItemsCount = $donatur->wakafItems()->count() - count($this->wakaf_item_ids);

            if ($remainingItemsCount <= 0) {
                $validator->errors()->add('wakaf_item_ids',
                    'Tidak dapat menghapus semua item wakaf. Donatur harus memiliki minimal 1 item.');

                return;
            }

            // ENHANCED: Validate deletion percentage - don't allow deleting more than 80% at once
            $totalItems = $donatur->wakafItems()->count();
            $deletionPercentage = (count($this->wakaf_item_ids) / $totalItems) * 100;

            if ($deletionPercentage > 80) {
                $validator->errors()->add('wakaf_item_ids',
                    "Tidak dapat menghapus lebih dari 80% item sekaligus ({$deletionPercentage}%). ".
                    'Lakukan penghapusan secara bertahap untuk keamanan data.');

                return;
            }

            // REMOVED: Overly restrictive per-type validation
            // Allow deleting all items of a specific type as long as some items remain overall
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure wakaf_item_ids is an array and contains only unique integers
        $ids = $this->wakaf_item_ids ?? [];

        if (! is_array($ids)) {
            $ids = [];
        }

        // Convert to integers and remove duplicates
        $ids = array_unique(array_map('intval', array_filter($ids, 'is_numeric')));

        $this->merge(['wakaf_item_ids' => array_values($ids)]);
    }
}
