<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

/*
 * Super-admin memantau semua gudang seperti Manager Gudang (01/11/2026).
 *
 * Sebelumnya ringkasan Dashboard Gudang dan daftar kerja Performa Kerja hanya dibuat
 * untuk SPV (gudangnya) dan PJ murni (semua gudang). Super-admin bukan keduanya, jadi
 * halamannya bisa dibuka tetapi tanpa ringkasan — yang tampil malah tampilan freelancer
 * tanpa tugas.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->superAdmin = saAkun('Super Admin', [RoleEnum::SUPER_ADMIN->value], null);
    $this->andi = saAkun('Andi', [RoleEnum::WAREHOUSE->value], $this->smg);
    $this->citra = saAkun('Citra', [RoleEnum::WAREHOUSE->value], $this->btl);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function saAkun(string $nama, array $peran, ?Gudang $gudang): User
{
    $u = User::factory()->create(['name' => $nama, 'is_active' => true, 'gudang_id' => $gudang?->id]);
    $u->syncRoles($peran);

    return $u->refresh();
}

function saTugas(User $staf, int $target, int $selesai): DailyPackingTask
{
    return DailyPackingTask::create([
        'user_id' => $staf->id,
        'gudang_id' => $staf->gudang_id,
        'tanggal_tugas' => today(),
        'total_target' => $target,
        'total_selesai' => $selesai,
        'status' => 'in_progress',
        'assigned_at' => now(),
        'started_at' => now()->subHour(),
    ]);
}

it('super-admin menerima ringkasan semua gudang di Dashboard Gudang', function () {
    saTugas($this->andi, 40, 20);
    saTugas($this->citra, 30, 10);

    $props = $this->actingAs($this->superAdmin)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];
    $m = $props['ringkasanManager'];

    expect($m)->not->toBeNull()
        ->and(collect($m['per_gudang'])->pluck('nama')->all())->toBe(['Gudang Semarang', 'Gudang Boyolali'])
        ->and($m['gabungan']['target'])->toEqual(70)
        ->and((int) $m['gabungan']['selesai'])->toEqual(30)
        // Super-admin bukan SPV: ringkasan satu gudang tetap kosong.
        ->and($props['ringkasanGudang'])->toBeNull();
});

it('ringkasan super-admin ikut disegarkan lewat dashboard-status saat ?ringkasan=1', function () {
    saTugas($this->andi, 40, 20);

    $data = $this->actingAs($this->superAdmin)
        ->getJson('/admin/warehouse/dashboard-status?ringkasan=1')
        ->assertOk()
        ->json('data');

    expect($data['ringkasanManager'])->not->toBeNull()
        ->and($data['ringkasanManager']['gabungan']['target'])->toEqual(40)
        ->and($data['ringkasanGudang'])->toBeNull();
});

it('super-admin melihat daftar kerja freelancer semua gudang di Performa Kerja', function () {
    saTugas($this->andi, 20, 12);
    saTugas($this->citra, 30, 25);

    $d = $this->actingAs($this->superAdmin)
        ->get(route('admin.warehouse.performance'))
        ->assertOk()
        ->viewData('page')['props']['daftarKerja'];

    expect($d)->not->toBeNull()
        ->and(collect($d['gudang'])->pluck('kode')->all())->toBe(['SMG', 'BTL'])
        ->and(collect($d['staf'])->pluck('gudang_kode', 'nama')->all())->toBe(['Citra' => 'BTL', 'Andi' => 'SMG']);
});

it('cakupan peran lain tidak berubah', function () {
    $pj = saAkun('Manager Gudang', [RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], null);
    $spv = saAkun('SPV Semarang', [RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->smg);

    $ringkasan = fn (User $u) => $this->actingAs($u)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];

    expect($ringkasan($pj)['ringkasanManager'])->not->toBeNull()
        ->and($ringkasan($spv)['ringkasanManager'])->toBeNull()
        ->and($ringkasan($spv)['ringkasanGudang'])->not->toBeNull()
        ->and($ringkasan($this->andi)['ringkasanManager'])->toBeNull()
        ->and($ringkasan($this->andi)['ringkasanGudang'])->toBeNull();
});

it('super-admin tetap tidak masuk kandidat penugasan packing', function () {
    expect($this->superAdmin->isFreelancerMurni())->toBeFalse()
        ->and(User::freelancerMurni()->pluck('name')->all())->not->toContain('Super Admin');
});
