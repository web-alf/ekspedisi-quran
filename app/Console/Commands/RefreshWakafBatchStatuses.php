<?php

namespace App\Console\Commands;

use App\Models\WakafBatch;
use Illuminate\Console\Command;

class RefreshWakafBatchStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wakaf-batch:refresh-statuses {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh stale WakafBatch statuses based on their pengiriman statuses';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Refreshing WakafBatch statuses...');
        $this->info('Dry run mode: ' . ($dryRun ? 'ON' : 'OFF'));
        $this->newLine();

        // Get all wakaf batches
        $batches = WakafBatch::with(['pengiriman.status'])->get();
        
        $updated = 0;
        $unchanged = 0;
        $changes = [];

        foreach ($batches as $batch) {
            $oldStatus = $batch->status;
            $oldProgress = $batch->distribution_progress;
            
            // Calculate what the status should be (without saving)
            $batch->updateStatus();
            $newStatus = $batch->status;
            $newProgress = $batch->distribution_progress;
            
            // Reload to get original status if dry run
            if ($dryRun) {
                $batch->refresh();
            }
            
            if ($oldStatus !== $newStatus || abs($oldProgress - $newProgress) > 0.01) {
                $updated++;
                $changes[] = [
                    'batch_code' => $batch->batch_code,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'old_progress' => $oldProgress,
                    'new_progress' => $newProgress,
                    'pengiriman_count' => $batch->pengiriman->count(),
                ];
                
                if (!$dryRun) {
                    $this->line("<fg=yellow>Updated:</> {$batch->batch_code} | {$oldStatus} → {$newStatus} | {$oldProgress}% → {$newProgress}%");
                } else {
                    $this->line("<fg=blue>Would update:</> {$batch->batch_code} | {$oldStatus} → {$newStatus} | {$oldProgress}% → {$newProgress}%");
                }
            } else {
                $unchanged++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("Total batches processed: " . $batches->count());
        $this->info("Batches that needed updates: {$updated}");
        $this->info("Batches unchanged: {$unchanged}");

        if ($updated > 0) {
            $this->newLine();
            $this->table(
                ['Batch Code', 'Old Status', 'New Status', 'Old Progress', 'New Progress', 'Pengiriman Count'],
                collect($changes)->map(function($change) {
                    return [
                        $change['batch_code'],
                        $change['old_status'],
                        $change['new_status'],
                        $change['old_progress'] . '%',
                        $change['new_progress'] . '%',
                        $change['pengiriman_count']
                    ];
                })->toArray()
            );
        }

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->info("To apply these changes, run the command without --dry-run flag:");
            $this->line("php artisan wakaf-batch:refresh-statuses");
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run completed!' : 'Status refresh completed!');

        return Command::SUCCESS;
    }
}