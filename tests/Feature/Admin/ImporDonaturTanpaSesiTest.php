<?php

use App\Imports\DonaturImport;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use App\Services\DonaturImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

uses(RefreshDatabase::class);

/**
 * Impor harus jalan TANPA sesi login.
 *
 * `created_by` di donatur, wakaf_items, dan pengiriman semuanya NOT NULL, sementara
 * `auth()->id()` bernilai null di luar sesi pengguna (console, penjadwal, atau saat
 * pengujian dari baris perintah). Tanpa penanganan, impor gagal total dengan
 * "Column 'created_by' cannot be null" — dan galatnya baru ketahuan setelah dijalankan
 * sungguhan, bukan saat diuji lewat sesi.
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

    // Ada pengguna di basis data, tetapi TIDAK ada yang login — persis keadaan console.
    $this->admin = User::factory()->create(['is_active' => true]);
});

function berkasImporTanpaSesi(array $baris): string
{
    $judul = [
        'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur',
        'jumlah_a5', 'jumlah_a6', 'jumlah_iqra', 'donation_date', 'doa_untuk_semua',
        'wakif_name', 'doa_request', 'relationship_to_donatur',
    ];

    $ss = new Spreadsheet;
    $ss->getActiveSheet()->fromArray($judul, null, 'A1');
    $ss->getActiveSheet()->fromArray($baris, null, 'A2');

    $path = sys_get_temp_dir().'/tanpa-sesi-'.uniqid().'.xlsx';
    (new XlsxWriter($ss))->save($path);

    return $path;
}

it('impor berhasil dijalankan tanpa sesi login sama sekali', function () {
    // Tidak ada actingAs() di sini — inilah pokok persoalannya.
    expect(auth()->id())->toBeNull();

    $path = berkasImporTanpaSesi([
        ['DN-TANPA-SESI', 'Uji Tanpa Sesi', '081234567890', null, null, 3, 0, 0, '2026-01-15', null, 'Alm. Ahmad', 'doa', 'Almarhum'],
    ]);

    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    $hasil = $import->getResults();

    expect($hasil['error_count'])->toBe(0)
        ->and($hasil['success_count'])->toBe(1)
        ->and($hasil['mushaf_count'])->toBe(3);

    $donatur = Donatur::where('kode_donatur', 'DN-TANPA-SESI')->firstOrFail();

    // Ketiga tabel berkolom created_by NOT NULL; semuanya harus terisi.
    expect($donatur->created_by)->not->toBeNull()
        ->and(WakafItem::where('donatur_id', $donatur->id)->whereNull('created_by')->count())->toBe(0)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->whereNull('created_by')->count())->toBe(0);

    // Dipakai pengguna yang benar-benar ada, bukan sekadar angka apa saja.
    expect(User::whereKey($donatur->created_by)->exists())->toBeTrue();

    @unlink($path);
});

it('impor dengan sesi login tetap memakai pengguna yang sedang login', function () {
    $this->actingAs($this->admin);

    $path = berkasImporTanpaSesi([
        ['DN-DENGAN-SESI', 'Uji Dengan Sesi', '081234567890', null, null, 2, 0, 0, '2026-01-15', null, null, null, null],
    ]);

    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    $donatur = Donatur::where('kode_donatur', 'DN-DENGAN-SESI')->firstOrFail();

    // Bukan sekadar "ada isinya" — harus pengguna yang tepat.
    expect($donatur->created_by)->toBe($this->admin->id)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('created_by', $this->admin->id)->count())->toBe(2)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->where('created_by', $this->admin->id)->count())->toBe(2);

    @unlink($path);
});

it('impor lanjutan atas donatur lama tidak kehilangan pembuat aslinya', function () {
    // Donatur dibuat dulu oleh seseorang, lalu diimpor ulang oleh yang lain.
    $this->actingAs($this->admin);
    $path1 = berkasImporTanpaSesi([
        ['DN-PEMBUAT', 'Pembuat Asli', '081234567890', null, null, 1, 0, 0, '2026-01-15', null, null, null, null],
    ]);
    Excel::import(new DonaturImport(new DonaturImportService), $path1);

    $pembuatAsli = $this->admin->id;

    // Pengguna lain mengimpor donasi susulan tanpa sesi.
    Auth::logout();
    $path2 = berkasImporTanpaSesi([
        ['DN-PEMBUAT', null, null, null, null, 1, 0, 0, null, null, 'Wakif Lain', null, 'Keluarga'],
    ]);
    Excel::import(new DonaturImport(new DonaturImportService), $path2);

    $donatur = Donatur::where('kode_donatur', 'DN-PEMBUAT')->firstOrFail();

    // Donatur yang sudah ada tetap memakai pembuat aslinya, bukan ditimpa jadi pengguna
    // pertama di basis data. Kalau tertimpa, jejak siapa membuat data itu hilang.
    expect($donatur->created_by)->toBe($pembuatAsli)
        ->and($donatur->total_a5_count)->toBe(2);

    @unlink($path1);
    @unlink($path2);
});
