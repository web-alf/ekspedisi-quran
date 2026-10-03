<?php

use App\Exports\MushafRequestTemplateExport;
use App\Imports\MushafRequestImport;
use App\Models\MushafRequest;
use App\Services\Cache\GeographicCacheService;
use App\Services\MushafAddressResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

/**
 * Cakupan: kolom alamat (provinsi, kota/kabupaten, kecamatan, kelurahan) pada
 * permintaan mushaf hasil IMPORT.
 *
 * Dulu seluruh kolom ini SELALU null, sehingga halaman detail tidak
 * menampilkan alamat lengkap dan form "Informasi Lembaga" — yang mewajibkan
 * latitude/longitude — tidak bisa disimpan.
 */

/** Koordinat & alamat nyata dari template resmi. */
const LAT_TPL = -8.0868357;

const LNG_TPL = 112.2396983;

const ALAMAT_TPL = 'Lingkungan, Plosorejo RT.2/RW.5, Bence, Kec. Garum, Blitar';

/** Siapkan tiruan layanan: perpanjangan tautan, reverse geocoding, dan data wilayah. */
function tirukanLayananWilayah(): void
{
    Http::fake([
        // Tautan pendek Google Maps -> URL panjang yang memuat koordinat.
        'maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Masjid/@'.LAT_TPL.','.LNG_TPL.',17z',
        ]),

        // Reverse geocoding (Nominatim).
        'nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Bence, Blitar, Jawa Timur, 66182, Indonesia',
            'address' => [
                'village' => 'Bence',
                'county' => 'Blitar',
                'state' => 'Jawa Timur',
                'postcode' => '66182',
                'country' => 'Indonesia',
            ],
        ]),

        // Data wilayah (emsifa) — dipangkas ke yang dipakai uji ini.
        '*/provinces.json' => Http::response([
            ['id' => '35', 'name' => 'JAWA TIMUR'],
        ]),
        '*/regencies/35.json' => Http::response([
            ['id' => '3505', 'province_id' => '35', 'name' => 'KABUPATEN BLITAR'],
            ['id' => '3572', 'province_id' => '35', 'name' => 'KOTA BLITAR'],
        ]),
        '*/districts/3505.json' => Http::response([
            ['id' => '3505160', 'regency_id' => '3505', 'name' => 'GARUM'],
            ['id' => '3505100', 'regency_id' => '3505', 'name' => 'SLOROK'],
        ]),
        '*/villages/3505160.json' => Http::response([
            ['id' => '3505160003', 'district_id' => '3505160', 'name' => 'GARUM'],
            ['id' => '3505160007', 'district_id' => '3505160', 'name' => 'SLOROK'],
            ['id' => '3505160009', 'district_id' => '3505160', 'name' => 'KARANGREJO'],
        ]),
    ]);
}

beforeEach(function () {
    Cache::flush();
    tirukanLayananWilayah();
    $this->resolver = new MushafAddressResolver(app(GeographicCacheService::class));
});

// ============================================
// PENGISIAN OTOMATIS
// ============================================

it('mengisi provinsi, kota/kabupaten, kecamatan, kode pos, dan koordinat', function () {
    $hasil = $this->resolver->resolve(ALAMAT_TPL, 'https://maps.app.goo.gl/na4EG41yECawFzka8');

    expect($hasil['provinsi'])->toBe('JAWA TIMUR')
        ->and($hasil['provinsi_id'])->toBe('35')
        ->and($hasil['kota_kabupaten'])->toBe('KABUPATEN BLITAR')
        ->and($hasil['kota_kabupaten_id'])->toBe('3505')
        ->and($hasil['kecamatan'])->toBe('GARUM')
        ->and($hasil['kecamatan_id'])->toBe('3505160')
        ->and($hasil['kode_pos'])->toBe('66182')
        ->and((float) $hasil['latitude'])->toBe(LAT_TPL)
        ->and((float) $hasil['longitude'])->toBe(LNG_TPL);
});

it('memperluas tautan peta pendek karena koordinatnya tidak ada di dalam tautan', function () {
    // maps.app.goo.gl tidak memuat koordinat sama sekali — tanpa diperluas,
    // alamat tidak akan pernah terisi.
    $hasil = $this->resolver->resolve(ALAMAT_TPL, 'https://maps.app.goo.gl/na4EG41yECawFzka8');

    expect($hasil['latitude'])->not->toBeNull()
        ->and($hasil['provinsi'])->toBe('JAWA TIMUR');
});

it('memakai tautan peta yang sudah memuat koordinat tanpa memperluas', function () {
    $hasil = $this->resolver->resolve(
        ALAMAT_TPL,
        'https://www.google.com/maps/place/@'.LAT_TPL.','.LNG_TPL.',17z',
    );

    expect((float) $hasil['latitude'])->toBe(LAT_TPL)
        ->and($hasil['provinsi'])->toBe('JAWA TIMUR');
});

// ============================================
// TIDAK MENEBAK — bagian terpenting
// ============================================

it('tidak menebak kelurahan dari NAMA JALAN yang kebetulan sama dengan kelurahan', function () {
    // "Jl. Slorok, ... Kec. Garum" — Slorok juga nama kelurahan di Garum,
    // tetapi alamat ini menyebutnya sebagai JALAN, bukan kelurahan.
    // Kalau ini lolos, baris berisi kelurahan yang salah tanpa gejala apa pun.
    $hasil = $this->resolver->resolve(
        'Jl. Slorok, RT.2/RW.1, Lingkungan Tanggung, Bence, Kec. Garum, Blitar',
        'https://maps.app.goo.gl/uXgwGX5nAtuyiSPJ9',
    );

    expect($hasil['kelurahan_desa'])->toBeNull('Nama jalan tidak boleh dianggap kelurahan')
        ->and($hasil['kelurahan_desa_id'])->toBeNull()
        // Kecamatan tetap harus terbaca — membuang bagian jalan tidak boleh
        // ikut membuang bagian alamat sesudahnya.
        ->and($hasil['kecamatan'])->toBe('GARUM');
});

it('tidak menebak kelurahan yang tidak ada di sumber data wilayah', function () {
    // "Bence" dan "Tanggung" tidak ada di sumber data wilayah; "Slorok" ada di
    // dua kecamatan berbeda. Semuanya harus dibiarkan kosong, bukan ditebak.
    foreach ([ALAMAT_TPL, 'Perum Gardenia G1, Bence, Kec. Garum, Blitar'] as $alamat) {
        $hasil = $this->resolver->resolve($alamat, 'https://maps.app.goo.gl/na4EG41yECawFzka8');
        expect($hasil['kelurahan_desa'])->toBeNull();
    }
});

it('tidak menebak kelurahan bila alamat hanya menyebut nama kecamatannya', function () {
    // Kecamatan GARUM juga nama sebuah kelurahan di kecamatan itu. Tanpa
    // pengecualian, alamat ini menghasilkan "kecamatan GARUM, kelurahan GARUM".
    $hasil = $this->resolver->resolve('Bence, Kec. Garum, Blitar', 'https://maps.app.goo.gl/na4EG41yECawFzka8');

    expect($hasil['kecamatan'])->toBe('GARUM')
        ->and($hasil['kelurahan_desa'])->toBeNull();
});

it('mengisi kelurahan hanya bila alamat benar-benar menyebutnya', function () {
    // Alamat yang menyebut kelurahan secara eksplisit harus terisi — supaya
    // kehati-hatian di atas tidak berubah menjadi "tidak pernah mengisi".
    $hasil = $this->resolver->resolve(
        'Dusun Krajan, Karangrejo, Kec. Garum, Blitar',
        'https://maps.app.goo.gl/na4EG41yECawFzka8',
    );

    expect($hasil['kelurahan_desa'])->toBe('KARANGREJO')
        ->and($hasil['kelurahan_desa_id'])->toBe('3505160009');
});

// ============================================
// TAHAN GAGAL — tidak boleh menghambat import
// ============================================

it('tidak mengisi apa pun bila tautan peta tidak bisa dibaca, dan tetap menyimpan teks alamat', function () {
    $hasil = $this->resolver->resolve(ALAMAT_TPL, 'https://goo.gl/maps/tidak-ada');

    // Tanpa koordinat, menentukan provinsi/kota berarti menyisir seluruh
    // Indonesia — tidak sepadan dengan nilainya, jadi dibiarkan kosong.
    expect($hasil['latitude'])->toBeNull()
        ->and($hasil['provinsi'])->toBeNull()
        ->and($hasil['kecamatan'])->toBeNull()
        // Yang penting: teks alamatnya tidak hilang.
        ->and($hasil['alamat_detail'])->toBe(ALAMAT_TPL);
});

it('tidak memanggil layanan apa pun bila tautan peta tidak ada', function () {
    $hasil = $this->resolver->resolve(ALAMAT_TPL, null);

    Http::assertNothingSent();
    expect($hasil['latitude'])->toBeNull()
        ->and($hasil['provinsi'])->toBeNull();
});

it('tetap menyimpan teks alamat walau wilayahnya tidak bisa diuraikan', function () {
    $hasil = $this->resolver->resolve(ALAMAT_TPL, null);

    expect($hasil['alamat_detail'])->toBe(ALAMAT_TPL);
});

it('membiarkan import berjalan walau layanan geocoding mati', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/@'.LAT_TPL.','.LNG_TPL.',17z',
        ]),
        'nominatim.openstreetmap.org/*' => Http::response('Server Error', 500),
        '*/provinces.json' => Http::response('Server Error', 500),
    ]);

    $import = new MushafRequestImport($this->resolver);
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_lengkap' => ALAMAT_TPL,
        'link_gmaps' => 'https://maps.app.goo.gl/na4EG41yECawFzka8',
        'jumlah_kebutuhan_mushaf' => '100',
        'urgensi' => 'sedang',
    ]]));

    // Yang penting: barisnya TETAP masuk, bukan gagal.
    expect(MushafRequest::count())->toBe(1);
    expect($import->getResults()['success_count'])->toBe(1);

    // Koordinat tetap tersimpan walau geocoding gagal.
    $req = MushafRequest::first();
    expect((float) $req->latitude)->toBe(LAT_TPL)
        ->and($req->alamat_lengkap)->toBe(ALAMAT_TPL);
});

// ============================================
// UJUNG KE UJUNG: template resmi
// ============================================

it('mengisi kolom alamat pada template yang diimpor apa adanya', function () {
    // Inilah keluhan aslinya: berkas template diimpor, dan kolom alamat kosong.
    Excel::store(new MushafRequestTemplateExport, 'template-uji.xlsx');
    $berkas = Storage::disk('local')->path('template-uji.xlsx');

    $import = new MushafRequestImport($this->resolver);
    Excel::import($import, $berkas);

    expect($import->getResults()['success_count'])->toBe(2);

    $req = MushafRequest::first();

    // Baris contoh template mengisi kolom wilayahnya SENDIRI, jadi kolomnya
    // terisi tanpa perlu diuraikan — dan tautan contohnya tidak pernah disentuh.
    expect($req->provinsi)->toBe('Jawa Timur')
        ->and($req->kota_kabupaten)->toBe('Kabupaten Blitar')
        ->and($req->kecamatan)->toBe('Garum')
        ->and($req->kelurahan_desa)->toBe('Contoh Kelurahan')
        ->and($req->alamat_detail)->toBe('Jl. Contoh No. 1, RT.2/RW.5, Lingkungan Contoh')
        ->and((float) $req->latitude)->toBe(-8.0868357)
        ->and((float) $req->longitude)->toBe(112.2396983);

    // Dua baris contoh memakai jalur yang BERBEDA, dan itu memang yang diuji:
    // baris yang sudah punya koordinat tidak menyentuh layanan peta sama sekali,
    // sedangkan baris yang mengosongkan kolom wilayah diuraikan (tautannya
    // diikuti, lalu reverse geocoding dijalankan).
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'contohBarisSatu'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'contohBarisDua'));

    unlink($berkas);
});

it('tidak mengirim data pribadi pemohon di dalam template', function () {
    // Tiga baris contoh template yang lama adalah data pemohon SUNGGUHAN:
    // nama lembaga, alamat, dan nomor HP-nya lengkap serta masih aktif
    // (REQ-2026-00025 s/d 00027). Template yang diunduh dan diedarkan tidak
    // boleh memuat data pribadi orang lain.
    Excel::store(new MushafRequestTemplateExport, 'template-privasi.xlsx');
    $berkas = Storage::disk('local')->path('template-privasi.xlsx');

    $isi = file_get_contents($berkas);

    foreach (['TPQ Al Falah Plosorejo', 'Yayasan Al Hikmah Peduli', 'MI Darul Huda Bence'] as $nama) {
        expect($isi)->not->toContain($nama);
    }

    foreach (['085731507971', '081553843650', '085649645815'] as $hp) {
        expect($isi)->not->toContain($hp);
    }

    unlink($berkas);
});

it('tidak menimpa alamat_lengkap yang sudah diisi pengimpor', function () {
    // Model meng-generate alamat_lengkap dari komponen wilayah saat creating,
    // TETAPI hanya bila kosong. Teks asli dari berkas harus dipertahankan.
    $import = new MushafRequestImport($this->resolver);
    $import->collection(new Collection([[
        'nama_lembaga' => 'TPQ Uji',
        'nama_penganggung_jawab_1' => 'Ahmad',
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_lengkap' => ALAMAT_TPL,
        'link_gmaps' => 'https://maps.app.goo.gl/na4EG41yECawFzka8',
        'jumlah_kebutuhan_mushaf' => '100',
        'urgensi' => 'sedang',
    ]]));

    expect(MushafRequest::first()->alamat_lengkap)->toBe(ALAMAT_TPL);
});
