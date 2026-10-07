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
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Pratinjau impor: menunjukkan berapa yang AKAN masuk, sebelum apa pun tersimpan.
 *
 * Pada impor puluhan ribu baris, salah ketik jumlah tidak lagi ketahuan setelah impor
 * selesai — jadi harus ketahuan sebelum. Yang paling penting dari pratinjau ini bukan
 * angkanya, melainkan bahwa ia TIDAK menyimpan apa pun.
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

    // Izin donatur.import dipasang lewat peran: rutenya dijaga middleware, bukan
    // sekadar pemanggilan policy di dalam controller.
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    foreach (['dashboard.view', 'donatur.read', 'donatur.create', 'donatur.update', 'donatur.import'] as $izin) {
        Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
    }
    Role::findByName('super-admin')->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('super-admin');
    $this->actingAs($this->user);
});

function buatBerkasImpor(array $baris): string
{
    $judul = [
        'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur',
        'jumlah_a5', 'jumlah_a6', 'jumlah_iqra', 'donation_date', 'doa_untuk_semua',
        'wakif_name', 'doa_request', 'relationship_to_donatur',
    ];

    $ss = new Spreadsheet;
    $ss->getActiveSheet()->fromArray($judul, null, 'A1');
    $ss->getActiveSheet()->fromArray($baris, null, 'A2');

    $path = sys_get_temp_dir().'/pratinjau-'.uniqid().'.xlsx';
    (new XlsxWriter($ss))->save($path);

    return $path;
}

it('pratinjau melaporkan jumlah donatur, mushaf, dan rincian per jenis', function () {
    $path = buatBerkasImpor([
        ['DN-P1', 'Irvan', '081234567890', null, null, 10, 0, 0, '2026-01-15', null, 'Alm. Ahmad', null, 'Almarhum'],
        ['DN-P1', null, null, null, null, 5, 0, 0, null, null, 'Ibu Siti', null, 'Keluarga'],
        ['DN-P2', 'Budi', '081234567891', null, null, 3, 2, 1, '2026-01-16', 'doa', null, null, null],
    ]);

    $res = $this->post('/admin/donatur-import-pratinjau', ['file' => UploadedFile::fake()->createWithContent('uji.xlsx', file_get_contents($path))]);

    $res->assertSuccessful()
        ->assertJson([
            'jumlah_donatur' => 2,
            'jumlah_mushaf' => 21, // 15 + 6
            'a5' => 18,
            'a6' => 2,
            'iqra' => 1,
            'donatur_baru' => 2,
            'donatur_ada' => 0,
        ]);

    // Rincian per donatur ikut dikirim supaya halaman bisa menampilkannya.
    $perDonatur = $res->json('per_donatur');
    expect($perDonatur)->toHaveCount(2)
        ->and($perDonatur[0]['kode'])->toBe('DN-P1')
        ->and($perDonatur[0]['baris'])->toBe(2)
        ->and($perDonatur[0]['mushaf'])->toBe(15)
        ->and($perDonatur[0]['wakifBerbeda'])->toBe(2);

    @unlink($path);
});

it('pratinjau TIDAK menyimpan apa pun ke basis data', function () {
    $path = buatBerkasImpor([
        ['DN-P3', 'Irvan', '081234567890', null, null, 3, 0, 0, '2026-01-15', null, null, null, null],
    ]);

    $this->post('/admin/donatur-import-pratinjau', ['file' => UploadedFile::fake()->createWithContent('uji.xlsx', file_get_contents($path))])
        ->assertSuccessful();

    // Inilah inti pengamannya: pratinjau hanya membaca.
    expect(Donatur::count())->toBe(0)
        ->and(WakafItem::count())->toBe(0)
        ->and(Pengiriman::count())->toBe(0);

    @unlink($path);
});

it('pratinjau membedakan donatur baru dari donatur yang sudah ada', function () {
    Donatur::create([
        'kode_donatur' => 'DN-SUDAH', 'nama_donatur' => 'Lama', 'no_hp' => '+628111111111',
        'donation_date' => '2026-01-01', 'prayer_mode' => 'semua_donatur',
        'total_a5_count' => 1, 'total_a6_count' => 0, 'total_iqra_count' => 0, 'donation_count' => 1,
        'created_by' => $this->user->id,
    ]);

    $path = buatBerkasImpor([
        ['DN-SUDAH', 'Lama', '08111111111', null, null, 1, 0, 0, '2026-01-15', null, null, null, null],
        ['DN-BARU', 'Baru', '08222222222', null, null, 1, 0, 0, '2026-01-15', null, null, null, null],
    ]);

    $res = $this->post('/admin/donatur-import-pratinjau', ['file' => UploadedFile::fake()->createWithContent('uji.xlsx', file_get_contents($path))]);

    $res->assertSuccessful()->assertJson([
        'donatur_baru' => 1,
        'donatur_ada' => 1,
        'jumlah_donatur' => 2,
    ]);

    @unlink($path);
});

it('pratinjau melaporkan baris bermasalah tanpa menghentikan pembacaan', function () {
    $path = buatBerkasImpor([
        ['DN-BAIK', 'Irvan', '081234567890', null, null, 2, 0, 0, '2026-01-15', null, null, null, null],
        ['DN-RUSAK', null, '081234567891', null, null, 2, 0, 0, '2026-01-15', null, null, null, null], // tanpa nama
    ]);

    $res = $this->post('/admin/donatur-import-pratinjau', ['file' => UploadedFile::fake()->createWithContent('uji.xlsx', file_get_contents($path))]);

    $res->assertSuccessful()
        ->assertJson(['jumlah_donatur' => 1]);

    expect($res->json('galat'))->toHaveCount(1)
        ->and($res->json('galat.0.error'))->toContain('nama_donatur');

    @unlink($path);
});

it('membandingkan angka pratinjau dengan hasil impor sungguhan', function () {
    $baris = [
        ['DN-BANDING', 'Irvan', '081234567890', null, null, 10, 0, 0, '2026-01-15', null, 'Alm. Ahmad', null, 'Almarhum'],
        ['DN-BANDING', null, null, null, null, 7, 3, 2, null, null, 'Ibu Siti', null, 'Keluarga'],
    ];
    $path = buatBerkasImpor($baris);

    $pratinjau = $this->post('/admin/donatur-import-pratinjau', ['file' => UploadedFile::fake()->createWithContent('uji.xlsx', file_get_contents($path))]);
    $angkaPratinjau = [
        'donatur' => $pratinjau->json('jumlah_donatur'),
        'mushaf' => $pratinjau->json('jumlah_mushaf'),
    ];

    // Impor sungguhan atas berkas yang sama.
    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);
    $hasil = $import->getResults();

    // Kalau angka di layar berbeda dari hasil impornya, pratinjaunya menyesatkan.
    expect($angkaPratinjau['donatur'])->toBe($hasil['success_count'])
        ->and($angkaPratinjau['mushaf'])->toBe($hasil['mushaf_count']);

    @unlink($path);
});

it('pratinjau ditolak bagi yang tidak berizin impor', function () {
    $path = buatBerkasImpor([
        ['DN-TOLAK', 'Irvan', '081234567890', null, null, 1, 0, 0, '2026-01-15', null, null, null, null],
    ]);

    $pengguna = User::factory()->create(['is_active' => true]);
    $this->actingAs($pengguna);

    $this->post('/admin/donatur-import-pratinjau', ['file' => UploadedFile::fake()->createWithContent('uji.xlsx', file_get_contents($path))])
        ->assertForbidden();

    @unlink($path);
});
