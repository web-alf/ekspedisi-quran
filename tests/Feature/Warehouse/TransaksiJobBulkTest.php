<?php

use App\Jobs\Warehouse\BulkItemAssignmentJob;
use App\Jobs\Warehouse\BulkStatusUpdateJob;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Job bulk dulu melakukan `continue` di dalam `DB::beginTransaction()` tanpa
 * rollBack. Queue worker berumur panjang dan tidak menutup transaksi yang
 * bocor, sehingga seluruh pekerjaan berikutnya hanya menjadi savepoint yang
 * tak pernah ter-commit sementara kunci barisnya terus tertahan. Ukurannya:
 * tingkat transaksi setelah job selesai harus sama dengan sebelum job jalan.
 */
it('tidak membiarkan transaksi terbuka saat kerdus tidak ditemukan atau kosong', function () {
    $status = StatusPengiriman::factory()->create();
    $kerdusKosong = PackingBox::factory()->create([
        'daily_packing_task_id' => DailyPackingTask::factory()->create()->id,
    ]);
    $job = new BulkStatusUpdateJob([], $status->id, userId: User::factory()->create()->id);

    $tingkatAwal = DB::transactionLevel();

    $hasil = (fn () => $this->processBoxChunk([999999, $kerdusKosong->id], $status))->call($job);

    expect(DB::transactionLevel())->toBe($tingkatAwal)
        ->and($hasil['errors'])->toHaveCount(2)
        ->and($hasil['boxes_processed'])->toBe(0)
        ->and($hasil['items_failed'])->toBe(0);
});

it('tidak membiarkan transaksi terbuka saat resi tidak ditemukan', function () {
    $job = new BulkItemAssignmentJob([], userId: User::factory()->create()->id);

    $tingkatAwal = DB::transactionLevel();

    $hasil = (fn () => $this->processItemChunk([999998, 999999]))->call($job);

    expect(DB::transactionLevel())->toBe($tingkatAwal)
        ->and($hasil['items_failed'])->toBe(2);
});
