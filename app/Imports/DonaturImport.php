<?php

namespace App\Imports;

use App\Services\DonaturImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DonaturImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsErrors, SkipsFailures;

    protected $errors = [];

    protected $successCount = 0;

    protected DonaturImportService $importService;

    public function __construct(DonaturImportService $importService)
    {
        $this->importService = $importService;
    }

    public function collection(Collection $rows): void
    {
        Log::info('DonaturImport: Starting collection with '.$rows->count().' rows');

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();

            Log::info('Processing row '.($index + 1), ['data' => $rowData]);

            try {
                $normalizedPhone = $this->normalizePhoneNumber($rowData['no_hp'] ?? '');

                $dataToCreate = [
                    'kode_donatur' => trim($rowData['kode_donatur']),
                    'nama_donatur' => trim($rowData['nama_donatur']),
                    'no_hp' => $normalizedPhone,
                    'email_donatur' => ! empty($rowData['email_donatur']) ? trim($rowData['email_donatur']) : null,
                    'alamat_donatur' => ! empty($rowData['alamat_donatur']) ? trim($rowData['alamat_donatur']) : null,
                    'jumlah_a5' => (int) ($rowData['jumlah_a5'] ?? 0),
                    'jumlah_a6' => (int) ($rowData['jumlah_a6'] ?? 0),
                    'jumlah_iqra' => (int) ($rowData['jumlah_iqra'] ?? 0),
                    'donation_date' => $this->parseDate($rowData['donation_date'] ?? null),
                    'doa_untuk_semua' => ! empty($rowData['doa_untuk_semua']) ? trim($rowData['doa_untuk_semua']) : null,
                ];

                $this->importService->createFromArray($dataToCreate);

                $this->successCount++;
            } catch (\Exception $e) {
                Log::error('Error creating Donatur for row '.($index + 1), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $rowData,
                ]);

                $this->errors[] = [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                    'data' => $rowData,
                ];
            }
        }

        Log::info('DonaturImport: Finished collection', [
            'success_count' => $this->successCount,
            'error_count' => count($this->errors),
        ]);
    }

    public function rules(): array
    {
        return [
            'kode_donatur' => 'required|string|max:50',
            'nama_donatur' => 'required|string|max:255',
            'no_hp' => 'required',
            'email_donatur' => 'nullable|string',
            'alamat_donatur' => 'nullable|string',
            'jumlah_a5' => 'nullable|integer|min:0',
            'jumlah_a6' => 'nullable|integer|min:0',
            'jumlah_iqra' => 'nullable|integer|min:0',
            'donation_date' => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'kode_donatur.required' => 'Kode donatur wajib diisi',
            'nama_donatur.required' => 'Nama donatur wajib diisi',
            'no_hp.required' => 'Nomor HP wajib diisi',
            'donation_date.required' => 'Tanggal donasi wajib diisi',
        ];
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (substr($phone, 0, 1) === '0') {
            $phone = '+62'.substr($phone, 1);
        }

        if (substr($phone, 0, 2) === '62') {
            $phone = '+'.$phone;
        }

        if (substr($phone, 0, 1) !== '+') {
            $phone = '+'.$phone;
        }

        return $phone;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);

            return $date->format('Y-m-d');
        }

        $date = strtotime($value);
        if ($date !== false) {
            return date('Y-m-d', $date);
        }

        return null;
    }

    public function getResults(): array
    {
        $allErrors = array_merge($this->errors, $this->getValidationFailureErrors());

        return [
            'success_count' => $this->successCount,
            'error_count' => count($allErrors),
            'errors' => $allErrors,
        ];
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    protected function getValidationFailureErrors(): array
    {
        $errors = [];

        foreach ($this->failures() as $failure) {
            $errors[] = [
                'row' => $failure->row(),
                'error' => 'Validation: '.implode(', ', $failure->errors()),
                'attribute' => $failure->attribute(),
                'data' => $failure->values(),
            ];
        }

        return $errors;
    }
}
