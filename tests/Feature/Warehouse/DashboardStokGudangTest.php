<?php

use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\StokMushaf;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: seksi "Stok" di dashboard gudang (keputusan pemilik 27/10/2026).
 *
 * Isinya dua kelompok kartu: tahap resi (Proses Pesan, Proses Packing, Selesai Packing,
 * Sudah Dikarduskan & Siap Distribusi) dan mushaf yang belum ada muwakif per jenis/varian
 * (A5 terjemahan/tanpa, A6 terjemahan/tanpa, IQRA).
 *
 * Bedanya peran: Manager menghitung SELURUH resi (termasuk yang belum bertagih gudang —
 * di produksi 26.079 dari 26.080 resi pemesanan), SPV hanya gudangnya sendiri.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->spvSmg = User::factory()->create(['name' => 'SPV Semarang', 'is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->spvSmg->syncRoles(['warehouse', 'spv-gudang']);

    $this->manager = User::factory()->create(['name' => 'Manager Gudang', 'is_active' => true, 'gudang_id' => null]);
    $this->manager->syncRoles(['manager-repacking']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->a5 = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->a5->update(['default_capacity' => 20]);
    $this->a6 = JenisQuran::where('kode_jenis', 'A6')->first() ?? JenisQuran::factory()->a6()->create();
    $this->iqro = JenisQuran::whereIn('kode_jenis', ['IQRO', 'IQRA'])->first() ?? JenisQuran::factory()->create(['kode_jenis' => 'IQRO', 'nama_jenis' => 'IQRA']);
});

function stResi(string $slug, ?Gudang $gudang, int $eks = 1): Pengiriman
{
    return Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        'gudang_id' => $gudang?->id,
        'jumlah_quran' => $eks,
    ]);
}

function stStok(Gudang $gudang, JenisQuran $jenis, bool $terjemahan, int $eks): void
{
    StokMushaf::updateOrCreate(
        ['gudang_id' => $gudang->id, 'jenis_quran_id' => $jenis->id, 'terjemahan' => $terjemahan],
        ['jumlah_eks' => $eks],
    );
}

function stKerdusTersegel(User $staf, Gudang $gudang, int $isi): PackingBox
{
    $tugas = DailyPackingTask::create([
        'user_id' => $staf->id, 'tanggal_tugas' => today(), 'total_target' => $isi,
        'total_selesai' => 0, 'status' => 'assigned', 'assigned_at' => now(), 'gudang_id' => $gudang->id,
    ]);

    $box = PackingBox::create([
        'kode_kerdus' => 'KB-'.uniqid(), 'daily_packing_task_id' => $tugas->id, 'gudang_id' => $gudang->id,
        'kapasitas' => 20, 'jumlah_terisi' => $isi, 'status' => PackingBox::STATUS_SEALED,
    ]);

    for ($i = 1; $i <= $isi; $i++) {
        PackingItem::create([
            'packing_box_id' => $box->id, 'pengiriman_id' => stResi('selesai-packing', $gudang)->id,
            'packed_by' => $staf->id, 'urutan_dalam_box' => $i, 'quantity' => 1, 'packed_at' => now(),
        ]);
    }

    return $box;
}

it('mengirim empat angka tahap resi untuk Manager, termasuk resi tanpa gudang', function () {
    stResi('pemesanan', null, 300);
    stResi('pemesanan', null, 700);
    stResi('packing', null);
    stResi('packing', null);
    stResi('packing', $this->btl);
    foreach ([1, 2, 3] as $_) {
        stResi('selesai-packing', $this->smg);
    }

    $stok = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok'];

    expect($stok['pemesanan'])->toMatchArray(['resi' => 2, 'eks' => 1000])
        ->and($stok['packing'])->toBe(3)
        ->and($stok['selesai'])->toBe(3);
});

it('mengirim angka tahap resi SPV hanya untuk gudangnya', function () {
    stResi('pemesanan', null, 500);
    stResi('pemesanan', $this->smg);
    stResi('packing', $this->smg);
    stResi('packing', $this->btl);
    stResi('selesai-packing', $this->btl);

    $stok = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanGudang']['stok'];

    expect($stok['pemesanan'])->toMatchArray(['resi' => 1, 'eks' => 1])
        ->and($stok['packing'])->toBe(1)
        ->and($stok['selesai'])->toBe(0);
});

it('menghitung mushaf yang sudah dikarduskan dari kerdus tersegel', function () {
    $staf = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $staf->assignRole('warehouse');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    stKerdusTersegel($staf, $this->smg, 5);

    // Kerdus yang belum tersegel tidak dihitung.
    // Satu tugas per staf per tanggal — kerdus pembanding ini menempel di tugas kemarin.
    $tugasLain = DailyPackingTask::create([
        'user_id' => $staf->id, 'tanggal_tugas' => today()->subDay(), 'total_target' => 3,
        'total_selesai' => 1, 'status' => 'in_progress', 'gudang_id' => $this->smg->id,
    ]);
    PackingBox::create([
        'kode_kerdus' => 'KB-BELUM-'.uniqid(), 'daily_packing_task_id' => $tugasLain->id,
        'gudang_id' => $this->smg->id, 'kapasitas' => 20,
        'jumlah_terisi' => 3, 'status' => PackingBox::STATUS_FILLING,
    ]);

    $stok = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok'];

    expect($stok['kerdus'])->toMatchArray(['jumlah' => 1, 'eks' => 5]);
});

it('selalu mengirim lima kartu mushaf belum ada muwakif, termasuk yang nol', function () {
    stStok($this->smg, $this->a5, true, 20);
    stStok($this->smg, $this->a5, false, 15);
    stStok($this->btl, $this->a6, false, 120);
    stStok($this->smg, $this->iqro, false, 160);

    $stok = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok'];

    expect($stok['muwakif'])->toHaveCount(5)
        ->and(collect($stok['muwakif'])->pluck('eks', 'label')->all())->toBe([
            'A5 · Terjemahan' => 20,
            'A5 · Tanpa Terjemahan' => 15,
            'A6 · Terjemahan' => 0,
            'A6 · Tanpa Terjemahan' => 120,
            'IQRA' => 160,
        ]);
});

it('menjumlahkan stok dua gudang untuk Manager dan hanya gudangnya untuk SPV', function () {
    stStok($this->smg, $this->a5, true, 19);
    stStok($this->btl, $this->a5, true, 1);
    stStok($this->smg, $this->a6, false, 90);
    stStok($this->btl, $this->a6, false, 30);

    $mgr = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok'];
    $spv = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanGudang']['stok'];

    $milik = fn (array $stok, string $label) => collect($stok['muwakif'])->firstWhere('label', $label)['eks'];

    expect($milik($mgr, 'A5 · Terjemahan'))->toBe(20)
        ->and($milik($mgr, 'A6 · Tanpa Terjemahan'))->toBe(120)
        ->and($milik($spv, 'A5 · Terjemahan'))->toBe(19)
        ->and($milik($spv, 'A6 · Tanpa Terjemahan'))->toBe(90);
});

it('mengirim stok tiap gudang terpisah untuk seksi per gudang', function () {
    stStok($this->smg, $this->a5, true, 19);
    stStok($this->btl, $this->a5, true, 1);
    stResi('packing', $this->smg);
    stResi('packing', $this->btl);
    stResi('packing', $this->btl);

    $per = collect($this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['per_gudang'])
        ->keyBy('kode');

    // Urutan seksi = urutan gudang (Semarang lalu Boyolali).
    expect($per->keys()->all())->toContain('SMG', 'BTL')
        ->and($per['SMG']['stok']['packing'])->toBe(1)
        ->and($per['BTL']['stok']['packing'])->toBe(2)
        ->and(collect($per['SMG']['stok']['muwakif'])->firstWhere('label', 'A5 · Terjemahan')['eks'])->toBe(19)
        ->and(collect($per['BTL']['stok']['muwakif'])->firstWhere('label', 'A5 · Terjemahan')['eks'])->toBe(1);
});

it('menulis selisih resi tanpa gudang pada angka total, bukan disembunyikan', function () {
    stResi('pemesanan', null, 300);
    stResi('pemesanan', null, 700);
    stResi('pemesanan', $this->smg);
    stResi('packing', null);
    stResi('packing', $this->btl);

    $stok = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok'];

    expect($stok['pemesanan']['resi'])->toBe(3)
        ->and($stok['tanpa_gudang']['pemesanan'])->toBe(2)
        ->and($stok['packing'])->toBe(2)
        ->and($stok['tanpa_gudang']['packing'])->toBe(1);
});

it('menulis eks pemesanan tanpa gudang untuk catatan di dashboard Manager', function () {
    stResi('pemesanan', null, 300);
    stResi('pemesanan', null, 700);
    stResi('pemesanan', $this->smg, 40);

    $stok = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok'];

    expect($stok['tanpa_gudang']['pemesanan'])->toBe(2)
        ->and($stok['tanpa_gudang']['pemesanan_eks'])->toBe(1000);
});

it('memberi status habis, rendah, atau normal pada stok muwakif tiap gudang', function () {
    // Kapasitas A5 = 20 eks per dus (beforeEach): 5 eks = rendah, 40 eks = normal.
    stStok($this->smg, $this->a5, true, 40);
    stStok($this->btl, $this->a5, true, 5);

    $per = collect($this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['per_gudang'])
        ->keyBy('kode');

    $status = fn (string $kode, string $label) => collect($per[$kode]['stok']['muwakif'])->firstWhere('label', $label)['status'];

    expect($status('SMG', 'A5 · Terjemahan'))->toBe('normal')
        ->and($status('BTL', 'A5 · Terjemahan'))->toBe('rendah')
        ->and($status('SMG', 'A6 · Terjemahan'))->toBe('habis')
        ->and($status('BTL', 'IQRA'))->toBe('habis');
});

it('hanya membedakan habis atau ada pada stok muwakif gabungan', function () {
    stStok($this->btl, $this->a5, true, 5);

    $muwakif = collect($this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['stok']['muwakif'])
        ->keyBy('label');

    expect($muwakif['A5 · Terjemahan']['status'])->toBe('normal')
        ->and($muwakif['A6 · Terjemahan']['status'])->toBe('habis');
});

it('tidak mengirim seksi stok ke peran yang bukan SPV atau Manager', function () {
    $staf = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $staf->assignRole('warehouse');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($staf)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];

    expect($props['ringkasanGudang'] ?? null)->toBeNull()
        ->and($props['ringkasanManager'] ?? null)->toBeNull();
});
