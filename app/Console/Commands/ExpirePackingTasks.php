<?php

namespace App\Console\Commands;

use App\Services\PackingAssignmentService;
use Illuminate\Console\Command;

class ExpirePackingTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packing:expire-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire packing tasks left open from the previous day and warn users below 50% achievement';

    /**
     * Execute the console command.
     */
    public function handle(PackingAssignmentService $assignmentService): int
    {
        try {
            $assignmentService->processDailyExpiration();
        } catch (\Throwable $e) {
            $this->error('Failed to expire packing tasks: '.$e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Daily packing expiration processed for '.today()->subDay()->format('Y-m-d'));

        return Command::SUCCESS;
    }
}
