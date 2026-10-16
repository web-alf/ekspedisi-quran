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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Jenis Quran wajib lengkap untuk mapping A5/A6/IQRA
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

function buatExcelDonatur(string $kode, int $a5, int $a6, int $iqra): string
{
    $ss = new Spreadsheet;
    $sh = $ss->getActiveSheet();
    $sh->fromArray([
        'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur',
        'jumlah_a5', 'jumlah_a6', 'jumlah_iqra', 'donation_date', 'doa_untuk_semua',
    ], null, 'A1');
    $sh->fromArray([
        $kode, 'Uji Import', '081234567890', 'uji@test.com', 'Jl. Test',
        $a5, $a6, $iqra, '2026-01-15', 'Semoga berkah',
    ], null, 'A2');

    $path = sys_get_temp_dir()."/{$kode}.xlsx";
    (new Xlsx($ss))->save($path);

    return $path;
}

it('import membuat donatur + wakaf items + pengiriman sesuai jumlah', function () {
    $path = buatExcelDonatur('DN-IMP-1', 2, 1, 0);

    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    $results = $import->getResults();
    expect($results['success_count'])->toBe(1)
        ->and($results['error_count'])->toBe(0);

    $donatur = Donatur::where('kode_donatur', 'DN-IMP-1')->firstOrFail();
    expect($donatur->total_a5_count)->toBe(2)
        ->and($donatur->total_a6_count)->toBe(1)
        ->and($donatur->donation_count)->toBe(1);

    expect(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(3)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(3);

    @unlink($path);
});

it('import ulang kode sama tidak menggandakan wakaf items & pengiriman', function () {
    $path = buatExcelDonatur('DN-IMP-2', 2, 1, 0);

    // Import pertama
    Excel::import(new DonaturImport(new DonaturImportService), $path);
    // Import kedua dengan kode donatur yang sama
    Excel::import(new DonaturImport(new DonaturImportService), $path);

    $donatur = Donatur::where('kode_donatur', 'DN-IMP-2')->firstOrFail();

    // Donatur tetap satu (update, bukan duplikat)
    expect(Donatur::where('kode_donatur', 'DN-IMP-2')->count())->toBe(1);

    // Total terakumulasi
    expect($donatur->donation_count)->toBe(2)
        ->and($donatur->total_a5_count)->toBe(4)
        ->and($donatur->total_a6_count)->toBe(2);

    // Hanya 2 batch item (3 + 3), BUKAN 4 A5 + 2 A6 berulang
    expect(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(6)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(6);

    @unlink($path);
});

it('normalisasi nomor HP ke format +62', function () {
    $path = buatExcelDonatur('DN-IMP-3', 1, 0, 0);

    Excel::import(new DonaturImport(new DonaturImportService), $path);

    $donatur = Donatur::where('kode_donatur', 'DN-IMP-3')->firstOrFail();
    expect($donatur->no_hp)->toStartWith('+62');

    @unlink($path);
});
