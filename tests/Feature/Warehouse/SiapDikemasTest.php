<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Seksi "Siap Dikemas" di Proses Packing (30/10/2026): resi yang bisa dikerjakan freelancer,
 * sesuai jenis tugas (mis. A6) dan gudang tugas — resi gudang itu atau yang belum punya
 * gudang. Aturannya sama dengan kandidat mode Kardus: status Proses Packing, belum masuk
 * kerdus, belum diambil tugas mana pun, urut resi terlama.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    StatusPengirimanCache::clear();

    $this->statusPacking = StatusPengiriman::firstOrCreate(['slug' => 'packing'], ['nama' => 'Proses Packing', 'urutan' => 4]);
    $this->statusPemesanan = StatusPengiriman::firstOrCreate(['slug' => 'pemesanan'], ['nama' => 'Proses Pemesanan', 'urutan' => 1]);
    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->a5 = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->a6 = JenisQuran::where('kode_jenis', 'A6')->first() ?? JenisQuran::factory()->a6()->create();
    $this->a6->update(['default_capacity' => 40]);

    $this->freelancer = User::factory()->create(['is_active' => true, 'gudang_id' => $this->btl->id]);
    $this->freelancer->syncRoles([RoleEnum::WAREHOUSE->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->tugas = DailyPackingTask::factory()->create([
        'user_id' => $this->freelancer->id,
        'gudang_id' => $this->btl->id,
        'tanggal_tugas' => today(),
        'total_target' => 80,
        'total_selesai' => 12,
        'jenis_quran_id' => $this->a6->id,
        'terjemahan' => true,
    ]);

    $this->resi = fn (array $isian = []): Pengiriman => Pengiriman::factory()->create([
        'jenis_quran_id' => $this->a6->id,
        'status_id' => $this->statusPacking->id,
        'gudang_id' => $this->btl->id,
        'jumlah_quran' => 1,
        ...$isian,
    ]);
});

function siapDikemasProps(User $akun): ?array
{
    return test()->actingAs($akun)->get('/admin/warehouse/packing')->assertOk()->viewData('page')['props']['siapDikemas'] ?? null;
}

it('hanya menghitung resi A6 berstatus Proses Packing di gudang tugas atau tanpa gudang', function () {
    $pertama = ($this->resi)(['jumlah_quran' => 10, 'nama_lembaga' => 'TPQ Baiturrohim']);
    $kedua = ($this->resi)(['jumlah_quran' => 1, 'nama_lembaga' => null, 'nama_penerima' => 'Ahmad Fauzi']);
    $tanpaGudang = ($this->resi)(['gudang_id' => null, 'jumlah_quran' => 40]);

    // Tidak dihitung: jenis lain, gudang lain, belum Proses Packing, sudah dikemas, sudah diambil tugas.
    ($this->resi)(['jenis_quran_id' => $this->a5->id]);
    ($this->resi)(['gudang_id' => $this->smg->id]);
    ($this->resi)(['status_id' => $this->statusPemesanan->id]);
    $sudahDikemas = ($this->resi)();
    PackingItem::factory()->create([
        'packing_box_id' => PackingBox::factory()->create(['daily_packing_task_id' => $this->tugas->id, 'jenis_quran_id' => $this->a6->id])->id,
        'pengiriman_id' => $sudahDikemas->id,
    ]);
    $sudahDiambil = ($this->resi)();
    DailyPackingTaskItem::create(['daily_packing_task_id' => $this->tugas->id, 'pengiriman_id' => $sudahDiambil->id, 'assigned_at' => now()]);

    $siap = siapDikemasProps($this->freelancer);

    expect($siap)->toMatchArray([
        'jenis' => 'A6',
        'gudang' => $this->btl->nama,
        'jumlah_resi' => 3,
        'jumlah_eks' => 51,
        'sisa_tugas' => 68,
    ])->and(collect($siap['contoh'])->pluck('no_resi')->all())->toBe([$pertama->no_resi, $kedua->no_resi, $tanpaGudang->no_resi])
        ->and($siap['contoh'][0])->toMatchArray(['penerima' => 'TPQ Baiturrohim', 'jenis' => 'A6', 'eks' => 10])
        ->and($siap['contoh'][1]['penerima'])->toBe('Ahmad Fauzi');
});

it('menampilkan paling banyak lima contoh resi terlama', function () {
    $semua = collect(range(1, 7))->map(fn () => ($this->resi)());

    $siap = siapDikemasProps($this->freelancer);

    expect($siap['jumlah_resi'])->toBe(7)
        ->and(collect($siap['contoh'])->pluck('no_resi')->all())->toBe($semua->take(5)->pluck('no_resi')->all());
});

it('tugas tanpa jenis menampilkan semua jenis di gudangnya', function () {
    $this->tugas->update(['jenis_quran_id' => null, 'terjemahan' => null]);
    ($this->resi)();
    ($this->resi)(['jenis_quran_id' => $this->a5->id]);

    $siap = siapDikemasProps($this->freelancer);

    expect($siap['jenis'])->toBeNull()
        ->and($siap['jumlah_resi'])->toBe(2);
});

it('berkurang setelah freelancer memindai salah satu resinya', function () {
    $dipindai = ($this->resi)(['jumlah_quran' => 3]);
    ($this->resi)(['jumlah_quran' => 2]);

    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $dipindai->no_resi])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(siapDikemasProps($this->freelancer))->toMatchArray(['jumlah_resi' => 1, 'jumlah_eks' => 2, 'sisa_tugas' => 65]);
});

it('tidak dikirim bila tidak ada tugas aktif hari ini', function () {
    $this->tugas->delete();

    expect(siapDikemasProps($this->freelancer))->toBeNull();
});
