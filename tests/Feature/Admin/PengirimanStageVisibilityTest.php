<?php

use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\PengirimanStageVisibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Batas visibilitas tahap pengiriman untuk role manager.
 *
 * Role manager (Nalurita Firdausyah) hanya menangani pengiriman yang sudah
 * selesai dikerjakan gudang: Selesai Packing, Proses Pengiriman, Diterima
 * Penerima. Tahap awal (pemesanan, produksi, kedatangan, packing) tidak boleh
 * terlihat — termasuk lewat URL langsung, pencarian, ekspor, dan endpoint JSON.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Izin diambil dari seeder sungguhan supaya pembatasan tahap diuji di atas
    // wewenang manager yang sebenarnya (bukan role kosong yang kebetulan 403).
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Slug status berasal dari migrasi, tetapi seeder dapat menimpanya; pastikan
    // enam tahap yang relevan ada sebelum tiap test.
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
});

function stageManagerUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::MANAGER->value);

    return $user;
}

function stageSuperAdminUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user;
}

/**
 * Buat pengiriman pada status (slug) tertentu, tanpa memedulikan kolom wajib
 * yang tidak relevan bagi pengujian visibilitas.
 */
function pengirimanDenganStatus(string $slug): Pengiriman
{
    return Pengiriman::factory()->create([
        'no_resi' => 'EQ-2026-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
    ]);
}

it('membatasi hanya tiga tahap yang diminta untuk manager', function () {
    expect(PengirimanStageVisibility::allowedSlugs())
        ->toBe(['selesai-packing', 'pengiriman', 'diterima']);
});

it('hanya memberlakukan batas pada role manager', function () {
    expect(PengirimanStageVisibility::appliesTo(stageManagerUser()))->toBeTrue()
        ->and(PengirimanStageVisibility::appliesTo(stageSuperAdminUser()))->toBeFalse()
        ->and(PengirimanStageVisibility::appliesTo(null))->toBeFalse();
});

it('menyaring daftar pengiriman: tahap awal tidak ikut terkirim', function () {
    $dilihat = [
        pengirimanDenganStatus('selesai-packing'),
        pengirimanDenganStatus('pengiriman'),
        pengirimanDenganStatus('diterima'),
    ];
    $disembunyikan = [
        pengirimanDenganStatus('pemesanan'),
        pengirimanDenganStatus('produksi'),
        pengirimanDenganStatus('kedatangan'),
        pengirimanDenganStatus('packing'),
    ];

    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $ids = collect($props['pengiriman']['data'])->pluck('id')->all();

    expect($ids)->toHaveCount(3)
        ->and($ids)->toEqualCanonicalizing(collect($dilihat)->pluck('id')->all());

    foreach ($disembunyikan as $p) {
        expect($ids)->not->toContain($p->id);
    }
});

it('tidak bisa dibocorkan lewat parameter status di URL', function () {
    $tersembunyi = pengirimanDenganStatus('pemesanan');

    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index', ['status' => $tersembunyi->status_id]))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['pengiriman']['data'])->pluck('id')->all())->toBeEmpty();
});

it('tidak bisa dibocorkan lewat pencarian nomor resi', function () {
    $tersembunyi = pengirimanDenganStatus('packing');

    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index', ['search' => $tersembunyi->no_resi]))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['pengiriman']['data'])->pluck('id')->all())->toBeEmpty();
});

it('menyembunyikan angka statistik tahap awal dari manager', function () {
    pengirimanDenganStatus('pemesanan');
    pengirimanDenganStatus('packing');
    pengirimanDenganStatus('selesai-packing');
    pengirimanDenganStatus('pengiriman');

    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['stats']['pemesanan'])->toBe(0)
        ->and($props['stats']['packing'])->toBe(0)
        ->and($props['stats']['selesai_packing'])->toBe(1)
        ->and($props['stats']['pengiriman'])->toBe(1);
});

it('hanya menawarkan tiga status pada dropdown filter manager', function () {
    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['statusList'])->pluck('slug')->all())
        ->toEqualCanonicalizing(['selesai-packing', 'pengiriman', 'diterima']);
});

it('menolak manager membuka detail pengiriman tahap awal lewat URL langsung', function () {
    $tersembunyi = pengirimanDenganStatus('pemesanan');
    $boleh = pengirimanDenganStatus('pengiriman');

    $this->actingAs(stageManagerUser())->get(route('admin.pengiriman.show', $tersembunyi))->assertForbidden();
    $this->actingAs(stageManagerUser())->get(route('admin.pengiriman.show', $boleh))->assertSuccessful();
});

it('menolak manager membuka form edit pengiriman tahap awal', function () {
    $tersembunyi = pengirimanDenganStatus('packing');
    $boleh = pengirimanDenganStatus('diterima');

    $this->actingAs(stageManagerUser())->get(route('admin.pengiriman.edit', $tersembunyi))->assertForbidden();
    $this->actingAs(stageManagerUser())->get(route('admin.pengiriman.edit', $boleh))->assertSuccessful();
});

it('tidak mengubah pengiriman tahap awal walau ID-nya dikirim langsung', function () {
    $tersembunyi = pengirimanDenganStatus('pemesanan');
    $asli = $tersembunyi->alamat_tujuan;

    // Server boleh menolak (redirect dengan pesan) atau memproses tanpa efek —
    // yang penting datanya tidak berubah.
    $this->actingAs(stageManagerUser())
        ->post(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => [$tersembunyi->id],
            'alamat_tujuan' => 'Alamat disusupkan',
        ]);

    expect($tersembunyi->fresh()->alamat_tujuan)->toBe($asli);
});

it('tetap mengizinkan manager mengubah pengiriman pada tahap yang boleh', function () {
    $boleh = pengirimanDenganStatus('pengiriman');

    $this->actingAs(stageManagerUser())
        ->post(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => [$boleh->id],
            'alamat_tujuan' => 'Alamat baru yang sah',
        ])
        ->assertRedirect();

    expect($boleh->fresh()->alamat_tujuan)->toBe('Alamat baru yang sah');
});

it('tidak membocorkan data tahap awal lewat endpoint JSON per-resi', function () {
    $tersembunyi = pengirimanDenganStatus('pemesanan');
    $boleh = pengirimanDenganStatus('pengiriman');

    $this->actingAs(stageManagerUser())
        ->getJson('/admin/pengiriman-status/'.$tersembunyi->no_resi)
        ->assertNotFound();

    $this->actingAs(stageManagerUser())
        ->getJson('/admin/pengiriman-status/'.$boleh->no_resi)
        ->assertSuccessful();
});

it('tidak membatasi super-admin sama sekali', function () {
    pengirimanDenganStatus('pemesanan');
    pengirimanDenganStatus('packing');
    pengirimanDenganStatus('selesai-packing');

    $props = $this->actingAs(stageSuperAdminUser())
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['pengiriman']['data']))->toHaveCount(3)
        ->and($props['stageVisibility']['restricted'])->toBeFalse();
});

it('menandai konteks pembatasan untuk frontend', function () {
    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['stageVisibility']['restricted'])->toBeTrue()
        ->and($props['stageVisibility']['slugs'])->toBe(['selesai-packing', 'pengiriman', 'diterima']);
});

it('menutup rapat bila daftar tahap dikosongkan', function () {
    config(['pengiriman.manager_visible_stages' => []]);

    pengirimanDenganStatus('pengiriman');
    pengirimanDenganStatus('diterima');

    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.pengiriman.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    // Gagal-tertutup: salah konfigurasi tidak boleh malah membuka semua data.
    expect(collect($props['pengiriman']['data'])->pluck('id')->all())->toBeEmpty()
        ->and($props['stageVisibility']['restricted'])->toBeTrue();
});

it('mengabaikan batas bila slug tahap dihapus dari konfigurasi', function () {
    config(['pengiriman.manager_visible_stages' => []]);
    $kosong = PengirimanStageVisibility::allowedSlugs();
    config(['pengiriman.manager_visible_stages' => null]);

    expect($kosong)->toBe([])
        ->and(PengirimanStageVisibility::allowedSlugs())->toBe([]);
});

it('menjaga integritas: hanya status yang diminta yang tersaring dari database', function () {
    // Penjaga terhadap salah tulis slug (mis. 'selesai_packing' memakai garis
    // bawah) yang akan membuat daftar kosong tanpa terlihat.
    $slugs = DB::table('status_pengiriman')->pluck('slug')->all();

    foreach (PengirimanStageVisibility::allowedSlugs() as $slug) {
        expect($slugs)->toContain($slug);
    }
});

it('tidak membocorkan pengiriman tahap awal lewat aktivitas dashboard', function () {
    // Dashboard menampilkan "pengiriman terbaru" — tanpa filter, manager yang
    // hanya boleh melihat tiga tahap akhir malah melihat tiga pengiriman baru
    // yang semuanya masih tahap awal.
    $tersembunyi = pengirimanDenganStatus('pemesanan');
    $boleh = pengirimanDenganStatus('pengiriman');

    $props = $this->actingAs(stageManagerUser())
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $teks = json_encode($props['activities'] ?? $props['basicActivities'] ?? []);

    expect($teks)->not->toContain($tersembunyi->no_resi);
});
