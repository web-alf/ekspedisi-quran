<?php

use App\Models\MushafRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Peta "Penyebaran Distribusi Al-Qur'an" di halaman admin permintaan mushaf.
 *
 * Halaman ini punya peta sendiri — terpisah dari peta di halaman depan — dan
 * keduanya memakai judul yang sama. Pin di peta admin menampilkan alamat sampai
 * tingkat kelurahan/desa, jadi muatan dari server harus benar-benar membawa
 * kolom wilayah tersebut.
 *
 * Tes ini mengunci bentuk muatan itu: kalau seseorang merapikan `select()` di
 * controller dan menghapus `kecamatan`/`kelurahan_desa`, popup pin kehilangan
 * detail desa tanpa ada yang menyadarinya.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');
});

it('mengirim kolom wilayah sampai kelurahan/desa untuk pin peta admin', function () {
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

    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MushafRequest/Index')
            ->has('mapData', 1)
            ->where('mapData.0.id', $req->id)
            ->where('mapData.0.kecamatan', 'JEPARA')
            ->where('mapData.0.kelurahan_desa', 'DEMAAN')
            ->where('mapData.0.kode_pos', '59419')
            ->where('mapData.0.lat', -6.59958411)
            ->where('mapData.0.lng', 110.65751)
            ->where('mapData.0.kategori', 'Sekolah/Madrasah')
        );
});

it('mengirim pecahan per jenis mushaf pada peta admin', function () {
    $req = MushafRequest::factory()->create([
        'status' => 'completed',
        'latitude' => -8.0868357,
        'longitude' => 112.2396983,
        'jumlah_mushaf_approved' => 100,
        'jumlah_mushaf_a5_approved' => 60,
        'jumlah_mushaf_a6_approved' => 40,
        'jumlah_iqra_approved' => 0,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests')
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

it('hanya mengirim permintaan selesai berkoordinat pada peta admin', function () {
    MushafRequest::factory()->create(['status' => 'completed', 'latitude' => -6.9, 'longitude' => 110.4]);
    MushafRequest::factory()->create(['status' => 'pending', 'latitude' => -6.9, 'longitude' => 110.4]);
    MushafRequest::factory()->create(['status' => 'completed', 'latitude' => null, 'longitude' => null]);

    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('mapData', 1));
});

it('menyertakan nama_lembaga sebagai nama_penerima agar pin bisa dikelompokkan', function () {
    $req = MushafRequest::factory()->create([
        'status' => 'completed',
        'nama_lembaga' => 'Pondok Uji Peta',
        'latitude' => -8.0868357,
        'longitude' => 112.2396983,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('mapData', 1)
            ->where('mapData.0.nama_penerima', 'Pondok Uji Peta')
        );
});
