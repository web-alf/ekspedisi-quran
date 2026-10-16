<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\QueueJobFailedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LogQueueJobFailure
{
    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        // Log the failure with detailed information
        Log::error('Queue job failed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job_id' => $event->job->getJobId(),
            'job_class' => $this->getJobClass($event->job->payload()),
            'exception' => $event->exception->getMessage(),
            'exception_trace' => $event->exception->getTraceAsString(),
            'attempts' => $event->job->attempts(),
            'failed_at' => now()->toDateTimeString(),
            'payload' => $this->getSafePayload($event->job->payload()),
        ]);

        // Send notification to administrators
        $this->notifyAdministrators($event);
    }

    private function getJobClass($payload)
    {
        return $payload['displayName'] ?? ($payload['job'] ?? 'Unknown');
    }

    private function getSafePayload($payload)
    {
        // Remove sensitive data and large objects from payload for logging
        $safePayload = $payload;
        
        // Remove data that might be too large or sensitive
        unset($safePayload['data']['command']);
        
        return $safePayload;
    }

    private function notifyAdministrators(JobFailed $event)
    {
        try {
            // Get super admin users
            $admins = User::whereHas('roles', function($query) {
                $query->where('name', 'super-admin');
            })->get();

            if ($admins->isEmpty()) {
                Log::warning('No super admin users found to notify about job failure');
                return;
            }

            // Create a mock job object for notification
            $jobInstance = $this->createJobInstance($event);
            
            // Send notification to all admins
            Notification::send($admins, new QueueJobFailedNotification($jobInstance, $event->exception));
            
            Log::info('Queue job failure notification sent to administrators', [
                'admin_count' => $admins->count(),
                'job_class' => $this->getJobClass($event->job->payload())
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send queue job failure notification', [
                'error' => $e->getMessage(),
                'original_job_failure' => $this->getJobClass($event->job->payload())
            ]);
        }
    }

    private function createJobInstance(JobFailed $event)
    {
        // Create a simple object to hold job information
        return new class($event) {
            public $queue;
            public $payload;
            
            public function __construct($event) {
                $this->queue = $event->job->getQueue();
                $this->payload = $event->job->payload();
            }
        };
    }
}