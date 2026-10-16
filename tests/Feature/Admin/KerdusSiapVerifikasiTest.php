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
 * Daftar "Kerdus Siap Diverifikasi" di panel Pindai Kerdus (/admin/muatan).
 *
 * Kerdus yang sudah penuh/tersegel kini MUNCUL di halaman sisi distribusi, supaya
 * petugas tahu kerdus mana yang harus diperiksa — tanpa merusak verifikasi fisik:
 *   - jumlah isi tidak pernah ikut terkirim (hitungan buta);
 *   - statusnya mengikuti aturan syarat muat (pindai TERAKHIR, dan "berubah" bila isi
 *     bergeser sejak cocok);
 *   - kerdus yang belum selesai diisi dan kerdus yang isinya sudah di tangan kurir tidak
 *     ditampilkan.
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

it('tidak pernah mengirim jumlah isi kerdus ke layar (hitungan buta)', function () {
    kerdusDaftar('KB-20261016-010-A5-01', PackingBox::STATUS_SEALED, 17);

    $baris = propsKerdusSiap()['daftar']['data'][0];

    // Daftar-putih kunci: menambah kolom baru ke baris ini harus disengaja dan lewat tes
    // ini, supaya angka isi tidak ikut terkirim diam-diam lewat kolom yang baru.
    expect(array_keys($baris))->toEqualCanonicalizing([
        'id', 'kode_kerdus', 'jenis', 'jenis_kode', 'gudang', 'status_kerdus',
        'disegel_pada', 'verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada',
    ]);
});

it('menandai status verifikasi dengan aturan yang sama dengan syarat muat', function () {
    $belum = kerdusDaftar('KB-20261016-020-A5-01');
    $cocok = kerdusDaftar('KB-20261016-021-A5-01');
    $selisih = kerdusDaftar('KB-20261016-022-A5-01');
    $berubah = kerdusDaftar('KB-20261016-023-A5-01');

    foreach ([[$cocok, 3], [$selisih, 2], [$berubah, 3]] as [$box, $fisik]) {
        $this->actingAs($this->petugas)
            ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'jumlah_fisik' => $fisik])
            ->assertSuccessful();
    }

    // Isi kerdus bertambah SESUDAH dinyatakan cocok: harus diperiksa ulang.
    PackingItem::factory()->create([
        'packing_box_id' => $berubah->id,
        'pengiriman_id' => Pengiriman::factory()->create(['jumlah_quran' => 1])->id,
        'urutan_dalam_box' => 4,
        'quantity' => 1,
    ]);

    $status = collect(propsKerdusSiap(['siap_status' => 'semua'])['daftar']['data'])
        ->pluck('verifikasi', 'kode_kerdus');

    expect($status['KB-20261016-020-A5-01'])->toBe('belum')
        ->and($status['KB-20261016-021-A5-01'])->toBe('cocok')
        ->and($status['KB-20261016-022-A5-01'])->toBe('selisih')
        ->and($status['KB-20261016-023-A5-01'])->toBe('berubah');

    // Kesepakatan dengan syarat muat: hanya yang "cocok" boleh dimuat.
    expect($cocok->fresh()->alasanBelumBolehDimuat())->toBeNull()
        ->and($berubah->fresh()->alasanBelumBolehDimuat())->not->toBeNull()
        ->and($selisih->fresh()->alasanBelumBolehDimuat())->not->toBeNull()
        ->and($belum->fresh()->alasanBelumBolehDimuat())->not->toBeNull();
});

it('menampilkan yang perlu tindakan secara bawaan, dan bisa disaring per status', function () {
    kerdusDaftar('KB-20261016-030-A5-01');
    $cocok = kerdusDaftar('KB-20261016-031-A5-01');

    $this->actingAs($this->petugas)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $cocok->kode_kerdus, 'jumlah_fisik' => 3])
        ->assertSuccessful();

    $bawaan = propsKerdusSiap();

    expect($bawaan['filters']['status'])->toBe('perlu')
        ->and(kodeDiDaftar($bawaan))->toBe(['KB-20261016-030-A5-01'])
        ->and($bawaan['hitung'])->toMatchArray(['belum' => 1, 'cocok' => 1, 'perlu' => 1, 'semua' => 2]);

    expect(kodeDiDaftar(propsKerdusSiap(['siap_status' => 'cocok'])))->toBe(['KB-20261016-031-A5-01']);

    // Nilai asal-asalan jatuh ke bawaan, bukan galat.
    expect(propsKerdusSiap(['siap_status' => 'ngawur'])['filters']['status'])->toBe('perlu');
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

    $kode = kodeDiDaftar(propsKerdusSiap(['siap_status' => 'semua']));

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
