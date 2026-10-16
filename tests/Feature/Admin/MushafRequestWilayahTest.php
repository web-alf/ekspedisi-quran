<?php

use App\Models\MushafRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Halaman detail permintaan mushaf — kolom wilayah.
 *
 * Bug asli: halaman ini membaca provinsi/kabupaten/kecamatan/kelurahan dari
 * `mushafRequest` dengan benar, tetapi form "Edit Informasi Lembaga"-nya TIDAK
 * membawa ID wilayah (provinsi_id dst.). Dropdown di AddressFormIndonesia
 * memilih berdasarkan ID, jadi keempat dropdown tampil kosong walaupun datanya
 * ada — dan karena nilai yang disimpan kembali berasal dari dropdown yang
 * kosong, menyimpan form itu akan MENGHAPUS alamat yang sudah benar.
 *
 * Tes ini mengunci dua hal: halaman detail memuat kolom wilayahnya, dan
 * endpoint update benar-benar menyimpan ID wilayah — bukan mengosongkannya.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');

    $this->permintaan = MushafRequest::factory()->create([
        'status' => 'pending',
        'kategori_lembaga' => 'TPQ/TPA/Madin',
        'provinsi' => 'JAWA TENGAH',
        'provinsi_id' => '33',
        'kota_kabupaten' => 'KABUPATEN JEPARA',
        'kota_kabupaten_id' => '3320',
        'kecamatan' => 'JEPARA',
        'kecamatan_id' => '3320070',
        'kelurahan_desa' => 'DEMAAN',
        'kelurahan_desa_id' => '3320070001',
        'kode_pos' => '59419',
        'alamat_detail' => 'Jl. Sunan Mantingan No. 24A',
        'latitude' => -6.59958411,
        'longitude' => 110.65751,
        'urgensi_request' => 'Alquran kami banyak yang rusak',
    ]);
});

it('mengirim kolom wilayah lengkap ke halaman detail', function () {
    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests/'.$this->permintaan->id)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MushafRequest/Show')
            ->where('mushafRequest.provinsi', 'JAWA TENGAH')
            ->where('mushafRequest.kota_kabupaten', 'KABUPATEN JEPARA')
            ->where('mushafRequest.kecamatan', 'JEPARA')
            ->where('mushafRequest.kelurahan_desa', 'DEMAAN')
            ->where('mushafRequest.kode_pos', '59419')
            ->where('mushafRequest.alamat_detail', 'Jl. Sunan Mantingan No. 24A')
        );
});

it('mengirim ID wilayah ke halaman detail agar dropdown bisa memilih', function () {
    $this->actingAs($this->admin)
        ->get('/admin/mushaf-requests/'.$this->permintaan->id)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MushafRequest/Show')
            ->where('mushafRequest.provinsi_id', '33')
            ->where('mushafRequest.kota_kabupaten_id', '3320')
            ->where('mushafRequest.kecamatan_id', '3320070')
            ->where('mushafRequest.kelurahan_desa_id', '3320070001')
        );
});

it('menyimpan ID wilayah saat form lembaga dikirim', function () {
    $this->actingAs($this->admin)
        ->patch('/admin/mushaf-requests/'.$this->permintaan->id.'/lembaga', [
            'nama_lembaga' => 'TPQ Uji Wilayah',
            'kategori_lembaga' => 'TPQ/TPA/Madin',
            'alamat_lengkap' => 'Jl. Sunan Mantingan No. 24A, DEMAAN, JEPARA, KABUPATEN JEPARA, JAWA TENGAH, 59419',
            'provinsi' => 'JAWA TENGAH',
            'provinsi_id' => '33',
            'kota_kabupaten' => 'KABUPATEN JEPARA',
            'kota_kabupaten_id' => '3320',
            'kecamatan' => 'JEPARA',
            'kecamatan_id' => '3320070',
            'kelurahan_desa' => 'DEMAAN',
            'kelurahan_desa_id' => '3320070001',
            'kode_pos' => '59419',
            'alamat_detail' => 'Jl. Sunan Mantingan No. 24A',
            'latitude' => -6.59958411,
            'longitude' => 110.65751,
            'urgensi_request' => 'Alquran kami banyak yang rusak',
            'sumber_info' => 'WhatsApp',
        ])
        ->assertRedirect();

    $sesudah = $this->permintaan->fresh();

    expect($sesudah->provinsi_id)->toBe('33');
    expect($sesudah->kota_kabupaten_id)->toBe('3320');
    expect($sesudah->kecamatan_id)->toBe('3320070');
    expect($sesudah->kelurahan_desa_id)->toBe('3320070001');

    // Kolom nama pun tidak boleh hilang
    expect($sesudah->provinsi)->toBe('JAWA TENGAH');
    expect($sesudah->kecamatan)->toBe('JEPARA');
    expect($sesudah->kelurahan_desa)->toBe('DEMAAN');
});

it('tidak menghapus ID wilayah yang sudah tersimpan bila form tidak mengirimkannya', function () {
    // Perilaku server yang perlu dijaga: `updateLembaga` memakai
    // `$request->only([...])`, jadi kolom yang TIDAK dikirim tidak ikut ditimpa.
    //
    // Ini penting sebagai jaring pengaman: pada bug dropdown kosong, form lama
    // memang tidak mengirim ID wilayah. Kalau server menimpanya dengan null,
    // menyimpan form itu akan MENGHAPUS alamat yang sudah benar. Tes ini
    // memastikan data lama tetap utuh.
    $this->actingAs($this->admin)
        ->patch('/admin/mushaf-requests/'.$this->permintaan->id.'/lembaga', [
            'nama_lembaga' => 'TPQ Uji Wilayah',
            'kategori_lembaga' => 'TPQ/TPA/Madin',
            'alamat_lengkap' => 'Jl. Sunan Mantingan No. 24A, DEMAAN, JEPARA',
            'provinsi' => 'JAWA TENGAH',
            'kecamatan' => 'JEPARA',
            'kelurahan_desa' => 'DEMAAN',
            'alamat_detail' => 'Jl. Sunan Mantingan No. 24A',
            'latitude' => -6.59958411,
            'longitude' => 110.65751,
            'urgensi_request' => 'Alquran kami banyak yang rusak',
        ])
        ->assertRedirect();

    $sesudah = $this->permintaan->fresh();

    expect($sesudah->provinsi_id)->toBe('33');
    expect($sesudah->kota_kabupaten_id)->toBe('3320');
    expect($sesudah->kecamatan_id)->toBe('3320070');
    expect($sesudah->kelurahan_desa_id)->toBe('3320070001');
});
