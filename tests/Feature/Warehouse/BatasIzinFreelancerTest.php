<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\MushafRequest;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Batas izin freelancer (review role 21/11/2026). Freelancer boleh mengedit alamat resi
 * gudangnya, tetapi tidak boleh: melihat data Permintaan Mushaf lewat jalur samping,
 * memindah status kerdus keluar tahap gudang, memakai rute box-bulk, atau membuka sertifikat.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->jenisA5 = (JenisQuran::where('kode_jenis', 'A5')->first()
        ?? JenisQuran::factory()->a5()->create())->id;

    $this->akun = function (string $peran, ?Gudang $gudang): User {
        $akun = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $akun->syncRoles([$peran]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $akun->fresh();
    };

    $this->freelancer = ($this->akun)(RoleEnum::WAREHOUSE->value, $this->smg);
    $this->spv = ($this->akun)(RoleEnum::SPV_GUDANG->value, $this->smg);

    $this->statusId = fn (string $slug): int => StatusPengiriman::where('slug', $slug)->value('id');

    $this->resiSmg = Pengiriman::factory()->create(['status_id' => ($this->statusId)('packing'), 'gudang_id' => $this->smg->id]);
    $this->resiBtl = Pengiriman::factory()->create(['status_id' => ($this->statusId)('packing'), 'gudang_id' => $this->btl->id]);

    $tugas = DailyPackingTask::factory()->create([
        'user_id' => $this->freelancer->id,
        'gudang_id' => $this->smg->id,
        'tanggal_tugas' => today(),
    ]);
    $this->kerdus = PackingBox::factory()->filling()->create([
        'daily_packing_task_id' => $tugas->id,
        'gudang_id' => $this->smg->id,
        'jenis_quran_id' => $this->jenisA5,
    ]);
    PackingItem::factory()->create([
        'packing_box_id' => $this->kerdus->id,
        'pengiriman_id' => $this->resiSmg->id,
    ]);

    $this->permintaan = MushafRequest::factory()->create(['status' => 'approved', 'pengiriman_id' => null]);
});

it('freelancer tetap bisa membuka edit resi gudangnya tanpa daftar Permintaan Mushaf', function () {
    $props = $this->actingAs($this->freelancer)
        ->get(route('admin.pengiriman.edit', $this->resiSmg))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['approvedMushafRequests'])->toBe([]);
});

it('pemegang izin baca permintaan tetap mendapat daftar Permintaan Mushaf di edit resi', function () {
    $admin = ($this->akun)(RoleEnum::SUPER_ADMIN->value, null);

    $props = $this->actingAs($admin)
        ->get(route('admin.pengiriman.edit', $this->resiSmg))
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['approvedMushafRequests'])->pluck('id')->all())->toContain($this->permintaan->id);
});

it('freelancer mengubah alamat resi gudangnya', function () {
    $this->actingAs($this->freelancer)
        ->put(route('admin.pengiriman.update', $this->resiSmg), [
            'status_id' => $this->resiSmg->status_id,
            'alamat_tujuan' => 'Jl. Pemuda 12, Semarang',
        ])
        ->assertRedirect();

    expect($this->resiSmg->fresh()->alamat_tujuan)->toBe('Jl. Pemuda 12, Semarang');
});

it('freelancer tidak bisa membuka atau mengubah resi gudang lain', function () {
    $this->actingAs($this->freelancer)->get(route('admin.pengiriman.show', $this->resiBtl))->assertForbidden();
    $this->actingAs($this->freelancer)->get(route('admin.pengiriman.edit', $this->resiBtl))->assertForbidden();
    $this->actingAs($this->freelancer)
        ->put(route('admin.pengiriman.update', $this->resiBtl), [
            'status_id' => $this->resiBtl->status_id,
            'alamat_tujuan' => 'Alamat palsu',
        ])
        ->assertForbidden();

    expect($this->resiBtl->fresh()->alamat_tujuan)->not->toBe('Alamat palsu');
});

it('freelancer tidak bisa memakai rute box-bulk', function (string $jalur) {
    $this->actingAs($this->freelancer)
        ->postJson($jalur, [
            'qr_data' => $this->kerdus->kode_kerdus,
            'new_status_id' => ($this->statusId)('diterima'),
            'new_address' => 'Alamat palsu',
        ])
        ->assertForbidden();

    expect($this->resiSmg->fresh()->status_id)->toBe(($this->statusId)('packing'));
})->with([
    '/admin/box-bulk/preview',
    '/admin/box-bulk/update-status',
    '/admin/box-bulk/update-address',
    '/admin/box-bulk/update-both',
]);

it('Pemindai Kerdus menolak status di luar tahap gudang untuk freelancer', function (string $slug) {
    Queue::fake();

    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/box-scanner/update-status', [
            'box_id' => $this->kerdus->id,
            'new_status_id' => ($this->statusId)($slug),
        ])
        ->assertForbidden();

    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/box-scanner/bulk-update-status', [
            'box_ids' => [$this->kerdus->id],
            'new_status_id' => ($this->statusId)($slug),
        ])
        ->assertForbidden();

    expect($this->resiSmg->fresh()->status_id)->toBe(($this->statusId)('packing'));
    Queue::assertNothingPushed();
})->with(['pengiriman', 'diterima', 'batal']);

it('Pemindai Kerdus hanya menawarkan tahap gudang kepada freelancer', function () {
    $slug = collect($this->actingAs($this->freelancer)
        ->get('/admin/warehouse/box-scanner')
        ->assertOk()
        ->viewData('page')['props']['statusList'])->pluck('slug');

    expect($slug)->not->toContain('pengiriman')
        ->not->toContain('diterima')
        ->not->toContain('batal')
        ->toContain('selesai-packing');
});

it('scan Pemindai Kerdus tidak mengirim Permintaan Mushaf kepada freelancer', function () {
    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/box-scanner/scan', ['qr_code' => $this->kerdus->kode_kerdus])
        ->assertSuccessful()
        ->assertJsonPath('data.summary.mushaf_requests', [])
        ->assertJsonPath('data.summary.has_mushaf_request', false);
});

it('freelancer tidak bisa menautkan Permintaan Mushaf ke kerdus', function () {
    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/box-scanner/set-mushaf-address', [
            'box_id' => $this->kerdus->id,
            'mushaf_request_id' => $this->permintaan->id,
        ])
        ->assertForbidden();

    expect($this->permintaan->fresh()->status)->toBe('approved')
        ->and($this->resiSmg->fresh()->mushaf_request_id)->toBeNull();
});

it('freelancer tidak bisa membuka halaman sertifikat', function () {
    $this->actingAs($this->freelancer)->get(route('admin.certificates.index'))->assertForbidden();
});

it('migrasi mencabut izin sertifikat dari freelancer yang sudah ada', function () {
    $izin = [
        PermissionEnum::CERTIFICATES_READ->value,
        PermissionEnum::CERTIFICATES_GENERATE->value,
        PermissionEnum::CERTIFICATES_DOWNLOAD->value,
    ];
    $peran = Role::findByName(RoleEnum::WAREHOUSE->value);
    $peran->givePermissionTo($izin);

    $migrasi = require database_path('migrations/2026_11_21_225715_cabut_izin_sertifikat_dari_freelancer.php');
    $migrasi->up();

    expect($peran->fresh()->permissions->pluck('name')->intersect($izin))->toBeEmpty()
        ->and($peran->fresh()->hasPermissionTo(PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value))->toBeTrue();
});
