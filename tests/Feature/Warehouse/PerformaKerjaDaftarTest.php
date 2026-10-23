<?php

use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

/*
 * Halaman Performa Kerja (23/10/2026): SPV dan Manager Gudang melihat semua
 * freelancer yang DITUGASKAN pada satu tanggal (?tanggal=, default hari ini),
 * termasuk yang tidak mulai. Cakupan gudang sama dengan dashboard.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->spvSmg = pkAkun('SPV Semarang', ['warehouse', 'manager-repacking', 'spv-gudang'], $this->smg);
    $this->pj = pkAkun('Manager Gudang', ['warehouse', 'manager-repacking'], null);
    $this->andi = pkAkun('Andi', ['warehouse'], $this->smg);
    $this->budi = pkAkun('Budi', ['warehouse'], $this->smg);
    $this->citra = pkAkun('Citra', ['warehouse'], $this->btl);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function pkAkun(string $nama, array $peran, ?Gudang $gudang): User
{
    $u = User::factory()->create(['name' => $nama, 'is_active' => true, 'gudang_id' => $gudang?->id]);
    $u->syncRoles($peran);

    return $u;
}

function pkTugas(User $staf, $tanggal, int $target, int $selesai, bool $mulai = true): DailyPackingTask
{
    return DailyPackingTask::create([
        'user_id' => $staf->id,
        'tanggal_tugas' => $tanggal,
        'total_target' => $target,
        'total_selesai' => $selesai,
        'status' => ! $mulai ? 'assigned' : ($selesai >= $target ? 'completed' : 'in_progress'),
        'started_at' => $mulai ? now()->subHours(2) : null,
        'assigned_at' => now(),
        'gudang_id' => $staf->gudang_id,
    ]);
}

function pkDaftar($test, User $user, array $query = []): ?array
{
    return $test->actingAs($user)
        ->get(route('admin.warehouse.performance', $query))
        ->assertOk()
        ->viewData('page')['props']['daftarKerja'];
}

it('SPV melihat semua freelancer gudangnya yang ditugaskan hari ini, termasuk yang belum mulai', function () {
    pkTugas($this->andi, today(), 20, 12);
    pkTugas($this->budi, today(), 20, 0, mulai: false);
    pkTugas($this->citra, today(), 30, 25);

    $d = pkDaftar($this, $this->spvSmg);

    expect($d['tanggal'])->toBe(today()->format('Y-m-d'))
        ->and($d['hari_ini'])->toBe(today()->format('Y-m-d'))
        ->and(collect($d['gudang'])->pluck('kode')->all())->toBe(['SMG'])
        ->and(collect($d['staf'])->pluck('nama')->all())->toBe(['Andi', 'Budi'])
        ->and(collect($d['staf'])->pluck('status')->all())->toBe(['mulai', 'tidak_mulai'])
        ->and($d['ringkasan'])->toBe([
            'ditugaskan' => 2,
            'kerja' => 1,
            'tidak_mulai' => 1,
            'target' => 40,
            'selesai' => 12,
        ]);
});

it('menyaring berdasarkan tanggal yang dipilih', function () {
    $kemarin = today()->subDay();
    pkTugas($this->andi, $kemarin, 20, 20);
    pkTugas($this->budi, today(), 20, 5);

    $d = pkDaftar($this, $this->spvSmg, ['tanggal' => $kemarin->format('Y-m-d')]);

    expect($d['tanggal'])->toBe($kemarin->format('Y-m-d'))
        ->and(collect($d['staf'])->pluck('nama')->all())->toBe(['Andi'])
        ->and($d['staf'][0]['status'])->toBe('selesai')
        ->and($d['staf'][0]['persen'])->toBe(100);
});

it('Manager Gudang melihat freelancer semua gudang dengan kode gudangnya', function () {
    pkTugas($this->andi, today(), 20, 12);
    pkTugas($this->citra, today(), 30, 25);

    $d = pkDaftar($this, $this->pj);

    expect(collect($d['gudang'])->pluck('kode')->all())->toBe(['SMG', 'BTL'])
        ->and(collect($d['staf'])->pluck('gudang_kode', 'nama')->all())->toBe(['Citra' => 'BTL', 'Andi' => 'SMG']);
});

it('freelancer tidak menerima daftar kerja tim, hanya performa pribadinya', function () {
    pkTugas($this->andi, today(), 20, 12);
    pkTugas($this->budi, today(), 20, 3);

    $props = $this->actingAs($this->andi)
        ->get(route('admin.warehouse.performance'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['daftarKerja'])->toBeNull()
        ->and($props['recentTasks'])->toHaveCount(1);
});

it('mengembalikan daftar kosong bila tidak ada yang ditugaskan pada tanggal itu', function () {
    $d = pkDaftar($this, $this->spvSmg, ['tanggal' => today()->subDays(3)->format('Y-m-d')]);

    expect($d['staf'])->toBe([])
        ->and($d['ringkasan']['ditugaskan'])->toBe(0);
});

it('menolak tanggal yang tidak valid atau melewati hari ini', function (string $tanggal) {
    $this->actingAs($this->spvSmg)
        ->get(route('admin.warehouse.performance', ['tanggal' => $tanggal]))
        ->assertSessionHasErrors('tanggal');
})->with([
    'besok' => fn () => today()->addDay()->format('Y-m-d'),
    'format salah' => '23-10-2026',
    'bukan tanggal' => 'kemarin',
]);
