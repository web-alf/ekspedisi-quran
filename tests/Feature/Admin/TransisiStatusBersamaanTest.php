<?php

use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafBatch;
use App\Services\Cache\StatusPengirimanCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    StatusPengirimanCache::clear();
});

/**
 * CS A menyetujui dan CS B menolak permintaan yang sama hampir bersamaan.
 * Dulu keduanya lolos penjaga status (instance di memori) dan penulis terakhir
 * menang. Proses kedua ditiru dengan instance basi.
 */
it('menolak penolakan atas permintaan yang baru saja disetujui CS lain', function () {
    $permintaan = MushafRequest::factory()->create(['status' => 'pending']);
    $basi = MushafRequest::find($permintaan->id);
    $csA = User::factory()->create();
    $csB = User::factory()->create();

    $permintaan->approve($csA->id, 'Disetujui A');

    expect(fn () => $basi->reject($csB->id, 'Ditolak B'))
        ->toThrow(Exception::class, 'status approved tidak dapat ditolak');

    $permintaan->refresh();

    expect($permintaan->status)->toBe('approved')
        ->and($permintaan->reviewed_by)->toBe($csA->id)
        ->and($permintaan->rejected_at)->toBeNull();
});

it('menolak persetujuan ganda atas permintaan yang sama', function () {
    $permintaan = MushafRequest::factory()->create(['status' => 'reviewed']);
    $basi = MushafRequest::find($permintaan->id);
    $csA = User::factory()->create();

    $permintaan->approve($csA->id, 'Catatan A');

    expect(fn () => $basi->approve(User::factory()->create()->id, 'Catatan B'))
        ->toThrow(Exception::class, 'tidak dapat disetujui');

    expect($permintaan->fresh()->catatan_admin)->toBe('Catatan A');
});

it('masih menyetujui permintaan pending seperti biasa', function () {
    $permintaan = MushafRequest::factory()->create(['status' => 'pending']);

    expect($permintaan->approve(User::factory()->create()->id))->toBeTrue()
        ->and($permintaan->status)->toBe('approved')
        ->and($permintaan->fresh()->status)->toBe('approved');
});

/**
 * Status batch dulu dihitung ulang DI DALAM transaksi update status resi.
 * Dua kurir yang menandai 'diterima' pada dua resi terakhir masing-masing
 * hanya melihat perubahannya sendiri, sehingga batch macet di 'in_progress'.
 * Kini penghitungan ditunda sampai commit.
 */
it('menghitung ulang status batch setelah transaksi update resi commit', function () {
    Queue::fake();

    $diterima = StatusPengiriman::firstOrCreate(['slug' => 'diterima'], ['nama' => 'Diterima', 'urutan' => 7]);
    $pengirimanStatus = StatusPengiriman::firstOrCreate(['slug' => 'pengiriman'], ['nama' => 'Pengiriman', 'urutan' => 6]);

    $batch = WakafBatch::factory()->create(['status' => 'in_progress']);
    $resi = Pengiriman::factory()->count(2)->create([
        'wakaf_batch_id' => $batch->id,
        'status_id' => $pengirimanStatus->id,
    ]);

    DB::transaction(function () use ($resi, $diterima, $batch) {
        tibakan($resi);
        $resi->each->update(['status_id' => $diterima->id]);

        // Belum dihitung selama transaksi masih terbuka.
        expect($batch->fresh()->status)->toBe('in_progress');
    });

    expect($batch->fresh()->status)->toBe('completed');
});
