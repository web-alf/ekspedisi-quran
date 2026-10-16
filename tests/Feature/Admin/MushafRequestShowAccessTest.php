<?php

use App\Models\MushafRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Regresi: route admin.mushaf-requests.show pernah memakai parameter route
 * {mushaf_request} (snake_case) sementara controller memanggil
 * authorizeResource(..., 'mushafRequest'), sehingga policy tidak menemukan
 * model dan SETIAP role — termasuk super-admin — menerima 403.
 *
 * Lihat routes/web.php: ->parameters(['mushaf-requests' => 'mushafRequest']).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');

    $this->mushafRequest = MushafRequest::factory()->create();
});

test('route show mushaf-requests memakai parameter mushafRequest', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'admin.mushaf-requests.show');

    expect($route)->not->toBeNull()
        ->and($route->parameterNames())->toContain('mushafRequest')
        ->and($route->parameterNames())->not->toContain('mushaf_request');
});

test('super-admin dapat membuka halaman detail mushaf request', function () {
    $this->actingAs($this->admin)
        ->get("/admin/mushaf-requests/{$this->mushafRequest->id}")
        ->assertOk();
});

test('role tanpa permission mushaf-requests.read tetap ditolak', function () {
    $courier = User::factory()->create(['is_active' => true]);
    $courier->assignRole('courier');

    $this->actingAs($courier)
        ->get("/admin/mushaf-requests/{$this->mushafRequest->id}")
        ->assertForbidden();
});
