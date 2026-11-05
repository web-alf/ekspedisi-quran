<?php

use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Freelancer tanpa tugas diarahkan ke SPV gudangnya (05/11/2026).
 *
 * Tombol "Hubungi Supervisor" di Proses Packing dulu membuka Monitor & Penugasan —
 * halaman yang ditolak (403) untuk freelancer. Kini halaman Packing dan Dashboard Gudang
 * menerima nama SPV aktif di gudang akun itu, dan tautan Monitor hanya untuk pemegang
 * izinnya.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->freelancer = akunGudangDi('warehouse', 'Freelancer Semarang', $this->smg->id);
});

function akunGudangDi(string $role, string $nama, ?int $gudangId, bool $aktif = true): User
{
    $user = User::factory()->create(['name' => $nama, 'is_active' => $aktif, 'gudang_id' => $gudangId]);
    $user->syncRoles([$role]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

it('hanya menyebut SPV aktif di gudang yang sama', function () {
    akunGudangDi('spv-gudang', 'Zahra SPV', $this->smg->id);
    akunGudangDi('spv-gudang', 'Ahmad SPV', $this->smg->id);
    akunGudangDi('spv-gudang', 'SPV Boyolali', $this->btl->id);
    akunGudangDi('spv-gudang', 'SPV Nonaktif', $this->smg->id, aktif: false);
    akunGudangDi('warehouse', 'Freelancer Lain', $this->smg->id);

    expect($this->freelancer->namaSpvGudang())->toBe(['Ahmad SPV', 'Zahra SPV']);
});

it('tidak menyebut siapa pun bila akun belum ditempatkan di gudang', function () {
    akunGudangDi('spv-gudang', 'Zahra SPV', $this->smg->id);

    expect(akunGudangDi('warehouse', 'Freelancer Baru', null)->namaSpvGudang())->toBe([]);
});

it('mengirim nama SPV ke halaman Packing saat freelancer belum punya tugas', function () {
    akunGudangDi('spv-gudang', 'Zahra SPV', $this->smg->id);

    $this->actingAs($this->freelancer)
        ->get(route('admin.warehouse.packing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/Packing')
            ->where('spvGudang', ['Zahra SPV']));
});

it('mengirim nama SPV ke Dashboard Gudang saat freelancer belum punya tugas', function () {
    akunGudangDi('spv-gudang', 'Zahra SPV', $this->smg->id);

    $this->actingAs($this->freelancer)
        ->get('/admin/warehouse')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/Dashboard')
            ->where('spvGudang', ['Zahra SPV']));
});

it('tidak mencari SPV di Dashboard Gudang saat freelancer sudah punya tugas', function () {
    akunGudangDi('spv-gudang', 'Zahra SPV', $this->smg->id);

    DailyPackingTask::create([
        'user_id' => $this->freelancer->id,
        'tanggal_tugas' => today(),
        'total_target' => 80,
        'total_selesai' => 0,
        'status' => 'assigned',
        'assigned_at' => now(),
        'gudang_id' => $this->smg->id,
    ]);

    $this->actingAs($this->freelancer)
        ->get('/admin/warehouse')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('spvGudang', []));
});

it('menolak freelancer di Monitor & Penugasan, tujuan tombol lama', function () {
    $this->actingAs($this->freelancer)
        ->get('/admin/supervisor/warehouse-monitor')
        ->assertForbidden();
});
