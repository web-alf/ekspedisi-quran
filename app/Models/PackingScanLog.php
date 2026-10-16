<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Jejak audit scan packing yang gagal/dibatalkan — SIAPA yang scan,
 * QR mentah apa yang dipakai, dan penyebab penolakannya.
 *
 * Sengaja dipisah dari laravel.log: log file bergulir dan sulit ditelusuri
 * per petugas, sedangkan baris di tabel ini bisa dilihat langsung di tab
 * History halaman packing dan bertahan lintas sesi/refresh.
 */
class PackingScanLog extends Model
{
    protected $fillable = [
        'user_id',
        'daily_packing_task_id',
        'packing_box_id',
        'qr_data',
        'no_resi',
        'type',
        'message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dailyPackingTask()
    {
        return $this->belongsTo(DailyPackingTask::class);
    }

    public function packingBox()
    {
        return $this->belongsTo(PackingBox::class);
    }

    /**
     * Catat satu kegagalan scan. Tidak boleh melempar: pencatatan adalah
     * tujuan sekunder, menggagalkan respons scan karena insert log error
     * justru menambah masalah baru bagi petugas di lapangan.
     *
     * @param  int  $userId  petugas yang melakukan scan
     * @param  string  $qrData  QR mentah yang dipindai (atau konteks pengganti)
     * @param  string|null  $noResi  nomor resi bila berhasil diurai
     * @param  string  $type  jenis kegagalan (invalid_qr, not_found, dst.)
     * @param  string  $message  pesan kegagalan yang diperlihatkan ke petugas
     * @param  int|null  $taskId  tugas harian aktif saat scan, bila ada
     * @param  int|null  $boxId  kerdus yang sedang diisi, bila sudah diketahui
     */
    public static function recordFailed(
        int $userId,
        string $qrData,
        ?string $noResi,
        string $type,
        string $message,
        ?int $taskId = null,
        ?int $boxId = null,
    ): ?self {
        try {
            return static::create([
                'user_id' => $userId,
                'daily_packing_task_id' => $taskId,
                'packing_box_id' => $boxId,
                'qr_data' => mb_substr($qrData, 0, 500),
                'no_resi' => $noResi !== null ? mb_substr($noResi, 0, 100) : null,
                'type' => $type,
                'message' => mb_substr($message, 0, 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat packing_scan_logs', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
