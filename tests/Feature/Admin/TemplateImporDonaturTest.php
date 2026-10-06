<?php

use App\Exports\DonaturTemplateExport;
use App\Imports\DonaturImport;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafItem;
use App\Services\DonaturImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * Templat impor yang BENAR-BENAR diunduh pengguna.
 *
 * Tes impor yang lama membuat berkas Excel-nya sendiri, sehingga templat yang sungguh
 * diunduh dari halaman Manajemen Donasi Wakaf tidak pernah ikut teruji. Kalau templat
 * dan pembacanya berselisih, pengguna melihat kegagalan yang tidak bisa dijelaskan —
 * dan tidak ada tes yang menangkapnya.
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

/** Simpan templat ke berkas, seperti yang diterima pengguna saat mengunduhnya. */
function simpanTemplat(): string
{
    $path = sys_get_temp_dir().'/templat-donatur-'.uniqid().'.xlsx';
    Excel::store(new DonaturTemplateExport, basename($path), 'local');

    // Excel::store menulis ke disk; salin ke jalur sementara agar mudah dibaca.
    $isi = Storage::disk('local')->get(basename($path));
    file_put_contents($path, $isi);
    Storage::disk('local')->delete(basename($path));

    return $path;
}

/** Baca seluruh baris templat apa adanya (tanpa perantara pustaka impor). */
function bacaTemplat(string $path): array
{
    $ss = IOFactory::load($path);
    $baris = $ss->getActiveSheet()->toArray(null, true, false, false);

    return array_values(array_filter($baris, fn ($b) => array_filter($b, fn ($v) => $v !== null && $v !== '') !== []));
}

it('judul kolom di templat persis sama dengan yang dibaca pengimpor', function () {
    $path = simpanTemplat();

    $judul = bacaTemplat($path)[0];

    // Inilah nama kolom yang diakses DonaturImport lewat WithHeadingRow. Satu huruf saja
    // berbeda, barisnya dianggap kosong dan pengguna hanya melihat "berhasil 0".
    expect($judul)->toBe([
        'kode_donatur',
        'nama_donatur',
        'no_hp',
        'email_donatur',
        'alamat_donatur',
        'jumlah_a5',
        'jumlah_a6',
        'jumlah_iqra',
        'donation_date',
        'doa_untuk_semua',
    ]);

    @unlink($path);
});

it('templat memuat contoh baris yang bisa dibaca pengimpor', function () {
    $path = simpanTemplat();

    $baris = bacaTemplat($path);

    expect(count($baris))->toBeGreaterThan(1);

    foreach (array_slice($baris, 1) as $contoh) {
        expect($contoh[0])->not->toBeEmpty()   // kode_donatur
            ->and($contoh[1])->not->toBeEmpty() // nama_donatur
            ->and($contoh[2])->not->toBeEmpty() // no_hp
            ->and($contoh[8])->not->toBeEmpty(); // donation_date
    }

    @unlink($path);
});

it('contoh baris di templat dapat diimpor tanpa galat', function () {
    $path = simpanTemplat();

    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    $hasil = $import->getResults();

    // Setiap contoh di templat harus lolos aturan impor sendiri. Kalau tidak, templat
    // mengajarkan format yang justru ditolak oleh aplikasinya.
    expect($hasil['error_count'])->toBe(0)
        ->and($hasil['success_count'])->toBeGreaterThan(0);

    @unlink($path);
});

it('contoh baris di templat menghasilkan donatur lengkap dengan item dan resi', function () {
    $path = simpanTemplat();

    Excel::import(new DonaturImport(new DonaturImportService), $path);

    $donatur = Donatur::where('kode_donatur', 'DN-001')->firstOrFail();

    expect($donatur->nama_donatur)->not->toBeEmpty()
        ->and($donatur->no_hp)->toStartWith('+62');

    // 2 A5 + 1 A6 = 3 mushaf, masing-masing dengan satu resi.
    expect($donatur->total_a5_count)->toBe(2)
        ->and($donatur->total_a6_count)->toBe(1)
        ->and(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(3)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(3);

    @unlink($path);
});

it('tanggal contoh di templat terbaca sebagai tanggal, bukan teks', function () {
    $path = simpanTemplat();

    Excel::import(new DonaturImport(new DonaturImportService), $path);

    $donatur = Donatur::where('kode_donatur', 'DN-001')->firstOrFail();

    // Kalau tanggal di templat disimpan sebagai teks yang tidak bisa dibaca, impor tetap
    // "berhasil" tetapi tanggalnya null — kesalahan yang mudah lolos dari mata.
    expect($donatur->donation_date)->not->toBeNull()
        ->and($donatur->donation_date->format('Y-m-d'))->toBe('2024-01-15');

    @unlink($path);
});
