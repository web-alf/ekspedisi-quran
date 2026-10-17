<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Memindahkan satu akun SPV/staf gudang ke gudang lain (atau melepasnya dari gudang).
 * Wewenangnya: super-admin dan manager gudang, BUKAN SPV — lihat
 * User::bolehAturPenempatanGudang().
 */
class PenempatanGudangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->bolehAturPenempatanGudang();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // null = "belum ditempatkan" (sengaja boleh, mis. staf pindah tugas sementara).
            'gudang_id' => ['present', 'nullable', 'integer', Rule::exists('gudang', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gudang_id.present' => 'Pilih gudang tujuan.',
            'gudang_id.exists' => 'Gudang tidak ditemukan atau tidak aktif.',
        ];
    }
}
