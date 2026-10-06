<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tiga route gudang pernah terdaftar di routes/web.php padahal berkas halaman
 * Svelte-nya tidak ada, sehingga membukanya melempar error "page component not
 * found". Tes ini menjaga supaya ketiganya benar-benar merender komponen yang
 * ada, dan tetap dijaga izin yang semestinya.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'warehouse.tasks.view',
        'warehouse.packing.view',
        'warehouse.boxes.view',
        'warehouse.dashboard',
    ] as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function stafGudang(array $izin): User
{
    $user = User::factory()->create(['is_active' => true]);
    // Grup route /admin/warehouse dijaga `warehouse.dashboard` di level grup,
    // jadi izin itu wajib ada di samping izin spesifik halaman.
    $user->givePermissionTo(array_merge(['warehouse.dashboard'], $izin));

    return $user;
}

it('merender halaman monitor tugas untuk staf gudang', function () {
    $user = stafGudang(['warehouse.tasks.view']);

    $this->actingAs($user)
        ->get('/admin/warehouse/job-monitor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/JobMonitor')
            ->has('stats')
            ->has('activeJobs')
            ->has('recentJobs'));
});

it('merender halaman riwayat packing untuk staf gudang', function () {
    $user = stafGudang(['warehouse.packing.view']);

    $this->actingAs($user)
        ->get('/admin/warehouse/packing/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/PackingHistory')
            ->has('history'));
});

it('merender halaman kolaborasi kerdus untuk staf gudang', function () {
    $user = stafGudang(['warehouse.boxes.view']);

    $this->actingAs($user)
        ->get('/admin/warehouse/shared-collaboration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/SharedCollaboration')
            ->has('sharedBoxes')
            ->has('currentUser'));
});

it('menolak pengguna tanpa izin gudang dari monitor tugas', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get('/admin/warehouse/job-monitor')
        ->assertForbidden();
});
