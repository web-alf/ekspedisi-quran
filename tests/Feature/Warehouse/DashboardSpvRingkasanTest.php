<?php

use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\StokMushaf;
use App\Models\TransferMushaf;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Ringkasan gudang di dashboard SPV (18/10/2026): selalu ter-coup ke gudang akun,
 * staf tidak menerima ringkasan. Meniru pola verifikasi skill: props dibaca lewat
 * render halaman sebagai user sesungguhnya.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seeder dulu (membuat role+izin), baru akun ditempelkan perannya — terbalik
    // urutannya membuat role terpasang tanpa izin dan halaman menolak (302).
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // firstOrCreate: migrasi sudah membuat kedua gudang ini di DB uji.
    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $buat = function (string $nama, array $peran, $gudang) {
        $u = User::factory()->create(['name' => $nama, 'is_active' => true, 'gudang_id' => $gudang?->id]);
        $u->syncRoles($peran);

        return $u;
    };

    $this->spvSmg = $buat('SPV Semarang', ['warehouse', 'spv-gudang'], $this->smg);
    $this->spvBtl = $buat('SPV Boyolali', ['warehouse', 'spv-gudang'], $this->btl);
    $this->stafSmg = $buat('Staf Semarang', ['warehouse'], $this->smg);
    $this->stafBtl = $buat('Staf Boyolali', ['warehouse'], $this->btl);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function rgTugas(User $staf, int $target, int $selesai, int $mulaiJamLalu = 2): DailyPackingTask
{
    return DailyPackingTask::create([
        'user_id' => $staf->id,
        'tanggal_tugas' => today(),
        'total_target' => $target,
        'total_selesai' => $selesai,
        'status' => $selesai >= $target ? 'completed' : 'in_progress',
        'started_at' => now()->subHours($mulaiJamLalu),
        'assigned_at' => now(),
        // Diisi manual karena gudang sudah diketahui di sini (bukan lewat booted).
        'gudang_id' => $staf->gudang_id,
    ]);
}

it('SPV melihat ringkasan gudangnya sendiri, bukan gudang lain', function () {
    $stafSmgLain = User::factory()->create(['name' => 'Staf Semarang 2', 'is_active' => true, 'gudang_id' => $this->smg->id]);
    $stafSmgLain->syncRoles(['warehouse']);
    rgTugas($this->stafSmg, 20, 12);
    rgTugas($stafSmgLain, 20, 8);
    rgTugas($this->stafBtl, 30, 25);

    $props = $this->actingAs($this->spvSmg)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];
    $r = $props['ringkasanGudang'];

    expect($r['gudang']['kode'])->toBe('SMG')
        ->and($r['packing']['target'])->toBe(40)
        ->and($r['packing']['selesai'])->toBe(20)
        ->and($r['packing']['jumlah_staf'])->toBe(2)
        ->and(collect($r['packing']['per_staf'])->pluck('nama')->all())->toBe(['Staf Semarang', 'Staf Semarang 2']);

    $propsBtl = $this->actingAs($this->spvBtl)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];
    expect($propsBtl['ringkasanGudang']['gudang']['kode'])->toBe('BTL')
        ->and($propsBtl['ringkasanGudang']['packing']['target'])->toBe(30);
});

it('staf tidak menerima ringkasan gudang', function () {
    rgTugas($this->stafSmg, 20, 12);

    $props = $this->actingAs($this->stafSmg)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];

    expect($props['ringkasanGudang'])->toBeNull();
});

it('staf yang belum mulai dihitung dan ditandai', function () {
    rgTugas($this->stafSmg, 20, 0);
    $belum = DailyPackingTask::create([
        'user_id' => $this->stafBtl->id, 'tanggal_tugas' => today(), 'total_target' => 10, 'total_selesai' => 0,
        'status' => 'assigned', 'assigned_at' => now(), 'gudang_id' => $this->stafBtl->gudang_id,
    ]);

    $r = $this->actingAs($this->spvBtl)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanGudang'];

    expect($r['packing']['staf_belum_mulai'])->toBe(1)
        ->and($r['packing']['per_staf'][0]['mulai'])->toBeFalse();
});

it('transfer menunggu hanya yang ditujukan ke gudang SPV', function () {
    TransferMushaf::create([
        'kode' => 'TM-001', 'gudang_asal_id' => $this->btl->id, 'gudang_tujuan_id' => $this->smg->id,
        'status' => 'diproses', 'jumlah_resi' => 3, 'jumlah_eks' => 3, 'created_by' => $this->spvBtl->id,
    ]);
    TransferMushaf::create([
        'kode' => 'TM-002', 'gudang_asal_id' => $this->smg->id, 'gudang_tujuan_id' => $this->btl->id,
        'status' => 'diproses', 'jumlah_resi' => 5, 'jumlah_eks' => 5, 'created_by' => $this->spvSmg->id,
    ]);
    TransferMushaf::create([
        'kode' => 'TM-003', 'gudang_asal_id' => $this->btl->id, 'gudang_tujuan_id' => $this->smg->id,
        'status' => 'diterima', 'jumlah_resi' => 1, 'jumlah_eks' => 1, 'created_by' => $this->spvBtl->id,
        'received_by' => $this->spvSmg->id, 'received_at' => now(),
    ]);

    $rSmg = $this->actingAs($this->spvSmg)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanGudang'];
    $rBtl = $this->actingAs($this->spvBtl)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanGudang'];

    expect(collect($rSmg['perlu_tindakan']['transfer_menunggu'])->pluck('kode')->all())->toBe(['TM-001'])
        ->and($rSmg['perlu_tindakan']['transfer_menunggu'][0]['asal'])->toBe('Gudang Boyolali')
        ->and(collect($rBtl['perlu_tindakan']['transfer_menunggu'])->pluck('kode')->all())->toBe(['TM-002']);
});

it('beban gudang menghitung resi bergudang per tahap dan dalam kerdus', function () {
    $buat = function (string $slug, $gudang, bool $keKerdus = false) use (&$n) {
        $p = Pengiriman::factory()->create([
            'no_resi' => 'EQ-2026-9'.str_pad((string) ($n += 1), 4, '0', STR_PAD_LEFT),
            'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
            'gudang_id' => $gudang?->id,
        ]);

        if ($keKerdus) {
            $tugas = DailyPackingTask::create(['user_id' => $this->stafSmg->id, 'tanggal_tugas' => today(), 'total_target' => 1, 'total_selesai' => 0, 'status' => 'assigned', 'assigned_at' => now(), 'gudang_id' => $gudang?->id]);
            $box = PackingBox::create(['kode_kerdus' => 'KB-UJI-'.$n, 'daily_packing_task_id' => $tugas->id, 'gudang_id' => $gudang?->id, 'kapasitas' => 20, 'jumlah_terisi' => 1, 'status' => 'filling']);
            PackingItem::create(['packing_box_id' => $box->id, 'pengiriman_id' => $p->id, 'packed_by' => $this->stafSmg->id, 'urutan_dalam_box' => 1, 'quantity' => 1, 'packed_at' => now()]);
        }

        return $p;
    };

    $buat('pemesanan', $this->smg);
    $buat('pemesanan', $this->smg);
    $buat('pemesanan', $this->btl);
    $buat('produksi', $this->smg);
    $buat('packing', $this->smg);
    $buat('selesai-packing', $this->smg);
    $buat('selesai-packing', null); // tanpa gudang — tidak boleh masuk hitungan mana pun
    $buat('packing', $this->smg, true); // dalam kerdus

    $r = $this->actingAs($this->spvSmg)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanGudang'];

    expect($r['beban'])->toBe([
        // Resi dalam kerdus tetap berstatus packing — 'dalam_kerdus' info tambahan, bukan pemindah tahap.
        'pemesanan' => 2, 'produksi' => 1, 'packing' => 2, 'selesai_packing' => 1, 'dalam_kerdus' => 1,
    ]);
});

it('kerdus menunggu muat menghitung kerdus penuh gudang yang belum termuat', function () {
    $tugas = DailyPackingTask::create(['user_id' => $this->stafSmg->id, 'tanggal_tugas' => today(), 'total_target' => 1, 'total_selesai' => 1, 'status' => 'completed', 'assigned_at' => now(), 'gudang_id' => $this->smg->id]);
    // Satu resi per kerdus: packing_items.pengiriman_id unik global.
    $resiPerKode = [];
    foreach (['A' => 'sealed', 'B' => 'full', 'C' => 'filling'] as $kode => $status) {
        $resiPerKode[$kode] = Pengiriman::factory()->create(['status_id' => StatusPengiriman::where('slug', 'packing')->value('id'), 'gudang_id' => $this->smg->id]);
    }

    foreach ([['A', 'sealed'], ['B', 'full'], ['C', 'filling']] as [$kode, $status]) {
        $p = $resiPerKode[$kode];
        $box = PackingBox::create(['kode_kerdus' => "KB-UJI-$kode", 'daily_packing_task_id' => $tugas->id, 'gudang_id' => $this->smg->id, 'kapasitas' => 20, 'jumlah_terisi' => 1, 'status' => $status]);
        PackingItem::create(['packing_box_id' => $box->id, 'pengiriman_id' => $p->id, 'packed_by' => $this->stafSmg->id, 'urutan_dalam_box' => 1, 'quantity' => 1, 'packed_at' => now()]);
    }
    // Kerdus tersegel gudang lain tidak dihitung.
    $btl = PackingBox::create(['kode_kerdus' => 'KB-UJI-BTL', 'daily_packing_task_id' => $tugas->id, 'gudang_id' => $this->btl->id, 'kapasitas' => 20, 'jumlah_terisi' => 1, 'status' => 'sealed']);
    PackingItem::create(['packing_box_id' => $btl->id, 'pengiriman_id' => Pengiriman::factory()->create(['gudang_id' => $this->btl->id])->id, 'packed_by' => $this->stafBtl->id, 'urutan_dalam_box' => 1, 'quantity' => 1, 'packed_at' => now()]);

    $r = $this->actingAs($this->spvSmg)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanGudang'];

    // KB-UJI-A (sealed, isi resi packing) dan KB-UJI-B (full, resi yang sama) menunggu;
    // KB-UJI-C masih filling, kerdus BTL bukan milik gudang ini.
    expect($r['perlu_tindakan']['kerdus_menunggu_muat'])->toBe(2);
});

it('stok per jenis mengikuti satuan halaman Stok Mushaf', function () {
    $a5 = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::create(['kode_jenis' => 'A5', 'nama_jenis' => "Al-Qur'an A5"]);

    StokMushaf::create(['gudang_id' => $this->smg->id, 'jenis_quran_id' => $a5->id, 'terjemahan' => true, 'jumlah_eks' => 45]);
    StokMushaf::create(['gudang_id' => $this->smg->id, 'jenis_quran_id' => $a5->id, 'terjemahan' => false, 'jumlah_eks' => 8]);

    $r = $this->actingAs($this->spvSmg)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanGudang'];

    // Baris varian ber-terjemahan dulu, lalu tanpa — dua baris, bukan tercampur.
    expect($r['stok'])->toHaveCount(2)
        ->and($r['stok'][0]['jenis'])->toBe($a5->nama_jenis)
        ->and($r['stok'][0]['varian'])->toBe('Ada terjemahan')
        ->and($r['stok'][0]['eks'])->toBe(45)
        ->and($r['stok'][0]['dus'])->toBe(2) // kapasitas A5 = 20/dus
        ->and($r['stok'][1]['varian'])->toBe('Tanpa terjemahan')
        ->and($r['stok'][1]['eks'])->toBe(8);
});
