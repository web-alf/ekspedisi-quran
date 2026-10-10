<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class PerbaruiTemplateWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::WHATSAPP_TEMPLATES_WRITE->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul template wajib diisi.',
            'content.required' => 'Isi pesan wajib diisi.',
        ];
    }
}
