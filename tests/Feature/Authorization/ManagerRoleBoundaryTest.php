<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cakupan: batas kewenangan role manager dan jaminan super-admin.
 *
 * Konteks: role manager sebelumnya hanya ada di database produksi (dibuat manual,
 * 70 izin) dan tidak tercatat di kode. Setelah didaftarkan ke RolePermissionSeeder,
 * manager sengaja tidak diberi permissions.* maupun *.delete pada data produksi.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('mendaftarkan role manager di RoleEnum', function () {
    expect(RoleEnum::MANAGER->value)->toBe('manager');
});

it('membuat role manager saat seeder dijalankan', function () {
    expect(Role::where('name', 'manager')->exists())->toBeTrue();
});

it('tidak memberi manager izin mengelola definisi permission', function () {
    $manager = Role::where('name', 'manager')->first();
    $perms = $manager->permissions->pluck('name');

    expect($perms)->not->toContain(PermissionEnum::PERMISSIONS_READ->value)
        ->and($perms)->not->toContain(PermissionEnum::PERMISSIONS_CREATE->value)
        ->and($perms)->not->toContain(PermissionEnum::PERMISSIONS_UPDATE->value)
        ->and($perms)->not->toContain(PermissionEnum::PERMISSIONS_DELETE->value);
});

it('tidak memberi manager izin hapus data produksi', function () {
    $manager = Role::where('name', 'manager')->first();
    $perms = $manager->permissions->pluck('name');

    foreach ([
        PermissionEnum::DONATUR_DELETE->value,
        PermissionEnum::SHIPMENTS_DELETE->value,
        PermissionEnum::MUSHAF_REQUESTS_DELETE->value,
        PermissionEnum::CERTIFICATES_DELETE->value,
        PermissionEnum::WAKAF_BATCH_DELETE->value,
        PermissionEnum::TEMPLATES_DELETE->value,
    ] as $forbidden) {
        expect($perms)->not->toContain($forbidden);
    }
});

it('tetap memberi manager wewenang baca dan ubah', function () {
    $manager = Role::where('name', 'manager')->first();
    $perms = $manager->permissions->pluck('name');

    foreach ([
        PermissionEnum::DASHBOARD_VIEW->value,
        PermissionEnum::DONATUR_READ->value,
        PermissionEnum::DONATUR_UPDATE->value,
        PermissionEnum::DONATUR_CREATE->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE->value,
        PermissionEnum::MUSHAF_REQUESTS_APPROVE->value,
        PermissionEnum::CERTIFICATES_GENERATE->value,
    ] as $expected) {
        expect($perms)->toContain($expected);
    }
});

it('memberi manager izin lebih banyak daripada supervisor', function () {
    $manager = Role::where('name', 'manager')->first()->permissions->count();
    $supervisor = Role::where('name', 'supervisor')->first()->permissions->count();

    expect($manager)->toBeGreaterThan($supervisor);
});

it('menolak manager membuka halaman manajemen user dan role', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)->get('/admin/users')->assertForbidden();
    $this->actingAs($manager)->get('/admin/roles')->assertForbidden();
});

it('menolak manager membuka halaman permissions', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)->get('/admin/permissions')->assertForbidden();
});

it('mengizinkan manager membuka halaman donatur dan mushaf request', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)->get('/admin/donatur')->assertSuccessful();
    $this->actingAs($manager)->get('/admin/mushaf-requests')->assertSuccessful();
});

it('super-admin selalu lolos pemeriksaan izin walau permission belum di-seed', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Permission yang sama sekali belum dibuat di tabel permissions
    expect($admin->can('permission.yang.belum.pernah.dibuat'))->toBeTrue();
});

it('super-admin lolos middleware permission walau izin belum di-seed', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);

    // Middleware permission memakai hasAnyPermission() yang tidak melewati
    // Gate::before, jadi super-admin butuh cabang khusus agar tidak terkunci.
    $middleware = app(PermissionMiddleware::class);
    $request = Request::create('/admin/donatur', 'GET');

    $reached = false;
    $middleware->handle($request, function () use (&$reached) {
        $reached = true;

        return response('ok');
    }, 'izin.yang.belum.di-seed');

    expect($reached)->toBeTrue();
});

it('super-admin tidak diberi izin operasional gudang oleh seeder', function () {
    $admin = Role::where('name', RoleEnum::SUPER_ADMIN->value)->first();
    $perms = $admin->permissions->pluck('name');

    foreach ([
        PermissionEnum::WAREHOUSE_PACKING_SCAN->value,
        PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
        PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
        PermissionEnum::WAREHOUSE_BOXES_SEAL->value,
    ] as $warehousePermission) {
        expect($perms)->not->toContain($warehousePermission);
    }
});

it('Gate before tidak memberi keleluasaan pada role non super-admin', function () {
    $courier = User::factory()->create(['is_active' => true]);
    $courier->assignRole(RoleEnum::COURIER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($courier->can('permission.yang.belum.pernah.dibuat'))->toBeFalse();
});
