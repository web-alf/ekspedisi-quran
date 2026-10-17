<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Daftar "Kerdus Siap Dipindai" di panel Pindai Kerdus (/admin/muatan).
 *
 * Kerdus penuh/tersegel yang belum dimuat ke kurir MUNCUL di halaman sisi distribusi,
 * dengan baris berbentuk sama seperti Tracking Kerdus gudang (tanpa status verifikasi).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    foreach (['packing' => 4, 'selesai-packing' => 5, 'pengiriman' => 6] as $slug => $urutan) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $urutan, 'is_active' => true, 'is_final' => false]
        );
    }

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->petugas = User::factory()->create(['is_active' => true]);
    $this->petugas->assignRole(RoleEnum::DISTRIBUSI->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** Kerdus berstatus tertentu berisi `$eks` resi selesai-packing (1 eks per resi). */
function kerdusDaftar(string $kode, string $status = PackingBox::STATUS_SEALED, int $eks = 3, ?int $gudangId = null, $sealedAt = null): PackingBox
{
    $box = PackingBox::factory()->create([
        'kode_kerdus' => $kode,
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
        'kapasitas' => $eks,
        'jumlah_terisi' => $eks,
        'status' => $status,
        'sealed_at' => $status === PackingBox::STATUS_SEALED ? ($sealedAt ?? now()) : null,
        'gudang_id' => $gudangId,
    ]);

    $statusId = StatusPengiriman::where('slug', 'selesai-packing')->value('id');

    for ($i = 1; $i <= $eks; $i++) {
        PackingItem::factory()->create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => Pengiriman::factory()->create(['status_id' => $statusId, 'jumlah_quran' => 1])->id,
            'urutan_dalam_box' => $i,
            'quantity' => 1,
        ]);
    }

    return $box;
}

/** Props daftar kerdus siap sebagaimana dirender halaman Muatan untuk petugas distribusi. */
function propsKerdusSiap(array $query = []): array
{
    return test()->actingAs(test()->petugas)
        ->get('/admin/muatan?'.http_build_query($query))
        ->assertSuccessful()
        ->viewData('page')['props']['pindaiKerdus']['siap'];
}

function kodeDiDaftar(array $siap): array
{
    return collect($siap['daftar']['data'])->pluck('kode_kerdus')->all();
}

it('menampilkan kerdus penuh dan tersegel, bukan yang masih kosong atau sedang diisi', function () {
    kerdusDaftar('KB-20261016-001-A5-01', PackingBox::STATUS_SEALED);
    kerdusDaftar('KB-20261016-002-A5-01', PackingBox::STATUS_FULL);
    kerdusDaftar('KB-20261016-003-A5-01', PackingBox::STATUS_FILLING);
    kerdusDaftar('KB-20261016-004-A5-01', PackingBox::STATUS_EMPTY, 0);

    $kode = kodeDiDaftar(propsKerdusSiap());

    expect($kode)->toContain('KB-20261016-001-A5-01', 'KB-20261016-002-A5-01')
        ->not->toContain('KB-20261016-003-A5-01')
        ->not->toContain('KB-20261016-004-A5-01');
});

it('mengirim baris berbentuk sama dengan Tracking Kerdus gudang, tanpa status verifikasi', function () {
    kerdusDaftar('KB-20261016-010-A5-01', PackingBox::STATUS_SEALED, 17, $this->smg->id);

    $siap = propsKerdusSiap();
    $baris = $siap['daftar']['data'][0];

    expect($baris)->toHaveKeys([
        'kode_kerdus', 'status', 'status_info', 'jenis_quran', 'kapasitas',
        'item_count', 'satuan', 'satuan_kapasitas', 'qr_code_base64',
        'seal_code', 'sealed_at', 'created_at', 'gudang',
    ])
        // Kolom Progress & User sengaja dibuang dari daftar distribusi.
        ->not->toHaveKeys(['progress_percentage', 'user_name', 'terisi'])
        ->not->toHaveKeys(['verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada'])
        ->and($baris['dipindai_pada'])->toBeNull()
        ->and($baris['dipindai_oleh'])->toBeNull()
        ->and($baris['item_count'])->toBe(17)
        ->and($baris['status_info']['label'])->toBe('Tersegel')
        ->and($baris['gudang'])->toBe('Gudang Semarang')
        ->and($baris['qr_code_base64'])->toStartWith('data:image/png;base64,')
        ->and($siap)->not->toHaveKey('hitung');
});

it('tetap menampilkan kerdus yang sudah dipindai selama belum dimuat', function () {
    $box = kerdusDaftar('KB-20261016-020-A5-01');

    $this->actingAs($this->petugas)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus])
        ->assertSuccessful();

    $baris = collect(propsKerdusSiap()['daftar']['data'])->firstWhere('kode_kerdus', 'KB-20261016-020-A5-01');

    // Kolom jam, metode, dan petugas diadopsi dari tabel riwayat lama.
    expect($baris)->not->toBeNull()
        ->and($baris['dipindai_pada'])->toBe(now()->format('d/m/Y H:i'))
        ->and($baris['dipindai_metode'])->toBe('qr')
        ->and($baris['dipindai_oleh'])->toBe($this->petugas->name);
});

it('menampilkan mushaf di dalam kerdus beserta riwayat pindainya', function () {
    $box = kerdusDaftar('KB-20261016-070-A5-01', PackingBox::STATUS_SEALED, 3, $this->smg->id);
    $resi = $box->packingItems()->with('pengiriman')->get()->pluck('pengiriman.no_resi')->all();

    $this->actingAs($this->petugas)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'metode' => 'manual'])
        ->assertSuccessful();

    $detail = $this->actingAs($this->petugas)
        ->getJson(route('admin.pindai-kerdus.show', $box))
        ->assertSuccessful()
        ->assertJsonPath('kerdus.kode_kerdus', 'KB-20261016-070-A5-01')
        ->assertJsonPath('kerdus.jumlah_mushaf', 3)
        ->assertJsonPath('kerdus.gudang', 'Gudang Semarang')
        ->assertJsonCount(3, 'mushaf')
        ->assertJsonCount(1, 'riwayat_pindai')
        ->assertJsonPath('riwayat_pindai.0.metode', 'manual')
        ->assertJsonPath('riwayat_pindai.0.oleh', $this->petugas->name)
        ->json();

    expect(collect($detail['mushaf'])->pluck('no_resi')->all())->toEqualCanonicalizing($resi)
        ->and($detail['mushaf'][0])->toHaveKeys(['no_resi', 'jenis', 'wakif', 'donatur', 'status', 'dikemas_oleh', 'tracking_url'])
        ->and($detail['mushaf'][0]['status'])->toBe(StatusPengiriman::where('slug', 'selesai-packing')->value('nama'));
});

it('menolak detail kerdus untuk peran tanpa izin pindai kerdus', function () {
    $box = kerdusDaftar('KB-20261016-080-A5-01');

    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($kurir->fresh())
        ->getJson(route('admin.pindai-kerdus.show', $box))
        ->assertForbidden();
});

it('menyembunyikan kerdus yang seluruh isinya sudah dimuat ke kurir', function () {
    $sudahDimuat = kerdusDaftar('KB-20261016-040-A5-01', PackingBox::STATUS_SEALED, 2);
    $sebagian = kerdusDaftar('KB-20261016-041-A5-01', PackingBox::STATUS_SEALED, 2);

    $muatan = Muatan::factory()->create();
    $urutan = 1;

    foreach ($sudahDimuat->packingItems as $item) {
        MuatanItem::create(['muatan_id' => $muatan->id, 'pengiriman_id' => $item->pengiriman_id, 'urutan' => $urutan++]);
    }

    MuatanItem::create(['muatan_id' => $muatan->id, 'pengiriman_id' => $sebagian->packingItems->first()->pengiriman_id, 'urutan' => $urutan]);

    $kode = kodeDiDaftar(propsKerdusSiap());

    expect($kode)->toContain('KB-20261016-041-A5-01')
        ->not->toContain('KB-20261016-040-A5-01');
});

it('menyaring per gudang dan kode kerdus, serta mendahulukan yang paling lama disegel', function () {
    kerdusDaftar('KB-20261016-050-A5-01', gudangId: $this->smg->id, sealedAt: now()->subHours(1));
    kerdusDaftar('KB-20261016-051-A6-01', gudangId: $this->btl->id, sealedAt: now()->subHours(5));
    kerdusDaftar('KB-20261016-052-A5-01', gudangId: null, sealedAt: now()->subHours(3));

    expect(kodeDiDaftar(propsKerdusSiap()))
        ->toBe(['KB-20261016-051-A6-01', 'KB-20261016-052-A5-01', 'KB-20261016-050-A5-01']);

    $btl = propsKerdusSiap(['siap_gudang' => $this->btl->id]);
    expect(kodeDiDaftar($btl))->toBe(['KB-20261016-051-A6-01'])
        ->and($btl['daftar']['data'][0]['gudang'])->toBe('Gudang Boyolali');

    expect(kodeDiDaftar(propsKerdusSiap(['siap_gudang' => 'belum'])))->toBe(['KB-20261016-052-A5-01']);
    expect(kodeDiDaftar(propsKerdusSiap(['siap_cari' => '-A6-'])))->toBe(['KB-20261016-051-A6-01']);
});

it('tidak mengirim daftar ke peran tanpa izin pindai kerdus', function () {
    kerdusDaftar('KB-20261016-060-A5-01');

    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);
    $kurir->givePermissionTo('muatan.read');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($kurir->fresh())
        ->get('/admin/muatan')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['pindaiKerdus'])->toBeNull();
});
