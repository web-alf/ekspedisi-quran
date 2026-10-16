<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class PerbaruiPengaturanWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::WHATSAPP_SETTINGS_WRITE->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['boolean'],

            // Kunci API hanya diganti bila diisi. Halaman pengaturan tidak pernah
            // menampilkan nilainya (kolomnya `hidden` di model), jadi kolom kosong
            // berarti "jangan ubah", bukan "kosongkan".
            'api_key' => ['nullable', 'string', 'max:500'],

            'base_url' => ['required', 'url', 'max:255'],
            'sender_number' => ['nullable', 'string', 'max:30'],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:120'],
            'max_per_minute' => ['required', 'integer', 'min:1', 'max:600'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_url.required' => 'Alamat dasar StarSender wajib diisi.',
            'base_url.url' => 'Alamat dasar harus berupa URL yang sah.',
            'delay_seconds.required' => 'Jeda antar pesan wajib diisi.',
            'max_per_minute.required' => 'Batas pesan per menit wajib diisi.',
            'quiet_hours_start.date_format' => 'Jam tenang harus dalam format HH:MM.',
            'quiet_hours_end.date_format' => 'Jam tenang harus dalam format HH:MM.',
        ];
    }
}
