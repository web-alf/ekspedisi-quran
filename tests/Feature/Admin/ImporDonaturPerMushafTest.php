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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

uses(RefreshDatabase::class);

/**
 * Templat impor donatur yang boleh menulis satu donatur dalam BEBERAPA baris.
 *
 * Inti perubahannya: mushaf-mushaf satu donatur bisa berbeda nama wakif, doa, dan
 * hubungannya — 17.907 dari 26.101 item di produksi memang begitu. Sebelumnya templat
 * hanya sanggup satu nama wakif untuk seluruh mushaf, sehingga data seperti itu tidak
 * mungkin dimasukkan.
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

/** Bangun berkas Excel dari baris-baris mentah (judul kolom mengikuti templat). */
function buatExcel(array $baris): string
{
    $judul = [
        'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur',
        'jumlah_a5', 'jumlah_a6', 'jumlah_iqra', 'donation_date', 'doa_untuk_semua',
        'wakif_name', 'doa_request', 'relationship_to_donatur',
    ];

    $ss = new Spreadsheet;
    $ss->getActiveSheet()->fromArray($judul, null, 'A1');
    $ss->getActiveSheet()->fromArray($baris, null, 'A2');

    $path = sys_get_temp_dir().'/impor-'.uniqid().'.xlsx';
    (new XlsxWriter($ss))->save($path);

    return $path;
}

function jalankanImpor(string $path): array
{
    $import = new DonaturImport(new DonaturImportService);
    Excel::import($import, $path);

    return $import->getResults();
}

it('satu donatur boleh ditulis beberapa baris dengan nama wakif berbeda', function () {
    $path = buatExcel([
        ['DN-MULTI', 'Irvan', '081234567890', 'irvan@test.com', 'Jl. Test', 10, 0, 0, '2026-01-15', null, 'Alm. Ahmad', 'doa untuk ahmad', 'Almarhum'],
        ['DN-MULTI', null, null, null, null, 5, 0, 0, null, null, 'Ibu Siti', 'doa untuk siti', 'Keluarga'],
    ]);

    $hasil = jalankanImpor($path);

    expect($hasil['error_count'])->toBe(0)
        ->and($hasil['success_count'])->toBe(1)
        ->and($hasil['mushaf_count'])->toBe(15);

    // Satu donatur, bukan dua.
    expect(Donatur::where('kode_donatur', 'DN-MULTI')->count())->toBe(1);

    $donatur = Donatur::where('kode_donatur', 'DN-MULTI')->firstOrFail();
    expect($donatur->total_a5_count)->toBe(15)
        ->and($donatur->donation_count)->toBe(1) // dua baris tetap SATU donasi
        ->and($donatur->prayer_mode)->toBe('customize_individual');

    expect(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(15)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(15);

    // Nama wakif benar-benar terpisah: 10 untuk yang pertama, 5 untuk yang kedua.
    expect(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Alm. Ahmad')->count())->toBe(10)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Ibu Siti')->count())->toBe(5);

    // Nomor berjalan 1-15 tanpa kembar.
    $nomor = WakafItem::where('donatur_id', $donatur->id)->orderBy('sequence_in_type')->pluck('sequence_in_type')->all();
    expect($nomor)->toBe(range(1, 15));

    @unlink($path);
});

it('doa ikut terpisah per kelompok mushaf', function () {
    $path = buatExcel([
        ['DN-DOA', 'Irvan', '081234567890', null, null, 3, 0, 0, '2026-01-15', null, 'Alm. Ahmad', 'doa ahmad', 'Almarhum'],
        ['DN-DOA', null, null, null, null, 2, 0, 0, null, null, 'Ibu Siti', 'doa siti', 'Keluarga'],
    ]);

    jalankanImpor($path);

    $donatur = Donatur::where('kode_donatur', 'DN-DOA')->firstOrFail();

    expect(WakafItem::where('donatur_id', $donatur->id)->where('doa_request', 'doa ahmad')->count())->toBe(3)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('doa_request', 'doa siti')->count())->toBe(2)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('relationship_to_donatur', 'Almarhum')->count())->toBe(3)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('relationship_to_donatur', 'Keluarga')->count())->toBe(2);

    @unlink($path);
});

it('mushaf yang seragam cukup satu baris walau jumlahnya banyak', function () {
    // Donatur 50 mushaf seperti DKS189 di produksi: satu nama wakif untuk semuanya.
    $path = buatExcel([
        ['DN-BESAR', 'Winaryo', '081234567890', null, null, 50, 0, 0, '2026-01-15', 'doa winaryo', null, null, null],
    ]);

    $hasil = jalankanImpor($path);

    expect($hasil['success_count'])->toBe(1)
        ->and($hasil['mushaf_count'])->toBe(50);

    $donatur = Donatur::where('kode_donatur', 'DN-BESAR')->firstOrFail();

    // Nama wakif dikosongkan -> diisi nama donatur, seperti perilaku lama.
    expect($donatur->total_a5_count)->toBe(50)
        ->and($donatur->prayer_mode)->toBe('semua_donatur')
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Winaryo')->count())->toBe(50)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('doa_request', 'doa winaryo')->count())->toBe(50);

    @unlink($path);
});

it('satu mushaf satu baris juga boleh (cara paling mudah dibayangkan)', function () {
    $baris = [];
    foreach (range(1, 4) as $i) {
        $baris[] = ['DN-SATU', $i === 1 ? 'Irvan' : null, $i === 1 ? '081234567890' : null, null, null, 1, 0, 0, $i === 1 ? '2026-01-15' : null, null, "Wakif {$i}", null, 'Keluarga'];
    }

    $hasil = jalankanImpor(buatExcel($baris));

    expect($hasil['success_count'])->toBe(1)
        ->and($hasil['mushaf_count'])->toBe(4);

    $donatur = Donatur::where('kode_donatur', 'DN-SATU')->firstOrFail();

    expect(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(4)
        ->and($donatur->donation_count)->toBe(1)
        ->and(WakafItem::where('donatur_id', $donatur->id)->where('wakif_name', 'Wakif 3')->count())->toBe(1);

    @unlink($path = '');
});

it('berkas lama (satu baris satu donatur) tetap menghasilkan hasil yang sama', function () {
    // Berkas yang dibuat dengan templat LAMA tidak boleh rusak oleh perubahan ini.
    $path = buatExcel([
        ['DN-LAMA-1', 'Ahmad Fauzi', '+628123456789', 'ahmad@example.com', 'Jl. Mawar', 2, 1, 0, '2024-01-15', 'Semoga bermanfaat'],
        ['DN-LAMA-2', 'Siti Aminah', '08987654321', 'siti@example.com', 'Jl. Melati', 1, 0, 2, '2024-02-20', 'Untuk keluarga besar'],
    ]);

    $hasil = jalankanImpor($path);

    expect($hasil['success_count'])->toBe(2)
        ->and($hasil['error_count'])->toBe(0)
        ->and($hasil['mushaf_count'])->toBe(6);

    $a = Donatur::where('kode_donatur', 'DN-LAMA-1')->firstOrFail();
    expect($a->total_a5_count)->toBe(2)
        ->and($a->total_a6_count)->toBe(1)
        ->and($a->no_hp)->toBe('+628123456789')
        ->and($a->prayer_mode)->toBe('semua_donatur')
        ->and(WakafItem::where('donatur_id', $a->id)->count())->toBe(3);

    $b = Donatur::where('kode_donatur', 'DN-LAMA-2')->firstOrFail();
    expect($b->no_hp)->toBe('+628987654321') // 08... diseragamkan
        ->and(WakafItem::where('donatur_id', $b->id)->count())->toBe(3);

    @unlink($path);
});

it('import ulang kode sama menambah, tidak menggandakan', function () {
    $path = buatExcel([
        ['DN-ULANG', 'Irvan', '081234567890', null, null, 2, 0, 0, '2026-01-15', null, 'Alm. Ahmad', null, 'Almarhum'],
        ['DN-ULANG', null, null, null, null, 1, 0, 0, null, null, 'Ibu Siti', null, 'Keluarga'],
    ]);

    jalankanImpor($path);
    jalankanImpor($path);

    $donatur = Donatur::where('kode_donatur', 'DN-ULANG')->firstOrFail();

    expect(Donatur::where('kode_donatur', 'DN-ULANG')->count())->toBe(1)
        ->and($donatur->total_a5_count)->toBe(6)
        ->and($donatur->donation_count)->toBe(2);

    // 3 mushaf tiap import, jadi 6 — bukan 12.
    expect(WakafItem::where('donatur_id', $donatur->id)->count())->toBe(6)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(6);

    // Nomor tetap berurutan tanpa kembar (kunci unik di DB akan menolak kalau kembar).
    $nomor = WakafItem::where('donatur_id', $donatur->id)->orderBy('sequence_in_type')->pluck('sequence_in_type')->all();
    expect($nomor)->toBe([1, 2, 3, 4, 5, 6]);

    @unlink($path);
});

it('baris tanpa jumlah mushaf dilaporkan, bukan didiamkan', function () {
    $path = buatExcel([
        ['DN-KOSONG', 'Irvan', '081234567890', null, null, 0, 0, 0, '2026-01-15', null, null, null, null],
    ]);

    $hasil = jalankanImpor($path);

    expect($hasil['success_count'])->toBe(0)
        ->and($hasil['error_count'])->toBe(1)
        ->and($hasil['errors'][0]['error'])->toContain('Tidak ada jumlah mushaf');

    @unlink($path);
});

it('baris lanjutan boleh mengosongkan nama donatur dan tetap satu donatur yang benar', function () {
    // Kekhawatiran yang wajar saat melihat berkas contoh: baris ke-2 dan seterusnya
    // punya nama_donatur kosong. Kalau kolom itu benar-benar wajib di tiap baris,
    // seluruh baris lanjutan akan gagal dan berkasnya tidak berguna.
    //
    // Kolom keterangan donatur hanya dibaca dari baris PERTAMA kemunculan kodenya.
    $path = buatExcel([
        ['DN-KOSONG', 'Ibu Hartati', '081234567890', null, null, 10, 0, 0, '2026-02-01', null, 'Alm. Suami', null, 'Almarhum'],
        ['DN-KOSONG', null, null, null, null, 5, 0, 0, null, null, 'Ibu Hartati', null, 'Diri sendiri'],
        ['DN-KOSONG', null, null, null, null, 2, 0, 0, null, null, null, null, null],
    ]);

    $hasil = jalankanImpor($path);

    // Nama kosong di baris lanjutan TIDAK boleh dilaporkan sebagai galat.
    expect($hasil['error_count'])->toBe(0)
        ->and($hasil['success_count'])->toBe(1)
        ->and($hasil['mushaf_count'])->toBe(17);

    $d = Donatur::where('kode_donatur', 'DN-KOSONG')->firstOrFail();

    expect($d->nama_donatur)->toBe('Ibu Hartati')
        ->and($d->no_hp)->toBe('+6281234567890')
        ->and($d->donation_date->format('Y-m-d'))->toBe('2026-02-01')
        ->and($d->donation_count)->toBe(1)
        ->and(WakafItem::where('donatur_id', $d->id)->count())->toBe(17);

    // Tiap baris tetap punya nama wakifnya sendiri; yang dikosongkan jatuh ke nama donatur.
    expect(WakafItem::where('donatur_id', $d->id)->where('wakif_name', 'Alm. Suami')->count())->toBe(10)
        ->and(WakafItem::where('donatur_id', $d->id)->where('wakif_name', 'Ibu Hartati')->count())->toBe(7);

    @unlink($path);
});

it('kolom keterangan yang diisi di baris lanjutan tetap dihormati', function () {
    // Kebalikannya: baris pertama kosong, baris kedua mengisi. Ini tetap harus terbaca,
    // supaya tim entry tidak dihukum karena menaruh keterangan di baris mana pun.
    $path = buatExcel([
        ['DN-BOLAK', null, null, null, null, 4, 0, 0, null, null, 'Alm. Bapak', null, 'Almarhum'],
        ['DN-BOLAK', 'Bapak Sugeng', '085712345678', null, null, 3, 0, 0, '2026-03-05', null, null, null, null],
    ]);

    $hasil = jalankanImpor($path);

    expect($hasil['error_count'])->toBe(0)->and($hasil['success_count'])->toBe(1);

    $d = Donatur::where('kode_donatur', 'DN-BOLAK')->firstOrFail();

    expect($d->nama_donatur)->toBe('Bapak Sugeng')
        ->and($d->no_hp)->toBe('+6285712345678')
        ->and($d->donation_date->format('Y-m-d'))->toBe('2026-03-05')
        ->and(WakafItem::where('donatur_id', $d->id)->where('wakif_name', 'Alm. Bapak')->count())->toBe(4)
        ->and(WakafItem::where('donatur_id', $d->id)->where('wakif_name', 'Bapak Sugeng')->count())->toBe(3);

    @unlink($path);
});

it('baris lanjutan tanpa nama donatur tapi kode belum pernah muncul dilaporkan', function () {
    // Baris pertama sudah menyebut kode ini, jadi baris kedua boleh kosong.
    // Yang diuji kebalikannya: kode baru yang baris pertamanya tidak lengkap.
    $path = buatExcel([
        ['DN-TANPA-NAMA', null, null, null, null, 3, 0, 0, null, null, 'Alm. Ahmad', null, 'Almarhum'],
    ]);

    $hasil = jalankanImpor($path);

    expect($hasil['success_count'])->toBe(0)
        ->and($hasil['error_count'])->toBe(1)
        ->and($hasil['errors'][0]['error'])->toContain('nama_donatur');

    @unlink($path);
});

it('templat memuat lembar petunjuk dan kolom per-mushaf', function () {
    $path = sys_get_temp_dir().'/workbook-'.uniqid().'.xlsx';
    Excel::store(new DonaturTemplateWorkbook, basename($path), 'local');
    file_put_contents($path, Storage::disk('local')->get(basename($path)));
    Storage::disk('local')->delete(basename($path));

    $ss = IOFactory::load($path);

    expect($ss->getSheetNames())->toBe(['Donatur', 'Petunjuk']);

    $judul = $ss->getSheetByName('Donatur')->rangeToArray('A1:M1')[0];
    expect($judul)->toBe([
        'kode_donatur', 'nama_donatur', 'no_hp', 'email_donatur', 'alamat_donatur',
        'jumlah_a5', 'jumlah_a6', 'jumlah_iqra', 'donation_date', 'doa_untuk_semua',
        'wakif_name', 'doa_request', 'relationship_to_donatur',
    ]);

    // Petunjuk harus benar-benar menjelaskan arti kolom jumlah.
    $isiPetunjuk = implode(' ', array_map(
        fn ($b) => implode(' ', array_filter($b, fn ($v) => $v !== null)),
        $ss->getSheetByName('Petunjuk')->toArray()
    ));
    expect($isiPetunjuk)->toContain('jumlah MUSHAF, bukan jumlah dus')
        ->and($isiPetunjuk)->toContain('BEBERAPA BARIS');

    @unlink($path);
});

it('contoh baris di templat dapat diimpor apa adanya', function () {
    $path = sys_get_temp_dir().'/contoh-'.uniqid().'.xlsx';
    Excel::store(new DonaturTemplateWorkbook, basename($path), 'local');
    file_put_contents($path, Storage::disk('local')->get(basename($path)));
    Storage::disk('local')->delete(basename($path));

    $hasil = jalankanImpor($path);

    // Contoh di templat harus lolos aturan impornya sendiri — kalau tidak, templat
    // mengajarkan format yang justru ditolak aplikasinya.
    expect($hasil['error_count'])->toBe(0)
        ->and($hasil['success_count'])->toBe(2)
        ->and($hasil['mushaf_count'])->toBe(21); // DN-001: 10+5, DN-002: 3+2+1

    $a = Donatur::where('kode_donatur', 'DN-001')->firstOrFail();
    expect($a->total_a5_count)->toBe(15)
        ->and(WakafItem::where('donatur_id', $a->id)->where('wakif_name', 'Alm. H. Ahmad Subarjo')->count())->toBe(10)
        ->and(WakafItem::where('donatur_id', $a->id)->where('wakif_name', 'Ibu Siti Aminah')->count())->toBe(5);

    @unlink($path);
});
