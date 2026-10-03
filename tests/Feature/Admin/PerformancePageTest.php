<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\PerformanceMonitoringService;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cakupan: halaman /admin/performance milik super-admin.
 *
 * Konteks: halaman ini selalu 500 di produksi
 * ("Call to private method PerformanceMonitoringService::getSlowQueries()")
 * karena getSlowQueries() dan getApiStats() dideklarasikan private tetapi
 * dipanggil dari PerformanceController. Tes ini mencegah bug itu kembali.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
});

it('membuka halaman performance tanpa error 500', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin)
        ->get('/admin/performance')
        ->assertSuccessful();
});

it('mengekspos getSlowQueries dan getApiStats sebagai method publik', function () {
    // Bug aslinya: method private dipanggil dari controller -> Error 500.
    // Refleksi memastikan keduanya tetap publik.
    $service = new PerformanceMonitoringService;

    foreach (['getSlowQueries', 'getApiStats'] as $method) {
        expect((new ReflectionMethod($service, $method))->isPublic())
            ->toBeTrue("Method {$method}() harus publik karena dipanggil dari controller");
    }
});

it('mengembalikan struktur data yang dipakai halaman performance', function () {
    $service = app(PerformanceMonitoringService::class);

    expect($service->getSlowQueries())->toBeArray();
    expect($service->getApiStats())->toBeArray();
});
