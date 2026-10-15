<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Tab "Generate QR" & "Scan QR" di Data Resi bukan pekerjaan Manager Distribusi.
 *
 * Yang dikunci:
 *   1. Migrasi (bukan seeder) mencabut qr.generate & qr.scan dari manager — deploy
 *      hanya menjalankan migrasi.
 *   2. Halaman ?mode=generate-qr & ?mode=scan-status DIJAGA izinnya di server, bukan
 *      hanya disembunyikan tabnya.
 *   3. Manager tetap bisa melihat & mengunduh gambar QR resi lewat shipments.read.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function penggunaPeran(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function jalankanMigrasiCabutQr(): void
{
    $migrasi = require database_path('migrations/2026_10_15_123516_cabut_izin_qr_dari_manager_distribusi.php');
    $migrasi->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('mencabut qr.generate dan qr.scan dari manager lewat migrasi, tanpa menyentuh izin lain', function () {
    // Keadaan produksi sebelum migrasi: manager masih memegang keduanya.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();
    $manager->givePermissionTo([PermissionEnum::QR_GENERATE->value, PermissionEnum::QR_SCAN->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    jalankanMigrasiCabutQr();

    $manager = $manager->fresh();
    $gudang = Role::where('name', RoleEnum::WAREHOUSE->value)->first();

    expect($manager->hasPermissionTo(PermissionEnum::QR_GENERATE->value))->toBeFalse()
        ->and($manager->hasPermissionTo(PermissionEnum::QR_SCAN->value))->toBeFalse()
        ->and($manager->hasPermissionTo(PermissionEnum::QR_VERIFY->value))->toBeTrue()
        ->and($manager->hasPermissionTo(PermissionEnum::SHIPMENTS_READ->value))->toBeTrue()
        ->and($manager->hasPermissionTo(PermissionEnum::MUATAN_READ->value))->toBeTrue()
        // Role lain tidak ikut tercabut.
        ->and($gudang->hasPermissionTo(PermissionEnum::QR_GENERATE->value))->toBeTrue()
        ->and($gudang->hasPermissionTo(PermissionEnum::QR_SCAN->value))->toBeTrue();
});

it('aman dijalankan ulang saat manager sudah tidak memegang izinnya', function () {
    jalankanMigrasiCabutQr();
    jalankanMigrasiCabutQr();

    expect(Role::where('name', RoleEnum::MANAGER->value)->first()->hasPermissionTo(PermissionEnum::QR_GENERATE->value))->toBeFalse();
});

it('tidak lagi memberi manager qr.generate dan qr.scan dari seeder', function () {
    $manager = penggunaPeran(RoleEnum::MANAGER->value);

    $izin = $this->actingAs($manager)
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props']['auth']['user']['permissions'];

    // Tab di TabNavigation.svelte muncul dari izin ini (can.qr.generate / can.qr.scan).
    expect($izin)->not->toContain(PermissionEnum::QR_GENERATE->value)
        ->and($izin)->not->toContain(PermissionEnum::QR_SCAN->value)
        ->and($izin)->not->toContain(PermissionEnum::WAREHOUSE_QR_GENERATE->value)
        ->and($izin)->not->toContain(PermissionEnum::WAREHOUSE_QR_SCAN->value);
});

it('menolak manager membuka halaman Generate QR dan Scan QR lewat URL', function (string $mode) {
    $this->actingAs(penggunaPeran(RoleEnum::MANAGER->value))
        ->get(route('admin.pengiriman.index', ['mode' => $mode]))
        ->assertForbidden();
})->with(['generate-qr', 'scan-status']);

it('menolak peran lain tanpa izin QR yang hanya bisa membaca resi', function (string $mode) {
    $this->actingAs(penggunaPeran(RoleEnum::COURIER->value))
        ->get(route('admin.pengiriman.index', ['mode' => $mode]))
        ->assertForbidden();
})->with(['generate-qr', 'scan-status']);

it('tetap membuka halaman Generate QR dan Scan QR untuk gudang', function (string $mode) {
    $this->actingAs(penggunaPeran(RoleEnum::WAREHOUSE->value))
        ->get(route('admin.pengiriman.index', ['mode' => $mode]))
        ->assertSuccessful();
})->with(['generate-qr', 'scan-status']);

it('membiarkan manager membuka Daftar Resi', function () {
    $this->actingAs(penggunaPeran(RoleEnum::MANAGER->value))
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful();
});

it('membiarkan manager melihat dan mengunduh gambar QR resi', function () {
    Storage::fake();
    Storage::put('public/qr-codes/QR-EQ-2026-00001.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

    $resi = Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-00001',
        'qr_code_path' => 'public/qr-codes/QR-EQ-2026-00001.svg',
        'qr_code_data' => 'EQ-2026-00001',
    ]);

    $manager = penggunaPeran(RoleEnum::MANAGER->value);

    $this->actingAs($manager)->get(route('admin.qr.display', $resi))->assertSuccessful();
    $this->actingAs($manager)->get(route('admin.qr.download', $resi))->assertSuccessful();

    // Tetap tidak boleh MEMBUAT QR.
    $this->actingAs($manager)->postJson(route('admin.qr.generate', $resi))->assertForbidden();
});

it('menolak melihat gambar QR bagi pengguna tanpa izin baca resi', function () {
    Storage::fake();
    Storage::put('public/qr-codes/QR-EQ-2026-00002.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

    $resi = Pengiriman::factory()->create([
        'qr_code_path' => 'public/qr-codes/QR-EQ-2026-00002.svg',
        'qr_code_data' => 'EQ-2026-00002',
    ]);

    $this->actingAs(User::factory()->create(['is_active' => true]))
        ->get(route('admin.qr.display', $resi))
        ->assertForbidden();
});
