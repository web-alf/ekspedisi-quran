<?php

namespace App\Imports;

use App\Models\MushafRequest;
use App\Services\Cache\GeographicCacheService;
use App\Services\MushafAddressResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;

class MushafRequestImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use SkipsErrors, SkipsFailures;

    protected $errors = [];

    protected $successCount = 0;

    protected $validationFailures = [];

    public function __construct(
        private readonly MushafAddressResolver $addressResolver = new MushafAddressResolver(new GeographicCacheService),
    ) {}

    /**
     * Hanya lembar PERTAMA yang dibaca.
     *
     * Tanpa ini seluruh lembar ikut diproses, sehingga lembar "Panduan Kolom"
     * pada template terbaca sebagai data dan menghasilkan puluhan baris gagal
     * yang mengada-ada ("Nama lembaga wajib diisi" untuk tiap baris panduan).
     * Sekaligus menjaga import tetap benar bila pengisi berkas menambahkan
     * lembarnya sendiri.
     *
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        Log::info('MushafRequestImport: Starting collection with '.$rows->count().' rows');
        Log::info('Validation failures so far: '.count($this->failures()));

        foreach ($rows as $index => $row) {
            // Convert to array if it's an object (from Excel)
            $rowData = is_array($row) ? $row : $row->toArray();

            Log::info('Processing row '.($index + 1), ['data' => $rowData]);

            try {
                // Jumlah kebutuhan mushaf. Template kini memakai satu kolom angka
                // per jenis; kolom teks lama ("100 mushaf, 50 iqra") tetap dibaca
                // sebagai cadangan supaya berkas yang sudah beredar tidak rusak.
                $parsedQuantities = $this->kuantitas($rowData);
                Log::info('Parsed quantities for row '.($index + 1), $parsedQuantities);

                // Tanpa ini, baris yang jumlahnya nol tetap masuk tanpa keluhan.
                if ($parsedQuantities['total_mushaf'] + $parsedQuantities['iqra'] <= 0) {
                    throw new \Exception('Jumlah kebutuhan mushaf wajib diisi (kolom jumlah_mushaf_a5 / jumlah_mushaf_a6 / jumlah_iqra)');
                }

                // Parse koordinat from link gmaps if provided
                $coordinates = $this->parseGoogleMapsLink($rowData['link_gmaps'] ?? '');
                Log::info('Parsed coordinates for row '.($index + 1), ['coordinates' => $coordinates]);

                // Lengkapi kolom wilayah (provinsi..kelurahan) dari alamat + tautan
                // peta. Sebelumnya kolom-kolom ini SELALU null sehingga halaman
                // detail tidak menampilkan alamat lengkap dan form "Informasi
                // Lembaga" — yang mewajibkan latitude/longitude — tidak bisa
                // disimpan. Lihat App\Services\MushafAddressResolver.
                $wilayah = $this->addressResolver->resolve(
                    $rowData['alamat_lengkap'] ?? null,
                    $rowData['link_gmaps'] ?? null,
                    // Kolom wilayah yang diisi LANGSUNG di berkas dipakai apa
                    // adanya; hanya yang dikosongkan yang diuraikan otomatis.
                    [
                        'provinsi' => $rowData['provinsi'] ?? null,
                        'kota_kabupaten' => $rowData['kota_kabupaten'] ?? null,
                        'kecamatan' => $rowData['kecamatan'] ?? null,
                        'kelurahan_desa' => $rowData['kelurahan_desa'] ?? null,
                        'kode_pos' => $rowData['kode_pos'] ?? null,
                        'alamat_detail' => $rowData['alamat_detail'] ?? null,
                        'latitude' => $rowData['latitude'] ?? null,
                        'longitude' => $rowData['longitude'] ?? null,
                    ],
                );
                Log::info('Resolved address for row '.($index + 1), $wilayah);

                // Normalize phone number
                $normalizedPhone = $this->normalizePhoneNumber($rowData['nomor_hp'] ?? '');
                Log::info('Normalized phone for row '.($index + 1), ['phone' => $normalizedPhone]);

                // Get nama pengurus from either column name variant
                $namaPengurus = $rowData['nama_penanggung_jawab_1'] ?? $rowData['nama_penanggung_jawab'] ?? null;

                // Validate required field that might have different column name
                if (empty($namaPengurus)) {
                    throw new \Exception('Nama penanggung jawab wajib diisi (kolom nama_penanggung_jawab_1)');
                }

                // Tingkat urgensi (rendah/sedang/tinggi/mendesak). Kolom `urgensi`
                // boleh berisi tingkatnya, atau deskripsinya — format lama.
                $urgensi = $this->parseUrgensi($rowData['urgensi'] ?? 'sedang');

                // Kolom `urgensi_request` menyimpan DESKRIPSI ("kenapa mengajukan
                // permintaan"), sama seperti yang disimpan halaman publik dan yang
                // ditampilkan di halaman detail. Sebelumnya pengimpor menaruh
                // TINGKAT urgensi di sini, sehingga deskripsi dari berkas dibuang
                // dan tiga permintaan hasil import tercatat hanya sebagai "sedang".
                // Kolom `urgensi` pada template versi lama berisi deskripsi ini
                // juga ("Banyak Al-Qur'an yang sudah rusak"), jadi dibaca sebagai
                // cadangan supaya berkas yang sudah beredar tidak kehilangan isinya.
                $deskripsiUrgensi = trim((string) ($rowData['urgensi_request'] ?? ''));
                if ($deskripsiUrgensi === '') {
                    $deskripsiUrgensi = trim((string) ($rowData['urgensi'] ?? ''));
                }
                if ($deskripsiUrgensi === '') {
                    $deskripsiUrgensi = 'sedang';
                } elseif ($this->apakahTingkat($deskripsiUrgensi)) {
                    // Bila yang diisi ternyata tingkatnya, bukan cerita, penulisannya
                    // diseragamkan ("urgent" -> "mendesak").
                    $deskripsiUrgensi = $this->parseUrgensi($deskripsiUrgensi);
                }

                $dataToCreate = [
                    // Required fields
                    'nama_lembaga' => $rowData['nama_lembaga'],
                    'nama_pengurus_1' => $namaPengurus,
                    'whatsapp_pengurus_1' => $normalizedPhone,
                    // Kolom ini opsional sejak template memecah alamat per kolom
                    // wilayah; yang tersedia hanya `alamat_detail` pun tetap sah.
                    'alamat_lengkap' => $rowData['alamat_lengkap'] ?? null,

                    // Pengurus 2 (set to default - will be filled later)
                    'nama_pengurus_2' => '-',
                    'jabatan_pengurus_2' => '-',
                    'whatsapp_pengurus_2' => '-',

                    // Optional: Google Maps link & coordinates
                    'latitude' => $wilayah['latitude'] ?? $coordinates['lat'] ?? null,
                    'longitude' => $wilayah['longitude'] ?? $coordinates['lng'] ?? null,

                    // Jumlah mushaf breakdown
                    'jumlah_mushaf_a5' => $parsedQuantities['mushaf_a5'],
                    'jumlah_mushaf_a6' => $parsedQuantities['mushaf_a6'],
                    'jumlah_iqra' => $parsedQuantities['iqra'],
                    'jumlah_mushaf' => $parsedQuantities['total_mushaf'],
                    'jenis_mushaf_diminta' => $parsedQuantities['jenis_diminta'],

                    // Urgensi: DISIMPAN APA ADANYA, sama seperti yang diisi lewat
                    // halaman publik dan yang ditampilkan di halaman detail. Sebelumnya
                    // kolom ini diisi TINGKAT hasil penerkaannya, sehingga deskripsi dari
                    // berkas dibuang dan tiga permintaan hasil import tercatat hanya
                    // sebagai "sedang". Kolom ini juga wajib diisi oleh form
                    // "Informasi Lembaga", jadi tidak boleh kosong.
                    'urgensi_request' => $deskripsiUrgensi,

                    // Kolom yang diisi langsung di berkas dipakai apa adanya;
                    // baru jatuh ke nilai bawaan bila memang dikosongkan.
                    'kategori_lembaga' => $this->nilaiAtau($rowData['kategori_lembaga'] ?? null, 'Lembaga Lainnya'),
                    'jabatan_pengurus_1' => $this->nilaiAtau($rowData['jabatan_penanggung_jawab_1'] ?? null, 'Penanggung Jawab'),
                    'status' => 'pending',
                    'sumber_info' => $this->nilaiAtau($rowData['sumber_info'] ?? null, 'Import Excel'),

                    // Address breakdown fields — hasil App\MushafAddressResolver.
                    // Tingkat yang tidak bisa dipastikan tetap null (sumber data
                    // wilayah yang dipakai aplikasi belum lengkap).
                    'provinsi' => $wilayah['provinsi'],
                    'provinsi_id' => $wilayah['provinsi_id'],
                    'kota_kabupaten' => $wilayah['kota_kabupaten'],
                    'kota_kabupaten_id' => $wilayah['kota_kabupaten_id'],
                    'kecamatan' => $wilayah['kecamatan'],
                    'kecamatan_id' => $wilayah['kecamatan_id'],
                    'kelurahan_desa' => $wilayah['kelurahan_desa'],
                    'kelurahan_desa_id' => $wilayah['kelurahan_desa_id'],
                    'kode_pos' => $wilayah['kode_pos'],
                    'alamat_detail' => $wilayah['alamat_detail'],

                    // Simpan tautan petanya supaya koordinat bisa diturunkan
                    // ulang kapan saja — kalau tidak, sekali penguraian gagal,
                    // baris itu tidak akan pernah bisa dilengkapi lagi.
                    'link_gmaps' => $rowData['link_gmaps'] ?? null,

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
            'alamat_lengkap' => 'nullable|string',
            'alamat_detail' => 'nullable|string|max:500',

            // Kolom wilayah: seluruhnya opsional. Yang dikosongkan diuraikan
            // otomatis dari alamat + tautan peta.
            'provinsi' => 'nullable|string|max:255',
            'kota_kabupaten' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kelurahan_desa' => 'nullable|string|max:255',
            'kode_pos' => 'nullable',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'link_gmaps' => 'nullable|string',

            // Jumlah: kolom angka per jenis (template baru) ATAU kolom teks
            // tunggal (format lama). Salah satunya harus terisi — diperiksa
            // setelah baris diuraikan, karena `required` akan menolak berkas
            // lama yang kolomnya memang tidak ada.
            'jumlah_mushaf_a5' => 'nullable',
            'jumlah_mushaf_a6' => 'nullable',
            'jumlah_iqra' => 'nullable',
            'jumlah_kebutuhan_mushaf' => 'nullable',

            'urgensi' => 'nullable|string',
            'urgensi_request' => 'nullable|string',
            'kategori_lembaga' => 'nullable|string|max:255',
            'jabatan_penanggung_jawab_1' => 'nullable|string|max:255',
            'sumber_info' => 'nullable|string|max:255',
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
            'kode_pos.max' => 'Kode pos maksimal 10 karakter',
            'alamat_detail.max' => 'Detail alamat maksimal 500 karakter',
        ];
    }

    /**
     * Ambil jumlah kebutuhan dari kolom angka per jenis, atau dari kolom teks
     * tunggal versi lama.
     *
     * Template baru memakai `jumlah_mushaf_a5`, `jumlah_mushaf_a6`, dan
     * `jumlah_iqra` — satu kolom per jenis, sehingga pengisi tidak perlu tahu
     * format penulisan dan "50 A5, 30 A6" tidak lagi salah terbaca sebagai 50.
     * Kolom teks lama tetap didukung karena berkas yang sudah beredar tidak bisa
     * ditarik kembali.
     *
     * @param  array<string, mixed>  $rowData
     * @return array{mushaf_a5: int, mushaf_a6: int, iqra: int, total_mushaf: int, jenis_diminta: array<int, string>}
     */
    private function kuantitas(array $rowData): array
    {
        $ambil = function (string $kunci) use ($rowData): int {
            return (int) preg_replace('/[^0-9]/', '', (string) ($rowData[$kunci] ?? ''));
        };

        $a5 = $ambil('jumlah_mushaf_a5');
        $a6 = $ambil('jumlah_mushaf_a6');
        $iqra = $ambil('jumlah_iqra');

        if ($a5 + $a6 + $iqra > 0) {
            $jenis = [];
            if ($a5 > 0) {
                $jenis[] = 'A5';
            }
            if ($a6 > 0) {
                $jenis[] = 'A6';
            }
            if ($iqra > 0) {
                $jenis[] = 'IQRA';
            }

            return [
                'mushaf_a5' => $a5,
                'mushaf_a6' => $a6,
                'iqra' => $iqra,
                'total_mushaf' => $a5 + $a6,
                'jenis_diminta' => $jenis,
            ];
        }

        return $this->parseJumlahKebutuhan((string) ($rowData['jumlah_kebutuhan_mushaf'] ?? ''));
    }

    /**
     * Apakah nilai yang diisi memang TINGKAT urgensi, bukan cerita
     * "kenapa mengajukan permintaan"? Dipakai untuk membedakan keduanya, sebab
     * kolom lamanya bisa berisi dua-duanya.
     */
    private function apakahTingkat(string $nilai): bool
    {
        return in_array(strtolower(trim($nilai)), [
            'rendah', 'low', '1',
            'sedang', 'medium', 'normal', '2',
            'tinggi', 'high', '3',
            'mendesak', 'urgent', 'emergency', '4',
        ], true);
    }

    /** Pakai nilai dari berkas bila ada isinya, kalau tidak pakai bawaan. */
    private function nilaiAtau(mixed $nilai, string $bawaan): string
    {
        $nilai = trim((string) $nilai);

        return $nilai !== '' ? $nilai : $bawaan;
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
     * Apakah import punya baris yang gagal?
     *
     * WAJIB menghitung kegagalan VALIDASI juga, bukan hanya kegagalan saat
     * menyimpan. Baris yang tidak memenuhi aturan tidak pernah sampai ke
     * collection(), jadi kegagalannya hanya tercatat di $failures — dan
     * pemeriksaan yang hanya melihat $errors melaporkan "tidak ada masalah"
     * untuk berkas yang barisnya hilang separuh. Halaman lalu menampilkan
     * "Berhasil import N data" tanpa menyebut satu pun baris yang dibuang.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0 || $this->failures()->isNotEmpty();
    }
}
