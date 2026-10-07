<?php

namespace App\Imports;

use App\Services\DonaturImportService;
use App\Support\KodeDonatur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Membaca templat impor donatur.
 *
 * Satu donatur boleh ditulis dalam BEBERAPA baris ketika mushaf-mushafnya berbeda nama
 * wakif, doa, atau hubungannya — mis. 10 mushaf untuk Alm. H. A. Tas'an, lalu 5 untuk
 * Almh. Hj. Su'adah. Baris dengan `kode_donatur` sama digabung menjadi satu donasi.
 *
 * Kolom jumlah menyatakan berapa mushaf pada baris ITU, jadi penulisannya bebas: satu
 * orang satu baris dengan jumlah 50, atau 50 baris dengan jumlah 1 — hasilnya sama.
 * Donatur yang mushafnya seragam karena itu cukup satu baris, termasuk 50 mushaf.
 */
class DonaturImport implements SkipsOnError, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsErrors, SkipsFailures;

    // Sengaja tanpa tipe: properti ini dipakai bersama trait SkipsErrors, yang
    // mendeklarasikannya tanpa tipe juga. Memberinya tipe membuat keduanya bentrok.
    protected $errors = [];

    protected $successCount = 0;

    protected $mushafCount = 0;

    protected $perDonatur = [];

    /** Jumlah mushaf per jenis, dipakai halaman untuk menampilkan rincian pratinjau. */
    protected $perJenis = ['A5' => 0, 'A6' => 0, 'IQRA' => 0];

    /** Jenis wakaf yang dikenali, dipetakan ke kolom jumlahnya di templat. */
    private const JENIS = [
        'A5' => 'jumlah_a5',
        'A6' => 'jumlah_a6',
        'IQRA' => 'jumlah_iqra',
    ];

    /**
     * @param  bool  $pratinjauSaja  Kalau true, berkasnya dibaca dan diperiksa tetapi
     *                               TIDAK ada apa pun yang disimpan. Dipakai halaman
     *                               untuk menunjukkan berapa yang AKAN masuk sebelum
     *                               tim entry menekan Import.
     */
    public function __construct(protected DonaturImportService $importService, protected bool $pratinjauSaja = false) {}

    public function collection(Collection $rows): void
    {
        Log::info('DonaturImport: mulai memproses '.$rows->count().' baris');

        $perKode = [];
        $urutan = [];

        foreach ($rows as $index => $row) {
            $rowData = is_array($row) ? $row : $row->toArray();
            $nomorBaris = $index + 2; // baris 1 = judul kolom
            $kode = KodeDonatur::bersihkan($rowData['kode_donatur'] ?? null);

            if ($kode === '') {
                $this->errors[] = ['row' => $nomorBaris, 'error' => 'Kode donatur kosong', 'data' => $rowData];

                continue;
            }

            $barisItem = [];
            foreach (self::JENIS as $jenis => $kolom) {
                $jumlah = (int) ($rowData[$kolom] ?? 0);

                if ($jumlah < 0) {
                    $this->errors[] = [
                        'row' => $nomorBaris,
                        'error' => "{$kolom} tidak boleh negatif",
                        'data' => $rowData,
                    ];

                    continue 2;
                }

                if ($jumlah > 0) {
                    $barisItem[] = array_merge([
                        'wakaf_type' => $jenis,
                        'jumlah' => $jumlah,
                    ], $this->detailPerMushaf($rowData));
                }
            }

            if ($barisItem === []) {
                $this->errors[] = [
                    'row' => $nomorBaris,
                    'error' => 'Tidak ada jumlah mushaf pada baris ini (jumlah_a5, jumlah_a6, jumlah_iqra semuanya 0 atau kosong)',
                    'data' => $rowData,
                ];

                continue;
            }

            if (! isset($perKode[$kode])) {
                $perKode[$kode] = [
                    'ringkasan' => $this->ringkasan($rowData, $kode),
                    'baris' => $barisItem,
                    'baris_pertama' => $nomorBaris,
                ];
                $urutan[] = $kode;

                continue;
            }

            // Baris lanjutan boleh mengosongkan kolom keterangan donatur. Isian pertama
            // yang menang supaya hasilnya tidak bergantung pada baris terakhir.
            $perKode[$kode]['ringkasan'] = array_merge(
                $this->ringkasan($rowData, $kode),
                array_filter($perKode[$kode]['ringkasan'], fn ($v) => $v !== null && $v !== '')
            );

            $perKode[$kode]['baris'] = array_merge($perKode[$kode]['baris'], $barisItem);
        }

        foreach ($urutan as $kode) {
            $grup = $perKode[$kode];

            $kurang = [];
            foreach (['nama_donatur' => 'nama_donatur', 'no_hp' => 'no_hp', 'donation_date' => 'donation_date'] as $kunci => $label) {
                if (($grup['ringkasan'][$kunci] ?? '') === '') {
                    $kurang[] = $label;
                }
            }

            if ($kurang !== []) {
                $this->errors[] = [
                    'row' => $grup['baris_pertama'],
                    'error' => 'Kolom '.implode(', ', $kurang)." wajib diisi pada baris pertama donatur {$kode}",
                    'data' => $grup['ringkasan'],
                ];

                continue;
            }

            $jumlahMushafGrup = array_sum(array_column($grup['baris'], 'jumlah'));
            $wakifUnik = count(array_unique(array_filter(array_column($grup['baris'], 'wakif_name'))));

            foreach ($grup['baris'] as $b) {
                $this->perJenis[$b['wakaf_type']] += $b['jumlah'];
            }

            $this->perDonatur[] = [
                'kode' => $kode,
                'baris' => count($grup['baris']),
                'mushaf' => $jumlahMushafGrup,
                'wakifBerbeda' => $wakifUnik,
            ];

            // Mode pratinjau: berhenti di sini. Tidak ada donatur, item, maupun resi
            // yang dibuat — halaman hanya ingin tahu isi berkasnya.
            if ($this->pratinjauSaja) {
                $this->successCount++;
                $this->mushafCount += $jumlahMushafGrup;

                continue;
            }

            try {
                $this->importService->createFromRows($grup['ringkasan'], $grup['baris']);
                $this->successCount++;
                $this->mushafCount += $jumlahMushafGrup;
            } catch (\Exception $e) {
                Log::error("DonaturImport: gagal membuat donatur {$kode}", [
                    'error' => $e->getMessage(),
                    'data' => $grup['ringkasan'],
                ]);

                $this->errors[] = [
                    'row' => $grup['baris_pertama'],
                    'error' => $e->getMessage(),
                    'data' => $grup['ringkasan'],
                ];
            }
        }

        Log::info('DonaturImport: selesai', [
            'success_count' => $this->successCount,
            'error_count' => count($this->errors),
            'mushaf_count' => $this->mushafCount,
        ]);
    }

    public function rules(): array
    {
        // Kolom keterangan donatur sengaja longgar: pada baris lanjutan kolom itu boleh
        // dikosongkan karena sudah diisi di baris pertama donatur yang sama. Pemeriksaan
        // kelengkapan ada di collection(), supaya pesannya menyebut kode donatur dan
        // dilaporkan sekali per donatur — bukan sekali per baris.
        return [
            'kode_donatur' => 'required|string|max:50',
            'nama_donatur' => 'nullable|string|max:255',
            'no_hp' => 'nullable',
            'email_donatur' => 'nullable|string|max:255',
            'alamat_donatur' => 'nullable|string',
            'jumlah_a5' => 'nullable|integer|min:0',
            'jumlah_a6' => 'nullable|integer|min:0',
            'jumlah_iqra' => 'nullable|integer|min:0',
            'donation_date' => 'nullable',
            'doa_untuk_semua' => 'nullable|string',
            'wakif_name' => 'nullable|string|max:255',
            'doa_request' => 'nullable|string',
            'relationship_to_donatur' => 'nullable|string|max:100',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'kode_donatur.required' => 'Kode donatur wajib diisi',
            'jumlah_a5.integer' => 'Jumlah A5 harus berupa angka',
            'jumlah_a6.integer' => 'Jumlah A6 harus berupa angka',
            'jumlah_iqra.integer' => 'Jumlah Iqra harus berupa angka',
            'jumlah_a5.min' => 'Jumlah A5 tidak boleh negatif',
            'jumlah_a6.min' => 'Jumlah A6 tidak boleh negatif',
            'jumlah_iqra.min' => 'Jumlah Iqra tidak boleh negatif',
        ];
    }

    /**
     * Rincian per-mushaf yang boleh berbeda antar baris.
     *
     * @param  array<string,mixed>  $rowData
     * @return array{wakif_name:?string,doa_request:?string,relationship_to_donatur:?string}
     */
    private function detailPerMushaf(array $rowData): array
    {
        return [
            'wakif_name' => $this->teks($rowData['wakif_name'] ?? null) ?: null,
            'doa_request' => $this->teks($rowData['doa_request'] ?? null) ?: null,
            'relationship_to_donatur' => $this->teks($rowData['relationship_to_donatur'] ?? null) ?: null,
        ];
    }

    /**
     * Kolom keterangan donatur (berlaku untuk seluruh mushafnya).
     *
     * @param  array<string,mixed>  $rowData
     * @return array<string,?string>
     */
    private function ringkasan(array $rowData, string $kode): array
    {
        return [
            'kode_donatur' => $kode,
            'nama_donatur' => $this->teks($rowData['nama_donatur'] ?? null) ?: null,
            'no_hp' => $this->normalizePhoneNumber($this->teks($rowData['no_hp'] ?? null)),
            'email_donatur' => $this->teks($rowData['email_donatur'] ?? null) ?: null,
            'alamat_donatur' => $this->teks($rowData['alamat_donatur'] ?? null) ?: null,
            'donation_date' => $this->parseDate($rowData['donation_date'] ?? null),
            'doa_untuk_semua' => $this->teks($rowData['doa_untuk_semua'] ?? null) ?: null,
        ];
    }

    private function teks(mixed $nilai): string
    {
        return trim((string) $nilai);
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        if ($phone === '') {
            return '';
        }

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
    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $date = Date::excelToDateTimeObject($value);

            return $date->format('Y-m-d');
        }

        $date = strtotime((string) $value);
        if ($date !== false) {
            return date('Y-m-d', $date);
        }

        return null;
    }

    public function getResults(): array
    {
        return [
            'success_count' => $this->successCount,
            'error_count' => count($this->errors),
            'errors' => $this->errors,
            'mushaf_count' => $this->mushafCount,
            'per_donatur' => $this->perDonatur,
            'per_jenis' => $this->perJenis,
        ];
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
