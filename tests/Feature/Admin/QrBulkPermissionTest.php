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

    // Tidak ada mock QrCode di sini: controller sekarang memakai QrCodeService,
    // dan mem-mock facade-nya memicu "Cannot redeclare ..." di Mockery sekaligus
    // membuat tes tidak menguji pembuatan QR yang sebenarnya.
    $response = $this->actingAs($this->userWithWarehousePermission)
        ->postJson('/admin/qr/bulk-generate', [
            'pengiriman_ids' => [$pengiriman->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'summary' => ['success' => 1, 'errors' => 0],
        ]);

    $pengiriman->refresh();

    // Berkasnya benar-benar dibuat, dan isi QR-nya hanya nomor resi —
    // format yang sama dengan yang dibaca pemindai gudang.
    expect($pengiriman->qr_code_path)->not->toBeNull()
        ->and($pengiriman->qr_code_data)->toBe($pengiriman->no_resi);

    Storage::assertExists($pengiriman->qr_code_path);
});

it('denies bulk qr generation when user lacks permissions', function () {
    $userWithoutPermission = User::factory()->create();

    $response = $this->actingAs($userWithoutPermission)
        ->postJson('/admin/qr/bulk-generate', [
            'pengiriman_ids' => [1],
        ]);

    $response->assertForbidden();
});
