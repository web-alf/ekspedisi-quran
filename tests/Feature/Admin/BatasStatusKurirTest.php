<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\PengirimanStageVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Batas status untuk KURIR.
 *
 * Kurir mengantar barang dan mengabarkan PERJALANANNYA. Tahap gudang (pemesanan,
 * produksi, kedatangan, packing, selesai packing) bukan wewenang mereka, dan
 * "Diterima Penerima" punya jalur sendiri yang hanya boleh diselesaikan role
 * distribusi/manager. "Batal" selalu ikut: kurir yang menemukan alamat tidak ada
 * harus bisa mengabarkannya.
 *
 * Dua hal yang dijaga di sini dan mudah tertukar:
 *
 *   1. BATAS PILIHAN — status mana yang boleh DIPILIH kurir.
 *   2. BUKAN BATAS DATA — kurir tetap harus bisa MELIHAT resi lintas status di
 *      halaman Pengiriman. Kalau keduanya tertukar, daftar resi kurir ikut
 *      terpotong dan pekerjaannya tidak terlihat.
 *
 * Ketiga jalur yang menampilkan daftar status diuji semuanya, karena dulu
 * masing-masing memuat daftarnya sendiri sehingga bisa berbeda isi.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $izin = [
        PermissionEnum::DASHBOARD_VIEW->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
        PermissionEnum::MUSHAF_REQUESTS_READ->value,
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUATAN_COMPLETE->value,
    ];

    foreach ($izin as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    foreach ([
        RoleEnum::COURIER->value => 'Kurir',
        RoleEnum::DISTRIBUSI->value => 'Distribusi',
        RoleEnum::MANAGER->value => 'Manager Distribusi',
        RoleEnum::WAREHOUSE->value => 'Gudang',
        RoleEnum::SUPER_ADMIN->value => 'Super Admin',
    ] as $name => $display) {
        Role::firstOrCreate(['name' => $name], ['display_name' => $display, 'guard_name' => 'web']);
    }

    Role::where('name', RoleEnum::COURIER->value)->first()->syncPermissions($izin);
    Role::where('name', RoleEnum::DISTRIBUSI->value)->first()->syncPermissions($izin);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Delapan status seperti di produksi, lengkap dengan urutannya — aturan
    // maju/mundur bergantung pada urutan, jadi urutannya harus nyata.
    //
    // Dibersihkan lebih dulu: basis data uji bisa membawa status sisa dari
    // migrasi/seeder lain, dan tes yang menghitung "8 status" akan gagal karena
    // kelebihan yang tidak ada hubungannya dengan yang sedang diuji.
    StatusPengiriman::query()->delete();

    foreach ([
        'pemesanan' => 1,
        'produksi' => 2,
        'kedatangan' => 3,
        'packing' => 4,
        'selesai-packing' => 5,
        'pengiriman' => 6,
        'diterima' => 7,
        'batal' => 99,
    ] as $slug => $urutan) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            [
                'nama' => ucwords(str_replace('-', ' ', $slug)),
                'urutan' => $urutan,
                'is_active' => true,
                'is_final' => in_array($slug, ['diterima', 'batal'], true),
            ]
        );
    }
});

function pengguna(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

function slugStatus(?User $user): array
{
    return PengirimanStageVisibility::visibleProgressStatuses($user)
        ->pluck('slug')
        ->all();
}

// --- Aturan dasarnya ---

it('membatasi pilihan status kurir ke perjalanan dan batal', function () {
    expect(slugStatus(pengguna(RoleEnum::COURIER->value)))
        ->toBe(['pengiriman', 'batal']);
});

it('tidak membatasi role lain', function () {
    // Pembatasan data mereka sudah ditangani applyToQuery/isVisible; membatasi
    // pilihannya di sini akan ikut mempersempit pekerjaan mereka.
    foreach ([RoleEnum::DISTRIBUSI->value, RoleEnum::SUPER_ADMIN->value, RoleEnum::WAREHOUSE->value] as $role) {
        expect(slugStatus(pengguna($role)))
            ->toHaveCount(8)
            ->toContain('pemesanan', 'packing', 'diterima');
    }
});

it('gagal-tertutup: daftar kosong berarti kurir tidak bisa memilih apa pun', function () {
    // Aturan yang sama seperti manager. Kalau daftar kosong dianggap "boleh
    // semua", salah konfigurasi justru MEMBUKA wewenang, bukan menutupnya.
    config()->set('pengiriman.courier_progress_stages', []);

    expect(slugStatus(pengguna(RoleEnum::COURIER->value)))->toBe([]);
});

it('mengabaikan status yang tidak aktif', function () {
    StatusPengiriman::where('slug', 'pengiriman')->update(['is_active' => false]);

    expect(slugStatus(pengguna(RoleEnum::COURIER->value)))->toBe(['batal']);
});

// --- Penegakan di server ---

it('MENOLAK kurir memindahkan status muatan ke tahap gudang walau memanggil endpoint langsung', function () {
    // Inti pembatasan: menyembunyikan pilihan di tampilan bukan pengamanan.
    $kurir = pengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'selesai-packing')->value('id'),
    ]);
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $idPacking = StatusPengiriman::where('slug', 'packing')->value('id');

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.status-perjalanan', $muatan), ['status_id' => $idPacking])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect($resi->fresh()->status->slug)->toBe('selesai-packing');
});

it('MENGIZINKAN kurir memindahkan status muatan ke perjalanan', function () {
    $kurir = pengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'selesai-packing')->value('id'),
    ]);
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.status-perjalanan', $muatan), [
            'status_id' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
        ])
        ->assertSuccessful();

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('MENOLAK kurir memakai halaman ubah status untuk melompat ke tahap gudang', function () {
    // Halaman ini punya aturan "status berikutnya" sendiri, jadi jalur ini bisa
    // bocor walau halaman lain sudah dibatasi.
    $kurir = pengguna(RoleEnum::COURIER->value);
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
    ]);

    $this->actingAs($kurir)
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => StatusPengiriman::where('slug', 'packing')->value('id'),
            'catatan' => 'coba mundur ke gudang',
        ])
        ->assertSessionHasErrors('error');

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

// --- Apa yang terlihat di tiap halaman ---

it('hanya menawarkan dua status ke kurir di halaman ubah status', function () {
    $kurir = pengguna(RoleEnum::COURIER->value);
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'selesai-packing')->value('id'),
    ]);

    $props = $this->actingAs($kurir)
        ->get(route('admin.pengiriman.update-status.form', $resi))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $slug = collect($props['statusList'])->pluck('slug')->filter()->all();

    // Dari "selesai-packing" status berikutnya adalah pengiriman dan batal;
    // keduanya memang diizinkan, jadi tidak ada yang tersisa untuk dibuang.
    expect($slug)->toBe(['pengiriman', 'batal']);
});

it('hanya menawarkan dua status ke kurir di dropdown muatan', function () {
    $kurir = pengguna(RoleEnum::COURIER->value);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();

    $props = $this->actingAs($kurir)
        ->get(route('admin.muatan.show', $muatan))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['statusPerjalanan'])->pluck('slug')->all())
        ->toBe(['pengiriman', 'batal']);
});

it('hanya menawarkan dua status ke kurir di filter halaman Pengiriman', function () {
    $kurir = pengguna(RoleEnum::COURIER->value);

    $props = $this->actingAs($kurir)
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['statusList'])->pluck('slug')->all())
        ->toBe(['pengiriman', 'batal']);
});

it('tetap menawarkan delapan status ke super-admin di filter halaman Pengiriman', function () {
    $admin = pengguna(RoleEnum::SUPER_ADMIN->value);

    $props = $this->actingAs($admin)
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['statusList'])->toHaveCount(8);
});

// --- Yang TIDAK boleh ikut berubah: data yang dilihat kurir ---

it('kurir TETAP melihat resi lintas status, bukan hanya yang sedang diantar', function () {
    // Ini pembeda yang mudah salah: yang dibatasi adalah PILIHAN status, bukan
    // baris pengiriman. Kalau ini gagal, pekerjaan kurir jadi tidak terlihat.
    $kurir = pengguna(RoleEnum::COURIER->value);

    foreach (['pemesanan', 'packing', 'selesai-packing', 'pengiriman'] as $slug) {
        Pengiriman::factory()->create([
            'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        ]);
    }

    $props = $this->actingAs($kurir)
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $slugTerlihat = collect($props['pengiriman']['data'] ?? $props['pengiriman'])
        ->pluck('status.slug')
        ->sort()
        ->values()
        ->all();

    expect($slugTerlihat)->toBe(['packing', 'pemesanan', 'pengiriman', 'selesai-packing']);
});

it('tidak menyentuh pembatasan tahap milik manager', function () {
    // Manager tetap dibatasi seperti semula lewat config terpisah.
    $manager = pengguna(RoleEnum::MANAGER->value);
    $manager->givePermissionTo(PermissionEnum::SHIPMENTS_READ->value);

    expect(PengirimanStageVisibility::restricts($manager))->toBeTrue()
        ->and(PengirimanStageVisibility::allowedSlugs())
        ->toBe(['selesai-packing', 'pengiriman', 'diterima']);
});

it('menggabungkan batas manager dan batas kurir, bukan saling menggantikan', function () {
    // Jebakan yang pernah terjadi: fungsi ini dulu mengembalikan seluruh daftar
    // begitu penggunanya bukan kurir, sehingga batas manager ikut hilang dan
    // manager melihat pilihan tahap awal (pemesanan s/d packing) yang datanya
    // sendiri tidak pernah muncul untuk dia.
    //
    // "Batal" ikut muncul karena manager mengerjakan perjalanan kurir
    // bawahannya, termasuk mencatat resi yang gagal diantar.
    $manager = pengguna(RoleEnum::MANAGER->value);
    $manager->givePermissionTo(PermissionEnum::SHIPMENTS_READ->value);

    expect(collect(PengirimanStageVisibility::visibleProgressStatuses($manager))->pluck('slug')->sort()->values()->all())
        ->toBe(['batal', 'diterima', 'pengiriman', 'selesai-packing']);
});

it('tidak menghilangkan wewenang kurir bila seseorang juga berperan manager', function () {
    // Seseorang yang memegang kedua peran mendapat GABUNGAN wewenangnya:
    // sebagai kurir ia boleh memindahkan perjalanan (pengiriman/batal), sebagai
    // manager ia juga menangani tahap yang datanya ia lihat (selesai-packing,
    // diterima). Yang penting: salah satu peran tidak diam-diam menghapus yang
    // lain — itu yang dulu terjadi pada penyusunan daftar secara irisan.
    $keduanya = pengguna(RoleEnum::MANAGER->value);
    $keduanya->assignRole(RoleEnum::COURIER->value);
    $keduanya->givePermissionTo(PermissionEnum::SHIPMENTS_READ->value);

    expect(collect(PengirimanStageVisibility::visibleProgressStatuses($keduanya))->pluck('slug')->sort()->values()->all())
        ->toBe(['batal', 'diterima', 'pengiriman', 'selesai-packing']);
});

it('manager TIDAK mendapat tahap gudang walau kini boleh memakai tahap kurir', function () {
    // Inti perubahan "manager mengawasi kurir": manager memperoleh "Batal",
    // bukan memperoleh tahap gudang. Kalau tes ini gagal, artinya wewenang
    // manager ikut meluas ke pemesanan/produksi/packing — bukan yang dimaksud.
    $manager = pengguna(RoleEnum::MANAGER->value);
    $manager->givePermissionTo(PermissionEnum::SHIPMENTS_READ->value);

    $slug = collect(PengirimanStageVisibility::visibleProgressStatuses($manager))->pluck('slug')->all();

    expect($slug)->not->toContain('pemesanan')
        ->and($slug)->not->toContain('produksi')
        ->and($slug)->not->toContain('kedatangan')
        ->and($slug)->not->toContain('packing')
        ->and($slug)->toContain('batal');
});
