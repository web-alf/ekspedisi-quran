<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Jatah gudang dari Manager Gudang ke SPV (30/10/2026).
 *
 * PJ memberi target ke SPV (hierarki 27/10/2026), SPV meneruskannya ke freelancer. Tugas SPV
 * itu dulu ikut dijumlah di "Packing Hari Ini" sehingga target gudang terhitung dua kali, dan
 * SPV tidak bisa melihat berapa jatahnya yang sudah dibagi. Kini tugas SPV dipisah sebagai
 * `jatah` (target, sudah dibagi, sisa, lebih) dan tidak masuk angka packing gudang.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->a5 = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();

    $this->buatAkun = function (string $nama, array $peran, ?Gudang $gudang = null): User {
        $user = User::factory()->create(['name' => $nama, 'is_active' => true, 'gudang_id' => $gudang?->id]);
        $user->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    };

    $this->tugasHariIni = function (User $user, int $target, ?Gudang $gudang, bool $mulai = false): DailyPackingTask {
        return DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'gudang_id' => $gudang?->id,
            'tanggal_tugas' => today(),
            'total_target' => $target,
            'jenis_quran_id' => $this->a5->id,
            'terjemahan' => true,
            'status' => $mulai ? DailyPackingTask::STATUS_IN_PROGRESS : DailyPackingTask::STATUS_ASSIGNED,
            'started_at' => $mulai ? now() : null,
        ]);
    };

    $this->spvSmg = ($this->buatAkun)('SPV Semarang', [RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);
    $this->spvBtl = ($this->buatAkun)('SPV Boyolali', [RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->btl);
    $this->pj = ($this->buatAkun)('Manager Gudang', [RoleEnum::MANAGER_REPACKING->value]);

    // PJ memberi Semarang jatah 120; SPV Semarang membaginya 50 + 40 ke dua freelancer.
    ($this->tugasHariIni)($this->spvSmg, 120, $this->smg, mulai: true);
    ($this->tugasHariIni)(($this->buatAkun)('Freelancer A', [RoleEnum::WAREHOUSE->value], $this->smg), 50, $this->smg, mulai: true);
    ($this->tugasHariIni)(($this->buatAkun)('Freelancer B', [RoleEnum::WAREHOUSE->value], $this->smg), 40, $this->smg);
    // Boyolali: freelancer bertugas 30 tanpa jatah dari PJ.
    ($this->tugasHariIni)(($this->buatAkun)('Freelancer C', [RoleEnum::WAREHOUSE->value], $this->btl), 30, $this->btl);
});

it('tidak menjumlah jatah SPV ke Packing Hari Ini dan menampilkannya terpisah', function () {
    $packing = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->assertOk()
        ->viewData('page')['props']['ringkasanGudang']['packing'];

    expect($packing['target'])->toBe(90)
        ->and($packing['jumlah_staf'])->toBe(2)
        ->and($packing['staf_belum_mulai'])->toBe(1)
        ->and(collect($packing['per_staf'])->pluck('nama')->sort()->values()->all())->toBe(['Freelancer A', 'Freelancer B'])
        ->and($packing['jatah'])->toBe([
            'target' => 120,
            'jenis' => ['A5 Terjemahan'],
            'spv' => ['SPV Semarang'],
            'dibagi' => 90,
            'sisa' => 30,
            'lebih' => 0,
        ]);
});

it('menandai pembagian yang melebihi jatah', function () {
    ($this->tugasHariIni)(($this->buatAkun)('Freelancer D', [RoleEnum::WAREHOUSE->value], $this->smg), 60, $this->smg);

    $jatah = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->viewData('page')['props']['ringkasanGudang']['packing']['jatah'];

    expect($jatah)->toMatchArray(['target' => 120, 'dibagi' => 150, 'sisa' => 0, 'lebih' => 30]);
});

it('gudang tanpa jatah dari PJ tetap menghitung tugas freelancernya', function () {
    $packing = $this->actingAs($this->spvBtl)->get('/admin/warehouse')
        ->viewData('page')['props']['ringkasanGudang']['packing'];

    expect($packing['target'])->toBe(30)
        ->and($packing['jatah'])->toMatchArray(['target' => 0, 'jenis' => [], 'spv' => [], 'dibagi' => 30, 'sisa' => 0, 'lebih' => 0]);
});

it('dashboard PJ memisahkan jatah per gudang dan gabungannya tanpa jatah SPV', function () {
    $ringkasan = $this->actingAs($this->pj)->get('/admin/warehouse')
        ->assertOk()
        ->viewData('page')['props']['ringkasanManager'];

    $perGudang = collect($ringkasan['per_gudang'])->keyBy('kode');

    expect($ringkasan['gabungan']['target'])->toBe(120)
        ->and($perGudang['SMG']['target'])->toBe(90)
        ->and($perGudang['SMG']['jatah'])->toMatchArray(['target' => 120, 'dibagi' => 90, 'sisa' => 30])
        ->and($perGudang['BTL']['jatah'])->toMatchArray(['target' => 0, 'dibagi' => 30]);
});

it('Monitor & Penugasan: SPV melihat jatah gudangnya saja, PJ semua gudang', function () {
    $jatahSpv = $this->actingAs($this->spvSmg)->get(route('admin.supervisor.warehouse-monitor'))
        ->assertOk()
        ->viewData('page')['props']['jatahGudang'];

    expect($jatahSpv)->toHaveCount(1)
        ->and($jatahSpv[0])->toMatchArray(['nama' => $this->smg->nama, 'target' => 120, 'dibagi' => 90, 'sisa' => 30]);

    $jatahPj = $this->actingAs($this->pj)->get(route('admin.supervisor.warehouse-monitor'))
        ->viewData('page')['props']['jatahGudang'];

    expect(collect($jatahPj)->pluck('target', 'nama')->all())->toMatchArray([
        $this->smg->nama => 120,
        $this->btl->nama => 0,
    ]);

    $this->actingAs($this->spvSmg)->getJson(route('admin.supervisor.warehouse-monitor.data'))
        ->assertOk()
        ->assertJsonPath('jatahGudang.0.sisa', 30);
});
