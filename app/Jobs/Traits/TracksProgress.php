<?php

namespace App\Jobs\Traits;

use App\Models\JobProgress;
use App\Models\User;
use App\Notifications\CertificateJobCompletedNotification;
use Illuminate\Support\Facades\Log;

trait TracksProgress
{
    protected $jobProgress;

    protected $processedItems = 0;

    protected $failedItems = 0;

    /**
     * Initialize job progress tracking
     */
    protected function initializeProgress($jobType, $title, $totalItems, $metadata = [])
    {
        try {
            $this->jobProgress = JobProgress::createForJob(
                $this->job->getJobId(),
                $jobType,
                $title,
                $totalItems,
                $metadata
            );

            Log::info('Job progress initialized', [
                'job_id' => $this->job->getJobId(),
                'job_type' => $jobType,
                'total_items' => $totalItems,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize job progress: '.$e->getMessage());
        }
    }

    /**
     * Mark job as started
     */
    protected function markAsProcessing()
    {
        if ($this->jobProgress) {
            $this->jobProgress->markAsProcessing();
        }
    }

    /**
     * Update progress during processing
     */
    protected function updateProgress(?int $processed = null, ?int $failed = null)
    {
        if ($processed !== null) {
            $this->processedItems = $processed;
        }

        if ($failed !== null) {
            $this->failedItems = $failed;
        }

        if ($this->jobProgress) {
            $this->jobProgress->updateProgress($this->processedItems, $this->failedItems);
        }
    }

    /**
     * Increment processed items
     */
    protected function incrementProcessed($count = 1)
    {
        $this->processedItems += $count;
        $this->updateProgress();
    }

    /**
     * Increment failed items
     */
    protected function incrementFailed($count = 1)
    {
        $this->failedItems += $count;
        $this->updateProgress();
    }

    /**
     * Mark job as completed
     */
    protected function markAsCompleted($results = [])
    {
        if ($this->jobProgress) {
            $this->jobProgress->markAsCompleted($results);
            $this->sendNotification();
        }
    }

    /**
     * Mark job as failed
     */
    protected function markAsFailed($errorMessage)
    {
        if ($this->jobProgress) {
            $this->jobProgress->markAsFailed($errorMessage);
            $this->sendNotification();
        }
    }

    /**
     * Send notification to user about job completion/failure
     */
    protected function sendNotification()
    {
        try {
            if (! $this->jobProgress || ! $this->jobProgress->user_id) {
                return;
            }

            // Only send notifications for certificate jobs
            if (! $this->isCertificateJob()) {
                return;
            }

            $user = User::find($this->jobProgress->user_id);
            if (! $user) {
                Log::warning('User not found for job notification', [
                    'job_progress_id' => $this->jobProgress->id,
                    'user_id' => $this->jobProgress->user_id,
                ]);

                return;
            }

            // Send notification for completed or failed jobs
            if (in_array($this->jobProgress->status, ['completed', 'failed'])) {
                $user->notify(new CertificateJobCompletedNotification($this->jobProgress));

                Log::info('Job completion notification sent', [
                    'job_progress_id' => $this->jobProgress->id,
                    'user_id' => $user->id,
                    'job_status' => $this->jobProgress->status,
                    'job_type' => $this->jobProgress->job_type,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send job notification', [
                'job_progress_id' => $this->jobProgress?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if this is a certificate-related job
     */
    protected function isCertificateJob(): bool
    {
        if (! $this->jobProgress) {
            return false;
        }

        $certificateJobTypes = [
            'single_certificate_generation',
            'bulk_certificate_generation',
            'consolidated_certificate_generation',
            'certificate_generation', // Legacy type
        ];

        return in_array($this->jobProgress->job_type, $certificateJobTypes);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Job failed: '.$exception->getMessage(), [
            'job_id' => $this->job?->getJobId(),
            'exception' => $exception,
        ]);

        $this->markAsFailed($exception->getMessage());
    }
}
