<?php

namespace App\Console\Commands;

use App\Jobs\WhatsApp\KirimWhatsAppJob;
use App\Models\WhatsAppNotification;
use Illuminate\Console\Command;

/**
 * Menguras antrean notifikasi yang tertahan.
 *
 * Dua sebab baris bisa tertahan berstatus 'menunggu':
 *   1. dibuat saat jam tenang, jadi sengaja tidak diantrekan;
 *   2. jobnya hilang (worker mati, antrean dibersihkan) sebelum sempat jalan.
 *
 * Hanya baris yang sudah berumur lebih dari 30 menit yang diantrekan ulang.
 * Itu penting: baris 'menunggu' yang BARU saja dibuat mungkin jobnya masih
 * menunggu di antrean, dan mengantrekannya lagi berarti dua job mengirim pesan
 * yang sama secara bersamaan — risiko yang sengaja dihindari sejak awal.
 */
class WhatsAppKirimTertundaCommand extends Command
{
    protected $signature = 'whatsapp:kirim-tertunda
                          {--menit=30 : Umur minimum (menit) sebelum baris diantrekan ulang}
                          {--limit=200 : Jumlah maksimum per jalan}';

    protected $description = 'Antrekan ulang notifikasi WhatsApp yang tertahan berstatus menunggu';

    public function handle(): int
    {
        $menit = max(1, (int) $this->option('menit'));
        $batas = max(1, (int) $this->option('limit'));

        $idTertahan = WhatsAppNotification::query()
            ->menunggu()
            ->where('created_at', '<=', now()->subMinutes($menit))
            ->orderBy('id')
            ->limit($batas)
            ->pluck('id');

        foreach ($idTertahan as $id) {
            KirimWhatsAppJob::dispatch($id);
        }

        $this->info("Diantrekan ulang: {$idTertahan->count()} notifikasi.");

        return self::SUCCESS;
    }
}
