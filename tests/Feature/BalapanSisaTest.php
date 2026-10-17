<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\Donatur;
use App\Models\Muatan;
use App\Models\MuatanItem;
use App\Models\MushafRequest;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\PindaiKerdusLog;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\UserPerformance;
use App\Services\Cache\StatusPengirimanCache;
use App\Services\PackingAssignmentService;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Race condition sisa setelah 244e2b6 & 62470b6 (temuan tinggi & sedang).
 *
 * Satu koneksi tidak bisa menjalankan dua request bersamaan, jadi "request lain" ditiru
 * dengan `sisipSetelahBacaan()`: tepat SETELAH kode membaca sesuatu TANPA KUNCI, perubahan
 * request lain ditulis langsung ke tabel. Itulah jendela balapannya — pembacaan tanpa
 * kunci lalu keputusan berdasarkan hasil yang sudah basi. Pembacaan berkunci (FOR UPDATE)
 * sengaja tidak dipakai sebagai pemicu: request lain memang tidak bisa menyela di sana.
 *
 * Setiap tes di sini gagal pada kode sebelum perbaikan.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    $this->seed(JenisQuranSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    StatusPengirimanCache::clear();
});

/**
 * Jalankan `$aksi` sekali, tepat setelah query SELECT tanpa kunci yang cocok `$pola`
 * (regex atas SQL) selesai dijalankan. `$aksi` boleh mengembalikan `false` untuk
 * menunggu query cocok berikutnya (mis. binding yang berbeda).
 */
function sisipSetelahBacaan(string $pola, Closure $aksi): void
{
    $sudah = false;

    DB::listen(function (QueryExecuted $query) use (&$sudah, $pola, $aksi) {
        $sql = strtolower($query->sql);

        if ($sudah || ! str_starts_with($sql, 'select') || str_contains($sql, 'for update')) {
            return;
        }

        if (preg_match($pola, $sql) === 1) {
            $sudah = true;

            if ($aksi($query) === false) {
                $sudah = false;
            }
        }
    });
}

function statusSlug(string $slug): StatusPengiriman
{
    return StatusPengiriman::where('slug', $slug)->firstOrFail();
}

function penggunaBalapan(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function resiBerstatus(string $slug, array $atribut = []): Pengiriman
{
    return Pengiriman::factory()->create(array_merge([
        'status_id' => statusSlug($slug)->id,
        'jumlah_quran' => 1,
    ], $atribut));
}

/** Kerdus tersegel berisi resi siap muat, sudah lolos verifikasi isi (cocok). */
function kerdusTerverifikasi(array $resinya, string $kode): PackingBox
{
    $box = PackingBox::factory()->create([
        'kode_kerdus' => $kode,
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
        'kapasitas' => count($resinya),
        'jumlah_terisi' => count($resinya),
        'status' => PackingBox::STATUS_SEALED,
        'sealed_at' => now(),
    ]);

    foreach ($resinya as $i => $resi) {
        PackingItem::factory()->create([
            'packing_box_id' => $box->id,
            'pengiriman_id' => $resi->id,
            'urutan_dalam_box' => $i + 1,
            'quantity' => 1,
        ]);
    }

    PindaiKerdusLog::create([
        'packing_box_id' => $box->id,
        'kode_kerdus' => $kode,
        'jumlah_mushaf' => count($resinya),
        'jumlah_fisik' => count($resinya),
        'hasil' => PindaiKerdusLog::HASIL_COCOK,
    ]);

    return $box;
}

// --- H1: status final / mundur dicek di dalam kunci -------------------------------------

it('H1: tidak mengeluarkan resi dari Diterima walau status perjalanan dibaca sebelum distribusi selesai', function () {
    $kurir = penggunaBalapan(RoleEnum::COURIER->value);
    $kurir->givePermissionTo(['muatan.read', 'muatan.scan']);
    $muatan = Muatan::factory()->untukKurir($kurir)->create();
    $resi = resiBerstatus('pengiriman');
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => $resi->id]);

    $diterima = statusSlug('diterima')->id;

    // Request lain (distribusi menyelesaikan muatan) commit tepat setelah request kurir
    // membaca daftar resinya.
    sisipSetelahBacaan('/from `pengiriman` where `id` in/', function () use ($resi, $diterima) {
        DB::table('pengiriman')->where('id', $resi->id)->update(['status_id' => $diterima]);
    });

    $this->actingAs($kurir)
        ->postJson(route('admin.muatan.status-perjalanan', $muatan), ['status_id' => statusSlug('batal')->id])
        ->assertSuccessful()
        ->assertJsonPath('gagal', 1);

    expect($resi->fresh()->status_id)->toBe($diterima);
});

it('H1: menolak perubahan dari instance basi lewat ubahStatusBilaBoleh', function () {
    $resi = resiBerstatus('pengiriman');
    $basi = Pengiriman::with('status')->find($resi->id);

    DB::table('pengiriman')->where('id', $resi->id)->update(['status_id' => statusSlug('diterima')->id]);

    expect($basi->ubahStatusBilaBoleh(statusSlug('batal')))->toBe('ditolak')
        ->and($resi->fresh()->status->slug)->toBe('diterima');

    // Status mundur juga ditolak, sedangkan maju tetap boleh.
    $lain = resiBerstatus('pengiriman');
    expect($lain->ubahStatusBilaBoleh(statusSlug('selesai-packing')))->toBe('ditolak')
        ->and($lain->ubahStatusBilaBoleh(statusSlug('tiba')))->toBe('berhasil')
        ->and($lain->fresh()->status->slug)->toBe('tiba');
});

// --- M1: total muatan dihitung dalam satu pernyataan ------------------------------------

it('M1: total muatan tidak kehilangan resi yang masuk di sela hitung dan tulis', function () {
    $muatan = Muatan::factory()->create();
    MuatanItem::factory()->create(['muatan_id' => $muatan->id, 'pengiriman_id' => resiBerstatus('selesai-packing')->id]);
    $resiLain = resiBerstatus('selesai-packing');

    // Pindai kedua ke muatan yang sama commit tepat setelah hitungan pertama dibaca.
    sisipSetelahBacaan('/count\(\*\).*from `muatan_items`/', function () use ($muatan, $resiLain) {
        DB::table('muatan_items')->insert([
            'muatan_id' => $muatan->id,
            'pengiriman_id' => $resiLain->id,
            'urutan' => 2,
            'dimuat_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $muatan->syncTotals();

    // Pindai kedua memanggil syncTotals sendiri; yang diuji: hitungan pertama tidak
    // menimpa angka yang sudah lebih baru dengan angka basi.
    expect($muatan->fresh()->total_resi)->toBe(MuatanItem::where('muatan_id', $muatan->id)->count());
});

// --- M2: pindai kerdus di halaman kurir utuh atau tidak sama sekali ----------------------

it('M2: tidak memecah satu kerdus ke dua muatan dan tidak membalas 500', function () {
    $kurir = penggunaBalapan(RoleEnum::COURIER->value);
    $kurir->givePermissionTo(['muatan.read', 'muatan.scan']);
    $muatanKu = Muatan::factory()->untukKurir($kurir)->create(['tanggal_muatan' => today()]);
    $muatanLain = Muatan::factory()->create();

    [$a, $b, $c] = [resiBerstatus('selesai-packing'), resiBerstatus('selesai-packing'), resiBerstatus('selesai-packing')];
    $box = kerdusTerverifikasi([$a, $b, $c], 'KB-20261015-009-A5-01');

    // Pemindai lain memuat resi C ke muatan lain tepat setelah pemindai ini memeriksanya.
    sisipSetelahBacaan('/from `muatan_items` where `pengiriman_id` = \?/', function (QueryExecuted $q) use ($c, $muatanLain) {
        if ((int) ($q->bindings[0] ?? 0) !== $c->id) {
            return false;
        }

        DB::table('muatan_items')->insert([
            'muatan_id' => $muatanLain->id, 'pengiriman_id' => $c->id, 'urutan' => 1,
            'dimuat_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    $this->actingAs($kurir)
        ->postJson(route('admin.kurir.pindai.box'), ['kode' => $box->kode_kerdus, 'muatan_id' => $muatanKu->id])
        ->assertStatus(409);

    expect(MuatanItem::where('muatan_id', $muatanKu->id)->count())->toBe(0);
});

// --- M3: permintaan mushaf diselesaikan setelah commit ----------------------------------

it('M3: memutuskan "selesai" setelah commit, bukan di tengah transaksi resi', function () {
    $permintaan = MushafRequest::factory()->create(['status' => 'processed']);
    $a = resiBerstatus('pengiriman', ['mushaf_request_id' => $permintaan->id]);
    $b = resiBerstatus('pengiriman', ['mushaf_request_id' => $permintaan->id]);
    $permintaan->update(['pengiriman_id' => $a->id]);

    $diterima = statusSlug('diterima')->id;

    DB::transaction(function () use ($a, $b, $diterima, $permintaan) {
        tibakan($a);
        $a->updateStatus($diterima);
        tibakan($b);
        $b->updateStatus($diterima);

        // Selama transaksi belum commit, keputusan belum diambil: di dunia nyata
        // transaksi lain belum melihat resi ini diterima, jadi keputusan di sini bisa
        // salah (dua resi terakhir sama-sama melihat yang lain belum diterima).
        expect($permintaan->fresh()->status)->toBe('processed');
    });

    expect($permintaan->fresh()->status)->toBe('completed');
});

it('M3: tidak menyelesaikan permintaan yang baru sebagian resinya diterima', function () {
    $permintaan = MushafRequest::factory()->create(['status' => 'processed']);
    $a = resiBerstatus('pengiriman', ['mushaf_request_id' => $permintaan->id]);
    resiBerstatus('pengiriman', ['mushaf_request_id' => $permintaan->id]);
    $permintaan->update(['pengiriman_id' => $a->id]);

    tibakan($a);
    $a->updateStatus(statusSlug('diterima')->id);

    expect($permintaan->fresh()->status)->toBe('processed');
});

// --- M4: set alamat massal tidak menimpa resi milik permintaan lain ---------------------

it('M4: menolak set alamat massal atas resi yang baru diklaim permintaan lain', function () {
    $admin = penggunaBalapan(RoleEnum::SUPER_ADMIN->value);
    $permintaanLama = MushafRequest::factory()->create(['status' => 'processed']);
    $permintaanBaru = MushafRequest::factory()->create(['status' => 'approved', 'pengiriman_id' => null]);

    // Resi X sudah diklaim "Proses ke Pengiriman" permintaan lain setelah daftar dibuka.
    $resi = resiBerstatus('selesai-packing', [
        'mushaf_request_id' => $permintaanLama->id,
        'alamat_tujuan' => 'Alamat permintaan lama',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => [$resi->id],
            'alamat_tujuan' => 'Alamat permintaan baru',
            'mushaf_request_id' => $permintaanBaru->id,
        ])
        ->assertSessionHasErrors();

    expect($resi->fresh()->mushaf_request_id)->toBe($permintaanLama->id)
        ->and($resi->fresh()->alamat_tujuan)->toBe('Alamat permintaan lama')
        ->and($permintaanBaru->fresh()->status)->toBe('approved');
});

it('M4: tetap mengisi alamat resi yang belum dimiliki permintaan mana pun', function () {
    $admin = penggunaBalapan(RoleEnum::SUPER_ADMIN->value);
    $permintaan = MushafRequest::factory()->create(['status' => 'approved', 'pengiriman_id' => null]);
    $resi = resiBerstatus('selesai-packing', ['mushaf_request_id' => null, 'alamat_tujuan' => null]);

    $this->actingAs($admin)
        ->post(route('admin.pengiriman.bulk-alamat'), [
            'pengiriman_ids' => [$resi->id],
            'alamat_tujuan' => 'Jl. Baru 1',
            'mushaf_request_id' => $permintaan->id,
        ])
        ->assertSessionHasNoErrors();

    expect($resi->fresh()->mushaf_request_id)->toBe($permintaan->id)
        ->and($permintaan->fresh()->status)->toBe('processed');
});

// --- M5: set alamat per kerdus memakai permintaan yang dikunci --------------------------

it('M5: tidak memenuhi satu permintaan dua kali saat diproses pihak lain bersamaan', function () {
    $admin = penggunaBalapan(RoleEnum::SUPER_ADMIN->value);
    $permintaan = MushafRequest::factory()->create(['status' => 'approved', 'pengiriman_id' => null]);
    $resi = resiBerstatus('selesai-packing');
    $box = kerdusTerverifikasi([$resi], 'KB-20261015-009-A5-05');
    $kirimanLain = resiBerstatus('selesai-packing');

    // CS lain menjalankan "Proses ke Pengiriman" tepat setelah permintaan dibaca.
    // Pola menyasar pembacaan model permintaan, bukan query validasi `exists`.
    sisipSetelahBacaan('/^select \* from `mushaf_requests` where/', function () use ($permintaan, $kirimanLain) {
        DB::table('mushaf_requests')->where('id', $permintaan->id)
            ->update(['status' => 'processed', 'pengiriman_id' => $kirimanLain->id]);
    });

    $this->actingAs($admin)
        ->postJson(route('admin.warehouse.box-scanner.set-mushaf-address'), [
            'box_id' => $box->id,
            'mushaf_request_id' => $permintaan->id,
        ]);

    // Invarian: satu permintaan tidak terpenuhi DUA kali (oleh kiriman lain DAN oleh
    // kerdus ini). Pada kode baru tidak ada pembacaan permintaan tanpa kunci, jadi
    // "proses pihak lain" tidak bisa menyela: kerdus tertaut dan permintaan ditandai
    // diproses atas kerdus ini — proses pihak lain yang datang sesudahnya akan ditolak.
    $terpenuhiDuaKali = (int) $resi->fresh()->mushaf_request_id === $permintaan->id
        && (int) $permintaan->fresh()->pengiriman_id === $kirimanLain->id;

    expect($terpenuhiDuaKali)->toBeFalse()
        ->and($permintaan->fresh()->status)->toBe('processed');
});

it('M5: menandai permintaan diproses setelah kerdus ditautkan, sehingga tidak bisa ditautkan dua kali', function () {
    $admin = penggunaBalapan(RoleEnum::SUPER_ADMIN->value);
    $permintaan = MushafRequest::factory()->create(['status' => 'approved', 'pengiriman_id' => null]);
    $boxA = kerdusTerverifikasi([resiBerstatus('selesai-packing')], 'KB-20261015-009-A5-06');
    $boxB = kerdusTerverifikasi([resiBerstatus('selesai-packing')], 'KB-20261015-009-A5-07');

    $this->actingAs($admin)
        ->postJson(route('admin.warehouse.box-scanner.set-mushaf-address'), ['box_id' => $boxA->id, 'mushaf_request_id' => $permintaan->id])
        ->assertSuccessful();

    $this->actingAs($admin)
        ->postJson(route('admin.warehouse.box-scanner.set-mushaf-address'), ['box_id' => $boxB->id, 'mushaf_request_id' => $permintaan->id])
        ->assertStatus(400);

    expect($permintaan->fresh()->status)->toBe('processed')
        ->and(Pengiriman::where('mushaf_request_id', $permintaan->id)->count())->toBe(1);
});

// --- M6: progres tugas dibandingkan dengan angka terbaru --------------------------------

it('M6: menandai tugas selesai walau instance tugasnya dimuat sebelum pindai lain', function () {
    $tugas = DailyPackingTask::factory()->create([
        'tanggal_tugas' => today(),
        'total_target' => 2,
        'total_selesai' => 0,
        'status' => DailyPackingTask::STATUS_IN_PROGRESS,
    ]);

    $basi = DailyPackingTask::find($tugas->id);

    // Pindai lain menaikkan progres lebih dulu.
    DB::table('daily_packing_tasks')->where('id', $tugas->id)->increment('total_selesai');

    $basi->incrementProgress();

    expect($tugas->fresh()->total_selesai)->toBe(2)
        ->and($tugas->fresh()->status)->toBe(DailyPackingTask::STATUS_COMPLETED);
});

it('M6: tidak menghitung hari selesai dua kali saat progres terus bertambah', function () {
    $tugas = DailyPackingTask::factory()->create([
        'tanggal_tugas' => today(),
        'total_target' => 1,
        'total_selesai' => 0,
        'status' => DailyPackingTask::STATUS_IN_PROGRESS,
    ]);

    $tugas->incrementProgress();
    $hariSelesai = (int) UserPerformance::where('user_id', $tugas->user_id)->sum('total_hari_complete');

    $tugas->incrementProgress();

    expect((int) UserPerformance::where('user_id', $tugas->user_id)->sum('total_hari_complete'))->toBe($hariSelesai);
});

// --- M8: redistribusi klik ganda --------------------------------------------------------

it('M8: klik ganda redistribusi tidak menggeser target dua kali', function () {
    $sumber = DailyPackingTask::factory()->create(['tanggal_tugas' => today(), 'total_target' => 5, 'total_selesai' => 0]);
    $penerima = User::factory()->create(['is_active' => true]);
    $tujuan = DailyPackingTask::factory()->create([
        'user_id' => $penerima->id, 'tanggal_tugas' => today(), 'total_target' => 3,
        'status' => DailyPackingTask::STATUS_COMPLETED,
    ]);

    foreach ([resiBerstatus('packing'), resiBerstatus('packing')] as $resi) {
        DailyPackingTaskItem::factory()->create([
            'daily_packing_task_id' => $sumber->id,
            'pengiriman_id' => $resi->id,
            'is_packed' => false,
        ]);
    }

    $layanan = app(PackingAssignmentService::class);

    // Klik pertama selesai tepat setelah klik kedua membaca daftar item.
    sisipSetelahBacaan('/from `daily_packing_task_items`.*`is_packed`/', function () use ($layanan, $sumber, $penerima) {
        $layanan->redistributeTasks(DailyPackingTask::find($sumber->id), [$penerima->id]);
    });

    $layanan->redistributeTasks(DailyPackingTask::find($sumber->id), [$penerima->id]);

    expect($tujuan->fresh()->total_target)->toBe(5)
        ->and($sumber->fresh()->total_target)->toBe(3)
        ->and(DailyPackingTaskItem::where('daily_packing_task_id', $tujuan->id)->count())->toBe(2);
});

// --- M10: kiriman ganda identik ---------------------------------------------------------

it('M10: klik ganda Simpan donatur tidak menggandakan donasi dan resi', function () {
    $admin = penggunaBalapan(RoleEnum::SUPER_ADMIN->value);

    $data = [
        'kode_donatur' => 'DN-GANDA-001',
        'nama_donatur' => 'Donatur Klik Ganda',
        'no_hp' => '+628123456789',
        'jenis_wakaf_dipilih' => ['A5'],
        'jumlah_a5' => 2,
        'jumlah_a6' => 0,
        'jumlah_iqra' => 0,
        'donation_date' => '2026-10-15',
        'prayer_mode' => 'semua_donatur',
        'doa_untuk_semua' => 'Doa',
    ];

    $this->actingAs($admin)->post('/admin/donatur', $data)->assertRedirect();
    $this->actingAs($admin)->post('/admin/donatur', $data)->assertRedirect()->assertSessionHas('error');

    $donatur = Donatur::where('kode_donatur', 'DN-GANDA-001')->firstOrFail();

    expect($donatur->fresh()->total_quran)->toBe(2)
        ->and(Pengiriman::where('donatur_id', $donatur->id)->count())->toBe(2);
});

it('M10: mengizinkan kiriman ulang yang sama setelah kiriman pertama gagal validasi', function () {
    $admin = penggunaBalapan(RoleEnum::SUPER_ADMIN->value);

    $salah = ['kode_donatur' => 'DN-GANDA-002', 'nama_donatur' => 'Tanpa HP'];

    $this->actingAs($admin)->post('/admin/donatur', $salah)->assertSessionHasErrors();
    $this->actingAs($admin)->post('/admin/donatur', $salah)->assertSessionHasErrors('no_hp');
});
