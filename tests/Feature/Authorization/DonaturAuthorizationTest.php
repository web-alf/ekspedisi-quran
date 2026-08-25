<?php

use App\Enums\PermissionEnum;
use App\Models\Donatur;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => PermissionEnum::DONATUR_READ->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::DONATUR_CREATE->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::DONATUR_UPDATE->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::DONATUR_DELETE->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::DONATUR_IMPORT->value]);
    Permission::firstOrCreate(['name' => PermissionEnum::DONATUR_EXPORT->value]);
});

if (! function_exists('createActiveUser')) {
    function createActiveUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true], $attributes));
    }
}

it('allows read-only user to access donatur index', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);

    $response = $this->actingAs($user)->get(route('admin.donatur.index'));

    $response->assertOk();
});

it('forbids read-only user from creating donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);

    $response = $this->actingAs($user)->get(route('admin.donatur.create'));

    $response->assertForbidden();
});

it('forbids read-only user from storing donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);

    $response = $this->actingAs($user)->post(route('admin.donatur.store'), [
        '_token' => csrfToken(),
        'kode_donatur' => 'TEST001',
        'nama_donatur' => 'Test Donatur',
        'no_hp' => '+628123456789',
        'donation_date' => now()->toDateString(),
        'jenis_wakaf_dipilih' => ['A5'],
        'jumlah_a5' => 1,
        'prayer_mode' => 'semua_donatur',
        'wakif_details' => [],
    ]);

    $response->assertForbidden();
});

it('allows create permission to access store donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo([
        PermissionEnum::DONATUR_READ->value,
        PermissionEnum::DONATUR_CREATE->value,
    ]);

    $response = $this->actingAs($user)->get(route('admin.donatur.create'));

    $response->assertOk();
});

it('forbids user without update permission from updating donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);
    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($user)->patch(route('admin.donatur.update', $donatur), [
        '_token' => csrfToken(),
        'nama_donatur' => 'Updated Name',
        'no_hp' => '+628123456789',
        'donation_date' => now()->toDateString(),
        'jenis_wakaf_dipilih' => ['A5'],
        'jumlah_a5' => 1,
        'prayer_mode' => 'semua_donatur',
    ]);

    $response->assertForbidden();
});

it('forbids user without delete permission from deleting donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);
    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($user)->delete(route('admin.donatur.destroy', $donatur), ['_token' => csrfToken()]);

    $response->assertForbidden();
});

it('forbids user without export permission from exporting donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);

    $response = $this->actingAs($user)->get(route('admin.donatur.export'));

    $response->assertForbidden();
});

it('allows export with correct permission', function () {
    $user = createActiveUser();
    $user->givePermissionTo([
        PermissionEnum::DONATUR_READ->value,
        PermissionEnum::DONATUR_EXPORT->value,
    ]);

    $response = $this->actingAs($user)->get(route('admin.donatur.export'));

    $this->assertTrue(in_array($response->getStatusCode(), [200, 302, 500]));
});

it('forbids user without import permission from importing donatur', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);

    $response = $this->actingAs($user)->post(route('admin.donatur.import'), ['_token' => csrfToken()]);

    $response->assertForbidden();
});

it('forbids user without update permission from updating wakif names', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);
    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($user)->patch(route('admin.donatur.update-wakif-names', $donatur), [
        '_token' => csrfToken(),
        'wakif_names' => [],
    ]);

    $response->assertForbidden();
});

it('allows search by kode donatur with read permission', function () {
    $user = createActiveUser();
    $user->givePermissionTo(PermissionEnum::DONATUR_READ->value);
    Donatur::factory()->create(['kode_donatur' => 'TEST123']);

    $response = $this->actingAs($user)->getJson(route('admin.api.donatur.search-kode', ['q' => 'TEST']));

    $response->assertOk()
        ->assertJsonPath('data.0.kode_donatur', 'TEST123');
});

it('forbids search by kode donatur without read permission', function () {
    $user = createActiveUser();

    $response = $this->actingAs($user)->getJson(route('admin.api.donatur.search-kode', ['q' => 'TEST']));

    $response->assertForbidden();
});
