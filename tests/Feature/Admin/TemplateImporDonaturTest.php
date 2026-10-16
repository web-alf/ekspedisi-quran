<?php

use App\Exports\DonaturTemplateWorkbook;
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
    Excel::store(new DonaturTemplateWorkbook, basename($path), 'local');

    // Excel::store menulis ke disk; salin ke jalur sementara agar mudah dibaca.
    file_put_contents($path, Storage::disk('local')->get(basename($path)));
    Storage::disk('local')->delete(basename($path));

    return $path;
}

/** Baca seluruh baris lembar data apa adanya (tanpa perantara pustaka impor). */
function bacaTemplat(string $path): array
{
    $ss = IOFactory::load($path);
    $baris = $ss->getSheetByName('Donatur')->toArray(null, true, false, false);

    return array_values(array_filter($baris, fn ($b) => array_filter($b, fn ($v) => $v !== null && $v !== '') !== []));
}

it('judul kolom di templat persis sama dengan yang dibaca pengimpor', function () {
    $path = simpanTemplat();

    $judul = bacaTemplat($path)[0];

    // Inilah nama kolom yang diakses DonaturImport lewat WithHeadingRow. Satu huruf saja
    // berbeda, kolomnya dianggap kosong dan pengguna hanya melihat "berhasil 0".
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
        'wakif_name',
        'doa_request',
        'relationship_to_donatur',
    ]);

    @unlink($path);
});

it('setiap baris contoh menyebut donatur yang lengkap', function () {
    $path = simpanTemplat();

    $baris = bacaTemplat($path);

    expect(count($baris))->toBeGreaterThan(1);

    // Satu donatur boleh ditulis beberapa baris, jadi yang wajib lengkap adalah baris
    // PERTAMA tiap donatur — baris lanjutannya boleh mengosongkan kolom keterangan.
    $sudahLengkap = [];
    foreach (array_slice($baris, 1) as $contoh) {
        $kode = $contoh[0];
        expect($kode)->not->toBeEmpty();

        if (isset($sudahLengkap[$kode])) {
            continue;
        }

        expect($contoh[1])->not->toBeEmpty()   // nama_donatur
            ->and($contoh[2])->not->toBeEmpty() // no_hp
            ->and($contoh[8])->not->toBeEmpty(); // donation_date

        $sudahLengkap[$kode] = true;
    }

    // Templat harus mencontohkan cara berulang, bukan hanya satu baris per donatur.
    expect($baris)->toHaveCount(4); // 1 judul + 2 baris DN-001 + 1 baris DN-002

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

    // DN-001 ditulis 2 baris: 10 A5 + 5 A5 = 15 mushaf, masing-masing satu resi.
    expect($donatur->total_a5_count)->toBe(15)
        ->and(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(15)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(15)
        // Dua baris tetap satu donasi.
        ->and($donatur->donation_count)->toBe(1);

    // Nama wakif per baris benar-benar terpisah.
    expect(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Alm. H. Ahmad Subarjo')->count())->toBe(10)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Ibu Siti Aminah')->count())->toBe(5);

    @unlink($path);
});

it('contoh yang mushafnya seragam memakai satu baris saja', function () {
    $path = simpanTemplat();

    Excel::import(new DonaturImport(new DonaturImportService), $path);

    $donatur = Donatur::where('kode_donatur', 'DN-002')->firstOrFail();

    expect($donatur->total_a5_count)->toBe(3)
        ->and($donatur->total_a6_count)->toBe(2)
        ->and($donatur->total_iqra_count)->toBe(1)
        ->and($donatur->prayer_mode)->toBe('semua_donatur')
        // Nama wakif dikosongkan -> diisi nama donatur, seperti perilaku lama.
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Budi Santoso')->count())->toBe(6);

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

it('templat menyertakan lembar petunjuk yang menjelaskan arti kolom jumlah', function () {
    $path = simpanTemplat();

    $ss = IOFactory::load($path);

    expect($ss->getSheetNames())->toBe(['Donatur', 'Petunjuk']);

    // Tanpa penjelasan ini, tim entry harus menebak bahwa jumlah_a5 berarti jumlah
    // MUSHAF — salah tebak berujung pada data yang masuk tetapi salah, bukan galat.
    $isi = implode(' ', array_map(
        fn ($b) => implode(' ', array_filter($b, fn ($v) => $v !== null)),
        $ss->getSheetByName('Petunjuk')->toArray()
    ));

    expect($isi)->toContain('jumlah MUSHAF, bukan jumlah dus')
        ->and($isi)->toContain('BEBERAPA BARIS')
        ->and($isi)->toContain('wakif_name');

    @unlink($path);
});
