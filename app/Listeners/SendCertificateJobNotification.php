<?php

namespace App\Listeners;

use App\Models\JobProgress;
use App\Models\User;
use App\Notifications\CertificateJobCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendCertificateJobNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'notifications';

    public $tries = 3;

    public $backoff = [30, 60, 120];

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        try {
            // Get job progress record
            $jobProgress = $this->findJobProgress($event);

            if (! $jobProgress) {
                Log::warning('Job progress not found for notification', [
                    'event' => get_class($event),
                    'job_id' => $this->getJobId($event),
                ]);

                return;
            }

            // Only send notifications for certificate-related jobs
            if (! $this->isCertificateJob($jobProgress)) {
                return;
            }

            // Find user to notify
            $user = User::find($jobProgress->user_id);
            if (! $user) {
                Log::warning('User not found for job notification', [
                    'job_progress_id' => $jobProgress->id,
                    'user_id' => $jobProgress->user_id,
                ]);

                return;
            }

            // Send notification for completed or failed jobs
            if (in_array($jobProgress->status, ['completed', 'failed'])) {
                $user->notify(new CertificateJobCompletedNotification($jobProgress));

                Log::info('Certificate job notification sent', [
                    'job_progress_id' => $jobProgress->id,
                    'user_id' => $user->id,
                    'job_status' => $jobProgress->status,
                    'job_type' => $jobProgress->job_type,
                ]);

                // Send to admins for failed jobs
                if ($jobProgress->status === 'failed' && $this->shouldNotifyAdmins($jobProgress)) {
                    $this->notifyAdmins($jobProgress);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to send certificate job notification', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Find job progress record from event
     */
    private function findJobProgress($event): ?JobProgress
    {
        $jobId = $this->getJobId($event);

        if (! $jobId) {
            return null;
        }

        return JobProgress::where('job_id', $jobId)->first();
    }

    /**
     * Extract job ID from various event types
     */
    private function getJobId($event): ?string
    {
        // Handle different queue event types
        if (isset($event->job)) {
            if (method_exists($event->job, 'getJobId')) {
                return $event->job->getJobId();
            }

            if (isset($event->job->uuid)) {
                return $event->job->uuid;
            }

            if (isset($event->job->id)) {
                return (string) $event->job->id;
            }
        }

        // Handle job-specific events
        if (method_exists($event, 'getJobId')) {
            return $event->getJobId();
        }

        // Check if event has direct job_id property
        if (isset($event->job_id)) {
            return $event->job_id;
        }

        return null;
    }

    /**
     * Check if job is certificate-related
     */
    private function isCertificateJob(JobProgress $jobProgress): bool
    {
        $certificateJobTypes = [
            'single_certificate_generation',
            'bulk_certificate_generation',
            'consolidated_certificate_generation',
            'certificate_generation', // Legacy type
        ];

        return in_array($jobProgress->job_type, $certificateJobTypes);
    }

    /**
     * Check if admins should be notified for failed job
     */
    private function shouldNotifyAdmins(JobProgress $jobProgress): bool
    {
        // Notify admins for:
        // - Bulk operations that failed
        // - Jobs with high item counts
        // - Critical failures

        return $jobProgress->total_items >= 10 ||
               str_contains($jobProgress->job_type, 'bulk') ||
               str_contains($jobProgress->error_message ?? '', 'critical');
    }

    /**
     * Send notification to administrators
     */
    private function notifyAdmins(JobProgress $jobProgress): void
    {
        try {
            // Find admin users with certificate permissions
            $admins = User::permission('certificates.generate')->get();

            if ($admins->isEmpty()) {
                return;
            }

            // Send notification to all admins
            Notification::send($admins, new CertificateJobCompletedNotification($jobProgress));

            Log::info('Admin notification sent for failed certificate job', [
                'job_progress_id' => $jobProgress->id,
                'admin_count' => $admins->count(),
                'job_type' => $jobProgress->job_type,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify admins about failed certificate job', [
                'job_progress_id' => $jobProgress->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed($event, \Exception $exception): void
    {
        Log::error('Certificate job notification listener failed', [
            'event' => get_class($event),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
