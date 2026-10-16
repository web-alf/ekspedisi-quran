<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cakupan: alur "packing kardus" — mushaf datang SUDAH dalam kardus/dos dari
 * produksi, jadi gudang tidak memindai tiap mushaf; cukup menyebut jenis +
 * jumlah kerdus.
 *
 * Yang dikunci di sini:
 * - isi kerdus dipilih sistem secara FIFO (resi paling lama menunggu lebih
 *   dulu), deterministik & bisa diaudit;
 * - tiap mushaf tetap terkait ke resi/QR-nya (packing_items menyimpan
 *   pengiriman_id + packed_by), karena sertifikat & pelacakan donatur
 *   menempel ke resi;
 * - progres tugas naik sejumlah mushaf (eks);
 * - stok kurang -> kerdus "terisi" + kekurangan dilaporkan; resi yang tidak
 *   cukup ruang dilaporkan sebagai penghalang, TIDAK diam-diam dilewati;
 * - resi di luar status packing / yang sudah pernah dikemas tidak ikut.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    StatusPengirimanCache::clear();
});

function kardusPetugas(): User
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

function kardusJenisA5(): JenisQuran
{
    $jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();

    // Kapasitas dipatok supaya uji tidak bergantung pada isi seeder.
    $jenis->update(['default_capacity' => 20]);

    return $jenis->refresh();
}

function kardusStatus(string $slug, string $nama, int $urutan): StatusPengiriman
{
    return StatusPengiriman::firstOrCreate(
        ['slug' => $slug],
        ['nama' => $nama, 'urutan' => $urutan],
    );
}

/**
 * Resi berstatus packing untuk jenis tertentu, dibuat berurutan supaya id naik
 * dan urutan FIFO bisa diperiksa.
 *
 * @param  array<int, int>  $eksPerResi  jumlah mushaf tiap resi (default 1 eks)
 * @return array<int, Pengiriman>
 */
function kardusResi(JenisQuran $jenis, int $jumlah, array $eksPerResi = []): array
{
    $statusPacking = kardusStatus('packing', 'Proses Packing', 4);
    $daftar = [];

    for ($i = 0; $i < $jumlah; $i++) {
        $daftar[] = Pengiriman::factory()->create([
            'jenis_quran_id' => $jenis->id,
            'status_id' => $statusPacking->id,
            'jumlah_quran' => $eksPerResi[$i] ?? 1,
        ]);
    }

    return $daftar;
}

it('mencatat 1 kerdus penuh tanpa memindai tiap mushaf, isinya tetap terkait ke resi', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();
    $resi = kardusResi($jenis, 20);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_mushaf', 20)
        ->assertJsonPath('data.kekurangan', 0)
        ->assertJsonPath('data.kerdus.0.jumlah_terisi', 20)
        ->assertJsonPath('data.kerdus.0.status', 'sealed');

    // Progres tugas dalam mushaf (eks), bukan jumlah resi.
    expect(DailyPackingTask::where('user_id', $petugas->id)->first()->total_selesai)->toBe(20);

    // Tiap mushaf tetap punya baris kerdus sendiri yang menunjuk resinya.
    $itemResi = PackingItem::with('packingBox')->get();
    expect($itemResi)->toHaveCount(20)
        ->and($itemResi->pluck('pengiriman_id')->sort()->values()->all())
        ->toBe(collect($resi)->pluck('id')->sort()->values()->all())
        ->and($itemResi->pluck('packed_by')->unique()->all())->toBe([$petugas->id]);

    // Kerdus penuh -> tersegel -> isinya naik ke selesai-packing.
    $statusSelesai = kardusStatus('selesai-packing', 'Selesai Packing', 5);
    expect(PackingBox::first()->status)->toBe('sealed')
        ->and(Pengiriman::whereIn('id', collect($resi)->pluck('id'))->where('status_id', $statusSelesai->id)->count())->toBe(20);
});

it('mengisi kerdus secara FIFO: resi paling lama menunggu lebih dulu, sisanya tidak disentuh', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();
    $resi = kardusResi($jenis, 25);

    $this->actingAs($petugas)->postJson('/admin/warehouse/packing/catat-kerdus', [
        'jenis_quran_id' => $jenis->id,
        'jumlah_kerdus' => 1,
    ])->assertOk();

    $idTerpakai = PackingItem::pluck('pengiriman_id')->sort()->values()->all();
    $idHarapan = collect($resi)->take(20)->pluck('id')->sort()->values()->all();
    $idSisa = collect($resi)->skip(20)->pluck('id')->sort()->values()->all();

    expect($idTerpakai)->toBe($idHarapan)
        ->and(DailyPackingTaskItem::whereIn('pengiriman_id', $idSisa)->count())->toBe(0);
});

it('melaporkan kekurangan bila stok resi tidak cukup satu kerdus penuh', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();
    kardusResi($jenis, 12);

    $respons = $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_mushaf', 12)
        ->assertJsonPath('data.diminta', 20)
        ->assertJsonPath('data.kekurangan', 8)
        ->assertJsonPath('data.kerdus.0.jumlah_terisi', 12)
        ->assertJsonPath('data.kerdus.0.status', 'filling');

    // Kerdus separuh JANGAN tampak seperti kerdus penuh.
    expect(PackingBox::first()->status)->toBe('filling')
        ->and(DailyPackingTask::where('user_id', $petugas->id)->first()->total_selesai)->toBe(12)
        ->and($respons->json('data.jenis.kapasitas'))->toBe(20);
});

it('melaporkan resi yang tidak cukup ruang sebagai penghalang, tanpa melanggar FIFO', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();

    // Resi pertama butuh 60 eks (tidak muat di kerdus A5 20 eks), lalu 20 resi biasa.
    $resiBesar = kardusResi($jenis, 1, [60])[0];
    kardusResi($jenis, 20);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.tertahan.no_resi', $resiBesar->no_resi)
        ->assertJsonPath('data.tertahan.butuh', 60)
        ->assertJsonPath('data.tertahan.tersedia', 20)
        ->assertJsonPath('data.total_mushaf', 0);

    // Resi besar itu tetap belum dikemas (tidak dilewati diam-diam), dan tidak
    // ada resi di belakangnya yang menyerobot urutan.
    expect(PackingItem::count())->toBe(0)
        ->and(PackingBox::first()->jumlah_terisi)->toBe(0);
});

it('tidak mengambil resi yang belum berstatus packing atau sudah pernah dikemas', function () {
    $petugas = kardusPetugas();
    $taskId = DailyPackingTask::where('user_id', $petugas->id)->value('id');
    $jenis = kardusJenisA5();
    $statusPemesanan = kardusStatus('pemesanan', 'Proses Pemesanan', 1);
    $statusPacking = kardusStatus('packing', 'Proses Packing', 4);

    // Jenis lain (A6): satu resinya sudah dikemas, satunya belum — dua-duanya
    // harus diabaikan karena kerdus tidak boleh dicampur jenis.
    $jenisLain = JenisQuran::firstOrCreate(
        ['kode_jenis' => 'A6'],
        ['nama_jenis' => 'Al-Quran Ukuran A6', 'default_capacity' => 40, 'is_active' => true],
    );
    $sudahDikemas = Pengiriman::factory()->create([
        'jenis_quran_id' => $jenisLain->id,
        'status_id' => $statusPacking->id,
    ]);
    $boxLama = PackingBox::factory()->create([
        'daily_packing_task_id' => $taskId,
        'jenis_quran_id' => $jenisLain->id,
    ]);
    PackingItem::factory()->create([
        'packing_box_id' => $boxLama->id,
        'pengiriman_id' => $sudahDikemas->id,
        'packed_by' => $petugas->id,
    ]);

    $jenisLainResi = Pengiriman::factory()->create([
        'jenis_quran_id' => $jenisLain->id,
        'status_id' => $statusPacking->id,
    ]);

    // Jenis A5: satu resi belum berstatus packing, tiga siap dikemas.
    $belumPacking = Pengiriman::factory()->create([
        'jenis_quran_id' => $jenis->id,
        'status_id' => $statusPemesanan->id,
    ]);
    $baru = kardusResi($jenis, 3);

    $respons = $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.total_mushaf', 3)
        ->assertJsonPath('data.kekurangan', 17);

    // Hanya kerdus A5 baru ini yang boleh menyentuh ketiga resi tersebut.
    $idDiKerdusBaru = PackingBox::find($respons->json('data.kerdus.0.id'))
        ->packingItems()
        ->pluck('pengiriman_id')
        ->sort()
        ->values()
        ->all();

    expect($idDiKerdusBaru)->toBe(collect($baru)->pluck('id')->sort()->values()->all())
        ->and($idDiKerdusBaru)->not->toContain($belumPacking->id)
        ->and($idDiKerdusBaru)->not->toContain($jenisLainResi->id)
        ->and($idDiKerdusBaru)->not->toContain($sudahDikemas->id);
});

it('menghitung progres dalam mushaf (eks) walau satu resi berisi banyak eks', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();

    // 1 resi berisi 4 eks + 16 resi berisi 1 eks = tepat 20 eks (1 kerdus A5 penuh).
    kardusResi($jenis, 1, [4]);
    kardusResi($jenis, 16);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.total_mushaf', 20)
        ->assertJsonPath('data.kerdus.0.jumlah_terisi', 20)
        ->assertJsonPath('data.kerdus.0.penuh', true);

    expect(PackingItem::count())->toBe(17)
        ->and(DailyPackingTask::where('user_id', $petugas->id)->first()->total_selesai)->toBe(20);
});

it('mencatat beberapa kerdus sekaligus', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();
    kardusResi($jenis, 40);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.jumlah_kerdus', 2)
        ->assertJsonPath('data.total_mushaf', 40)
        ->assertJsonPath('data.kekurangan', 0);

    expect(PackingBox::count())->toBe(2)
        ->and(PackingBox::pluck('kode_kerdus')->unique())->toHaveCount(2)
        ->and(DailyPackingTask::where('user_id', $petugas->id)->first()->total_selesai)->toBe(40);
});

it('menolak dengan pesan jelas bila tidak ada resi berstatus packing untuk jenis itu', function () {
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => $jenis->id,
            'jumlah_kerdus' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'kardus_error');

    expect(PackingBox::count())->toBe(0)
        ->and(PackingItem::count())->toBe(0);
});

it('menolak petugas tanpa tugas aktif dan petugas tanpa izin packing', function () {
    $jenis = kardusJenisA5();
    kardusResi($jenis, 3);

    $tanpaTugas = User::factory()->create(['is_active' => true]);
    $tanpaTugas->assignRole(RoleEnum::WAREHOUSE->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($tanpaTugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', ['jenis_quran_id' => $jenis->id])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'permission_error');

    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($kurir)
        ->postJson('/admin/warehouse/packing/catat-kerdus', ['jenis_quran_id' => $jenis->id])
        ->assertForbidden();

    expect(PackingItem::count())->toBe(0);
});

it('memvalidasi jenis dan jumlah kerdus yang diminta', function () {
    $petugas = kardusPetugas();

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', ['jumlah_kerdus' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('jenis_quran_id');

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', ['jenis_quran_id' => 9999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('jenis_quran_id');

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', [
            'jenis_quran_id' => kardusJenisA5()->id,
            'jumlah_kerdus' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('jumlah_kerdus');
});

it('melewati resi yang dikemas staf lain di sela pemilihan FIFO, bukan gagal duplicate key', function () {
    // Race condition: subquery "belum dikemas" pada kolam FIFO dibaca dari snapshot
    // transaksi. Resi yang baru saja dikemas staf lain (commit di sela-sela) masih tampak
    // bebas, lalu addItemSafe gagal dengan duplicate key dan SELURUH catatan kerdus batal.
    // Staf lain ditiru dengan menyisipkan baris kerdusnya tepat setelah kolam dibaca.
    $petugas = kardusPetugas();
    $jenis = kardusJenisA5();
    $resi = kardusResi($jenis, 3);

    $kerdusLain = PackingBox::factory()->create([
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
        'jenis_quran_id' => $jenis->id,
    ]);

    $sudah = false;
    DB::listen(function ($query) use (&$sudah, $resi, $kerdusLain, $petugas) {
        $sql = strtolower($query->sql);

        if ($sudah || ! str_contains($sql, 'daily_packing_task_items') || ! str_contains($sql, 'not exists')) {
            return;
        }

        $sudah = true;
        DB::table('packing_items')->insert([
            'packing_box_id' => $kerdusLain->id,
            'pengiriman_id' => $resi[0]->id,
            'packed_by' => $petugas->id,
            'packed_at' => now(),
            'urutan_dalam_box' => 1,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', ['jenis_quran_id' => $jenis->id, 'jumlah_kerdus' => 1])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_mushaf', 2);

    expect(PackingItem::where('pengiriman_id', $resi[0]->id)->value('packing_box_id'))->toBe($kerdusLain->id);
});
