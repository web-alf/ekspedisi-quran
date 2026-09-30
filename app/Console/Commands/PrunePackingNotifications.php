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
                            {--days=30 : Umur minimum (hari) sebelum notifikasi boleh dihapus}
                            {--include-unread : Ikut hapus notifikasi yang belum dibaca}
                            {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus notifikasi packing lama supaya lonceng tidak menumpuk';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = PackingNotification::query()->where('created_at', '<', $cutoff);

        // Notifikasi yang belum dibaca sengaja dipertahankan: menghapusnya berarti
        // staf kehilangan informasi yang belum sempat mereka lihat. Pakai
        // --include-unread bila memang ingin membersihkan seluruhnya.
        if (! $this->option('include-unread')) {
            $query->where('is_read', true);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info("Tidak ada notifikasi lebih tua dari {$days} hari yang perlu dihapus.");

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] {$count} notifikasi akan dihapus (lebih tua dari {$days} hari).");

            return Command::SUCCESS;
        }

        $query->delete();

        $this->info("Menghapus {$count} notifikasi lebih tua dari {$days} hari.");

        return Command::SUCCESS;
    }
}
