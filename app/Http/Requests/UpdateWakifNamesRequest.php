<?php

namespace App\Http\Requests;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWakifNamesRequest extends FormRequest
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
     * For updating individual wakaf item names only
     */
    public function rules(): array
    {
        return [
            'wakif_updates' => ['required', 'array', 'min:1'],
            'wakif_updates.*.wakaf_item_id' => ['required', 'integer', 'exists:wakaf_items,id'],
            'wakif_updates.*.wakif_name' => ['required', 'string', 'max:255'],
            'wakif_updates.*.doa_request' => ['nullable', 'string'],
            'wakif_updates.*.relationship_to_donatur' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'wakif_updates.required' => 'Data update wakif harus disediakan.',
            'wakif_updates.array' => 'Data update wakif harus berupa array.',
            'wakif_updates.min' => 'Minimal harus ada satu update wakif.',
            'wakif_updates.*.wakaf_item_id.required' => 'ID item wakaf harus diisi.',
            'wakif_updates.*.wakaf_item_id.exists' => 'Item wakaf tidak ditemukan.',
            'wakif_updates.*.wakif_name.required' => 'Nama wakif harus diisi.',
            'wakif_updates.*.wakif_name.max' => 'Nama wakif maksimal 255 karakter.',
            'wakif_updates.*.relationship_to_donatur.max' => 'Hubungan dengan donatur maksimal 100 karakter.',
        ];
    }
}
