<?php

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Tahap akhir distribusi: batch yang seluruh resinya sudah diterima ditutup dan
 * catatan sertifikatnya dibuat.
 *
 * Yang perlu dibuktikan di sini bukan "ada perintahnya", tapi bahwa RANTAINYA
 * tersambung. Rantai itu putus di dua tempat di produksi:
 *
 *   1. Empat dari lima jalur pembuatan resi tidak mengisi `wakaf_batch_id`,
 *      sehingga `WakafBatch::pengiriman()` selalu kosong. Karena itu
 *      `WakafBatch::updateStatus()` selalu menyimpulkan "belum ada pengiriman",
 *      lalu menulis ulang status batch menjadi pending_distribution. Itu sebabnya
 *      15.552 batch menggantung walau observer dan perintah refresh sudah lama ada.
 *   2. Tidak ada apa pun yang menutup batch saat resinya sampai tujuan.
 *
 * Uji terpenting: `status batch ikut maju setelah resinya tertaut` — itu bukti
 * rantai utuh dari resi sampai batch, dan akan gagal kalau tautannya putus lagi.
 */
beforeEach(function () {
    // Jamin user id 1 ada: catatan sertifikat memakai auth()->id() ?? 1, dan
    // `generated_by` punya foreign key ke users.
    User::factory()->create();

    foreach (['pemesanan', 'produksi', 'kedatangan', 'packing', 'selesai-packing', 'pengiriman'] as $i => $slug) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $i + 1, 'is_active' => true, 'is_final' => false]
        );
    }

    StatusPengiriman::firstOrCreate(
        ['slug' => 'diterima'],
        ['nama' => 'Diterima Penerima', 'urutan' => 7, 'is_active' => true, 'is_final' => true]
    );

    StatusPengiriman::firstOrCreate(
        ['slug' => 'batal'],
        ['nama' => 'Batal', 'urutan' => 99, 'is_active' => true, 'is_final' => true]
    );
});

function statusTahapAkhir(string $slug): int
{
    return StatusPengiriman::query()->where('slug', $slug)->value('id');
}

/**
 * Membuat resi yang TIDAK tertaut ke batch — meniru data lama di produksi.
 * Batch dibuat SESUDAH resi supaya hook di Pengiriman::boot() tidak menautkannya.
 */
function resiLamaTahapAkhir(Donatur $donatur, int $statusId): Pengiriman
{
    return Pengiriman::factory()->create([
        'donatur_id' => $donatur->id,
        'status_id' => $statusId,
        'wakaf_batch_id' => null,
    ]);
}

describe('penautan resi baru ke batch', function () {
    it('menautkan resi baru ke batch milik donaturnya', function () {
        $donatur = Donatur::factory()->create();
        $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id]);

        $resi = Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'status_id' => statusTahapAkhir('pemesanan'),
        ]);

        expect($resi->fresh()->wakaf_batch_id)->toBe($batch->id);
    });

    it('tidak menebak kalau donaturnya punya lebih dari satu batch', function () {
        $donatur = Donatur::factory()->create();
        WakafBatch::factory()->create(['donatur_id' => $donatur->id]);
        WakafBatch::factory()->create(['donatur_id' => $donatur->id]);

        $resi = Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'status_id' => statusTahapAkhir('pemesanan'),
        ]);

        expect($resi->fresh()->wakaf_batch_id)->toBeNull();
    });

    it('menghormati tautan yang sudah diisi walaupun batchnya ganda', function () {
        $donatur = Donatur::factory()->create();
        WakafBatch::factory()->create(['donatur_id' => $donatur->id]);
        $batchB = WakafBatch::factory()->create(['donatur_id' => $donatur->id]);

        $resi = Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'status_id' => statusTahapAkhir('pemesanan'),
            'wakaf_batch_id' => $batchB->id,
        ]);

        expect($resi->fresh()->wakaf_batch_id)->toBe($batchB->id);
    });

    it('tidak menautkan resi ke batch milik donatur lain', function () {
        $donaturA = Donatur::factory()->create();
        $donaturB = Donatur::factory()->create();
        $batchB = WakafBatch::factory()->create(['donatur_id' => $donaturB->id]);

        $resi = Pengiriman::factory()->create([
            'donatur_id' => $donaturA->id,
            'status_id' => statusTahapAkhir('pemesanan'),
        ]);

        expect($resi->fresh()->wakaf_batch_id)->toBeNull()
            ->and($batchB->fresh()->pengiriman()->count())->toBe(0);
    });
});

describe('waktu terima resi', function () {
    it('mencatat received_at saat status berpindah ke diterima', function () {
        $resi = Pengiriman::factory()->create(['status_id' => statusTahapAkhir('produksi')]);

        expect($resi->fresh()->received_at)->toBeNull();

        $resi->updateStatus(statusTahapAkhir('diterima'));

        expect($resi->fresh()->received_at)->not->toBeNull();
    });

    it('tidak menimpa received_at yang sudah ada', function () {
        $sebelumnya = now()->subDays(3)->startOfSecond();

        $resi = Pengiriman::factory()->create([
            'status_id' => statusTahapAkhir('produksi'),
            'received_at' => $sebelumnya,
        ]);

        $resi->updateStatus(statusTahapAkhir('diterima'));

        expect($resi->fresh()->received_at->toDateTimeString())->toBe($sebelumnya->toDateTimeString());
    });

    it('tidak mencatat received_at untuk status selain diterima', function () {
        $resi = Pengiriman::factory()->create(['status_id' => statusTahapAkhir('pemesanan')]);

        $resi->updateStatus(statusTahapAkhir('packing'));

        expect($resi->fresh()->received_at)->toBeNull();
    });
});

describe('perintah penautan resi lama', function () {
    it('menautkan resi lama ke batch donaturnya', function () {
        $donatur = Donatur::factory()->create();
        $resi = resiLamaTahapAkhir($donatur, statusTahapAkhir('packing'));
        $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);

        expect($resi->fresh()->wakaf_batch_id)->toBeNull();

        $this->artisan('wakaf:tautkan-pengiriman')->assertSuccessful();

        expect($resi->fresh()->wakaf_batch_id)->toBe($batch->id);
    });

    it('melewatkan resi yang donaturnya tidak punya batch', function () {
        $donatur = Donatur::factory()->create();
        $resi = resiLamaTahapAkhir($donatur, statusTahapAkhir('packing'));

        $this->artisan('wakaf:tautkan-pengiriman')->assertSuccessful();

        expect($resi->fresh()->wakaf_batch_id)->toBeNull();
    });

    it('melewatkan resi yang donaturnya punya batch ganda', function () {
        $donatur = Donatur::factory()->create();
        $resi = resiLamaTahapAkhir($donatur, statusTahapAkhir('packing'));
        WakafBatch::factory()->create(['donatur_id' => $donatur->id]);
        WakafBatch::factory()->create(['donatur_id' => $donatur->id]);

        $this->artisan('wakaf:tautkan-pengiriman')->assertSuccessful();

        expect($resi->fresh()->wakaf_batch_id)->toBeNull();
    });

    it('tidak mengubah apa pun di mode kering', function () {
        $donatur = Donatur::factory()->create();
        $resi = resiLamaTahapAkhir($donatur, statusTahapAkhir('packing'));
        WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);

        $this->artisan('wakaf:tautkan-pengiriman --kering')->assertSuccessful();

        expect($resi->fresh()->wakaf_batch_id)->toBeNull();
    });

    it('aman dijalankan dua kali', function () {
        $donatur = Donatur::factory()->create();
        $resi = resiLamaTahapAkhir($donatur, statusTahapAkhir('packing'));
        $batch = WakafBatch::factory()->create(['donatur_id' => $donatur->id, 'status' => 'pending_distribution']);

        $this->artisan('wakaf:tautkan-pengiriman')->assertSuccessful();
        $this->artisan('wakaf:tautkan-pengiriman')->assertSuccessful();

        expect($resi->fresh()->wakaf_batch_id)->toBe($batch->id);
    });

    /**
     * Pemotongan per rentang id harus menautkan SELURUH resi, bukan hanya yang
     * kena potongan pertama. Versi sebelumnya memakai `UPDATE ... JOIN ... LIMIT`,
     * yang ditolak MySQL 8 (produksi) walau diterima MariaDB 10.11 (lokal) —
     * jadi uji ini menjaga jalur pemotongan yang benar-benar dipakai produksi.
     */
    it('menautkan semua resi walau diproses berpotong-potong', function () {
        $resi = [];

        // Resi dibuat LEBIH DULU, batch menyusul — kalau tidak, hook di
        // Pengiriman::boot() sudah menautkannya dan yang teruji bukan perintah ini.
        foreach (range(1, 5) as $i) {
            $donatur = Donatur::factory()->create();
            $resi[$donatur->id] = resiLamaTahapAkhir($donatur, statusTahapAkhir('packing'));
        }

        $batchPerDonatur = [];
        foreach (array_keys($resi) as $idDonatur) {
            $batchPerDonatur[$idDonatur] = WakafBatch::factory()->create(['donatur_id' => $idDonatur])->id;
        }

        // Buktikan dulu memang belum tertaut, kalau tidak uji ini bisa lulus palsu.
        foreach ($resi as $r) {
            expect($r->fresh()->wakaf_batch_id)->toBeNull();
        }

        $this->artisan('wakaf:tautkan-pengiriman --chunk=2')->assertSuccessful();

        foreach ($batchPerDonatur as $idDonatur => $idBatch) {
            expect(Pengiriman::query()->where('donatur_id', $idDonatur)->value('wakaf_batch_id'))->toBe($idBatch);
        }
    });

    /**
     * Bukti rantai tersambung: setelah resinya tertaut, status batch harus maju
     * sendiri. Inilah yang selama ini tidak pernah terjadi di produksi.
     */
    it('membuat status batch ikut maju setelah resinya tertaut', function () {
        $donatur = Donatur::factory()->create();
        $resi = resiLamaTahapAkhir($donatur, statusTahapAkhir('diterima'));
        $batch = WakafBatch::factory()->create([
            'donatur_id' => $donatur->id,
            'status' => 'pending_distribution',
        ]);

        expect($batch->fresh()->status)->toBe('pending_distribution');

        $this->artisan('wakaf:tautkan-pengiriman')->assertSuccessful();

        expect($resi->fresh()->wakaf_batch_id)->toBe($batch->id)
            ->and($batch->fresh()->status)->toBe('completed');
    });
});

describe('perintah penutup tahap akhir', function () {
    /** Batch dengan satu resi yang statusnya sudah diterima, tanpa lewat observer. */
    function batchSampaiTahapAkhir(Donatur $donatur): array
    {
        $batch = WakafBatch::factory()->create([
            'donatur_id' => $donatur->id,
            'status' => 'pending_distribution',
        ]);

        $resi = Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'wakaf_batch_id' => $batch->id,
            'status_id' => statusTahapAkhir('pemesanan'),
        ]);

        // Ubah langsung di DB supaya observer tidak menutup batch lebih dulu;
        // uji ini harus membuktikan perintahnya, bukan observernya.
        DB::table('pengiriman')->where('id', $resi->id)->update(['status_id' => statusTahapAkhir('diterima')]);

        return [$batch, $resi];
    }

    it('menutup batch yang seluruh resinya sudah diterima', function () {
        [$batch] = batchSampaiTahapAkhir(Donatur::factory()->create());

        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

        expect($batch->fresh()->status)->toBe('completed');
    });

    it('tidak menutup batch yang masih punya resi berjalan', function () {
        $donatur = Donatur::factory()->create();
        $batch = WakafBatch::factory()->create([
            'donatur_id' => $donatur->id,
            'status' => 'pending_distribution',
        ]);

        Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'wakaf_batch_id' => $batch->id,
            'status_id' => statusTahapAkhir('diterima'),
        ]);
        Pengiriman::factory()->create([
            'donatur_id' => $donatur->id,
            'wakaf_batch_id' => $batch->id,
            'status_id' => statusTahapAkhir('packing'),
        ]);

        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

        expect($batch->fresh()->status)->not->toBe('completed');
    });

    it('tidak menyentuh batch yang belum punya resi sama sekali', function () {
        $batch = WakafBatch::factory()->create([
            'donatur_id' => Donatur::factory()->create()->id,
            'status' => 'pending_distribution',
        ]);

        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

        expect($batch->fresh()->status)->toBe('pending_distribution');
    });

    /**
     * Sertifikat hanya boleh dibuat setelah wakafnya tuntas. Batch yang resinya
     * masih jalan tidak boleh dibuatkan sertifikat, walau ada resi yang sudah
     * sampai — kalau tidak, donatur menerima sertifikat untuk wakaf yang belum
     * selesai didistribusikan.
     */
    it('tidak membuat sertifikat untuk batch yang masih berjalan', function () {
        $donaturSampai = Donatur::factory()->create();
        $donaturJalan = Donatur::factory()->create();

        $batch = WakafBatch::factory()->create([
            'donatur_id' => $donaturSampai->id,
            'status' => 'pending_distribution',
        ]);

        Pengiriman::factory()->create([
            'donatur_id' => $donaturSampai->id,
            'wakaf_batch_id' => $batch->id,
            'status_id' => statusTahapAkhir('diterima'),
        ]);
        $resiJalan = Pengiriman::factory()->create([
            'donatur_id' => $donaturJalan->id,
            'wakaf_batch_id' => $batch->id,
            'status_id' => statusTahapAkhir('packing'),
        ]);

        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

        expect(Sertifikat::query()->count())->toBe(0)
            ->and($resiJalan->fresh()->sertifikat_generated)->toBeFalse();
    });

    it('membuat catatan sertifikat donatur dan menandai resinya', function () {
        $donatur = Donatur::factory()->create();
        [$batch, $resi] = batchSampaiTahapAkhir($donatur);

        expect(Sertifikat::query()->count())->toBe(0);

        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

        expect(Sertifikat::query()->where('donatur_id', $donatur->id)->where('is_consolidated', true)->exists())->toBeTrue()
            ->and($resi->fresh()->sertifikat_generated)->toBeTrue()
            ->and($resi->fresh()->received_at)->not->toBeNull();
    });

    it('tidak menggandakan catatan sertifikat saat dijalankan ulang', function () {
        $donatur = Donatur::factory()->create();
        batchSampaiTahapAkhir($donatur);

        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();
        $this->artisan('wakaf:tutup-pengiriman')->assertSuccessful();

        expect(Sertifikat::query()->where('donatur_id', $donatur->id)->count())->toBe(1);
    });

    it('tidak mengubah apa pun di mode kering', function () {
        $donatur = Donatur::factory()->create();
        [$batch] = batchSampaiTahapAkhir($donatur);

        $this->artisan('wakaf:tutup-pengiriman --kering')->assertSuccessful();

        expect($batch->fresh()->status)->toBe('pending_distribution')
            ->and(Sertifikat::query()->count())->toBe(0);
    });
});
