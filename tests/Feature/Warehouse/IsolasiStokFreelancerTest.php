<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\TransferMushaf;
use App\Models\User;
use App\Services\StokMushafService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Isolasi stok mushaf per freelancer (28/10/2026).
 *
 * Stok dimiliki GUDANG, jadi "stok milik freelancer" = stok gudang tempat ia ditempatkan.
 * Sebelumnya hanya SPV yang tercakup gudangnya; freelancer (role `warehouse`, izin
 * `stok.read`) menerima kartu, daftar, pergerakan, dan dokumen transfer SEMUA gudang.
 * Batasnya ditegakkan di server lewat User::cakupanGudangStok() — gagal-tertutup: akun
 * tanpa gudang tidak melihat stok apa pun.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->jenis->update(['default_capacity' => 20]);

    $this->buatAkun = function (array $peran, ?Gudang $gudang = null): User {
        $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $user->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    };

    $this->admin = ($this->buatAkun)([RoleEnum::SUPER_ADMIN->value]);
    $this->freelancerSmg = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);
    $this->freelancerBtl = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->btl);

    $stok = app(StokMushafService::class);
    $stok->catatMasuk($this->smg, $this->jenis, 90, false, 'Isi Semarang', $this->admin);
    $stok->catatMasuk($this->btl, $this->jenis, 60, false, 'Isi Boyolali', $this->admin);
    $this->dokumen = $stok->buatTransfer($this->btl, $this->smg, $this->jenis, 20, false, null, $this->admin);
});

/**
 * Seluruh gudang_id yang muncul di props halaman Stok Mushaf.
 *
 * @param  array<string, mixed>  $props
 * @return array<string, array<int, int>>
 */
function isolasiGudangDiHalamanStok(array $props): array
{
    $namaKeId = Gudang::pluck('id', 'nama');

    return [
        'stokKartu' => collect($props['stokKartu'])->pluck('gudang_id')->unique()->values()->all(),
        'stokList' => collect($props['stokList'])->pluck('gudang_id')->unique()->values()->all(),
        'pergerakanList' => collect($props['pergerakanList'])->map(fn ($b) => $namaKeId[$b['gudang']])->unique()->values()->all(),
    ];
}

it('freelancer hanya melihat stok gudangnya sendiri di kartu, daftar, dan pergerakan', function (string $akun, string $gudangSendiri) {
    $props = $this->actingAs($this->{$akun})->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    $idSendiri = $this->{$gudangSendiri}->id;

    expect(isolasiGudangDiHalamanStok($props))->toBe([
        'stokKartu' => [$idSendiri],
        'stokList' => [$idSendiri],
        'pergerakanList' => [$idSendiri],
    ])->and($props['tercakupGudang'])->toBeTrue();
})->with([
    'freelancer Semarang' => ['freelancerSmg', 'smg'],
    'freelancer Boyolali' => ['freelancerBtl', 'btl'],
]);

it('freelancer tetap melihat angka stok miliknya secara utuh', function () {
    $props = $this->actingAs($this->freelancerSmg)->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['stokList'])->toHaveCount(1)
        ->and($props['stokList'][0])->toMatchArray(['gudang_id' => $this->smg->id, 'eks' => 90, 'dus' => 4, 'sisaEks' => 10])
        ->and($props['stokKartu'][0]['baris'][0])->toMatchArray(['eks' => 90, 'dus' => 4, 'sisa_eks' => 10]);
});

it('parameter URL tidak bisa membuka stok gudang lain', function () {
    $props = $this->actingAs($this->freelancerSmg)
        ->get('/admin/warehouse/stok?gudang_id='.$this->btl->id)
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['stokList'])->pluck('gudang_id')->all())->not->toContain($this->btl->id)
        ->and(collect($props['stokKartu'])->pluck('gudang_id')->all())->not->toContain($this->btl->id);
});

it('menolak freelancer yang menyentuh stok atau dokumen gudang lain lewat URL langsung', function () {
    $this->actingAs($this->freelancerSmg)
        ->postJson('/admin/warehouse/stok/entri', [
            'gudang_id' => $this->btl->id,
            'jenis_quran_id' => $this->jenis->id,
            'terjemahan' => 0,
            'tipe' => 'keluar',
            'jumlah_eks' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($this->freelancerSmg)
        ->postJson("/admin/warehouse/stok/transfer/{$this->dokumen->id}/terima")
        ->assertForbidden();

    $this->actingAs($this->freelancerSmg)
        ->postJson("/admin/warehouse/stok/transfer/{$this->dokumen->id}/batalkan")
        ->assertForbidden();
});

it('tab stok di halaman Perpindahan Mushaf juga tercakup gudang freelancer', function () {
    $props = $this->actingAs($this->freelancerBtl)->get('/admin/transfer-mushaf')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['stokList'])->pluck('gudang_id')->unique()->values()->all())->toBe([$this->btl->id])
        ->and($props['tercakupGudang'])->toBeTrue()
        ->and($props['peranPjGudang'])->toBeFalse();
});

it('freelancer tanpa gudang tidak melihat stok apa pun (gagal-tertutup)', function () {
    $tanpaGudang = ($this->buatAkun)([RoleEnum::WAREHOUSE->value]);

    TransferMushaf::create([
        'kode' => 'TM-ISO-1', 'gudang_asal_id' => $this->smg->id, 'gudang_tujuan_id' => $this->btl->id,
        'status' => 'diproses', 'jumlah_resi' => 1, 'jumlah_eks' => 1, 'created_by' => $this->admin->id,
    ]);

    $stok = $this->actingAs($tanpaGudang)->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    $perpindahan = $this->actingAs($tanpaGudang)->get('/admin/transfer-mushaf')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($stok['stokKartu'])->toBeEmpty()
        ->and($stok['stokList'])->toBeEmpty()
        ->and($stok['pergerakanList'])->toBeEmpty()
        ->and($perpindahan['stokList'])->toBeEmpty()
        ->and($perpindahan['transferStokList'])->toBeEmpty()
        ->and($perpindahan['transferList'])->toBeEmpty();
});

it('super-admin dan PJ murni tetap melihat stok semua gudang', function (array $peran) {
    $akun = ($this->buatAkun)($peran);

    $props = $this->actingAs($akun)->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    $semua = [$this->smg->id, $this->btl->id];

    expect(isolasiGudangDiHalamanStok($props))->toBe([
        'stokKartu' => $semua,
        'stokList' => $semua,
        'pergerakanList' => [$this->btl->id, $this->smg->id],
    ])->and($props['tercakupGudang'])->toBeFalse();
})->with([
    'super-admin' => [[RoleEnum::SUPER_ADMIN->value]],
    'PJ gudang@ (manager-repacking + warehouse)' => [[RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value]],
]);

it('SPV gudang tetap tercakup gudangnya sendiri seperti sebelumnya', function () {
    $spvSmg = ($this->buatAkun)([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);

    $props = $this->actingAs($spvSmg)->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(isolasiGudangDiHalamanStok($props))->toBe([
        'stokKartu' => [$this->smg->id],
        'stokList' => [$this->smg->id],
        'pergerakanList' => [$this->smg->id],
    ])->and($props['bolehKelola'])->toBeTrue();
});
