<?php

use App\Imports\DonaturImport;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\DonaturImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

uses(RefreshDatabase::class);

/**
 * Tanggal di berkas impor datang dalam berbagai format, dan yang paling berbahaya
 * BUKAN yang gagal — melainkan yang terbaca tetapi salah.
 *
 * `strtotime("1/2/2026")` berhasil dan menghasilkan 2 Januari mengikuti kebiasaan
 * Amerika, padahal yang dimaksud 1 Februari. Tidak ada galat, tidak ada peringatan;
 * yang tersimpan cuma tanggal yang keliru. Format hari-dulu harus menang.
 */
beforeEach(function () {
    JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran A5', 'is_active' => true, 'harga' => 50000]);
    JenisQuran::firstOrCreate(['kode_jenis' => 'A6'], ['nama_jenis' => 'Al-Quran A6', 'is_active' => true, 'harga' => 35000]);
    JenisQuran::firstOrCreate(['kode_jenis' => 'IQRA'], ['nama_jenis' => 'Iqra', 'is_active' => true, 'harga' => 25000]);

    StatusPengiriman::firstOrCreate(
        ['slug' => 'pemesanan'],
        ['nama' => 'Proses Pemesanan', 'deskripsi' => 'Default', 'warna' => 'purple', 'urutan' => 1, 'is_active' => true, 'is_final' => false]
    );

    cache()->forget('jenis_quran_mapping');

    $this->user = User::factory()->create(['is_active' => true]);
    $this->actingAs($this->user);
});

function imporTanggal(string $tanggal): ?Donatur
{
    $judul = [
        'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur',
        'jumlah_a5', 'jumlah_a6', 'jumlah_iqra', 'donation_date', 'doa_untuk_semua',
        'wakif_name', 'doa_request', 'relationship_to_donatur',
    ];

    $ss = new Spreadsheet;
    $ss->getActiveSheet()->fromArray($judul, null, 'A1');
    $ss->getActiveSheet()->fromArray([
        'TGL-'.substr(md5($tanggal), 0, 8), 'Uji Tanggal', '081234567890', null, null,
        1, 0, 0, $tanggal, null, null, null, null,
    ], null, 'A2');

    $path = sys_get_temp_dir().'/tanggal-'.uniqid().'.xlsx';
    (new XlsxWriter($ss))->save($path);

    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    @unlink($path);

    return Donatur::where('kode_donatur', 'TGL-'.substr(md5($tanggal), 0, 8))->first();
}

it('membaca format Indonesia hari/bulan/tahun sebagai hari dulu', function () {
    // Inti persoalannya: 15/01/2026 harus 15 JANUARI, dan 01/02/2026 harus 1 FEBRUARI.
    $harusnya = [
        '15/01/2026' => '2026-01-15',
        '01/02/2026' => '2026-02-01',
        '1/2/2026' => '2026-02-01',   // kalau salah baca jadi 2 Januari — tanpa galat
        '31/12/2026' => '2026-12-31',
        '05/06/2026' => '2026-06-05',
    ];

    foreach ($harusnya as $tulis => $benar) {
        $d = imporTanggal($tulis);

        expect($d)->not->toBeNull("tanggal {$tulis} gagal diimpor");
        expect($d->donation_date->format('Y-m-d'))->toBe($benar, "tanggal '{$tulis}' salah baca");
    }
});

it('format ISO dan format Indonesia menghasilkan tanggal yang sama', function () {
    $iso = imporTanggal('2026-03-15');

    expect($iso)->not->toBeNull()
        ->and($iso->donation_date->format('Y-m-d'))->toBe('2026-03-15');
});

it('menerima pemisah tanda hubung dan titik', function () {
    foreach (['15-01-2026', '15.01.2026'] as $tulis) {
        $d = imporTanggal($tulis);

        expect($d)->not->toBeNull("tanggal {$tulis} gagal diimpor")
            ->and($d->donation_date->format('Y-m-d'))->toBe('2026-01-15', "tanggal '{$tulis}' salah baca");
    }
});

it('membalik urutan bila jelas bulan dulu, tapi tidak menebak saat ambigu', function () {
    // 01/13/2026 mustahil sebagai hari-dulu, jadi pasti bulan-dulu -> 13 Januari.
    $d = imporTanggal('01/13/2026');
    expect($d)->not->toBeNull()
        ->and($d->donation_date->format('Y-m-d'))->toBe('2026-01-13');

    // 05/06/2026 ambigu; HARUS dibaca hari-dulu (5 Juni), bukan 6 Mei.
    $a = imporTanggal('05/06/2026');
    expect($a->donation_date->format('Y-m-d'))->toBe('2026-06-05');
});

it('menolak tanggal yang tidak ada di kalender', function () {
    // 31/02/2026 tidak pernah ada. Lebih baik donaturnya dilaporkan gagal daripada
    // tanggalnya digeser diam-diam menjadi 2 atau 3 Maret.
    $d = imporTanggal('31/02/2026');

    expect($d)->toBeNull();
});
