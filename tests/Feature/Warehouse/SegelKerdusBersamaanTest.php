<?php

use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Auto-seal saat kerdus penuh bisa berbarengan dengan klik "Segel". Dulu
 * seal() mengecek `is_sealed` pada instance di memori, sehingga proses kedua
 * (yang memegang instance basi berstatus 'filling') menimpa seal_code yang
 * sudah tercetak di label dan mengaktifkan kerdus berikutnya untuk kedua kalinya.
 */
it('tidak menyegel ulang kerdus yang baru saja disegel proses lain', function () {
    $task = DailyPackingTask::factory()->create();
    $kerdus = PackingBox::factory()->filling()->create(['daily_packing_task_id' => $task->id]);
    $berikutnya = PackingBox::factory()->create(['daily_packing_task_id' => $task->id, 'status' => PackingBox::STATUS_EMPTY]);
    $cadangan = PackingBox::factory()->create(['daily_packing_task_id' => $task->id, 'status' => PackingBox::STATUS_EMPTY]);

    $basi = PackingBox::find($kerdus->id);

    $kerdus->seal();
    $kodeSegel = $kerdus->fresh()->seal_code;

    $basi->seal();

    expect($kerdus->fresh()->seal_code)->toBe($kodeSegel)
        ->and($berikutnya->fresh()->status)->toBe(PackingBox::STATUS_FILLING)
        // Kerdus ketiga tidak boleh ikut diaktifkan oleh segel kedua.
        ->and($cadangan->fresh()->status)->toBe(PackingBox::STATUS_EMPTY)
        // Instance basi disegarkan agar pemanggil melihat keadaan sebenarnya.
        ->and($basi->is_sealed)->toBeTrue();
});
