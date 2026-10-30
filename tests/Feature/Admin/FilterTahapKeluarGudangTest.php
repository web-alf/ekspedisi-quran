<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Tautan "Sudah terdistribusi" di seksi Siap Dikemas (30/10/2026) membuka Data Resi dengan
 * `tahap=keluar-gudang`: resi Dalam Pengiriman, Tiba, dan Diterima sekaligus. Filter status
 * biasa hanya menerima satu status, jadi tahap ini butuh filternya sendiri.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    StatusPengirimanCache::clear();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');

    $this->resi = fn (string $slug, ?Gudang $gudang = null): Pengiriman => Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        'gudang_id' => ($gudang ?? $this->smg)->id,
    ]);

    $this->akun = function (array $peran, ?Gudang $gudang = null): User {
        $akun = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $akun->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $akun;
    };
});

function noResiDataResi(User $akun, string $query = ''): array
{
    $halaman = test()->actingAs($akun)->get('/admin/pengiriman'.$query)->assertOk()->viewData('page');

    return collect($halaman['props']['pengiriman']['data'])->pluck('no_resi')->sort()->values()->all();
}

it('hanya menampilkan resi Dalam Pengiriman, Tiba, dan Diterima', function () {
    $keluar = collect(['pengiriman', 'tiba', 'diterima'])->map(fn ($slug) => ($this->resi)($slug)->no_resi);
    foreach (['pemesanan', 'packing', 'selesai-packing', 'batal'] as $slug) {
        ($this->resi)($slug);
    }

    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->smg);

    expect(noResiDataResi($freelancer, '?tahap=keluar-gudang'))->toBe($keluar->sort()->values()->all())
        ->and(noResiDataResi($freelancer))->toHaveCount(7);
});

it('mengirim filter tahap ke halaman supaya chip filternya tampil', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->smg);

    $this->actingAs($freelancer)
        ->get('/admin/pengiriman?tahap=keluar-gudang')
        ->assertOk()
        ->assertInertia(fn ($halaman) => $halaman->where('filters.tahap', 'keluar-gudang'));
});

it('tetap tunduk pada cakupan gudang SPV', function () {
    $milikSendiri = ($this->resi)('diterima', $this->btl);
    ($this->resi)('diterima', $this->smg);

    $spvBoyolali = ($this->akun)([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->btl);

    expect(noResiDataResi($spvBoyolali, '?tahap=keluar-gudang'))->toBe([$milikSendiri->no_resi]);
});

it('mengabaikan nilai tahap yang tidak dikenal', function () {
    ($this->resi)('packing');
    ($this->resi)('diterima');

    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->smg);

    expect(noResiDataResi($freelancer, '?tahap=sembarang'))->toHaveCount(2);
});

it('tautan Sudah dikemas bisa dibuka freelancer, SPV, dan Manager Gudang', function (array $peran, bool $berGudang) {
    $akun = ($this->akun)($peran, $berGudang ? $this->smg : null);

    $this->actingAs($akun)
        ->get('/admin/box-tracking?selesai_packing=1')
        ->assertOk()
        ->assertInertia(fn ($halaman) => $halaman->where('filters.selesai_packing', '1'));
})->with([
    'freelancer' => [[RoleEnum::WAREHOUSE->value], true],
    'SPV' => [[RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], true],
    'Manager Gudang' => [[RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], false],
]);
