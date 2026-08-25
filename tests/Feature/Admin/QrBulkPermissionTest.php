<?php

use App\Models\Pengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'warehouse.qr.bulk_generate',
    ] as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Storage::fake('local');
    Storage::fake('public');

    $this->userWithWarehousePermission = User::factory()->create();
    $this->userWithWarehousePermission->givePermissionTo(['warehouse.qr.bulk_generate']);
});

it('allows bulk qr generation when user has warehouse permission', function () {
    $pengiriman = Pengiriman::factory()->create();

    QrCode::shouldReceive('format')->once()->andReturnSelf();
    QrCode::shouldReceive('size')->once()->andReturnSelf();
    QrCode::shouldReceive('margin')->once()->andReturnSelf();
    QrCode::shouldReceive('errorCorrection')->once()->andReturnSelf();
    QrCode::shouldReceive('generate')->once()->andReturn('fake-qr-data');

    $response = $this->actingAs($this->userWithWarehousePermission)
        ->postJson('/admin/qr/bulk-generate', [
            'pengiriman_ids' => [$pengiriman->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);
});

it('denies bulk qr generation when user lacks permissions', function () {
    $userWithoutPermission = User::factory()->create();

    $response = $this->actingAs($userWithoutPermission)
        ->postJson('/admin/qr/bulk-generate', [
            'pengiriman_ids' => [1],
        ]);

    $response->assertForbidden();
});
