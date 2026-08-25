<?php

namespace App\Imports;

use App\Models\MushafRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MushafRequestImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsErrors, SkipsFailures;

    protected $errors = [];

    protected $successCount = 0;

    protected $validationFailures = [];

    public function collection(Collection $rows): void
    {
        Log::info('MushafRequestImport: Starting collection with '.$rows->count().' rows');
        Log::info('Validation failures so far: '.count($this->failures()));

        foreach ($rows as $index => $row) {
            // Convert to array if it's an object (from Excel)
            $rowData = is_array($row) ? $row : $row->toArray();

            Log::info('Processing row '.($index + 1), ['data' => $rowData]);

            try {
                // Parse jumlah kebutuhan mushaf (could be format like "100 mushaf, 50 iqra")
                $parsedQuantities = $this->parseJumlahKebutuhan($rowData['jumlah_kebutuhan_mushaf'] ?? '');
                Log::info('Parsed quantities for row '.($index + 1), $parsedQuantities);

                // Parse koordinat from link gmaps if provided
                $coordinates = $this->parseGoogleMapsLink($rowData['link_gmaps'] ?? '');
                Log::info('Parsed coordinates for row '.($index + 1), ['coordinates' => $coordinates]);

                // Normalize phone number
                $normalizedPhone = $this->normalizePhoneNumber($rowData['nomor_hp'] ?? '');
                Log::info('Normalized phone for row '.($index + 1), ['phone' => $normalizedPhone]);

                // Get nama pengurus from either column name variant
                $namaPengurus = $rowData['nama_penanggung_jawab_1'] ?? $rowData['nama_penanggung_jawab'] ?? null;

                // Validate required field that might have different column name
                if (empty($namaPengurus)) {
                    throw new \Exception('Nama penanggung jawab wajib diisi (kolom nama_penanggung_jawab_1)');
                }

                // Parse urgensi - could be in column or in jumlah_kebutuhan_mushaf
                $urgensi = $this->parseUrgensi($rowData['urgensi'] ?? 'sedang');

                $dataToCreate = [
                    // Required fields
                    'nama_lembaga' => $rowData['nama_lembaga'],
                    'nama_pengurus_1' => $namaPengurus,
                    'whatsapp_pengurus_1' => $normalizedPhone,
                    'alamat_lengkap' => $rowData['alamat_lengkap'],

                    // Pengurus 2 (set to default - will be filled later)
                    'nama_pengurus_2' => '-',
                    'jabatan_pengurus_2' => '-',
                    'whatsapp_pengurus_2' => '-',

                    // Optional: Google Maps link & coordinates
                    'latitude' => $coordinates['lat'] ?? null,
                    'longitude' => $coordinates['lng'] ?? null,

                    // Jumlah mushaf breakdown
                    'jumlah_mushaf_a5' => $parsedQuantities['mushaf_a5'],
                    'jumlah_mushaf_a6' => $parsedQuantities['mushaf_a6'],
                    'jumlah_iqra' => $parsedQuantities['iqra'],
                    'jumlah_mushaf' => $parsedQuantities['total_mushaf'],
                    'jenis_mushaf_diminta' => $parsedQuantities['jenis_diminta'],

                    // Urgensi
                    'urgensi_request' => $urgensi,

                    // Defaults for optional fields
                    'kategori_lembaga' => 'Lembaga Lainnya',
                    'jabatan_pengurus_1' => 'Penanggung Jawab',
                    'status' => 'pending',
                    'sumber_info' => 'Import Excel',

                    // Address breakdown fields - null (will be filled later)
                    'provinsi' => null,
                    'provinsi_id' => null,
                    'kota_kabupaten' => null,
                    'kota_kabupaten_id' => null,
                    'kecamatan' => null,
                    'kecamatan_id' => null,
                    'kelurahan_desa' => null,
                    'kelurahan_desa_id' => null,
                    'kode_pos' => null,
                    'alamat_detail' => null,

                    // Files - null (will be uploaded later)
                    'foto_santri_path' => null,
                    'foto_lembaga_path' => null,
                    'file_nama_santri_path' => null,
                ];

                Log::info('Attempting to create MushafRequest for row '.($index + 1), $dataToCreate);

                $created = MushafRequest::create($dataToCreate);

                Log::info('Successfully created MushafRequest ID: '.$created->id.' for row '.($index + 1));

                $this->successCount++;
            } catch (\Exception $e) {
                Log::error('Error creating MushafRequest for row '.($index + 1), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $rowData,
                ]);

                $this->errors[] = [
                    'row' => $index + 2, // +2 because index starts at 0 and we have header row
                    'error' => $e->getMessage(),
                    'data' => $rowData,
                ];
            }
        }

        Log::info('MushafRequestImport: Finished collection', [
            'success_count' => $this->successCount,
            'error_count' => count($this->errors),
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_lembaga' => 'required|string|max:255',
            'nama_penanggung_jawab_1' => 'nullable|string|max:255',
            'nomor_hp' => 'required|string',
            'alamat_lengkap' => 'required|string',
            'jumlah_kebutuhan_mushaf' => 'required',
            'link_gmaps' => 'nullable|string',
            'urgensi' => 'nullable|string',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nama_lembaga.required' => 'Nama lembaga wajib diisi',
            'nama_penanggung_jawab_1.required' => 'Nama penanggung jawab wajib diisi',
            'nomor_hp.required' => 'Nomor HP wajib diisi',
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi',
            'jumlah_kebutuhan_mushaf.required' => 'Jumlah kebutuhan mushaf wajib diisi',
        ];
    }

    /**
     * Parse jumlah kebutuhan mushaf dari berbagai format
     * Format yang diterima:
     * - "100" -> 100 mushaf A5
     * - "100 mushaf" -> 100 mushaf A5
     * - "100 mushaf, 50 iqra" -> 100 mushaf A5, 50 iqra
     * - "50 A5, 30 A6, 20 iqra" -> 50 A5, 30 A6, 20 iqra
     */
    private function parseJumlahKebutuhan(string $input): array
    {
        $input = strtolower(trim($input));

        $mushafA5 = 0;
        $mushafA6 = 0;
        $iqra = 0;
        $jenisDiminta = [];

        // Pattern matching
        if (preg_match('/(\d+)\s*(a5|mushaf\s*a5)/i', $input, $matches)) {
            $mushafA5 = (int) $matches[1];
            $jenisDiminta[] = 'A5';
        }

        if (preg_match('/(\d+)\s*(a6|mushaf\s*a6)/i', $input, $matches)) {
            $mushafA6 = (int) $matches[1];
            $jenisDiminta[] = 'A6';
        }

        if (preg_match('/(\d+)\s*iqra/i', $input, $matches)) {
            $iqra = (int) $matches[1];
            $jenisDiminta[] = 'IQRA';
        }

        // Jika hanya angka atau "X mushaf" tanpa spesifikasi jenis, anggap A5
        if (empty($jenisDiminta) && preg_match('/(\d+)/', $input, $matches)) {
            $mushafA5 = (int) $matches[1];
            $jenisDiminta[] = 'A5';
        }

        return [
            'mushaf_a5' => $mushafA5,
            'mushaf_a6' => $mushafA6,
            'iqra' => $iqra,
            'total_mushaf' => $mushafA5 + $mushafA6,
            'jenis_diminta' => $jenisDiminta,
        ];
    }

    /**
     * Parse Google Maps link untuk mendapatkan koordinat
     * Format yang diterima:
     * - https://maps.google.com/?q=-6.123,106.456
     * - https://www.google.com/maps/place/@-6.123,106.456
     * - https://goo.gl/maps/xxxxx (akan return null, perlu expand URL)
     */
    private function parseGoogleMapsLink(?string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        // Pattern untuk koordinat dari Google Maps URL
        $patterns = [
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',  // Format: @lat,lng
            '/q=(-?\d+\.\d+),(-?\d+\.\d+)/', // Format: ?q=lat,lng
            '/ll=(-?\d+\.\d+),(-?\d+\.\d+)/', // Format: ll=lat,lng
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'lat' => (float) $matches[1],
                    'lng' => (float) $matches[2],
                ];
            }
        }

        return null;
    }

    /**
     * Normalize phone number ke format 62xxx
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 08xxx to 628xxx
        if (substr($phone, 0, 1) === '0') {
            $phone = '62'.substr($phone, 1);
        }

        // Add 62 prefix if missing
        if (substr($phone, 0, 2) !== '62') {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    /**
     * Parse urgensi dari berbagai format input
     * Jika input adalah deskripsi kebutuhan, return default 'sedang'
     */
    private function parseUrgensi(string $input): string
    {
        $input = strtolower(trim($input));

        // Jika kosong, return default
        if (empty($input)) {
            return 'sedang';
        }

        // Cek apakah input adalah level urgensi yang valid
        return match (true) {
            in_array($input, ['rendah', 'low', '1']) => 'rendah',
            in_array($input, ['sedang', 'medium', 'normal', '2']) => 'sedang',
            in_array($input, ['tinggi', 'high', '3']) => 'tinggi',
            in_array($input, ['mendesak', 'urgent', 'emergency', '4']) => 'mendesak',
            // Jika input adalah deskripsi (bukan level urgensi), gunakan default 'sedang'
            default => 'sedang',
        };
    }

    /**
     * Get import results
     */
    public function getResults(): array
    {
        // Combine validation failures with processing errors
        $allErrors = array_merge($this->errors, $this->getValidationFailureErrors());

        return [
            'success_count' => $this->successCount,
            'error_count' => count($allErrors),
            'errors' => $allErrors,
        ];
    }

    /**
     * Convert validation failures to error format
     */
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

    /**
     * Check if import has errors
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
