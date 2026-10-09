<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\PengirimanStageVisibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Tahap awal distribusi (pemesanan, produksi, kedatangan, packing) adalah ranah
 * staff gudang.
 *
 * Yang diuji di sini adalah BATASNYA, bukan hanya "izinnya ada". Menambah izin
 * `shipments.update-status` ke gudang membuka seluruh jalur ubah status — dan
 * sebagian jalur itu tidak punya pemeriksaan status tujuan sama sekali. Kalau
 * batas tahapnya tidak ditegakkan di semua jalur, gudang justru bisa
 * memindahkan resi ke "pengiriman" atau "diterima", yang bukan wewenangnya.
 *
 * Catatan penting soal bentuk uji: `PengingirimanTrackingController` menolak
 * lompatan (status hanya boleh maju SATU langkah). Karena itu uji "penolakan"
 * harus memakai tujuan yang HANYA ditolak oleh batas tahap — kalau tidak, uji
 * itu lulus karena aturan urutan, bukan karena batas wewenang, dan batasnya
 * bisa dihapus tanpa ada uji yang gagal.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['pemesanan', 'produksi', 'kedatangan', 'packing', 'selesai-packing', 'pengiriman'] as $i => $slug) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $i + 1, 'is_active' => true, 'is_final' => false]
        );
    }
    StatusPengiriman::firstOrCreate(
        ['slug' => 'diterima'],
        ['nama' => 'Diterima Penerima', 'urutan' => 7, 'is_active' => true, 'is_final' => true]
    );
    StatusPengiriman::firstOrCreate(
        ['slug' => 'batal'],
        ['nama' => 'Batal', 'urutan' => 99, 'is_active' => true, 'is_final' => true]
    );
});

function gudangUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::WAREHOUSE->value);

    return $user->fresh();
}

function resiStatus(string $slug): Pengiriman
{
    return Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
    ]);
}

function statusId(string $slug): int
{
    return (int) StatusPengiriman::where('slug', $slug)->value('id');
}

it('memberi izin ubah status dan lacak resi kepada gudang', function () {
    $gudang = gudangUser();

    expect($gudang->hasPermissionTo(PermissionEnum::SHIPMENTS_UPDATE_STATUS->value))->toBeTrue()
        ->and($gudang->hasPermissionTo(PermissionEnum::SHIPMENTS_TRACK->value))->toBeTrue();
});

it('membatasi pilihan status gudang ke tahap awal saja', function () {
    $gudang = gudangUser();

    foreach (['pemesanan', 'produksi', 'kedatangan', 'packing'] as $slug) {
        expect(PengirimanStageVisibility::bolehPilihStatus($gudang, StatusPengiriman::where('slug', $slug)->first()))
            ->toBeTrue("gudang seharusnya boleh memilih {$slug}");
    }

    foreach (['selesai-packing', 'pengiriman', 'diterima', 'batal'] as $slug) {
        expect(PengirimanStageVisibility::bolehPilihStatus($gudang, StatusPengiriman::where('slug', $slug)->first()))
            ->toBeFalse("gudang seharusnya TIDAK boleh memilih {$slug}");
    }
});

it('hanya mengizinkan gudang menyentuh resi yang masih di tahap awal', function () {
    // Batas per-baris: tahap tujuan boleh sah, tetapi resinya sudah bukan
    // tanggung jawab gudang lagi.
    $gudang = gudangUser();
    $produksi = StatusPengiriman::where('slug', 'produksi')->first();

    expect(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('pemesanan'), $produksi))->toBeTrue()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('kedatangan'), $produksi))->toBeTrue()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('diterima'), $produksi))->toBeFalse()
        ->and(PengirimanStageVisibility::bolehPindahkan($gudang, resiStatus('pengiriman'), $produksi))->toBeFalse();
});

it('menawarkan hanya empat tahap awal pada dropdown pemindahan gudang', function () {
    $gudang = gudangUser();

    expect(collect(PengirimanStageVisibility::visibleProgressStatuses($gudang))->pluck('slug')->sort()->values()->all())
        ->toBe(['kedatangan', 'packing', 'pemesanan', 'produksi']);
});

it('gudang tetap melihat semua status pada filter daftar pengiriman', function () {
    // Filter bukan pemindahan: gudang perlu melihat resi lintas status supaya
    // pekerjaannya terlihat. Yang dibatasi adalah pilihannya, bukan filternya.
    $gudang = gudangUser();

    expect(collect(PengirimanStageVisibility::visibleStatuses($gudang))->pluck('slug'))
        ->toContain('pengiriman')
        ->toContain('diterima')
        ->toContain('produksi');
});

it('mengizinkan gudang memajukan pemesanan ke produksi lewat ubah status', function () {
    $resi = resiStatus('pemesanan');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('produksi'),
            'catatan' => 'Mushaf masuk proses produksi',
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('produksi'));
});

it('mengizinkan gudang melangkah dari produksi ke kedatangan', function () {
    $resi = resiStatus('produksi');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('kedatangan'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('kedatangan'));
});

it('menolak gudang memindahkan resi ke pengiriman', function () {
    // Resi di "kedatangan" (urutan 3) → "pengiriman" (urutan 6) ditolak DUA
    // aturan sekaligus (lompatan urutan dan batas tahap), jadi bentuk lain
    // dipakai untuk membuktikan batas tahapnya sendiri ada di bawah.
    $resi = resiStatus('kedatangan');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('pengiriman'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('kedatangan'));
});

it('menolak gudang menandai resi diterima penerima', function () {
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('diterima'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));
});

it('menolak gudang memindahkan resi packing ke selesai-packing', function () {
    // Uji penentu: "packing" (4) → "selesai-packing" (5) SAH menurut aturan
    // urutan, jadi satu-satunya yang menolaknya adalah batas tahap gudang.
    // Kalau batas itu dihapus, uji ini gagal — inilah yang "menggigit".
    $resi = resiStatus('packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('selesai-packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('menolak gudang membatalkan pengiriman', function () {
    $resi = resiStatus('kedatangan');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('batal'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('kedatangan'));
});

it('menolak gudang menarik kembali resi yang sudah diterima', function () {
    // Resi final: ditolak oleh aturan final DAN batas per-baris. Yang penting
    // datanya tidak berubah.
    $resi = resiStatus('diterima');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('diterima'));
});

it('menutup lubang di endpoint batch status', function () {
    // "selesai-packing" (5) → "pengiriman" (6) sah menurut urutan; ditolak
    // hanya karena bukan tahap gudang.
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->post(route('admin.pengiriman.batch-status'), [
            'pengiriman_ids' => [$resi->id],
            'status_id' => statusId('pengiriman'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));
});

it('menutup lubang di endpoint bulk status', function () {
    // Endpoint bulk TIDAK memakai batas DATA (applyToQuery) dan sebelumnya juga
    // tanpa batas pilihan sama sekali. Yang bisa membuktikannya hanya role yang
    // benar-benar memegang `shipments.bulk-update` SEKALIGUS punya batas
    // pilihan — yaitu manager. Memakai gudang/kurir di sini tidak menguji apa
    // pun: keduanya tidak punya izin bulk, jadi ditolak middleware lebih dulu
    // dan uji akan tetap lulus walau penjaganya dihapus.
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);
    $manager = $manager->fresh();

    expect($manager->hasPermissionTo(PermissionEnum::SHIPMENTS_BULK_UPDATE->value))->toBeTrue()
        ->and(PengirimanStageVisibility::bolehPilihStatus(
            $manager,
            StatusPengiriman::where('slug', 'produksi')->first()
        ))->toBeFalse();

    $resi = resiStatus('produksi');

    $this->actingAs($manager)
        ->post(route('admin.pengiriman.bulk-status'), [
            'pengiriman_ids' => [$resi->id],
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('produksi'));
});

it('menutup lubang di form edit pengiriman', function () {
    // Inilah jalur yang paling mudah luput: form edit hanya diperiksa dengan
    // izin `shipments.update`, yang juga dipegang gudang dan supervisor.
    $resi = resiStatus('selesai-packing');

    $this->actingAs(gudangUser())
        ->put(route('admin.pengiriman.update', $resi), [
            'status_id' => statusId('pengiriman'),
            'alamat_tujuan' => $resi->alamat_tujuan,
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('selesai-packing'));
});

it('menyaring pilihan status di form edit dengan predikat yang sama', function () {
    $resi = resiStatus('pemesanan');

    $props = $this->actingAs(gudangUser())
        ->get(route('admin.pengiriman.edit', $resi))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['statusList'])->pluck('slug')->sort()->values()->all())
        ->toBe(['kedatangan', 'packing', 'pemesanan', 'produksi']);
});

it('gudang masih boleh menyunting alamat tanpa mengubah status', function () {
    // Menyunting alamat tidak boleh ikut tertutup oleh batas pemindahan status.
    $resi = resiStatus('pemesanan');

    $this->actingAs(gudangUser())
        ->put(route('admin.pengiriman.update', $resi), [
            'status_id' => statusId('pemesanan'),
            'alamat_tujuan' => 'Alamat baru dari gudang',
        ]);

    expect($resi->fresh()->alamat_tujuan)->toBe('Alamat baru dari gudang')
        ->and($resi->fresh()->status_id)->toBe(statusId('pemesanan'));
});

it('tidak mengubah batas kurir maupun manager', function () {
    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);

    expect(collect(PengirimanStageVisibility::visibleProgressStatuses($kurir))->pluck('slug')->sort()->values()->all())
        ->toBe(['batal', 'pengiriman'])
        ->and(collect(PengirimanStageVisibility::visibleProgressStatuses($manager))->pluck('slug')->all())
        ->toEqualCanonicalizing(['selesai-packing', 'pengiriman', 'diterima', 'batal']);
});

it('menolak kurir memakai izinnya untuk tahap gudang', function () {
    // Kurir punya shipments.update-status, tetapi batas tahapnya tetap berlaku.
    // "pemesanan" (1) → "produksi" (2) sah menurut urutan, ditolak hanya karena
    // bukan tahap perjalanan.
    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);

    $resi = resiStatus('pemesanan');

    $this->actingAs($kurir)
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('produksi'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('pemesanan'));
});

it('memberi gudang izin lacak supaya halaman scan bisa mencari resi', function () {
    $resi = resiStatus('packing');

    $this->actingAs(gudangUser())
        ->getJson('/admin/pengiriman-status/'.$resi->no_resi)
        ->assertSuccessful();
});

it('tidak membatasi super-admin', function () {
    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $resi = resiStatus('kedatangan');

    $this->actingAs($superAdmin)
        ->post(route('admin.pengiriman.update-status', $resi), [
            'status_id' => statusId('packing'),
        ]);

    expect($resi->fresh()->status_id)->toBe(statusId('packing'));
});

it('menutup rapat bila daftar tahap gudang dikosongkan', function () {
    config(['pengiriman.warehouse_stage_slugs' => []]);

    $gudang = gudangUser();

    expect(PengirimanStageVisibility::bolehPilihStatus($gudang, StatusPengiriman::where('slug', 'produksi')->first()))
        ->toBeFalse()
        ->and(collect(PengirimanStageVisibility::visibleProgressStatuses($gudang)))->toBeEmpty();
});

it('menjaga integritas: slug tahap gudang harus ada di basis data', function () {
    // Penjaga terhadap salah tulis slug yang membuat daftar kosong tanpa terlihat.
    $slugs = StatusPengiriman::pluck('slug')->all();

    foreach (PengirimanStageVisibility::allowedGudangSlugs() as $slug) {
        expect($slugs)->toContain($slug);
    }
});
