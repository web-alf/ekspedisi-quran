<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\PackingScanLog;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cakupan: pencatatan scan yang GAGAL di halaman packing (packing_scan_logs).
 *
 * Dulu scan yang ditolak (QR tidak valid, resi tidak ada, sudah dipacking)
 * hanya muncul sesaat di tab "Scan Terakhir" (variabel memori) lalu hilang
 * saat refresh. Sekarang setiap kegagalan dicatat permanen BESERTA petugas
 * yang melakukan scan-nya; scan sukses tetap tidak menghasilkan baris log.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Cache status per-proses harus dikosongkan: RefreshDatabase membuat ulang
    // tabel tiap uji, sedangkan runtime cache masih memegang ID uji sebelumnya.
    StatusPengirimanCache::clear();
});

/**
 * Petugas gudang aktif + tugas packing hari ini (syarat lolos gate scanItems).
 */
function petugasGudangDenganTugas(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::WAREHOUSE->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    DailyPackingTask::factory()->create([
        'user_id' => $user->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
    ]);

    return $user;
}

/**
 * Jenis A5 — dipakai ulang bila sudah ada (DB uji tidak selalu kosong;
 * RefreshDatabase memakai transaksi per uji, bukan migrate:fresh).
 */
function jenisA5(): JenisQuran
{
    return JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
}

it('mencatat scan QR tidak valid beserta petugasnya', function () {
    $petugas = petugasGudangDenganTugas();

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => 'bukan resi sama sekali'])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'invalid_qr');

    expect(PackingScanLog::count())->toBe(1);

    $log = PackingScanLog::first();
    expect($log->user_id)->toBe($petugas->id)
        ->and($log->type)->toBe('invalid_qr')
        ->and($log->qr_data)->toBe('bukan resi sama sekali')
        ->and($log->no_resi)->toBeNull()
        ->and($log->message)->toBe('QR Code tidak valid');
});

it('mencatat resi yang tidak ditemukan lengkap dengan nomor resinya', function () {
    $petugas = petugasGudangDenganTugas();

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => 'EQ-2026-99999'])
        ->assertOk()
        ->assertJsonPath('type', 'not_found');

    $log = PackingScanLog::first();
    expect($log->user_id)->toBe($petugas->id)
        ->and($log->type)->toBe('not_found')
        ->and($log->no_resi)->toBe('EQ-2026-99999');
});

it('mencatat item yang sudah dipacking sebelumnya', function () {
    $petugas = petugasGudangDenganTugas();
    $task = DailyPackingTask::where('user_id', $petugas->id)->first();

    $jenis = jenisA5();
    $pengiriman = Pengiriman::factory()->create(['jenis_quran_id' => $jenis->id]);
    $box = PackingBox::factory()->create([
        'daily_packing_task_id' => $task->id,
        'jenis_quran_id' => $jenis->id,
    ]);
    PackingItem::factory()->create([
        'packing_box_id' => $box->id,
        'pengiriman_id' => $pengiriman->id,
        'packed_by' => $petugas->id,
    ]);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $pengiriman->no_resi])
        ->assertOk()
        ->assertJsonPath('type', 'already_packed');

    $log = PackingScanLog::first();
    expect($log->type)->toBe('already_packed')
        ->and($log->no_resi)->toBe($pengiriman->no_resi)
        ->and($log->user_id)->toBe($petugas->id)
        ->and($log->daily_packing_task_id)->toBeNull(); // tugas belum diambil saat penolakan ini
});

it('tidak mencatat scan yang berhasil', function () {
    $petugas = petugasGudangDenganTugas();

    // DB uji bisa memuat sisa status dari run sebelumnya (transaksi, bukan
    // migrate:fresh) — pakai ulang bila sudah ada.
    $statusPacking = StatusPengiriman::firstOrCreate(
        ['slug' => 'packing'],
        ['nama' => 'Proses Packing', 'urutan' => 4],
    );

    $jenis = jenisA5();
    $pengiriman = Pengiriman::factory()->create([
        'jenis_quran_id' => $jenis->id,
        'status_id' => $statusPacking->id,
    ]);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $pengiriman->no_resi])
        ->assertOk()
        ->assertJsonPath('success', true);

    // Scan sukses punya jejak permanennya sendiri (packing_items);
    // packing_scan_logs khusus untuk yang gagal.
    expect(PackingScanLog::count())->toBe(0)
        ->and(PackingItem::count())->toBe(1);
});

it('menampilkan siapa yang melakukan scan; petugas tanpa akses packing ditolak', function () {
    $petugasA = petugasGudangDenganTugas();
    $petugasB = petugasGudangDenganTugas();

    $this->actingAs($petugasA)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => 'salah total']);
    $this->actingAs($petugasB)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => 'EQ-2026-88888']);

    expect(PackingScanLog::count())->toBe(2);

    // Staf gudang lolos gate viewAllHistory (warehouse.box.update_any),
    // jadi riwayat menampilkan seluruh petugas beserta namanya — inilah
    // yang diminta tim: "beserta orang yang melakukan scannya".
    $responsA = $this->actingAs($petugasA)
        ->getJson('/admin/warehouse/packing/scan-logs')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('semua_staf', true);
    $logA = collect($responsA->json('data'));
    expect($logA->count())->toBe(2)
        ->and($logA->pluck('oleh')->sort()->values()->all())
        ->toBe(collect([$petugasA->name, $petugasB->name])->sort()->values()->all());

    // Super-admin juga melihat semuanya (gate viewAllHistory).
    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $responsSuper = $this->actingAs($superAdmin)
        ->getJson('/admin/warehouse/packing/scan-logs')
        ->assertOk()
        ->assertJsonPath('semua_staf', true);
    expect(collect($responsSuper->json('data'))->count())->toBe(2);

    // Petugas tanpa izin packing (kurir) tidak boleh membuka endpoint ini.
    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($kurir)
        ->getJson('/admin/warehouse/packing/scan-logs')
        ->assertForbidden();
});

it('tanpa tugas aktif, penolakan tetap dicatat (siapa yang mencoba)', function () {
    $petugas = User::factory()->create(['is_active' => true]);
    $petugas->assignRole(RoleEnum::WAREHOUSE->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Tidak ada DailyPackingTask untuk hari ini -> gate scanItems menolak.
    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => 'EQ-2026-12345'])
        ->assertOk()
        ->assertJsonPath('type', 'unauthorized');

    $log = PackingScanLog::first();
    expect($log->type)->toBe('unauthorized')
        ->and($log->user_id)->toBe($petugas->id);
});

it('membatalkan penugasan resi saat resi gagal masuk kerdus, tetapi tetap mencatat log-nya', function () {
    // Dulu kegagalan addItemSafe dikembalikan sebagai respons biasa sehingga
    // transaksi tetap commit: task item tersimpan, resi terkunci ke petugas ini
    // dan tidak pernah bisa diambil staf lain maupun masuk kerdus.
    $petugas = petugasGudangDenganTugas();

    $statusPacking = StatusPengiriman::firstOrCreate(
        ['slug' => 'packing'],
        ['nama' => 'Proses Packing', 'urutan' => 4],
    );

    $jenis = jenisA5();
    $pengiriman = Pengiriman::factory()->create([
        'jenis_quran_id' => $jenis->id,
        'status_id' => $statusPacking->id,
        // Jauh melebihi kapasitas kerdus mana pun.
        'jumlah_quran' => 10000,
    ]);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $pengiriman->no_resi])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'box_add_error');

    expect(DailyPackingTaskItem::where('pengiriman_id', $pengiriman->id)->exists())->toBeFalse()
        ->and(PackingItem::where('pengiriman_id', $pengiriman->id)->exists())->toBeFalse()
        ->and(PackingScanLog::where('type', 'box_add_error')->where('no_resi', $pengiriman->no_resi)->exists())->toBeTrue();
});

it('menolak konfirmasi jenis untuk resi yang sudah dipacking tanpa error SQL', function () {
    // Klik "Lanjut" ganda atau scan dari perangkat lain: jalur konfirmasi dulu
    // tidak mengunci maupun mengecek PackingItem, sehingga request kedua jatuh
    // ke duplicate key di database.
    $petugas = petugasGudangDenganTugas();
    $task = DailyPackingTask::where('user_id', $petugas->id)->first();

    $jenis = jenisA5();
    $pengiriman = Pengiriman::factory()->create(['jenis_quran_id' => $jenis->id]);
    $box = PackingBox::factory()->create([
        'daily_packing_task_id' => $task->id,
        'jenis_quran_id' => $jenis->id,
    ]);
    PackingItem::factory()->create([
        'packing_box_id' => $box->id,
        'pengiriman_id' => $pengiriman->id,
        'packed_by' => $petugas->id,
    ]);

    $respons = $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan-confirm', [
            'pengiriman_id' => $pengiriman->id,
            'confirm_seal_others' => true,
        ])
        ->assertJsonPath('success', false);

    expect($respons->json('message'))->toContain('sudah dipacking')
        ->and(PackingItem::where('pengiriman_id', $pengiriman->id)->count())->toBe(1);
});
