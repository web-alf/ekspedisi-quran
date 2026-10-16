<?php

namespace App\Console\Commands;

use App\Models\PackingNotification;
use Illuminate\Console\Command;

class PrunePackingNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packing:prune-notifications
                            {--days=30 : Umur minimum (hari) untuk notifikasi yang sudah dibaca}
                            {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus notifikasi packing yang sudah tidak berguna supaya lonceng tidak menumpuk';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        // Notifikasi yang menempel pada tugas hari yang SUDAH LEWAT tidak lagi
        // bisa ditindaklanjuti: pengingat "progress jam 3 sore" untuk kemarin
        // tidak ada gunanya dilihat hari ini. Aturan ini tidak memandang status
        // baca — kalau menunggu dibaca, lonceng akan menumpuk selamanya karena
        // satu pengingat per checkpoint per user per hari.
        $obsolete = PackingNotification::query()
            ->whereHas('dailyPackingTask', fn ($q) => $q->whereDate('tanggal_tugas', '<', today()));

        // Jaring pengaman untuk notifikasi yatim (tugasnya sudah dihapus) atau
        // yang sudah dibaca tapi lama menumpuk. Yang belum dibaca dan tidak
        // punya tugas sengaja dipertahankan: mungkin belum sempat dilihat.
        $staleRead = PackingNotification::query()
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('dailyPackingTask')
            ->where('is_read', true);

        $obsoleteCount = $obsolete->count();
        $staleCount = $staleRead->count();
        $total = $obsoleteCount + $staleCount;

        if ($total === 0) {
            $this->info('Tidak ada notifikasi yang perlu dihapus.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] {$total} notifikasi akan dihapus "
                ."({$obsoleteCount} dari tugas hari yang sudah lewat, "
                ."{$staleCount} sudah dibaca & lebih tua dari {$days} hari).");

            return Command::SUCCESS;
        }

        $obsolete->delete();
        $staleRead->delete();

        $this->info("Menghapus {$total} notifikasi "
            ."({$obsoleteCount} dari tugas hari yang sudah lewat, "
            ."{$staleCount} sudah dibaca & lebih tua dari {$days} hari).");

        return Command::SUCCESS;
    }
}
