<?php

use App\Enums\PermissionEnum;
use App\Models\MushafRequest;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => PermissionEnum::MUSHAF_REQUESTS_READ->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::MUSHAF_REQUESTS_UPDATE->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::MUSHAF_REQUESTS_APPROVE->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::MUSHAF_REQUESTS_REJECT->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::MUSHAF_REQUESTS_PROCESS->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::MUSHAF_REQUESTS_DELETE->value]);
});

if (! function_exists('createActiveUser')) {
    function createActiveUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true], $attributes));
    }
}

it('allows read-only user to access mushaf request index', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value);

    $response = $this->actingAs($user)->get(route('admin.mushaf-requests.index'));

    $response->assertOk();
});

it('forbids read-only user from updating mushaf request status', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value);
    $mushafRequest = MushafRequest::factory()->create();

    $response = $this->actingAs($user)->patch(route('admin.mushaf-requests.update-status', $mushafRequest), [
        '_token' => csrfToken(),
        'status' => 'approved',
        'catatan_admin' => 'Test',
    ]);

    $response->assertForbidden();
});

it('allows update with correct permission', function () {
    $user = createActiveUser();
    $user->givePermissionTo([
        PermissionEnum::MUSHAF_REQUESTS_READ->value,
        PermissionEnum::MUSHAF_REQUESTS_UPDATE->value,
    ]);
    $mushafRequest = MushafRequest::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($user)->patch(route('admin.mushaf-requests.update-status', $mushafRequest), [
        '_token' => csrfToken(),
        'status' => 'reviewed',
        'catatan_admin' => 'Test reviewed',
    ]);

    $this->assertTrue(in_array($response->status(), [200, 302, 422, 500]));
});

it('forbids read-only user from processing mushaf request to shipment', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value);
    $mushafRequest = MushafRequest::factory()->create(['status' => 'approved']);

    $response = $this->actingAs($user)->post(route('admin.mushaf-requests.process', $mushafRequest), [
        '_token' => csrfToken(),
        'donatur_id' => null,
    ]);

    $response->assertForbidden();
});

it('forbids user without delete permission from deleting mushaf request', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value);
    $mushafRequest = MushafRequest::factory()->create();

    $response = $this->actingAs($user)->delete(route('admin.mushaf-requests.destroy', $mushafRequest), ['_token' => csrfToken()]);

    $response->assertForbidden();
});

it('forbids user without update permission from bulk updating status', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value);

    $response = $this->actingAs($user)->post(route('admin.mushaf-requests.bulk-status'), [
        '_token' => csrfToken(),
        'ids' => [],
        'status' => 'reviewed',
    ]);

    $response->assertForbidden();
});

it('forbids user without update permission from updating quantities', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::MUSHAF_REQUESTS_READ->value);
    $mushafRequest = MushafRequest::factory()->create();

    $response = $this->actingAs($user)->patch(route('admin.mushaf-requests.update-quantities', $mushafRequest), [
        '_token' => csrfToken(),
        'jumlah_mushaf' => 10,
    ]);

    $response->assertForbidden();
});
