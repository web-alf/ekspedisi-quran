<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\MushafRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Freelancer Gudang (role warehouse) tidak boleh membuka data Permintaan Mushaf.
 *
 * Yang dikunci:
 *   1. Migrasi (bukan seeder) mencabut mushaf-requests.read & .process dari role
 *      warehouse — deploy hanya menjalankan migrasi.
 *   2. Lewat URL langsung freelancer ditolak di daftar Permintaan Mushaf, ekspornya,
 *      api/donatur-list (nama donatur + kontak lembaga), dan proses ke resi.
 *   3. SPV Gudang tetap memegang izinnya lewat role sendiri.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function akunBerperan(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function jalankanMigrasiCabutPermintaanMushaf(): void
{
    $migrasi = require database_path('migrations/2026_11_04_231013_cabut_izin_permintaan_mushaf_dari_freelancer.php');
    $migrasi->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('mencabut izin Permintaan Mushaf dari freelancer lewat migrasi, tanpa menyentuh izin lain', function () {
    // Keadaan produksi sebelum migrasi: freelancer masih memegang keduanya.
    $freelancer = Role::where('name', RoleEnum::WAREHOUSE->value)->first();
    $freelancer->givePermissionTo([PermissionEnum::MUSHAF_REQUESTS_READ->value, PermissionEnum::MUSHAF_REQUESTS_PROCESS->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    jalankanMigrasiCabutPermintaanMushaf();

    $freelancer = $freelancer->fresh();
    $spv = Role::where('name', RoleEnum::SPV_GUDANG->value)->first();

    expect($freelancer->hasPermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value))->toBeFalse()
        ->and($freelancer->hasPermissionTo(PermissionEnum::MUSHAF_REQUESTS_PROCESS->value))->toBeFalse()
        ->and($freelancer->hasPermissionTo(PermissionEnum::WAREHOUSE_PACKING_VIEW->value))->toBeTrue()
        ->and($freelancer->hasPermissionTo(PermissionEnum::SHIPMENTS_READ->value))->toBeTrue()
        ->and($freelancer->hasPermissionTo(PermissionEnum::STOK_READ->value))->toBeTrue()
        // Role lain tidak ikut tercabut.
        ->and($spv->hasPermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value))->toBeTrue()
        ->and($spv->hasPermissionTo(PermissionEnum::MUSHAF_REQUESTS_PROCESS->value))->toBeTrue();
});

it('aman dijalankan ulang saat freelancer sudah tidak memegang izinnya', function () {
    jalankanMigrasiCabutPermintaanMushaf();
    jalankanMigrasiCabutPermintaanMushaf();

    $freelancer = Role::where('name', RoleEnum::WAREHOUSE->value)->first();

    expect($freelancer->hasPermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value))->toBeFalse()
        ->and($freelancer->hasPermissionTo(PermissionEnum::MUSHAF_REQUESTS_PROCESS->value))->toBeFalse();
});

it('tidak lagi memberi freelancer izin Permintaan Mushaf dari seeder', function (PermissionEnum $izin) {
    expect(akunBerperan(RoleEnum::WAREHOUSE->value)->can($izin->value))->toBeFalse();
})->with([
    'baca' => PermissionEnum::MUSHAF_REQUESTS_READ,
    'proses ke resi' => PermissionEnum::MUSHAF_REQUESTS_PROCESS,
]);

it('menolak freelancer memproses permintaan menjadi resi', function () {
    $permintaan = MushafRequest::factory()->create(['status' => 'approved']);

    $this->actingAs(akunBerperan(RoleEnum::WAREHOUSE->value))
        ->post(route('admin.mushaf-requests.process', $permintaan))
        ->assertForbidden();

    expect($permintaan->fresh()->pengiriman_id)->toBeNull();
});

it('menolak freelancer membuka data Permintaan Mushaf lewat URL', function (string $rute) {
    $this->actingAs(akunBerperan(RoleEnum::WAREHOUSE->value))
        ->get(route($rute))
        ->assertForbidden();
})->with([
    'daftar permintaan' => 'admin.mushaf-requests.index',
    'ekspor' => 'admin.mushaf-requests.export',
    'templat' => 'admin.mushaf-requests.template',
    'daftar donatur & lembaga' => 'admin.api.donatur-list',
]);

it('tetap membuka halaman kerja gudang untuk freelancer', function (string $rute) {
    $this->actingAs(akunBerperan(RoleEnum::WAREHOUSE->value))
        ->get(route($rute))
        ->assertSuccessful();
})->with([
    'admin.pengiriman.index',
    'admin.warehouse.stok.index',
]);

it('tetap membiarkan SPV Gudang membuka daftar Permintaan Mushaf', function () {
    $this->actingAs(akunBerperan(RoleEnum::SPV_GUDANG->value))
        ->get(route('admin.mushaf-requests.index'))
        ->assertSuccessful();
});
