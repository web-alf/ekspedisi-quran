<?php

use App\Models\MushafRequest;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Peta "Penyebaran Distribusi Al-Qur'an" di halaman depan.
 *
 * Pin peta menampilkan alamat sampai tingkat kelurahan/desa dan pecahan jenis
 * mushaf, jadi muatan dari server harus benar-benar membawa kolom itu. Tes ini
 * mengunci bentuk muatannya supaya kolom yang dibutuhkan pin tidak terhapus
 * diam-diam saat `select()` di route dirapikan orang lain.
 */
it('mengirim kolom wilayah sampai kelurahan/desa untuk pin peta', function () {
    $req = MushafRequest::factory()->create([
        'status' => 'completed',
        'provinsi' => 'JAWA TENGAH',
        'kota_kabupaten' => 'KABUPATEN JEPARA',
        'kecamatan' => 'JEPARA',
        'kelurahan_desa' => 'DEMAAN',
        'kode_pos' => '59419',
        'latitude' => -6.59958411,
        'longitude' => 110.65751,
        'kategori_lembaga' => 'Sekolah/Madrasah',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->has('mapData', 1)
            ->where('mapData.0.id', $req->id)
            ->where('mapData.0.nama_lembaga', $req->nama_lembaga)
            ->where('mapData.0.kecamatan', 'JEPARA')
            ->where('mapData.0.kelurahan_desa', 'DEMAAN')
            ->where('mapData.0.kode_pos', '59419')
            ->where('mapData.0.lat', -6.59958411)
            ->where('mapData.0.lng', 110.65751)
            ->where('mapData.0.kategori', 'Sekolah/Madrasah')
        );
});

it('mengirim pecahan jumlah per jenis mushaf', function () {
    $req = MushafRequest::factory()->create([
        'status' => 'completed',
        'latitude' => -8.0868357,
        'longitude' => 112.2396983,
        'jumlah_mushaf_approved' => 100,
        'jumlah_mushaf_a5_approved' => 60,
        'jumlah_mushaf_a6_approved' => 40,
        'jumlah_iqra_approved' => 0,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('mapData', 1)
            ->where('mapData.0.id', $req->id)
            ->where('mapData.0.jumlah_mushaf', 100)
            ->where('mapData.0.jumlah_mushaf_a5', 60)
            ->where('mapData.0.jumlah_mushaf_a6', 40)
            ->where('mapData.0.jumlah_iqra', 0)
        );
});

it('hanya mengirim permintaan berstatus completed yang sudah punya koordinat', function () {
    MushafRequest::factory()->create(['status' => 'completed', 'latitude' => -6.9, 'longitude' => 110.4]);
    MushafRequest::factory()->create(['status' => 'pending', 'latitude' => -6.9, 'longitude' => 110.4]);
    MushafRequest::factory()->create(['status' => 'completed', 'latitude' => null, 'longitude' => null]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('mapData', 1));
});
