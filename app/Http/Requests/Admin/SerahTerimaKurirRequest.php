<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionEnum;
use App\Services\SerahTerimaKurirService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulir serah terima kurir: di tujuan, atau dialihkan di tengah jalan.
 *
 * Yang wajib berbeda per mode (keputusan pemilik sistem, 16/10/2026):
 *   - tujuan       : nama orang yang menerima di lokasi. Alamatnya sudah ada di resi.
 *   - tengah_jalan : nama, no HP, dan alamat penerima baru — tanpa itu, mushaf yang
 *                    berpindah tangan di jalan tidak bisa dilacak lagi sama sekali.
 * Foto dan GPS tidak diwajibkan di kedua mode.
 */
class SerahTerimaKurirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionEnum::MUATAN_SCAN->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tengahJalan = $this->input('mode') === SerahTerimaKurirService::MODE_TENGAH_JALAN;

        return [
            'mode' => ['required', Rule::in([SerahTerimaKurirService::MODE_TUJUAN, SerahTerimaKurirService::MODE_TENGAH_JALAN])],
            'pengiriman_ids' => ['required', 'array', 'min:1', 'max:'.SerahTerimaKurirService::MAKS_RESI],
            'pengiriman_ids.*' => ['integer', 'distinct'],
            'kode_kerdus' => ['nullable', 'string', 'max:60', Rule::prohibitedIf($tengahJalan)],
            'nama_penerima' => ['required', 'string', 'max:255'],
            // Kolom tujuan di resi (`receiver_contact`) hanya 20 karakter.
            'no_hp_penerima' => [$tengahJalan ? 'required' : 'nullable', 'string', 'max:20'],
            'alamat_penerima' => [$tengahJalan ? 'required' : 'nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'foto' => ['nullable', 'array', 'max:5'],
            'foto.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pengiriman_ids.required' => 'Pindai minimal satu mushaf atau kerdus dulu.',
            'pengiriman_ids.max' => 'Paling banyak '.SerahTerimaKurirService::MAKS_RESI.' resi per serah terima.',
            'kode_kerdus.prohibited' => 'Penyerahan di tengah jalan dipindai per mushaf, bukan per kerdus.',
            'nama_penerima.required' => 'Nama penerima wajib diisi.',
            'no_hp_penerima.required' => 'No. HP penerima wajib diisi untuk penyerahan di tengah jalan.',
            'no_hp_penerima.max' => 'No. HP paling panjang 20 karakter.',
            'alamat_penerima.required' => 'Alamat penerima wajib diisi untuk penyerahan di tengah jalan.',
            'foto.max' => 'Paling banyak 5 foto.',
            'foto.*.image' => 'Berkas foto harus berupa gambar.',
            'foto.*.max' => 'Ukuran tiap foto paling besar 10 MB.',
        ];
    }
}
