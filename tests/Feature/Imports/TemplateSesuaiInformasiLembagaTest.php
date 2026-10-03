<?php

use App\Exports\MushafRequestTemplateExport;
use App\Imports\MushafRequestImport;
use App\Models\MushafRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * Cakupan: template import harus memuat kolom yang sepadan dengan field yang ada
 * di halaman detail permintaan — kartu "Informasi Lembaga", "Informasi Kontak",
 * dan "Detail Permintaan".
 *
 * Sebelumnya template hanya memuat satu kolom `alamat_lengkap`, sementara form
 * "Informasi Lembaga" memecah alamat menjadi provinsi/kota/kecamatan/kelurahan/
 * kode pos/detail dan MEWAJIBKAN latitude+longitude. Akibatnya permintaan hasil
 * import tidak bisa disimpan lewat form itu.
 */
beforeEach(function () {
    Cache::flush();
    Http::fake(['*' => Http::response([], 200)]);
});

// ============================================
// TEMPLATE: kelengkapan kolom
// ============================================

it('memuat kolom untuk setiap field yang wajib diisi form Informasi Lembaga', function () {
    $kolom = (new MushafRequestTemplateExport)->headings();

    // Pasangan: field yang dikirim form "Informasi Lembaga"
    // (lihat updateLembaga pada MushafRequestController) => kolom di template.
    $wajib = [
        'nama_lembaga' => 'nama_lembaga',
        'kategori_lembaga' => 'kategori_lembaga',
        'alamat_lengkap' => 'alamat_lengkap',
        'provinsi' => 'provinsi',
        'kota_kabupaten' => 'kota_kabupaten',
        'kecamatan' => 'kecamatan',
        'kelurahan_desa' => 'kelurahan_desa',
        'kode_pos' => 'kode_pos',
        'alamat_detail' => 'alamat_detail',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'urgensi_request' => 'urgensi',
        'sumber_info' => 'sumber_info',
    ];

    foreach ($wajib as $fieldForm => $kolomTemplate) {
        expect(in_array($kolomTemplate, $kolom, true))
            ->toBeTrue("Template tidak punya kolom `{$kolomTemplate}` untuk field `{$fieldForm}`");
    }

    // Field kontak & detail permintaan, supaya satu berkas cukup memuat semuanya.
    foreach ([
        'nama_penanggung_jawab_1',
        'nomor_hp',
        'jabatan_penanggung_jawab_1',
        'jumlah_mushaf_a5',
        'jumlah_mushaf_a6',
        'jumlah_iqra',
    ] as $tambahan) {
        expect(in_array($tambahan, $kolom, true))->toBeTrue("Template tidak punya kolom `{$tambahan}`");
    }
});

it('memakai nama kolom yang dipetakan ke kolom tabel yang benar-benar ada', function () {
    $kolom = (new MushafRequestTemplateExport)->headings();

    // Hampir semuanya sama dengan nama kolom tabel; beberapa di antaranya
    // memakai istilah yang lebih jelas di berkas lalu dipetakan oleh pengimpor.
    $peta = [
        'nama_penanggung_jawab_1' => 'nama_pengurus_1',
        'nomor_hp' => 'whatsapp_pengurus_1',
        'jabatan_penanggung_jawab_1' => 'jabatan_pengurus_1',
        'urgensi' => 'urgensi_request',
    ];

    foreach ($kolom as $nama) {
        $namaTabel = $peta[$nama] ?? $nama;

        expect(Schema::hasColumn('mushaf_requests', $namaTabel))
            ->toBeTrue("Kolom template `{$nama}` tidak punya padanan di tabel (dicoba `{$namaTabel}`)");
    }
});

it('menaruh baris contoh di lembar Data dan panduannya di lembar terpisah', function () {
    Storage::fake('local');
    Excel::store(new MushafRequestTemplateExport, 'tpl.xlsx');
    $berkas = Storage::disk('local')->path('tpl.xlsx');

    $spreadsheet = IOFactory::load($berkas);
    $nama = $spreadsheet->getSheetNames();

    expect($nama)->toBe(['Data', 'Panduan Kolom'])
        ->and($spreadsheet->getActiveSheetIndex())->toBe(0, 'berkas dibuka pada lembar Data');

    // Pengimpor tidak memakai WithMultipleSheets, jadi hanya lembar PERTAMA yang
    // menjadi data — panduannya tidak akan ikut terimport sebagai baris.
    $import = new MushafRequestImport;
    Excel::import($import, $berkas);

    expect(MushafRequest::count())->toBe(1)
        ->and($import->getResults()['error_count'])->toBe(0);

    Storage::disk('local')->delete('tpl.xlsx');
});

// ============================================
// IMPORT: kolom wilayah yang diisi langsung
// ============================================

it('memakai kolom wilayah yang diisi di berkas apa adanya, tanpa menimpanya', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Sadar Alamat',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'provinsi' => 'DI Yogyakarta',
        'kota_kabupaten' => 'Kota Yogyakarta',
        'kecamatan' => 'Jetis',
        'kelurahan_desa' => 'Cokrodiningratan',
        'kode_pos' => '55233',
        'alamat_detail' => 'Jl. Contoh No. 1',
        'alamat_lengkap' => 'seharusnya tidak menimpa kolom di atas',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'link_gmaps' => '',
        'jumlah_mushaf_a5' => 100,
        'urgensi' => 'sedang',
    ]]));

    $req = MushafRequest::first();

    expect($req->provinsi)->toBe('DI Yogyakarta')
        ->and($req->kota_kabupaten)->toBe('Kota Yogyakarta')
        ->and($req->kecamatan)->toBe('Jetis')
        ->and($req->kelurahan_desa)->toBe('Cokrodiningratan')
        ->and($req->kode_pos)->toBe('55233')
        ->and($req->alamat_detail)->toBe('Jl. Contoh No. 1')
        ->and((float) $req->latitude)->toBe(-7.782)
        ->and((float) $req->longitude)->toBe(110.367);
});

it('tidak menghubungi layanan peta bila barisnya sudah lengkap', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Lengkap',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_detail' => 'Jl. Contoh No. 1',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'link_gmaps' => 'https://maps.app.goo.gl/tautanapapun',
        'jumlah_mushaf_a5' => 100,
        'urgensi' => 'sedang',
    ]]));

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'maps.app.goo.gl'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'nominatim'));
    expect(MushafRequest::count())->toBe(1);
});

// ============================================
// IMPORT: urgensi, kategori, jabatan, sumber info
// ============================================

it('menyimpan deskripsi urgensi apa adanya, bukan menggantinya dengan tingkat', function () {
    $deskripsi = "Qur'an kami rusak terkena banjir";

    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_detail' => 'Jl. Contoh',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'jumlah_mushaf_a5' => 10,
        'urgensi' => 'tinggi',
        'urgensi_request' => $deskripsi,
    ]]));

    // Dulu kolom ini diisi TINGKAT hasil penerkaannya ("sedang"), sehingga
    // cerita dari berkas dibuang — tiga permintaan hasil import tercatat begitu.
    expect(MushafRequest::first()->urgensi_request)->toBe($deskripsi);
});

it('tetap menyimpan deskripsi yang ditulis di kolom urgensi versi lama', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_detail' => 'Jl. Contoh',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'jumlah_mushaf_a5' => 10,
        'urgensi' => "Banyak Al-Qur'an yang sudah rusak",
    ]]));

    expect(MushafRequest::first()->urgensi_request)->toBe("Banyak Al-Qur'an yang sudah rusak");
});

it('menyeragamkan penulisan bila kolom urgensi berisi tingkat', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_detail' => 'Jl. Contoh',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'jumlah_mushaf_a5' => 10,
        'urgensi' => 'urgent',
    ]]));

    expect(MushafRequest::first()->urgensi_request)->toBe('mendesak');
});

it('memakai kategori, jabatan, dan sumber informasi dari berkas', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'Pondok Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'jabatan_penanggung_jawab_1' => 'Pengasuh',
        'kategori_lembaga' => 'Pondok Pesantren',
        'alamat_detail' => 'Jl. Contoh',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'jumlah_mushaf_a5' => 10,
        'urgensi' => 'sedang',
        'sumber_info' => 'Instagram',
    ]]));

    $req = MushafRequest::first();

    expect($req->kategori_lembaga)->toBe('Pondok Pesantren')
        ->and($req->jabatan_pengurus_1)->toBe('Pengasuh')
        ->and($req->sumber_info)->toBe('Instagram');
});

it('memakai nilai bawaan bila kolom opsionalnya dikosongkan', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_detail' => 'Jl. Contoh',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'jumlah_mushaf_a5' => 10,
        'urgensi' => 'sedang',
    ]]));

    $req = MushafRequest::first();

    expect($req->kategori_lembaga)->toBe('Lembaga Lainnya')
        ->and($req->jabatan_pengurus_1)->toBe('Penanggung Jawab')
        ->and($req->sumber_info)->toBe('Import Excel');
});

// ============================================
// IMPORT: jumlah
// ============================================

it('membaca jumlah dari kolom angka per jenis', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_detail' => 'Jl. Contoh',
        'latitude' => -7.782,
        'longitude' => 110.367,
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 20,
        'jumlah_iqra' => 30,
        'urgensi' => 'sedang',
    ]]));

    $req = MushafRequest::first();

    expect($req->jumlah_mushaf_a5)->toBe(100)
        ->and($req->jumlah_mushaf_a6)->toBe(20)
        ->and($req->jumlah_iqra)->toBe(30)
        ->and($req->jumlah_mushaf)->toBe(120)
        ->and($req->jenis_mushaf_diminta)->toContain('A5', 'A6', 'IQRA');
});

it('tetap bisa mengimpor berkas format lama yang hanya punya satu kolom jumlah', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Format Lama',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_lengkap' => 'Jl. Lama No. 2, Blitar',
        'link_gmaps' => '',
        'jumlah_kebutuhan_mushaf' => '50 A5, 10 iqra',
        'urgensi' => 'sedang',
    ]]));

    $req = MushafRequest::first();

    expect(MushafRequest::count())->toBe(1)
        ->and($req->jumlah_mushaf_a5)->toBe(50)
        ->and($req->jumlah_iqra)->toBe(10)
        ->and($req->jenis_mushaf_diminta)->toContain('A5', 'IQRA');
});

it('menolak baris yang tidak menyebut jumlah sama sekali', function () {
    $import = new MushafRequestImport;
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Tanpa Jumlah',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_lengkap' => 'Jl. Contoh',
        'link_gmaps' => '',
        'urgensi' => 'sedang',
    ]]));

    // Dulu baris ini masuk dengan jumlah 0 dan tidak ada keluhan apa pun.
    expect(MushafRequest::count())->toBe(0)
        ->and($import->getResults()['error_count'])->toBe(1)
        ->and($import->getResults()['errors'][0]['error'])->toContain('Jumlah kebutuhan mushaf wajib diisi');
});
