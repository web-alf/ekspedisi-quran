<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\PindaiKerdusLog;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pindai kerdus sisi distribusi = VERIFIKASI ISI, dan syarat sebelum kerdus dimuat.
 *
 * Janji yang dikunci:
 *   1. Kerdus terdaftar tidak bisa "dipindai saja": petugas wajib mengetik hitungan
 *      fisiknya, dan angka data TIDAK ditunjukkan sebelum itu (hitungan buta).
 *   2. Hitungan sama = cocok, beda = selisih.
 *   3. Kerdus hanya bisa dimuat bila verifikasi TERAKHIRNYA cocok dan isinya belum berubah
 *      sejak itu — di SEMUA jalur muat: pindai kerdus, pindai resi satuan, pilih dari
 *      daftar, dan halaman pindai kurir.
 *   4. Tidak ada override: kerdus selisih lolos hanya lewat pindai ulang yang cocok.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    foreach (['packing' => 4, 'selesai-packing' => 5, 'pengiriman' => 6] as $slug => $urutan) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $urutan, 'is_active' => true, 'is_final' => false]
        );
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function petugasDistribusi(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::DISTRIBUSI->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function kurirPemuat(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::COURIER->value);
    $user->givePermissionTo([PermissionEnum::MUATAN_READ->value, PermissionEnum::MUATAN_SCAN->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

/**
 * Kerdus tersegel berisi `$eks` resi siap diantar (masing-masing 1 eks).
 */
function kerdusSiapVerifikasi(int $eks, string $kode, string $slug = 'selesai-packing'): PackingBox
{
    $box = PackingBox::factory()->create([
        'kode_kerdus' => $kode,
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
        'kapasitas' => $eks,
        'jumlah_terisi' => $eks,
        'status' => PackingBox::STATUS_SEALED,
        'sealed_at' => now(),
    ]);

    $statusId = StatusPengiriman::where('slug', $slug)->value('id');

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

function verifikasi(User $petugas, PackingBox $box, int $jumlahFisik): TestResponse
{
    return test()->actingAs($petugas)->postJson(route('admin.pindai-kerdus.store'), [
        'kode' => $box->kode_kerdus,
        'jumlah_fisik' => $jumlahFisik,
    ]);
}

// --- Verifikasi isi ---

it('meminta hitungan fisik tanpa membocorkan jumlah di data', function () {
    $petugas = petugasDistribusi();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-01');

    $jawaban = $this->actingAs($petugas)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus])
        ->assertStatus(422)
        ->assertJsonPath('perlu_hitung', true)
        ->assertJsonPath('kode', $box->kode_kerdus);

    expect($jawaban->json())->not->toHaveKey('data')
        ->and($jawaban->json('message'))->not->toContain('3')
        ->and(PindaiKerdusLog::count())->toBe(0);
});

it('mencatat COCOK bila hitungan fisik sama dengan data', function () {
    $petugas = petugasDistribusi();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-02');

    verifikasi($petugas, $box, 3)
        ->assertSuccessful()
        ->assertJsonPath('hasil', 'cocok')
        ->assertJsonPath('data.jumlah_mushaf', 3)
        ->assertJsonPath('data.jumlah_fisik', 3);

    $baris = PindaiKerdusLog::sole();

    expect($baris->hasil)->toBe(PindaiKerdusLog::HASIL_COCOK)
        ->and($baris->jumlah_fisik)->toBe(3)
        ->and($baris->user_id)->toBe($petugas->id)
        ->and($box->fresh()->alasanBelumBolehDimuat())->toBeNull();
});

it('mencatat SELISIH bila hitungan fisik berbeda, dan menghitungnya di ringkasan', function () {
    $petugas = petugasDistribusi();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-03');

    $jawaban = verifikasi($petugas, $box, 2)
        ->assertSuccessful()
        ->assertJsonPath('hasil', 'selisih')
        ->assertJsonPath('data.jumlah_mushaf', 3)
        ->assertJsonPath('data.jumlah_fisik', 2);

    expect($jawaban->json('message'))->toContain('SELISIH')
        ->and($jawaban->json('total.selisih'))->toBe(1)
        ->and($box->fresh()->alasanBelumBolehDimuat())->toContain('SELISIH');
});

it('menerima hitungan nol untuk kerdus yang ternyata kosong', function () {
    $petugas = petugasDistribusi();
    $box = kerdusSiapVerifikasi(2, 'KB-20261015-001-A5-04');

    verifikasi($petugas, $box, 0)->assertSuccessful()->assertJsonPath('hasil', 'selisih');
});

it('menolak hitungan fisik negatif atau bukan angka', function (mixed $nilai) {
    $petugas = petugasDistribusi();
    $box = kerdusSiapVerifikasi(1, 'KB-20261015-001-A5-05');

    $this->actingAs($petugas)
        ->postJson(route('admin.pindai-kerdus.store'), ['kode' => $box->kode_kerdus, 'jumlah_fisik' => $nilai])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jumlah_fisik');

    expect(PindaiKerdusLog::count())->toBe(0);
})->with(['negatif' => -1, 'teks' => 'dua puluh']);

// --- Syarat muat: pindai kerdus di halaman muatan ---

it('menolak memuat kerdus yang belum diverifikasi', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-10');

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => $box->kode_kerdus])
        ->assertUnprocessable()
        ->assertJsonPath('perlu_verifikasi', true)
        ->assertJsonPath('success', false);

    expect(MuatanItem::count())->toBe(0);
});

it('menolak memuat kerdus SELISIH, lalu meloloskannya setelah pindai ulang cocok', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-11');

    verifikasi($petugas, $box, 2)->assertJsonPath('hasil', 'selisih');

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => $box->kode_kerdus])
        ->assertUnprocessable();

    expect(MuatanItem::count())->toBe(0);

    // Gudang membereskan isinya; distribusi menghitung ulang — sekarang cocok.
    verifikasi($petugas, $box, 3)->assertJsonPath('hasil', 'cocok');

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => $box->kode_kerdus])
        ->assertSuccessful()
        ->assertJsonPath('dimuat', 3);
});

it('memblokir kerdus yang dulu cocok tetapi pindai terakhirnya selisih', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-12');

    verifikasi($petugas, $box, 3)->assertJsonPath('hasil', 'cocok');
    verifikasi($petugas, $box, 1)->assertJsonPath('hasil', 'selisih');

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => $box->kode_kerdus])
        ->assertUnprocessable();
});

it('meminta verifikasi ulang bila isi kerdus berubah setelah diverifikasi', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $box = kerdusSiapVerifikasi(3, 'KB-20261015-001-A5-13');

    verifikasi($petugas, $box, 3)->assertJsonPath('hasil', 'cocok');

    // Satu resi dikeluarkan dari kerdus sesudah verifikasi.
    $box->packingItems()->first()->delete();

    $jawaban = $this->actingAs($petugas)
        ->postJson(route('admin.muatan.pindai.box', $muatan), ['kode' => $box->kode_kerdus])
        ->assertUnprocessable();

    expect($jawaban->json('message'))->toContain('berubah')
        ->and(MuatanItem::count())->toBe(0);
});

// --- Syarat muat: jalur lain tidak boleh jadi pintu belakang ---

it('menolak memuat resi satuan dari kerdus yang belum diverifikasi', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $box = kerdusSiapVerifikasi(2, 'KB-20261015-001-A5-20');
    $resi = $box->packingItems()->first()->pengiriman;

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $resi->no_resi])
        ->assertUnprocessable();

    verifikasi($petugas, $box, 2)->assertJsonPath('hasil', 'cocok');

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $resi->no_resi])
        ->assertSuccessful();
});

it('tetap memuat resi satuan yang tidak berada di kerdus mana pun', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'selesai-packing')->value('id'),
    ]);

    $this->actingAs($petugas)
        ->postJson(route('admin.muatan.scan', $muatan), ['no_resi' => $resi->no_resi])
        ->assertSuccessful();
});

it('melewati resi dari kerdus belum terverifikasi saat ditambahkan dari daftar', function () {
    $petugas = petugasDistribusi();
    $muatan = Muatan::factory()->create();
    $belum = kerdusSiapVerifikasi(2, 'KB-20261015-001-A5-21');
    $sudah = kerdusSiapVerifikasi(1, 'KB-20261015-001-A5-22');
    verifikasi($petugas, $sudah, 1)->assertJsonPath('hasil', 'cocok');

    $ids = $belum->packingItems()->pluck('pengiriman_id')
        ->merge($sudah->packingItems()->pluck('pengiriman_id'))
        ->all();

    $this->actingAs($petugas)
        ->post(route('admin.muatan.sync-items', $muatan), ['pengiriman_ids' => $ids])
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, '2 resi dilewati'));

    expect(MuatanItem::pluck('pengiriman_id')->all())->toBe($sudah->packingItems()->pluck('pengiriman_id')->all());
});

it('melewati resi dari kerdus belum terverifikasi saat membuat muatan', function () {
    $petugas = petugasDistribusi();
    $kurir = kurirPemuat();
    $belum = kerdusSiapVerifikasi(1, 'KB-20261015-001-A5-23');

    $this->actingAs($petugas)
        ->post(route('admin.muatan.store'), [
            'kurir_id' => $kurir->id,
            'tanggal_muatan' => today()->toDateString(),
            'pengiriman_ids' => $belum->packingItems()->pluck('pengiriman_id')->all(),
        ])
        ->assertRedirect();

    expect(Muatan::count())->toBe(1)
        ->and(MuatanItem::count())->toBe(0);
});

it('tidak menawarkan resi dari kerdus yang belum lolos verifikasi di daftar siap muat', function () {
    $petugas = petugasDistribusi();
    $belum = kerdusSiapVerifikasi(2, 'KB-20261015-001-A5-24');
    $sudah = kerdusSiapVerifikasi(1, 'KB-20261015-001-A5-25');
    verifikasi($petugas, $sudah, 1)->assertJsonPath('hasil', 'cocok');

    $daftar = $this->actingAs($petugas)
        ->get(route('admin.muatan.create'))
        ->assertSuccessful()
        ->viewData('page')['props']['resiSiap'] ?? null;

    expect($daftar)->not->toBeNull();

    $ditawarkan = collect($daftar)->pluck('id')->sort()->values()->all();

    expect($ditawarkan)->toBe($sudah->packingItems()->pluck('pengiriman_id')->sort()->values()->all());
});

// --- Syarat muat: halaman pindai kurir ---

it('menolak kurir memuat kerdus yang belum diverifikasi lewat halaman pindainya', function () {
    $kurir = kurirPemuat();
    $muatan = Muatan::factory()->untukKurir($kurir)->create(['tanggal_muatan' => today()]);
    $box = kerdusSiapVerifikasi(2, 'KB-20261015-001-A5-30');

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.pindai.box'), ['kode' => $box->kode_kerdus, 'muatan_id' => $muatan->id])
        ->assertUnprocessable()
        ->assertJsonPath('perlu_verifikasi', true);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.pindai.pcs'), [
            'kode' => $box->packingItems()->first()->pengiriman->no_resi,
            'muatan_id' => $muatan->id,
        ])
        ->assertUnprocessable();

    expect(MuatanItem::count())->toBe(0);

    verifikasi(petugasDistribusi(), $box, 2)->assertJsonPath('hasil', 'cocok');

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.pindai.box'), ['kode' => $box->kode_kerdus, 'muatan_id' => $muatan->id])
        ->assertSuccessful()
        ->assertJsonPath('data.dimuat', 2);
});

it('menolak kurir memuat resi yang belum selesai packing lewat halaman pindainya', function () {
    $kurir = kurirPemuat();
    $muatan = Muatan::factory()->untukKurir($kurir)->create(['tanggal_muatan' => today()]);
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'packing')->value('id'),
    ]);

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.pindai.pcs'), ['kode' => $resi->no_resi, 'muatan_id' => $muatan->id])
        ->assertUnprocessable();

    expect(MuatanItem::count())->toBe(0);
});
