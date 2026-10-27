<?php

namespace App\Http\Requests;

use App\Enums\PermissionEnum;
use App\Models\Donatur;
use App\Rules\DoaMuatResi;
use App\Support\KodeDonatur;
use Illuminate\Foundation\Http\FormRequest;
use libphonenumber\PhoneNumberUtil;

class UpdateDonaturRequest extends FormRequest
{
    /**
     * Seragamkan kode dan nama sebelum divalidasi — sama seperti saat dibuat, supaya
     * penyuntingan tidak bisa menyelipkan "ECB 81" sebagai kode yang berbeda dari "ECB81".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_donatur' => KodeDonatur::bersihkan($this->kode_donatur),
            'nama_donatur' => KodeDonatur::nama($this->nama_donatur),
        ]);
    }

    /**
     * Donatur yang sedang diedit (route model binding, atau id bila belum terikat).
     */
    private function donaturDiubah(): ?Donatur
    {
        $donatur = $this->route('donatur');

        return $donatur instanceof Donatur ? $donatur : Donatur::find($donatur);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::DONATUR_UPDATE->value);
    }

    /**
     * Get the validation rules that apply to the request.
     * Only validates editable fields - quantities are computed from wakaf_items
     */
    public function rules(): array
    {
        return [
            'kode_donatur' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/'],
            'nama_donatur' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:30', function ($attribute, $value, $fail) {
                try {
                    $phoneUtil = PhoneNumberUtil::getInstance();
                    $numberProto = $phoneUtil->parse($value, null);
                    if (! $phoneUtil->isValidNumber($numberProto)) {
                        $fail('Nomor telepon tidak valid.');
                    }
                } catch (\Exception $e) {
                    $fail('Format nomor telepon tidak valid. Gunakan format internasional, contoh: +628123456789.');
                }
            }],
            'email_donatur' => ['nullable', 'email', 'max:255'],
            'alamat_donatur' => ['nullable', 'string'],
            // Doa lama yang tidak diubah tetap diterima walau lebih dari batas (lihat DoaMuatResi).
            'doa_untuk_semua' => ['nullable', 'string', new DoaMuatResi($this->donaturDiubah()?->doa_untuk_semua)],
            'prayer_mode' => ['required', 'string', 'in:semua_donatur,customize_individual,mixed'],
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
            'no_hp.required' => 'Nomor telepon harus diisi.',
            'no_hp.max' => 'Nomor telepon terlalu panjang.',
            'email_donatur.email' => 'Format email tidak valid.',
            'prayer_mode.required' => 'Mode doa harus dipilih.',
            'prayer_mode.in' => 'Mode doa tidak valid. Pilihan yang tersedia: semua_donatur, customize_individual, mixed.',
        ];
    }

    /**
     * Perform additional validation after basic rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $donatur = $this->route('donatur');

            // Uniqueness check (exclude current donatur)
            $existingByCode = Donatur::where('kode_donatur', $this->kode_donatur)
                ->where('id', '!=', $donatur?->id)
                ->first();

            if ($existingByCode) {
                $validator->errors()->add('kode_donatur',
                    "Kode donatur sudah digunakan oleh: {$existingByCode->nama_donatur}. Gunakan kode yang berbeda.");
            }

            // Active shipment guard: only block kode_donatur change if there are active shipments
            if ($donatur && $donatur->kode_donatur !== $this->kode_donatur) {
                $nonCancelledItems = $donatur->wakafItems()
                    ->whereHas('pengiriman', function ($q) {
                        $q->whereHas('status', function ($sq) {
                            $sq->where('nama', '!=', 'Batal');
                        });
                    })
                    ->count();

                if ($nonCancelledItems > 0) {
                    $validator->errors()->add('kode_donatur',
                        'Kode donatur tidak dapat diubah karena sudah memiliki pengiriman aktif. Batalkan pengiriman terlebih dahulu.');
                }
            }
        });
    }
}
