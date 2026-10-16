<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class ScanStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can(PermissionEnum::STATUS_UPDATE->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'qr_data' => [
                'nullable',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    if ($value && ! $this->isValidQRData($value)) {
                        $fail('QR data format tidak valid.');
                    }
                },
            ],
            'no_resi' => [
                'required',
                'string',
                'regex:/^EQ-\d{4}-\d{5}$/',
                'exists:pengiriman,no_resi',
            ],
            'status_id' => [
                'required',
                'integer',
                'exists:status_pengiriman,id',
                function ($attribute, $value, $fail) {
                    $status = \App\Models\StatusPengiriman::find($value);
                    if (! $status || ! $status->is_active) {
                        $fail('Status yang dipilih tidak aktif.');
                    }
                },
            ],
            'catatan' => [
                'nullable',
                'string',
                'max:500',
                // Removed min:3 to allow empty catatan
            ],
            'lokasi' => [
                'nullable',
                'string',
                'max:255',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'dokumentasi' => [
                'nullable',
                'array',
                'max:5', // Maximum 5 files
            ],
            'dokumentasi.*' => [
                'nullable',
                'file',
                'image',
                'max:10240', // 10MB
                'mimes:jpeg,jpg,png,gif,webp',
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'no_resi.required' => 'Nomor resi wajib diisi.',
            'no_resi.regex' => 'Format nomor resi tidak valid. Contoh: EQ-2025-00001',
            'no_resi.exists' => 'Nomor resi tidak ditemukan dalam sistem.',
            'status_id.required' => 'Status baru wajib dipilih.',
            'status_id.exists' => 'Status yang dipilih tidak valid.',
            'catatan.max' => 'Catatan maksimal 500 karakter.',
            'qr_data.max' => 'Data QR terlalu panjang.',
            'lokasi.max' => 'Lokasi maksimal 255 karakter.',
            'latitude.between' => 'Latitude harus antara -90 dan 90.',
            'longitude.between' => 'Longitude harus antara -180 dan 180.',
            'dokumentasi.max' => 'Maksimal 5 file dokumentasi.',
            'dokumentasi.*.image' => 'File harus berupa gambar.',
            'dokumentasi.*.max' => 'Ukuran file maksimal 10MB.',
            'dokumentasi.*.mimes' => 'File harus berformat: jpeg, jpg, png, gif, webp.',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize no_resi
        if ($this->has('no_resi')) {
            $this->merge([
                'no_resi' => strtoupper(trim($this->no_resi)),
            ]);
        }

        // Clean catatan - allow empty strings
        if ($this->has('catatan')) {
            $catatan = trim($this->catatan ?? '');
            $this->merge([
                'catatan' => $catatan === '' ? null : $catatan,
            ]);
        }

        // Clean lokasi - allow empty strings
        if ($this->has('lokasi')) {
            $lokasi = trim($this->lokasi ?? '');
            $this->merge([
                'lokasi' => $lokasi === '' ? null : $lokasi,
            ]);
        }

        // Convert status_id to integer if it's a string
        if ($this->has('status_id') && is_string($this->status_id)) {
            $this->merge([
                'status_id' => (int) $this->status_id,
            ]);
        }
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if updating to same status
            if ($this->filled(['no_resi', 'status_id'])) {
                $pengiriman = \App\Models\Pengiriman::where('no_resi', $this->no_resi)->first();
                if ($pengiriman && $pengiriman->status_id == $this->status_id) {
                    $validator->errors()->add('status_id', 'Status yang dipilih sama dengan status saat ini.');
                }
            }

            // Validate QR data if provided - Updated for ultra-simple QR
            if ($this->filled('qr_data') && $this->filled('no_resi')) {
                try {
                    $qrData = json_decode($this->qr_data, true);
                    $qrResi = null;

                    if ($qrData) {
                        // JSON format (old)
                        $qrResi = $qrData['resi'] ?? $qrData['no_resi'] ?? null;
                    } else {
                        // Plain string format (new)
                        $plainResi = trim($this->qr_data);
                        if (preg_match('/^EQ-\d{4}-\d{5}$/', $plainResi)) {
                            $qrResi = $plainResi;
                        }
                    }

                    if ($qrResi && $qrResi !== $this->no_resi) {
                        $validator->errors()->add('qr_data', 'Nomor resi dalam QR tidak sesuai dengan input manual.');
                    }
                } catch (\Exception $e) {
                    // QR data validation already handled in rules
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->expectsJson()) {
            // Get detailed error information
            $errors = $validator->errors();
            $fieldErrors = $errors->toArray();

            // Add debugging info for common issues
            $debugInfo = [];
            $formatHints = [];

            // Check for same status issue
            if ($errors->has('status_id') && $this->filled(['no_resi', 'status_id'])) {
                $pengiriman = \App\Models\Pengiriman::where('no_resi', $this->no_resi)->first();
                if ($pengiriman) {
                    $debugInfo['current_status_id'] = $pengiriman->status_id;
                    $debugInfo['requested_status_id'] = $this->status_id;
                    $debugInfo['is_same_status'] = ($pengiriman->status_id == $this->status_id);
                }
            }

            // Add format hints for various validation errors
            foreach ($fieldErrors as $field => $fieldErrors) {
                $formatHints[$field] = $this->getFormatHints($field, $fieldErrors);
            }

            $response = response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $errors->all(),
                'field_errors' => $fieldErrors,
                'debug_info' => $debugInfo, // Add debug info for development
                'format_hints' => $formatHints, // Add format hints
            ], 422);

            throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
        }

        parent::failedValidation($validator);
    }

    /**
     * Validate QR data format - Updated for ultra-simple QR
     */
    private function isValidQRData(string $qrData): bool
    {
        try {
            // Try to decode JSON first (backward compatibility)
            $data = json_decode($qrData, true);

            if ($data) {
                // Support old JSON formats
                return (isset($data['resi']) && ! empty($data['resi'])) ||
                       (isset($data['no_resi']) && ! empty($data['no_resi']));
            }

            // If not JSON, check if it's a valid resi pattern (new ultra-simple format)
            return preg_match('/^EQ-\d{4}-\d{5}$/', trim($qrData));

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get validated no_resi (prioritize QR data if available) - Updated for ultra-simple QR
     */
    public function getValidatedNoResi(): string
    {
        if ($this->filled('qr_data')) {
            try {
                // Try JSON decode first (backward compatibility)
                $qrData = json_decode($this->qr_data, true);
                if ($qrData && isset($qrData['resi'])) {
                    return $qrData['resi'];
                } elseif ($qrData && isset($qrData['no_resi'])) {
                    return $qrData['no_resi']; // Backward compatibility
                }

                // If not JSON, treat as plain resi string (new ultra-simple format)
                $plainResi = trim($this->qr_data);
                if (preg_match('/^EQ-\d{4}-\d{5}$/', $plainResi)) {
                    return $plainResi;
                }
            } catch (\Exception $e) {
                // Fall back to manual input
            }
        }

        return $this->no_resi;
    }

    /**
     * Get QR verification status
     */
    public function isQRVerified(): bool
    {
        if (! $this->filled('qr_data')) {
            return false;
        }

        try {
            $qrData = json_decode($this->qr_data, true);

            return $qrData &&
                   isset($qrData['type']) &&
                   $qrData['type'] === 'ekspedisi_quran' &&
                   isset($qrData['signature']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get format hints for validation errors
     */
    private function getFormatHints(string $field, array $errors): array
    {
        $hints = [];

        foreach ($errors as $error) {
            $errorLower = strtolower($error);

            switch ($field) {
                case 'no_resi':
                    if (str_contains($errorLower, 'format') || str_contains($errorLower, 'regex')) {
                        $hints[] = 'Format yang diterima: EQ-YYYY-NNNNN (contoh: EQ-2025-00001)';
                    }
                    if (str_contains($errorLower, 'tidak ditemukan') || str_contains($errorLower, 'exists')) {
                        $hints[] = 'Nomor resi harus sudah terdaftar di sistem';
                    }
                    break;

                case 'status_id':
                    if (str_contains($errorLower, 'tidak valid') || str_contains($errorLower, 'exists')) {
                        $hints[] = 'Pilih status dari dropdown yang tersedia';
                    }
                    if (str_contains($errorLower, 'tidak aktif')) {
                        $hints[] = 'Status yang dipilih sudah tidak aktif';
                    }
                    if (str_contains($errorLower, 'sama dengan status saat ini')) {
                        $hints[] = 'Pilih status yang berbeda dari status saat ini';
                    }
                    break;

                case 'catatan':
                    if (str_contains($errorLower, 'minimal') || str_contains($errorLower, 'min')) {
                        $hints[] = 'Catatan minimal 3 karakter atau kosongkan saja';
                    }
                    if (str_contains($errorLower, 'maksimal') || str_contains($errorLower, 'max')) {
                        $hints[] = 'Catatan maksimal 500 karakter';
                    }
                    break;

                case 'lokasi':
                    if (str_contains($errorLower, 'maksimal') || str_contains($errorLower, 'max')) {
                        $hints[] = 'Lokasi maksimal 255 karakter';
                    }
                    break;

                case 'latitude':
                    if (str_contains($errorLower, 'between')) {
                        $hints[] = 'Latitude harus antara -90 hingga 90 (contoh: -6.2088)';
                    }
                    break;

                case 'longitude':
                    if (str_contains($errorLower, 'between')) {
                        $hints[] = 'Longitude harus antara -180 hingga 180 (contoh: 106.8456)';
                    }
                    break;

                case 'dokumentasi':
                    if (str_contains($errorLower, 'max') && str_contains($errorLower, 'file')) {
                        $hints[] = 'Maksimal 5 file dokumentasi';
                    }
                    if (str_contains($errorLower, 'image')) {
                        $hints[] = 'File harus berupa gambar (JPG, PNG, GIF, WebP)';
                    }
                    if (str_contains($errorLower, 'mimes')) {
                        $hints[] = 'Format file yang diterima: JPEG, JPG, PNG, GIF, WebP';
                    }
                    if (str_contains($errorLower, 'max') && str_contains($errorLower, 'kb')) {
                        $hints[] = 'Ukuran file maksimal 10MB';
                    }
                    break;
            }
        }

        return array_unique($hints);
    }
}
