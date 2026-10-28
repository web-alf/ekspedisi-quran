<?php

namespace App\Http\Requests;

use App\Enums\UkuranLabelResi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CetakResiMassalRequest extends FormRequest
{
    /**
     * Izin sudah dijaga middleware rute cetak massal.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `ukuran` diisi dari halaman pilih ukuran saat resi yang dipilih campuran (A5 + A6).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pengiriman_ids' => ['required', 'array'],
            'pengiriman_ids.*' => ['exists:pengiriman,id'],
            'ukuran' => ['nullable', Rule::enum(UkuranLabelResi::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pengiriman_ids.required' => 'Pilih minimal satu pengiriman untuk dicetak.',
            'pengiriman_ids.*.exists' => 'Pengiriman yang dipilih tidak ditemukan.',
            'ukuran.enum' => 'Ukuran label tidak dikenal.',
        ];
    }
}
