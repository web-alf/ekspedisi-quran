<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DailyPackingTask;
use App\Models\PackingNotification;
use Carbon\Carbon;

class CheckPackingProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packing:check-progress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check packing progress and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $hour = $now->hour;
        
        // Only check during working hours
        if ($hour < 8 || $hour > 18) {
            $this->info('Outside working hours, skipping check');
            return Command::SUCCESS;
        }
        
        // Get today's active tasks
        $activeTasks = DailyPackingTask::whereDate('tanggal_tugas', today())
            ->whereIn('status', ['assigned', 'in_progress'])
            ->with('user')
            ->get();
            
        if ($activeTasks->isEmpty()) {
            $this->info('No active tasks found');
            return Command::SUCCESS;
        }
        
        $this->info('Checking progress for ' . $activeTasks->count() . ' active tasks');
        
        foreach ($activeTasks as $task) {
            $this->checkAndNotify($task, $hour);
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Check task progress and send notification if needed
     */
    private function checkAndNotify(DailyPackingTask $task, int $hour)
    {
        $expectedProgress = $this->getExpectedProgress($hour);
        
        if ($expectedProgress === null) {
            return;
        }
        
        $actualProgress = $task->progress_percentage;
        
        // If progress is below expected
        if ($actualProgress < $expectedProgress['target']) {
            // Check if notification already sent for this checkpoint
            $existingNotification = PackingNotification::where('daily_packing_task_id', $task->id)
                ->where('type', 'reminder')
                ->whereDate('created_at', today())
                ->where('meta_data->checkpoint', $expectedProgress['checkpoint'])
                ->exists();
                
            if (!$existingNotification) {
                $this->sendProgressNotification($task, $expectedProgress, $actualProgress);
                $this->info("Notification sent to user {$task->user->name} - Progress: {$actualProgress}% (Expected: {$expectedProgress['target']}%)");
            }
        }
    }
    
    /**
     * Get expected progress based on time
     */
    private function getExpectedProgress(int $hour): ?array
    {
        $checkpoints = [
            ['hour' => 10, 'target' => 25, 'checkpoint' => '10am', 'level' => 'info'],
            ['hour' => 12, 'target' => 40, 'checkpoint' => '12pm', 'level' => 'warning'],
            ['hour' => 15, 'target' => 60, 'checkpoint' => '3pm', 'level' => 'warning'],
            ['hour' => 17, 'target' => 80, 'checkpoint' => '5pm', 'level' => 'critical'],
        ];
        
        foreach ($checkpoints as $checkpoint) {
            if ($hour === $checkpoint['hour']) {
                return $checkpoint;
            }
        }
        
        return null;
    }
    
    /**
     * Send progress notification
     */
    private function sendProgressNotification(DailyPackingTask $task, array $expectedProgress, float $actualProgress)
    {
        $gap = $expectedProgress['target'] - $actualProgress;
        
        $messages = [
            'info' => "Progress check jam {$expectedProgress['checkpoint']}: Anda baru mencapai {$actualProgress}%. Target: {$expectedProgress['target']}%.",
            'warning' => "Peringatan! Progress Anda ({$actualProgress}%) di bawah target {$expectedProgress['target']}%. Segera tingkatkan kecepatan packing.",
            'critical' => "URGENT! Waktu hampir habis. Progress: {$actualProgress}%, Target minimal: {$expectedProgress['target']}%. Butuh {$gap}% lagi!"
        ];
        
        PackingNotification::create([
            'user_id' => $task->user_id,
            'daily_packing_task_id' => $task->id,
            'type' => 'reminder',
            'level' => $expectedProgress['level'],
            'title' => 'Progress Check',
            'message' => $messages[$expectedProgress['level']],
            'meta_data' => [
                'checkpoint' => $expectedProgress['checkpoint'],
                'expected_progress' => $expectedProgress['target'],
                'actual_progress' => $actualProgress,
                'gap' => $gap
            ]
        ]);
    }
}