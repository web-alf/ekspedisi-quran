<?php

use App\Models\Donatur;
use App\Models\User;
use App\Support\KodeDonatur;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Donatur yang berdonasi rutin memakai kode yang sama berulang kali. Salah ketik
 * membuat orang yang sama dianggap donatur baru dan mendapat kode kedua — di
 * produksi itu benar-benar terjadi ("ECB81" dan "ECB 81" untuk orang yang sama).
 *
 * Kode dan nama karena itu diseragamkan otomatis, bukan sekadar diminta rapi.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    foreach ([
        'dashboard.view', 'users.read', 'donatur.read', 'donatur.create',
        'donatur.update', 'donatur.delete',
    ] as $izin) {
        Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
    }

    Role::findByName('super-admin')->syncPermissions(Permission::all());
    $this->seed(JenisQuranSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');
});

function donasi(array $ganti = []): array
{
    return array_merge([
        'kode_donatur' => 'ECB81',
        'nama_donatur' => 'Nadim Munir',
        'no_hp' => '+6281234567890',
        'email_donatur' => 'nadim@uji.test',
        'alamat_donatur' => 'Jl. Uji No. 1',
        'jenis_wakaf_dipilih' => ['A5'],
        'jumlah_a5' => 1,
        'jumlah_a6' => 0,
        'jumlah_iqra' => 0,
        'donation_date' => '2026-01-10',
        'prayer_mode' => 'semua_donatur',
        'doa_untuk_semua' => 'Berkah',
    ], $ganti);
}

it('membuang spasi pada kode donatur saat disimpan', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', donasi(['kode_donatur' => 'ECB 81']));

    expect(Donatur::pluck('kode_donatur')->all())->toBe(['ECB81']);
});

it('menjadikan kode donatur huruf besar', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', donasi(['kode_donatur' => 'ecb81']));

    expect(Donatur::pluck('kode_donatur')->all())->toBe(['ECB81']);
});

it('menghitung kode berspasi sebagai donatur yang sama, bukan donatur baru', function () {
    // Inti persoalannya: donasi kedua orang yang sama, kodenya salah ketik berspasi.
    $this->actingAs($this->admin)->post('/admin/donatur', donasi());

    $this->actingAs($this->admin)->post('/admin/donatur', donasi([
        'kode_donatur' => 'ECB 81',
        'donation_date' => '2026-02-10',
    ]));

    // Satu baris donatur, donasi bertambah — bukan dua baris.
    expect(Donatur::count())->toBe(1);
    expect(Donatur::first()->donation_count)->toBe(2);
    expect(Donatur::first()->kode_donatur)->toBe('ECB81');
});

it('menerima nama yang beda huruf besar kecil sebagai orang yang sama', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', donasi());

    $this->actingAs($this->admin)->post('/admin/donatur', donasi([
        'nama_donatur' => 'nadim munir',
        'donation_date' => '2026-02-10',
    ]))->assertSessionHasNoErrors();

    expect(Donatur::count())->toBe(1);
    expect(Donatur::first()->donation_count)->toBe(2);
});

it('menerima nama yang ada spasi berlebih sebagai orang yang sama', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', donasi());

    $this->actingAs($this->admin)->post('/admin/donatur', donasi([
        'nama_donatur' => '  Nadim   Munir  ',
        'donation_date' => '2026-02-10',
    ]))->assertSessionHasNoErrors();

    expect(Donatur::count())->toBe(1);
    expect(Donatur::first()->donation_count)->toBe(2);
});

it('meringkas spasi berlebih pada nama saat disimpan', function () {
    $this->actingAs($this->admin)->post('/admin/donatur', donasi(['nama_donatur' => 'Nadim   Munir ']));

    expect(Donatur::first()->nama_donatur)->toBe('Nadim Munir');
});

it('tetap menolak kode yang sudah dipakai orang BERBEDA', function () {
    // Penjaga sebaliknya: penyeragaman tidak boleh membuat dua orang berbeda
    // dianggap satu donatur.
    $this->actingAs($this->admin)->post('/admin/donatur', donasi());

    $this->actingAs($this->admin)->post('/admin/donatur', donasi([
        'nama_donatur' => 'Orang Lain',
    ]))->assertSessionHasErrors('kode_donatur');

    expect(Donatur::count())->toBe(1);
});

it('tetap menemukan kode lama yang masih berspasi saat dicari tanpa spasi', function () {
    // Data lama sudah terlanjur berspasi dan tidak diubah. Kalau pencariannya gagal,
    // orang yang sama akan dibuatkan kode baru lagi — masalah yang sama terulang.
    Donatur::create([
        'kode_donatur' => 'BTV 656',
        'nama_donatur' => 'Donny Prasetya',
        'no_hp' => '+6281234567890',
        'donation_date' => '2026-01-10',
        'total_a5_count' => 1, 'total_a6_count' => 0, 'total_iqra_count' => 0,
        'donation_count' => 1,
        'jenis_wakaf_dipilih' => ['A5'],
        'prayer_mode' => 'semua_donatur',
        'created_by' => $this->admin->id,
    ]);

    $hasil = $this->actingAs($this->admin)
        ->getJson('/admin/api/donatur/search-kode?q=BTV656')
        ->assertSuccessful()
        ->json('data');

    expect(collect($hasil)->pluck('kode_donatur')->all())->toContain('BTV 656');
});

it('menyeragamkan kode dan nama lewat helper-nya', function () {
    // Dasar semua perbaikan di atas.
    expect(KodeDonatur::bersihkan('ECB 81'))->toBe('ECB81');
    expect(KodeDonatur::bersihkan(' ecb-81 '))->toBe('ECB-81');
    expect(KodeDonatur::bersihkan('BTV'."\u{00A0}".'656'))->toBe('BTV656');
    expect(KodeDonatur::bersihkan('ECB–81'))->toBe('ECB-81');

    expect(KodeDonatur::nama('  Nadim   Munir '))->toBe('Nadim Munir');
    expect(KodeDonatur::namaSama('nadim munir', 'Nadim  Munir'))->toBeTrue();
    expect(KodeDonatur::namaSama('Nadim Munir', 'Nadim Muniruddin'))->toBeFalse();
    expect(KodeDonatur::namaSama('', 'Nadim'))->toBeFalse();
    expect(KodeDonatur::kodeSama('ECB 81', 'ecb81'))->toBeTrue();
});
