<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

if (! function_exists('createActiveUser')) {
    function createActiveUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create(array_merge(['is_active' => true], $attributes));
    }
}

it('has all required role enum values', function () {
    expect(RoleEnum::SUPER_ADMIN->value)->toBe('super-admin');
    expect(RoleEnum::CUSTOMER_SERVICE->value)->toBe('customer-service');
    expect(RoleEnum::WAREHOUSE->value)->toBe('warehouse');
    expect(RoleEnum::SUPERVISOR->value)->toBe('supervisor');
    expect(RoleEnum::COURIER->value)->toBe('courier');
});

it('has all required permission enum values', function () {
    expect(PermissionEnum::DASHBOARD_VIEW->value)->toBe('dashboard.view');
    expect(PermissionEnum::DONATUR_CREATE->value)->toBe('donatur.create');
    expect(PermissionEnum::SHIPMENTS_READ->value)->toBe('shipments.read');
    expect(PermissionEnum::CERTIFICATES_GENERATE->value)->toBe('certificates.generate');
    expect(PermissionEnum::WAREHOUSE_DASHBOARD->value)->toBe('warehouse.dashboard');
});

it('defines missing warehouse gates without error', function () {
    $user = createActiveUser();
    Permission::firstOrCreate(['name' => PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value]);
    $user->givePermissionTo([
        PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
        PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value,
    ]);

    expect(Gate::forUser($user)->allows('viewWarehouseStats'))->toBeTrue();
    expect(Gate::forUser($user)->allows('manageWarehouse'))->toBeTrue();
});

it('allows viewWarehouseStats only with warehouse.performance.view permission', function () {
    $user = createActiveUser();
    Permission::firstOrCreate(['name' => PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value]);

    expect(Gate::forUser($user)->allows('viewWarehouseStats'))->toBeFalse();

    $user->givePermissionTo(PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value);

    expect(Gate::forUser($user)->allows('viewWarehouseStats'))->toBeTrue();
});

it('allows manageWarehouse with supervisor or box override permission', function () {
    $user = createActiveUser();
    Permission::firstOrCreate(['name' => PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY->value]);

    expect(Gate::forUser($user)->allows('manageWarehouse'))->toBeFalse();

    $user->givePermissionTo(PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value);
    expect(Gate::forUser($user)->allows('manageWarehouse'))->toBeTrue();

    $user2 = User::factory()->create();
    $user2->givePermissionTo(PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY->value);
    expect(Gate::forUser($user2)->allows('manageWarehouse'))->toBeTrue();
});

it('registers all new policies in app service provider', function () {
    $policies = Gate::policies();

    expect($policies)->toHaveKey('App\Models\Donatur');
    expect($policies)->toHaveKey('App\Models\MushafRequest');
    expect($policies)->toHaveKey('App\Models\Sertifikat');
    expect($policies)->toHaveKey('App\Models\Pengiriman');
    expect($policies)->toHaveKey('App\Models\CertificateTemplate');
    expect($policies)->toHaveKey('App\Models\Gallery');
    expect($policies)->toHaveKey('App\Models\Video');
    expect($policies)->toHaveKey('App\Models\Faq');
    expect($policies)->toHaveKey('App\Models\Testimonial');
});

it('redirects unauthenticated users to login from admin routes', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});

it('returns 403 for json requests without required role', function () {
    $user = createActiveUser();
    Role::firstOrCreate(['name' => RoleEnum::CUSTOMER_SERVICE->value, 'guard_name' => 'web']);
    $user->assignRole(RoleEnum::CUSTOMER_SERVICE->value);

    $response = $this->actingAs($user)->getJson(route('admin.warehouse.dashboard'));

    $response->assertForbidden();
});
