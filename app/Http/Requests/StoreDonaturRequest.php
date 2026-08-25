<?php

namespace App\Http\Requests;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreDonaturRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::DONATUR_CREATE->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'kode_donatur' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/'],
            'nama_donatur' => ['required', 'string', 'max:255', 'min:2', 'regex:/^[a-zA-Z\s\.\'\-]+$/u'],
            'no_hp' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) {
                try {
                    $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                    $numberProto = $phoneUtil->parse($value, null);
                    if (! $phoneUtil->isValidNumber($numberProto)) {
                        $fail('Nomor telepon tidak valid.');
                    }
                } catch (\Exception $e) {
                    $fail('Format nomor telepon tidak valid. Gunakan format internasional, contoh: +628123456789.');
                }
            }],
            'email_donatur' => ['nullable', 'email', 'max:255'],
            'alamat_donatur' => ['nullable', 'string', 'max:500'],
            'donation_date' => ['required', 'date', 'before_or_equal:today', 'after:2020-01-01'], // ENHANCED: Date range validation
            'jenis_wakaf_dipilih' => ['required', 'array', 'min:1', 'max:3'],
            'jumlah_a5' => ['nullable', 'integer', 'min:0', 'max:500'],
            'jumlah_a6' => ['nullable', 'integer', 'min:0', 'max:300'],
            'jumlah_iqra' => ['nullable', 'integer', 'min:0', 'max:200'],
            'prayer_mode' => ['required', 'string', 'in:semua_donatur,customize_individual,mixed'],
            'doa_untuk_semua' => ['nullable', 'string', 'max:1000'],
            'wakif_details' => ['required_if:prayer_mode,customize_individual', 'array', 'max:1000'], // ENHANCED: Limit wakif details
            'wakif_details.*.wakif_name' => ['required_if:prayer_mode,customize_individual', 'string', 'max:255', 'min:2', 'regex:/^[a-zA-Z\s\.\'\-]+$/u'],
            'wakif_details.*.doa_request' => ['nullable', 'string', 'max:1000'],
            'wakif_details.*.relationship_to_donatur' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s\.\'\-]+$/u'],
            'wakif_details.*.wakaf_type' => ['required_if:prayer_mode,customize_individual', 'string', 'in:A5,A6,IQRA'],
            'wakif_details.*.sequence_in_type' => ['required_if:prayer_mode,customize_individual', 'integer', 'min:1', 'max:1000'],
            'wakif_details.*.global_sequence' => ['required_if:prayer_mode,customize_individual', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'kode_donatur.required' => 'Kode donatur harus diisi.',
            'kode_donatur.regex' => 'Kode donatur hanya boleh mengandung huruf besar, angka, dan tanda hubung.',
            'nama_donatur.required' => 'Nama donatur harus diisi.',
            'nama_donatur.min' => 'Nama donatur minimal 2 karakter.',
            'nama_donatur.regex' => 'Nama donatur hanya boleh mengandung huruf, spasi, titik, apostrof, dan tanda hubung.',
            'no_hp.required' => 'Nomor telepon harus diisi.',
            'no_hp.max' => 'Nomor telepon terlalu panjang.',
            'email_donatur.email' => 'Format email tidak valid.',
            'donation_date.required' => 'Tanggal donasi harus diisi.',
            'donation_date.before_or_equal' => 'Tanggal donasi tidak boleh lebih dari hari ini.',
            'donation_date.after' => 'Tanggal donasi tidak boleh sebelum tahun 2020.',
            'jenis_wakaf_dipilih.required' => 'Jenis wakaf harus dipilih.',
            'jenis_wakaf_dipilih.min' => 'Minimal pilih satu jenis wakaf.',
            'jenis_wakaf_dipilih.max' => 'Maksimal pilih 3 jenis wakaf.',
            'jumlah_a5.max' => 'Jumlah Mushaf A5 maksimal 500.',
            'jumlah_a6.max' => 'Jumlah Mushaf A6 maksimal 300.',
            'jumlah_iqra.max' => 'Jumlah IQRA maksimal 200.',
            'prayer_mode.required' => 'Mode doa harus dipilih.',
            'prayer_mode.in' => 'Mode doa tidak valid.',
            'doa_untuk_semua.max' => 'Doa untuk semua maksimal 1000 karakter.',
            'wakif_details.required_if' => 'Detail wakif harus diisi untuk mode kustomisasi individual.',
            'wakif_details.max' => 'Maksimal 1000 detail wakif dapat diinput.',
            'wakif_details.*.wakif_name.min' => 'Nama wakif minimal 2 karakter.',
            'wakif_details.*.wakif_name.regex' => 'Nama wakif hanya boleh mengandung huruf, spasi, titik, apostrof, dan tanda hubung.',
            'wakif_details.*.relationship_to_donatur.regex' => 'Hubungan dengan donatur hanya boleh mengandung huruf, spasi, titik, apostrof, dan tanda hubung.',
        ];
    }

    /**
     * Perform additional validation after basic rules pass with enhanced business logic.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // ENHANCED: Custom validation - jika jenis wakaf dipilih, jumlahnya harus > 0
            $jenisWakaf = $this->jenis_wakaf_dipilih ?? [];

            if (is_array($jenisWakaf) && in_array('A5', $jenisWakaf) && (! $this->jumlah_a5 || $this->jumlah_a5 <= 0)) {
                $validator->errors()->add('jumlah_a5', 'Jumlah Mushaf A5 harus diisi dan lebih dari 0 jika dipilih');
            }

            if (is_array($jenisWakaf) && in_array('A6', $jenisWakaf) && (! $this->jumlah_a6 || $this->jumlah_a6 <= 0)) {
                $validator->errors()->add('jumlah_a6', 'Jumlah Mushaf A6 harus diisi dan lebih dari 0 jika dipilih');
            }

            if (is_array($jenisWakaf) && in_array('IQRA', $jenisWakaf) && (! $this->jumlah_iqra || $this->jumlah_iqra <= 0)) {
                $validator->errors()->add('jumlah_iqra', 'Jumlah IQRA harus diisi dan lebih dari 0 jika dipilih');
            }

            // ENHANCED: Validate total quantities are reasonable
            $totalQuantity = ($this->jumlah_a5 ?? 0) + ($this->jumlah_a6 ?? 0) + ($this->jumlah_iqra ?? 0);
            if ($totalQuantity > 1000) {
                $validator->errors()->add('jumlah_total',
                    "Total semua item tidak boleh lebih dari 1000. Saat ini: {$totalQuantity}.");
            }

            if ($totalQuantity === 0) {
                $validator->errors()->add('jumlah_total', 'Minimal harus ada 1 item wakaf.');
            }

            // ENHANCED: Validate duplicate phone numbers with options
            if ($this->no_hp) {
                $existingDonatur = \App\Models\Donatur::where('no_hp', $this->no_hp)
                    ->where('kode_donatur', '!=', $this->kode_donatur)
                    ->first();

                if ($existingDonatur) {
                    $validator->errors()->add('no_hp',
                        "Nomor HP sudah terdaftar untuk donatur: {$existingDonatur->nama_donatur} ({$existingDonatur->kode_donatur}). ".
                        'Jika ini donatur yang sama, gunakan kode donatur yang sudah ada.');
                }
            }

            // ENHANCED: Validate prayer mode consistency
            if ($this->prayer_mode === 'customize_individual') {
                if (empty($this->wakif_details) || ! is_array($this->wakif_details)) {
                    $validator->errors()->add('wakif_details',
                        'Detail wakif harus diisi untuk mode customize individual.');
                } else {
                    // Validate wakif details count matches total quantity
                    $wakifDetailsCount = count($this->wakif_details);
                    if ($wakifDetailsCount !== $totalQuantity) {
                        $validator->errors()->add('wakif_details',
                            "Jumlah detail wakif ({$wakifDetailsCount}) harus sama dengan total kuantitas ({$totalQuantity}).");
                    }
                }
            }

            // ENHANCED: Validate kode_donatur uniqueness for new donatur creation mode
            $existingByCode = \App\Models\Donatur::where('kode_donatur', $this->kode_donatur)->first();
            if ($existingByCode && $existingByCode->nama_donatur !== $this->nama_donatur) {
                $validator->errors()->add('kode_donatur',
                    "Kode donatur sudah digunakan oleh: {$existingByCode->nama_donatur}. ".
                    'Gunakan kode yang berbeda atau pastikan nama donatur sama persis.');
            }
        });
    }
}
