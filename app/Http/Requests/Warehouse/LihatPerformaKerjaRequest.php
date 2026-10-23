<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Filter tanggal halaman Performa Kerja: siapa saja freelancer yang ditugaskan
 * pada satu tanggal. Kosong = hari ini. Akses halaman dijaga middleware
 * permission:warehouse.performance.view di rute.
 */
class LihatPerformaKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal.date_format' => 'Format tanggal tidak dikenali.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
        ];
    }

    public function tanggal(): Carbon
    {
        $tanggal = $this->validated('tanggal');

        return $tanggal ? Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay() : today();
    }
}
