<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PackingAssignmentService;
use Carbon\Carbon;

class RunDailyPackingAssignment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packing:daily-assignment {--date= : Date for assignment (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run daily packing assignment (target-only system) for warehouse users';

    /**
     * Execute the console command.
     */
    public function handle(PackingAssignmentService $assignmentService)
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        
        $this->info('Running daily packing assignment for date: ' . $date->format('Y-m-d'));
        
        try {
            $results = $assignmentService->runDailyAssignment($date);
            
            if (empty($results)) {
                $this->warn('No assignments made (possibly a holiday or weekend)');
                return Command::SUCCESS;
            }
            
            $this->info('Assignment Results (Target-Only System):');
            $this->table(
                ['User ID', 'Task ID', 'Status', 'Target', 'Carry Over'],
                collect($results)->map(function($result) {
                    return [
                        $result['user_id'],
                        $result['task_id'],
                        $result['status'],
                        $result['target'] ?? $result['resi_count'], // Fallback for backward compatibility
                        $result['carry_over'] ?? 0
                    ];
                })
            );
            
            $totalTarget = collect($results)->sum(function($result) {
                return $result['target'] ?? $result['resi_count'];
            });
            $this->info("Total target assigned: {$totalTarget}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to run daily assignment: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}