<?php

use App\Models\MushafRequest;
use App\Services\Cache\GeographicCacheService;

/**
 * Penjaga data: wilayah permintaan mushaf harus konsisten antara NAMA dan ID.
 *
 * Kasus nyata: sebuah permintaan di "Kota Jayapura" tersimpan dengan
 * kota_kabupaten_id menunjuk KABUPATEN JAYAPURA. Nama yang ditampilkan benar,
 * tetapi ID-nya salah — dan karena kecamatan ABEPURA hanya ada di KOTA JAYAPURA,
 * pencarian kecamatan dan kelurahan tidak menemukan apa pun. Di halaman detail
 * keduanya tampil kosong, padahal namanya tersimpan: persis keluhan "kecamatan
 * dan kelurahan masih belum berhasil".
 *
 * Ketidakkonsistenan seperti ini tidak menimbulkan galat apa pun — hanya dropdown
 * yang diam-diam kosong. Karena itu perlu diperiksa secara eksplisit.
 *
 * Tes ini membuat datanya SENDIRI (lewat factory) supaya benar-benar menguji
 * pemeriksaannya, bukan sekadar lulus karena basis data kosong.
 */

/** Susun satu baris rusak: nama "Kota Jayapura" tetapi ID kabupaten. */
function barisWilayahTertukar(): MushafRequest
{
    return MushafRequest::factory()->create([
        'no_request' => 'REQ-UJI-TERTUKAR',
        'provinsi' => 'Papua',
        'provinsi_id' => '94',
        'kota_kabupaten' => 'Kota Jayapura',
        'kota_kabupaten_id' => '9403', // KABUPATEN JAYAPURA
        'kecamatan' => 'Abepura',
        'kecamatan_id' => '9471020', // milik KOTA JAYAPURA (9471)
        'kelurahan_desa' => 'KOYA KOSO',
        'kelurahan_desa_id' => '9471020009',
    ]);
}

/** Susun satu baris benar. */
function barisWilayahBenar(): MushafRequest
{
    return MushafRequest::factory()->create([
        'no_request' => 'REQ-UJI-BENAR',
        'provinsi' => 'Papua',
        'provinsi_id' => '94',
        'kota_kabupaten' => 'Kota Jayapura',
        'kota_kabupaten_id' => '9471',
        'kecamatan' => 'Abepura',
        'kecamatan_id' => '9471020',
        'kelurahan_desa' => 'KOYA KOSO',
        'kelurahan_desa_id' => '9471020009',
    ]);
}

/** Jenis tempat: KOTA, KABUPATEN, atau null. */
function jenisWilayah(?string $nama): ?string
{
    if (empty($nama)) {
        return null;
    }
    $atas = mb_strtoupper($nama);

    return match (true) {
        (bool) preg_match('/\b(KOTA|KOTAMADYA)\b/', $atas) => 'KOTA',
        (bool) preg_match('/\b(KABUPATEN|KAB)\b/', $atas) => 'KABUPATEN',
        default => null,
    };
}

/** Nama master untuk tiap tingkat wilayah pada satu baris. */
function masterWilayah(MushafRequest $r): array
{
    $cache = app(GeographicCacheService::class);
    $master = [];

    foreach ($cache->getRegencies($r->provinsi_id) ?? [] as $k) {
        if ((string) $k['id'] === (string) $r->kota_kabupaten_id) {
            $master['kota_kabupaten'] = mb_strtoupper($k['name']);
        }
    }

    foreach ($cache->getDistricts($r->kota_kabupaten_id) ?? [] as $d) {
        if ((string) $d['id'] === (string) $r->kecamatan_id) {
            $master['kecamatan'] = mb_strtoupper($d['name']);
        }
    }

    foreach ($cache->getVillages($r->kecamatan_id) ?? [] as $v) {
        if ((string) $v['id'] === (string) $r->kelurahan_desa_id) {
            $master['kelurahan_desa'] = mb_strtoupper($v['name']);
        }
    }

    return $master;
}

/** Cari baris yang jenis kota/kabupatennya tertukar. */
function cariTertukar(): array
{
    $bermasalah = [];

    foreach (MushafRequest::query()->whereNotNull('kota_kabupaten_id')->get() as $r) {
        $namaMaster = masterWilayah($r)['kota_kabupaten'] ?? null;

        if ($namaMaster === null) {
            continue;
        }

        $diminta = jenisWilayah($r->kota_kabupaten);
        $milikMaster = jenisWilayah($namaMaster);

        if ($diminta !== null && $milikMaster !== null && $diminta !== $milikMaster) {
            $bermasalah[] = sprintf(
                'id %d (%s): nama "%s" tapi ID %s = %s',
                $r->id,
                $r->no_request,
                $r->kota_kabupaten,
                $r->kota_kabupaten_id,
                $namaMaster
            );
        }
    }

    return $bermasalah;
}

it('menandai baris yang kota/kabupatennya tertukar', function () {
    barisWilayahTertukar();

    // Pemeriksaannya HARUS menemukan baris itu — kalau tidak, tes ini tidak
    // menjaga apa pun.
    expect(cariTertukar())->toHaveCount(1);
    expect(cariTertukar()[0])->toContain('Kota Jayapura');
    expect(cariTertukar()[0])->toContain('KABUPATEN JAYAPURA');
});

it('menerima baris yang jenis wilayahnya sudah cocok', function () {
    barisWilayahBenar();

    expect(cariTertukar())->toBe([]);
});

it('membedakan KOTA dan KABUPATEN pada nama yang kembar', function () {
    expect(jenisWilayah('Kota Jayapura'))->toBe('KOTA');
    expect(jenisWilayah('Kabupaten Jayapura'))->toBe('KABUPATEN');
    expect(jenisWilayah('KOTA BLITAR'))->toBe('KOTA');
    expect(jenisWilayah('KABUPATEN BLITAR'))->toBe('KABUPATEN');
    expect(jenisWilayah('Jayapura'))->toBeNull();
});

it('menandai nama kecamatan yang tidak punya ID', function () {
    $r = MushafRequest::factory()->create([
        'kota_kabupaten_id' => '9471',
        'kecamatan' => 'Abepura',
        'kecamatan_id' => null,
    ]);

    $bermasalah = MushafRequest::query()
        ->whereNotNull('kecamatan')->where('kecamatan', '!=', '')
        ->whereNull('kecamatan_id')
        ->pluck('no_request')->all();

    expect($bermasalah)->toContain($r->no_request);
});

it('menandai nama kelurahan yang tidak punya ID', function () {
    $r = MushafRequest::factory()->create([
        'kecamatan_id' => '9471020',
        'kelurahan_desa' => 'KOYA KOSO',
        'kelurahan_desa_id' => null,
    ]);

    $bermasalah = MushafRequest::query()
        ->whereNotNull('kelurahan_desa')->where('kelurahan_desa', '!=', '')
        ->whereNull('kelurahan_desa_id')
        ->pluck('no_request')->all();

    expect($bermasalah)->toContain($r->no_request);
});

it('menandai ID kecamatan yang tidak berada di bawah kabupatennya', function () {
    $r = barisWilayahTertukar();

    // 9471020 (kecamatan di KOTA JAYAPURA) tidak berada di bawah 9403
    $idKecamatan = (string) $r->kecamatan_id;
    $nyambung = str_starts_with($idKecamatan, (string) $r->kota_kabupaten_id);

    expect($nyambung)->toBeFalse('kode kecamatan 9471020 tidak boleh dianggap milik 9403');
});

it('menandai ID kelurahan yang tidak berada di bawah kecamatannya', function () {
    $r = MushafRequest::factory()->create([
        'kecamatan_id' => '9471020',
        'kelurahan_desa_id' => '3320070001', // kelurahan di Jepara, Jawa Tengah
    ]);

    $idak = (string) $r->kelurahan_desa_id;
    $nyambung = str_starts_with($idak, (string) $r->kecamatan_id);

    expect($nyambung)->toBeFalse();
});
