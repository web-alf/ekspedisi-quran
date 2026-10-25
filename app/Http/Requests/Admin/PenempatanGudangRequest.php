<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleEnum;
use App\Models\User;
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
            // null = "belum ditempatkan" (sengaja boleh untuk staf, mis. pindah tugas
            // sementara). SPV WAJIB bergudang: SPV tanpa gudang tidak bisa bekerja sama
            // sekali (lihat CakupanGudang), jadi melepasnya hanya mematikan akunnya.
            'gudang_id' => [
                $this->targetAdalahSpv() ? 'required' : 'present',
                'nullable',
                'integer',
                Rule::exists('gudang', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gudang_id.present' => 'Pilih gudang tujuan.',
            'gudang_id.required' => 'SPV Gudang wajib ditempatkan di salah satu gudang.',
            'gudang_id.exists' => 'Gudang tidak ditemukan atau tidak aktif.',
        ];
    }

    private function targetAdalahSpv(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $target->hasRole(RoleEnum::SPV_GUDANG->value);
    }
}
