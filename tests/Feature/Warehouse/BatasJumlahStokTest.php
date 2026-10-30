<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\TransferStok;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Batas jumlah eks sekali catat stok / dokumen transfer stok (30/10/2026).
 *
 * Dulu maksimal 500 eks dengan pesan bawaan berbahasa Inggris ("The jumlah eks field must not
 * be greater than 500"), padahal stok satu gudang sudah ribuan eks — penerimaan 2.000 eks
 * ditolak. Kini maksimal 100.000 eks dengan pesan Indonesia.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->jenis->update(['default_capacity' => 20]);

    $this->pj = User::factory()->create(['is_active' => true]);
    $this->pj->syncRoles([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->entri = fn (array $isian) => $this->actingAs($this->pj)->postJson('/admin/warehouse/stok/entri', [
        'gudang_id' => $this->smg->id,
        'jenis_quran_id' => $this->jenis->id,
        'terjemahan' => 1,
        'tipe' => 'masuk',
        ...$isian,
    ]);
});

function stokEksSemarangA5(): int
{
    return (int) DB::table('stok_mushaf')
        ->where('gudang_id', Gudang::byKode('SMG')->id)
        ->value('jumlah_eks');
}

it('menerima stok masuk 2.000 eks sekaligus', function () {
    ($this->entri)(['jumlah_eks' => 2000])
        ->assertOk()
        ->assertJsonPath('data.eks', 2000)
        ->assertJsonPath('data.dus', 100);

    expect(stokEksSemarangA5())->toBe(2000);
});

it('menerima tepat 100.000 eks dan menolak lebih dari itu dengan pesan Indonesia', function () {
    ($this->entri)(['jumlah_eks' => 100000])->assertOk();

    ($this->entri)(['jumlah_eks' => 100001])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['jumlah_eks' => 'Jumlah maksimal 100.000 eks sekali catat.']);

    expect(stokEksSemarangA5())->toBe(100000);
});

it('memakai pesan Indonesia untuk jumlah kosong atau kurang dari 1', function () {
    ($this->entri)(['jumlah_eks' => 0])
        ->assertJsonValidationErrors(['jumlah_eks' => 'Jumlah minimal 1 eks.']);

    ($this->entri)(['jumlah_eks' => null])
        ->assertJsonValidationErrors(['jumlah_eks' => 'Isi jumlah eks.']);
});

it('stok keluar tetap tidak boleh melebihi stok yang ada', function () {
    ($this->entri)(['jumlah_eks' => 2000])->assertOk();

    ($this->entri)(['tipe' => 'keluar', 'jumlah_eks' => 2500])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(stokEksSemarangA5())->toBe(2000);
});

it('dokumen transfer stok menerima lebih dari 500 eks dan memakai batas yang sama', function () {
    ($this->entri)(['jumlah_eks' => 3000])->assertOk();

    $transfer = fn (int $eks) => $this->actingAs($this->pj)->postJson('/admin/warehouse/stok/transfer', [
        'gudang_asal_id' => $this->smg->id,
        'gudang_tujuan_id' => $this->btl->id,
        'jenis_quran_id' => $this->jenis->id,
        'terjemahan' => 1,
        'jumlah_eks' => $eks,
    ]);

    $transfer(1200)->assertOk();
    $transfer(100001)->assertJsonValidationErrors(['jumlah_eks' => 'Jumlah maksimal 100.000 eks sekali catat.']);

    expect(TransferStok::sole()->jumlah_eks)->toBe(1200);
});
