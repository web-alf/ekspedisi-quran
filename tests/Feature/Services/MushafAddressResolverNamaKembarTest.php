<?php

use App\Services\Cache\GeographicCacheService;
use App\Services\MushafAddressResolver;

/**
 * Pencocokan nama wilayah yang KEMBAR: banyak kabupaten dan kota memakai nama
 * yang sama persis di dalam satu provinsi.
 *
 * Kasus nyata dari produksi: sebuah permintaan di "Kota Jayapura" tersimpan
 * sebagai KABUPATEN JAYAPURA. Penyebabnya pencocokan membuang awalan
 * KOTA/KABUPATEN, sehingga "JAYAPURA" cocok ke dua entri sekaligus dan yang
 * terpilih adalah yang muncul lebih dulu di daftar — kebetulan kabupatennya.
 *
 * Akibatnya berantai: kecamatan "Abepura" hanya ada di KOTA JAYAPURA (9471),
 * jadi pencarian kecamatan di KABUPATEN JAYAPURA (9403) tidak menemukan apa pun.
 * Kolom kecamatan dan kelurahan tinggal kosong di halaman detail, walaupun
 * namanya tersimpan — dan itulah yang terlihat sebagai "wilayah kosong".
 */
beforeEach(function () {
    $this->resolver = app(MushafAddressResolver::class);
    $this->cache = app(GeographicCacheService::class);
});

it('memilih KOTA JAYAPURA, bukan KABUPATEN JAYAPURA, untuk nama kota yang sama', function () {
    $kota = $this->cache->getRegencies('94') ?? [];

    // Pastikan dulu datanya memang punya dua entri kembar ini
    $idKota = null;
    $idKabupaten = null;
    foreach ($kota as $r) {
        if (mb_strtoupper($r['name']) === 'KOTA JAYAPURA') {
            $idKota = (string) $r['id'];
        }
        if (mb_strtoupper($r['name']) === 'KABUPATEN JAYAPURA') {
            $idKabupaten = (string) $r['id'];
        }
    }

    expect($idKota)->not->toBeNull('data wilayah harus memuat KOTA JAYAPURA');
    expect($idKabupaten)->not->toBeNull('data wilayah harus memuat KABUPATEN JAYAPURA');

    // Resolver harus memilih yang KOTA, dan dari situ kecamatan ABEPURA ketemu.
    $hasil = $this->resolver->resolve(
        'Jl. Contoh No. 10, RT.1/RW.3, KOYA KOSO, Abepura, Kota Jayapura, Papua, 99351',
        null,
        [
            'provinsi' => 'Papua',
            'kota_kabupaten' => 'Kota Jayapura',
            'kecamatan' => 'Abepura',
            'kelurahan_desa' => 'KOYA KOSO',
            'latitude' => -2.6160972,
            'longitude' => 140.6730139,
            'alamat_detail' => 'Jl. Contoh No. 10',
        ],
    );

    expect($hasil['kota_kabupaten_id'])->toBe($idKota);
    expect($hasil['kota_kabupaten_id'])->not->toBe($idKabupaten);

    // Inilah keluhan pengguna: kecamatan dan kelurahan harus ikut terisi
    expect($hasil['kecamatan_id'])->not->toBeNull('kecamatan ABEPURA harus ketemu');
    expect($hasil['kelurahan_desa_id'])->not->toBeNull('kelurahan KOYA KOSO harus ketemu');
});

it('tetap menghormati jenis KABUPATEN bila itu yang tertulis', function () {
    $hasil = $this->resolver->resolve(
        'Jl. Contoh, Kabupaten Jayapura, Papua',
        null,
        [
            'provinsi' => 'Papua',
            'kota_kabupaten' => 'Kabupaten Jayapura',
        ],
    );

    $master = null;
    foreach ($this->cache->getRegencies('94') ?? [] as $r) {
        if ((string) $r['id'] === (string) $hasil['kota_kabupaten_id']) {
            $master = mb_strtoupper($r['name']);
        }
    }

    expect($master)->toBe('KABUPATEN JAYAPURA');
});

it('membedakan kota dan kabupaten untuk nama kembar lain (Blitar)', function () {
    $daftar = $this->cache->getRegencies('35') ?? [];
    $punyaKeduanya = collect($daftar)->filter(
        fn ($r) => in_array(mb_strtoupper($r['name']), ['KOTA BLITAR', 'KABUPATEN BLITAR'], true)
    );
    expect($punyaKeduanya)->toHaveCount(2);

    $hasil = $this->resolver->resolve('Jl. Contoh, Kota Blitar, Jawa Timur', null, [
        'provinsi' => 'Jawa Timur',
        'kota_kabupaten' => 'Kota Blitar',
    ]);

    $master = null;
    foreach ($daftar as $r) {
        if ((string) $r['id'] === (string) $hasil['kota_kabupaten_id']) {
            $master = mb_strtoupper($r['name']);
        }
    }

    expect($master)->toBe('KOTA BLITAR');
});

it('tidak terpengaruh bila teks tidak menyebut jenisnya', function () {
    // Tanpa penanda jenis, perilakunya tetap seperti semula: cocokkan apa adanya.
    $hasil = $this->resolver->resolve('Jl. Contoh, Blitar, Jawa Timur', null, [
        'provinsi' => 'Jawa Timur',
        'kota_kabupaten' => 'Blitar',
    ]);

    expect($hasil['kota_kabupaten_id'])->not->toBeNull();
});

it('tidak tertukar untuk nama kecamatan kembar', function () {
    // Kecamatan juga punya nama kembar antar kabupaten; pencocokan tetap
    // dibatasi pada daftar kabupaten yang bersangkutan.
    $hasil = $this->resolver->resolve(
        'Jl. Contoh No. 1, GARUM, Garum, Kabupaten Blitar, Jawa Timur, 66182',
        null,
        [
            'provinsi' => 'Jawa Timur',
            'kota_kabupaten' => 'Kabupaten Blitar',
            'kecamatan' => 'Garum',
            'kelurahan_desa' => 'GARUM',
        ],
    );

    expect($hasil['kecamatan'])->toBe('Garum');
    expect($hasil['kecamatan_id'])->not->toBeNull();
    expect($hasil['kelurahan_desa_id'])->not->toBeNull();
});
