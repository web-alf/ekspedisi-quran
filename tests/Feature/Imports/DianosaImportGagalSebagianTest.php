<?php

use App\Imports\MushafRequestImport;
use App\Models\MushafRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Validators\Failure;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::fake(['*' => Http::response([], 200)]);
});

/**
 * Membuktikan apa yang terjadi kalau SEBAGIAN baris berkas tidak memenuhi syarat.
 *
 * Keluhan "berkas tidak bisa diimport dengan sempurna" biasanya begini:
 * sebagian baris masuk, sisanya hilang tanpa penjelasan apa pun.
 */
it('melaporkan baris yang gagal validasi sebagai error', function () {
    $import = new MushafRequestImport;

    // Baris 2 lengkap; baris 3 TIDAK punya nomor HP (wajib).
    $rows = new Collection([
        [
            'nama_lembaga' => 'TPQ Lengkap',
            'nama_penanggung_jawab_1' => 'Ahmad',
            'nomor_hp' => '081234567890',
            'alamat_lengkap' => 'Jl. Mawar No. 1, Blitar',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '100',
            'urgensi' => 'sedang',
        ],
        [
            'nama_lembaga' => 'TPQ Tanpa HP',
            'nama_penanggung_jawab_1' => 'Budi',
            'nomor_hp' => '', // <- wajib, kosong
            'alamat_lengkap' => 'Jl. Melati No. 2, Blitar',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50',
            'urgensi' => 'sedang',
        ],
    ]);

    // Jalankan validasi seperti yang dilakukan Excel::import + WithValidation:
    // aturannya diberi awalan indeks baris (0.nama_lembaga, 1.nomor_hp, ...).
    $aturan = [];
    foreach ($rows->all() as $i => $baris) {
        foreach ($import->rules() as $kolom => $rule) {
            $aturan["{$i}.{$kolom}"] = $rule;
        }
    }

    $validator = validator($rows->all(), $aturan, $import->customValidationMessages());

    // Hanya baris pertama yang lolos; baris kedua gagal pada nomor_hp.
    expect($validator->passes())->toBeFalse();
    expect(array_keys($validator->errors()->toArray()))->toContain('1.nomor_hp');
});

it('melaporkan ada masalah walau baris gagal VALIDASI, bukan hanya gagal simpan', function () {
    $import = new MushafRequestImport;

    // Tirukan tepat apa yang dilakukan Excel::import: baris gagal validasi TIDAK
    // sampai ke collection(), jadi hanya tersimpan di $failures — bukan $errors.
    $import->onFailure(
        new Failure(3, 'nomor_hp', ['Nomor HP wajib diisi'], [
            'nama_lembaga' => 'TPQ Tanpa HP',
        ])
    );

    $hasil = $import->getResults();

    // getResults() menghitung kegagalan validasi...
    expect($hasil['error_count'])->toBe(1);

    // ...dan hasErrors() HARUS melihatnya juga. Controller memakai hasErrors()
    // untuk memutuskan apakah akan memperingatkan pengguna; kalau ia hanya
    // melihat kegagalan simpan, halaman menampilkan "Berhasil import" untuk
    // berkas yang barisnya hilang separuh.
    expect($import->hasErrors())
        ->toBeTrue('kegagalan validasi harus dianggap masalah');

    expect($hasil['errors'])->not->toBeEmpty();
});

it('memperingatkan pengguna saat hanya SEBAGIAN berkas yang masuk', function () {
    $import = new MushafRequestImport;

    // Dua baris lolos, satu gagal validasi (tidak pernah sampai ke collection()).
    $import->collection(new Collection([
        [
            'nama_lembaga' => 'TPQ Satu',
            'nama_penanggung_jawab_1' => 'Ahmad',
            'nomor_hp' => '081111111111',
            'alamat_lengkap' => 'Alamat satu',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '100',
            'urgensi' => 'sedang',
        ],
        [
            'nama_lembaga' => 'TPQ Dua',
            'nama_penanggung_jawab_1' => 'Budi',
            'nomor_hp' => '082222222222',
            'alamat_lengkap' => 'Alamat dua',
            'link_gmaps' => '',
            'jumlah_kebutuhan_mushaf' => '50',
            'urgensi' => 'sedang',
        ],
    ]));

    // Baris ketiga: gagal validasi, jadi Excel tidak memanggil collection().
    $import->onFailure(
        new Failure(4, 'alamat_lengkap', ['Alamat lengkap wajib diisi'], [
            'nama_lembaga' => 'TPQ Tiga',
        ])
    );

    $hasil = $import->getResults();

    expect(MushafRequest::count())->toBe(2, 'dua baris masuk');
    expect($hasil['success_count'])->toBe(2);
    expect($hasil['error_count'])->toBe(1, 'satu baris gagal');

    // Yang penting: baris ke-3 yang hilang TIDAK boleh lewat tanpa pemberitahuan.
    expect($import->hasErrors())->toBeTrue();

    // Dan pesannya harus menyebut angka yang berhasil, supaya pengguna tahu
    // berkasnya tidak masuk seluruhnya — bukan sekadar "ada N yang gagal".
    $pesan = ($hasil['success_count'] > 0)
        ? "Import selesai: {$hasil['success_count']} data berhasil, {$hasil['error_count']} data GAGAL dan tidak ikut masuk"
        : "Tidak ada data yang masuk: {$hasil['error_count']} baris gagal";

    expect($pesan)->toContain('2 data berhasil')
        ->and($pesan)->toContain('1 data GAGAL');
});

it('membuktikan baris tanpa nama lembaga tetap menimbulkan error yang terlihat', function () {
    $import = new MushafRequestImport;

    // Kalau nama_lembaga tidak ada, collection() melempar — jadi jalur INI
    // tercatat di $errors dan hasErrors() true. Artinya hanya kegagalan
    // VALIDASI yang tidak terlaporkan; kegagalan saat menyimpan terlaporkan.
    $import->collection(new Collection([[
        'nama_penanggung_jawab_1' => 'Ahmad',
        'nomor_hp' => '081234567890',
        'alamat_lengkap' => 'Alamat',
        'link_gmaps' => '',
        'jumlah_kebutuhan_mushaf' => '100',
        'urgensi' => 'sedang',
    ]]));

    expect(MushafRequest::count())->toBe(0);
    expect($import->hasErrors())->toBeTrue();
});
