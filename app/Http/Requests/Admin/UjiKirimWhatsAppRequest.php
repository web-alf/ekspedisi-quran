<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class UjiKirimWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::WHATSAPP_SYSTEM_TEST->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nomor' => ['required', 'string', 'min:8', 'max:20'],
            'pesan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor.required' => 'Nomor tujuan wajib diisi.',
            'nomor.min' => 'Nomor tujuan terlalu pendek.',
        ];
    }
}
