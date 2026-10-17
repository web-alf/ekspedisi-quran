<?php

use App\Http\Controllers\Admin\TransferMushafController;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\TransferMushaf;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * LOKASI MUSHAF — "mushaf ini ada di gudang mana?" (2026-10-15).
 *
 * Produksi punya DUA gudang (SMG Semarang, BTL Boyolali), tetapi gudang hanya menempel di
 * `users` dan `daily_packing_tasks` — resi dan kerdus tidak membawa lokasi sama sekali, dan
 * seluruh 354 tugas produksi tidak bertagih gudang. Akibatnya pertanyaan itu tidak bisa
 * dijawab dari data.
 *
 * Kontrak yang dikunci di sini:
 *   - kerdus membawa gudangnya sejak dibuat (gudang tugas → gudang pekerja → gudang penugasa);
 *   - resi mengikuti kerdus saat dimasukkan, atau gudang pelaku saat statusnya bergerak di
 *     tahap gudang, dan HANYA bila lokasinya belum pernah ditentukan;
 *   - setelah itu hanya dokumen perpindahan antar gudang yang boleh mengubahnya, dan
 *     perpindahannya terjadi SAAT DITERIMA, bukan saat dokumen dibuat;
 *   - Data Resi bisa disaring per gudang, termasuk "belum diketahui".
 */
beforeEach(function () {
    $this->seed(JenisQuranSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);

    // Dua gudang sudah dibuat oleh migrasi fase 2 (SMG Semarang, BTL Boyolali); di sini
    // diambil apa adanya, bukan dibuat ulang — kalau tidak, `gudang_kode_unique` menolak.
    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    foreach (['mushaf.transfer', 'shipments.read', 'warehouse.dashboard', 'warehouse.packing.view', 'warehouse.packing.scan'] as $izin) {
        Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
    }

    foreach (['spv-gudang', 'manager-repacking', 'warehouse', 'super-admin'] as $nama) {
        Role::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    // SPV Semarang: punya gudang, punya izin pindah, TIDAK punya warehouse.dashboard —
    // persis seperti keadaan produksi (spvsmg@). Halaman perpindahan harus tetap terbuka.
    $this->spvSmg = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->spvSmg->assignRole('spv-gudang');
    $this->spvSmg->givePermissionTo('mushaf.transfer', 'shipments.read');

    $this->spvBtl = User::factory()->create(['is_active' => true, 'gudang_id' => $this->btl->id]);
    $this->spvBtl->assignRole('spv-gudang');
    $this->spvBtl->givePermissionTo('mushaf.transfer', 'shipments.read');

    $this->superAdmin = User::factory()->create(['is_active' => true]);
    $this->superAdmin->assignRole('super-admin');
    $this->superAdmin->givePermissionTo('mushaf.transfer', 'shipments.read', 'warehouse.dashboard');

    $this->pekerja = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->pekerja->assignRole('warehouse');
    // Izin halaman packing: rute `/admin/warehouse/packing` bergerbang warehouse.packing.view
    // dan pengendalinya memanggil Gate::authorize('viewPacking').
    $this->pekerja->givePermissionTo('warehouse.dashboard', 'warehouse.packing.view', 'warehouse.packing.scan', 'shipments.read');

    $this->jenisA5 = JenisQuran::where('kode_jenis', 'A5')->value('id');
});

/** Resi siap dikerjakan gudang (status packing). */
function resiPacking(array $atribut = []): Pengiriman
{
    return Pengiriman::factory()->create(array_merge([
        'status_id' => StatusPengiriman::where('slug', 'packing')->value('id'),
        'jenis_quran_id' => JenisQuran::where('kode_jenis', 'A5')->value('id'),
        'jumlah_quran' => 1,
    ], $atribut));
}

function tugasPacking(User $user, ?int $gudangId = null): DailyPackingTask
{
    return DailyPackingTask::factory()->create([
        'user_id' => $user->id,
        'gudang_id' => $gudangId,
        'tanggal_tugas' => now()->toDateString(),
    ]);
}

it('memberi kerdus baru gudang dari gudang tugasnya', function () {
    $tugas = tugasPacking($this->pekerja, $this->btl->id);

    $kerdus = $tugas->getOrActivateBoxForJenis($this->jenisA5);

    expect($kerdus->gudang_id)->toBe($this->btl->id)
        // Dihitung SEKALI saat dibuat: mengubah tugas tidak boleh menggeser lokasi kerdus
        // yang sudah jadi (labelnya sudah tercetak dan barangnya ada di gudang nyata).
        ->and(tap($kerdus, fn () => $tugas->update(['gudang_id' => $this->smg->id]))->fresh()->gudang_id)->toBe($this->btl->id);
})->group('lokasi-mushaf');

it('memakai gudang pekerja lalu gudang penugasa bila tugas tak bertagih gudang', function () {
    // Keadaan produksi: tugas dibuat cron 06:00 tanpa gudang. Cadangan pertama = gudang
    // pekerja yang ditugasi.
    $tanpaTagihan = tugasPacking($this->pekerja, null);
    $kerdus = $tanpaTagihan->getOrActivateBoxForJenis($this->jenisA5);

    expect($kerdus->gudang_id)->toBe($this->smg->id);

    // Cadangan kedua: penugasa yang sedang login (pekerja punya tugas lain tanpa gudang).
    $this->actingAs($this->spvBtl);
    $lain = DailyPackingTask::factory()->create([
        'user_id' => User::factory()->create(['gudang_id' => null])->id,
        'gudang_id' => null,
        'tanggal_tugas' => now()->toDateString(),
    ]);

    expect($lain->getOrActivateBoxForJenis($this->jenisA5)->gudang_id)->toBe($this->btl->id);
})->group('lokasi-mushaf');

it('tidak menebak gudang bila ketiga sumbernya kosong', function () {
    $tanpaGudang = User::factory()->create(['gudang_id' => null]);
    $tugas = tugasPacking($tanpaGudang, null);

    expect($tugas->getOrActivateBoxForJenis($this->jenisA5)->gudang_id)->toBeNull();
})->group('lokasi-mushaf');

it('mengisi lokasi resi dari kerdus saat mushaf dimasukkan', function () {
    $tugas = tugasPacking($this->pekerja, $this->btl->id);
    $kerdus = $tugas->getOrActivateBoxForJenis($this->jenisA5);
    $resi = resiPacking();

    $kerdus->addItemSafe($resi, $this->pekerja->id);

    expect($resi->fresh()->gudang_id)->toBe($this->btl->id);
})->group('lokasi-mushaf');

it('membiarkan kerdus mewarisi gudang dari resinya bila kerdus belum punya', function () {
    $tugas = tugasPacking($this->pekerja, null);
    $kerdus = PackingBox::factory()->create([
        'daily_packing_task_id' => $tugas->id,
        'jenis_quran_id' => $this->jenisA5,
        'status' => PackingBox::STATUS_EMPTY,
        'jumlah_terisi' => 0,
        'gudang_id' => null,
    ]);
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $kerdus->addItemSafe($resi, $this->pekerja->id);

    expect($kerdus->fresh()->gudang_id)->toBe($this->smg->id)
        ->and($resi->fresh()->gudang_id)->toBe($this->smg->id);
})->group('lokasi-mushaf');

it('mengisi lokasi resi dari gudang pelaku saat status bergerak di tahap gudang', function () {
    $resi = resiPacking();
    $siapPacking = StatusPengiriman::where('slug', 'selesai-packing')->value('id');

    $this->actingAs($this->spvSmg);
    $resi->updateStatus($siapPacking, null, $this->spvSmg->id);

    expect($resi->fresh()->gudang_id)->toBe($this->smg->id);

    // Sekali ditentukan, TIDAK ditimpa oleh pelaku dari gudang lain: perpindahan harus
    // lewat dokumen, bukan karena ada orang lain membuka daftar.
    $this->actingAs($this->spvBtl);
    $resi->fresh()->updateStatus(StatusPengiriman::where('slug', 'packing')->value('id'), null, $this->spvBtl->id);

    expect($resi->fresh()->gudang_id)->toBe($this->smg->id);
})->group('lokasi-mushaf');

it('menampilkan dan menyaring kolom Gudang di Data Resi', function () {
    $berlokasi = resiPacking(['gudang_id' => $this->btl->id, 'no_resi' => 'EQ-2026-90001']);
    $tanpaLokasi = resiPacking(['gudang_id' => null, 'no_resi' => 'EQ-2026-90002']);

    $props = fn (array $query) => $this->actingAs($this->superAdmin)
        ->get('/admin/pengiriman?'.http_build_query($query))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $semua = $props([]);
    $noResi = collect($semua['pengiriman']['data'])->pluck('no_resi');
    $baris = collect($semua['pengiriman']['data'])->firstWhere('no_resi', 'EQ-2026-90001');

    expect($noResi)->toContain('EQ-2026-90001', 'EQ-2026-90002')
        // Relasinya ikut dimuat: kolom Gudang di layar membaca `item.gudang.nama`.
        ->and($baris['gudang']['nama'])->toBe('Gudang Boyolali')
        ->and($semua['gudangList'])->toHaveCount(2);

    $hanyaBtl = collect($props(['gudang' => $this->btl->id])['pengiriman']['data'])->pluck('no_resi');
    expect($hanyaBtl)->toContain('EQ-2026-90001')->not->toContain('EQ-2026-90002');

    $belum = collect($props(['gudang' => 'belum'])['pengiriman']['data'])->pluck('no_resi');
    expect($belum)->toContain('EQ-2026-90002')->not->toContain('EQ-2026-90001');
})->group('lokasi-mushaf');

it('memindahkan lokasi mushaf HANYA saat dokumen diterima', function () {
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$resi->id],
            'catatan' => 'permintaan SPV Boyolali',
        ])
        ->assertSuccessful();

    $dokumen = TransferMushaf::firstOrFail();

    // Selama masih berjalan, mushafnya MASIH di gudang asal.
    expect($dokumen->status)->toBe(TransferMushaf::STATUS_DIPROSES)
        ->and($dokumen->jumlah_resi)->toBe(1)
        ->and($dokumen->gudang_asal_id)->toBe($this->smg->id)
        ->and($resi->fresh()->gudang_id)->toBe($this->smg->id);

    // Gudang ASAL tidak boleh menerima dokumennya sendiri.
    $this->actingAs($this->spvSmg)
        ->post("/admin/transfer-mushaf/{$dokumen->id}/terima")
        ->assertForbidden();

    $this->actingAs($this->spvBtl)
        ->post("/admin/transfer-mushaf/{$dokumen->id}/terima")
        ->assertSuccessful();

    expect($dokumen->fresh()->status)->toBe(TransferMushaf::STATUS_DITERIMA)
        ->and($dokumen->fresh()->received_by)->toBe($this->spvBtl->id)
        ->and($resi->fresh()->gudang_id)->toBe($this->btl->id);
})->group('lokasi-mushaf');

it('tidak mengubah lokasi mushaf saat dokumen dibatalkan', function () {
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$resi->id],
        ])
        ->assertSuccessful();

    $dokumen = TransferMushaf::firstOrFail();

    $this->actingAs($this->spvSmg)
        ->post("/admin/transfer-mushaf/{$dokumen->id}/batalkan")
        ->assertSuccessful();

    expect($dokumen->fresh()->status)->toBe(TransferMushaf::STATUS_DIBATALKAN)
        ->and($resi->fresh()->gudang_id)->toBe($this->smg->id)
        ->and($dokumen->items()->count())->toBe(0);
})->group('lokasi-mushaf');

it('menolak mushaf yang tidak bisa dipindahkan dengan alasan yang bisa dibaca', function () {
    $ditolak = fn (string $pesan) => fn ($respons) => $respons->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', $pesan);

    // 1. Sudah di dalam kerdus: kerdus berisi banyak resi, memindahkan satu resinya saja
    //    membuat isi kerdus berpindah sebagian.
    $tugas = tugasPacking($this->pekerja, $this->smg->id);
    $kerdus = $tugas->getOrActivateBoxForJenis($this->jenisA5);
    $diKerdus = resiPacking();
    $kerdus->addItemSafe($diKerdus, $this->pekerja->id);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$diKerdus->id],
        ])
        ->assertJsonPath('success', false);

    expect(Pengiriman::find($diKerdus->id)->gudang_id)->toBe($this->smg->id);

    // 2. Mushaf yang sudah diterima penerima tidak ada lagi di gudang.
    $selesai = resiPacking([
        'gudang_id' => $this->smg->id,
        'status_id' => StatusPengiriman::where('slug', 'diterima')->value('id'),
    ]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$selesai->id],
        ])
        ->assertStatus(422);

    // 3. Asal = tujuan.
    $biasa = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->smg->id,
            'pengiriman_ids' => [$biasa->id],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Gudang asal dan tujuan tidak boleh sama.');

    // 4. Satu mushaf tidak boleh ada di dua dokumen berjalan.
    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$biasa->id],
        ])
        ->assertSuccessful();

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$biasa->id],
        ])
        ->assertStatus(422);
})->group('lokasi-mushaf');

it('membuka halaman perpindahan untuk SPV yang tidak punya warehouse.dashboard', function () {
    // Kalau halamannya ditaruh di dalam grup rute bergerbang warehouse.dashboard, pemegang
    // izin mushaf.transfer justru kena 403. Diuji langsung supaya tidak diam-diam kembali.
    expect($this->spvSmg->hasPermissionTo('warehouse.dashboard'))->toBeFalse();

    $this->actingAs($this->spvSmg)->get('/admin/transfer-mushaf')->assertSuccessful();
    $this->actingAs($this->superAdmin)->get('/admin/transfer-mushaf')->assertSuccessful();
})->group('lokasi-mushaf');

it('menolak peran tanpa izin memindahkan mushaf', function () {
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    // Freelancer gudang: boleh melihat, tidak boleh memindahkan.
    $this->actingAs($this->pekerja)
        ->post('/admin/transfer-mushaf', [
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$resi->id],
        ])
        ->assertForbidden();

    expect(TransferMushaf::count())->toBe(0)
        ->and($resi->fresh()->gudang_id)->toBe($this->smg->id);
})->group('lokasi-mushaf');

it('menyodorkan pilihan gudang di halaman packing untuk tugas yang belum bertagih', function () {
    // Tugas LAMA (lahir sebelum 16/10/2026, saat tugas belum otomatis membawa gudang
    // pekerjanya) — sejak itu DailyPackingTask::booted mengisinya, jadi disimulasikan.
    $tugas = tugasPacking($this->pekerja, null);
    DB::table('daily_packing_tasks')->where('id', $tugas->id)->update(['gudang_id' => null]);

    // Props: panel pilihan muncul karena gudangTugas masih kosong.
    $props = $this->actingAs($this->pekerja)
        ->get('/admin/warehouse/packing')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['gudangTugas'])->toBeNull()
        ->and($props['gudangList'])->toHaveCount(2);

    // Pekerja yang terpasang di Semarang menyimpan pilihannya → tersimpan ke TUGAS, bukan
    // hanya ke sesi: kerdus berikutnya mewarisi gudang ini.
    $this->actingAs($this->pekerja)
        ->postJson('/admin/warehouse/packing/gudang', ['gudang_id' => $this->smg->id])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($tugas->fresh()->gudang_id)->toBe($this->smg->id)
        ->and($tugas->getOrActivateBoxForJenis($this->jenisA5)->gudang_id)->toBe($this->smg->id);

    // Sekali saja: penetapan berikutnya ditolak, supaya pilihan ini tidak jadi celah
    // memindahkan lokasi mushaf tanpa dokumen perpindahan.
    $this->actingAs($this->pekerja)
        ->postJson('/admin/warehouse/packing/gudang', ['gudang_id' => $this->btl->id])
        ->assertStatus(422);
})->group('lokasi-mushaf');

it('menolak pekerja ber-gudang memilih gudang lain lewat panel packing', function () {
    // Tugas lama tanpa gudang (lihat tes di atas).
    $tugas = tugasPacking($this->pekerja, null);
    DB::table('daily_packing_tasks')->where('id', $tugas->id)->update(['gudang_id' => null]);

    $this->actingAs($this->pekerja)
        ->postJson('/admin/warehouse/packing/gudang', ['gudang_id' => $this->btl->id])
        ->assertForbidden();

    expect(DailyPackingTask::where('user_id', $this->pekerja->id)->value('gudang_id'))->toBeNull();
})->group('lokasi-mushaf');

it('mencari resi untuk dipindahkan lewat pencarian, bukan seluruh tabel', function () {
    resiPacking(['gudang_id' => $this->smg->id, 'no_resi' => 'EQ-2026-91001']);
    $diterima = resiPacking([
        'gudang_id' => $this->smg->id,
        'no_resi' => 'EQ-2026-91002',
        'status_id' => StatusPengiriman::where('slug', 'diterima')->value('id'),
    ]);

    $hasil = $this->actingAs($this->spvSmg)
        ->getJson('/admin/transfer-mushaf/cari?q=EQ-2026-91001')
        ->assertSuccessful()
        ->json('data');

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['no_resi'])->toBe('EQ-2026-91001')
        ->and($hasil[0]['boleh'])->toBeTrue()
        ->and($hasil[0]['gudang'])->toBe('Gudang Semarang');

    // Resi yang sudah diterima penerima tidak ditawarkan sama sekali.
    $akhir = $this->actingAs($this->spvSmg)
        ->getJson('/admin/transfer-mushaf/cari?q=EQ-2026-91002')
        ->assertSuccessful()
        ->json('data');

    expect($akhir)->toBeEmpty();
})->group('lokasi-mushaf');

it('menolak mengemas resi yang sedang dalam dokumen perpindahan', function () {
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', ['gudang_tujuan_id' => $this->btl->id, 'pengiriman_ids' => [$resi->id]])
        ->assertSuccessful();

    $kerdus = tugasPacking($this->pekerja, $this->smg->id)->getOrActivateBoxForJenis($this->jenisA5);

    expect(fn () => $kerdus->addItemSafe($resi->fresh(), $this->pekerja->id))
        ->toThrow(Exception::class, 'sedang dalam dokumen perpindahan');

    expect($resi->fresh()->packingItem)->toBeNull();
})->group('lokasi-mushaf');

it('menolak menerima dokumen bila resinya dikemas setelah dokumen dibuat', function () {
    // Race condition: syarat "tidak di dalam kerdus" dulu hanya dicek saat dokumen dibuat.
    // Resi yang masuk kerdus di gudang asal sebelum dokumen diterima akan pindah gudang
    // sendirian, sementara kerdusnya tetap di gudang asal.
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', ['gudang_tujuan_id' => $this->btl->id, 'pengiriman_ids' => [$resi->id]])
        ->assertSuccessful();

    $dokumen = TransferMushaf::firstOrFail();
    $kerdus = tugasPacking($this->pekerja, $this->smg->id)->getOrActivateBoxForJenis($this->jenisA5);

    // Dikemas lewat jalur yang tidak memeriksa dokumen (data lama / sebelum perbaikan).
    DB::table('packing_items')->insert([
        'packing_box_id' => $kerdus->id, 'pengiriman_id' => $resi->id, 'packed_by' => $this->pekerja->id,
        'packed_at' => now(), 'urutan_dalam_box' => 1, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($this->spvBtl)
        ->postJson("/admin/transfer-mushaf/{$dokumen->id}/terima")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($dokumen->fresh()->status)->toBe(TransferMushaf::STATUS_DIPROSES)
        ->and($resi->fresh()->gudang_id)->toBe($this->smg->id);
})->group('lokasi-mushaf');

it('memberi alasan yang bisa dibaca untuk resi yang sudah pernah dipindahkan', function () {
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', ['gudang_tujuan_id' => $this->btl->id, 'pengiriman_ids' => [$resi->id]])
        ->assertSuccessful();

    $this->actingAs($this->spvBtl)
        ->post('/admin/transfer-mushaf/'.TransferMushaf::firstOrFail()->id.'/terima')
        ->assertSuccessful();

    $respons = $this->actingAs($this->spvBtl)
        ->postJson('/admin/transfer-mushaf', ['gudang_tujuan_id' => $this->smg->id, 'pengiriman_ids' => [$resi->id]])
        ->assertStatus(422);

    expect($respons->json('message'))->toContain('sudah pernah dipindahkan')
        ->and($respons->json('message'))->not->toContain('SQLSTATE');
})->group('lokasi-mushaf');

it('membalas 422, bukan 500, saat dokumen diterima dua kali', function () {
    $resi = resiPacking(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->spvSmg)
        ->post('/admin/transfer-mushaf', ['gudang_tujuan_id' => $this->btl->id, 'pengiriman_ids' => [$resi->id]])
        ->assertSuccessful();

    $dokumen = TransferMushaf::firstOrFail();
    $basi = TransferMushaf::find($dokumen->id);

    $this->actingAs($this->spvBtl)->postJson("/admin/transfer-mushaf/{$dokumen->id}/terima")->assertSuccessful();

    // Klik kedua: instance basi masih "diproses", penjaga di dalam kunci yang menolak.
    $respons = app(TransferMushafController::class)
        ->terima(Request::create('/', 'POST')->setUserResolver(fn () => $this->spvBtl), $basi);

    expect($respons->getStatusCode())->toBe(422);
})->group('lokasi-mushaf');
