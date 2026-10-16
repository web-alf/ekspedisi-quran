<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\JobProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class JobMonitorController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Display job monitor dashboard
     */
    public function index()
    {
        Gate::authorize('viewJobMonitor', auth()->user());
        
        $stats = JobProgress::getStats(auth()->id());
        $activeJobs = JobProgress::getActiveJobsForUser(auth()->id());
        $recentJobs = JobProgress::getRecentJobsForUser(auth()->id(), 10);
        
        return Inertia::render('Warehouse/JobMonitor', [
            'stats' => $stats,
            'activeJobs' => $activeJobs,
            'recentJobs' => $recentJobs,
        ]);
    }

    /**
     * Get real-time job progress updates
     */
    public function getProgress(Request $request)
    {
        $request->validate([
            'job_ids' => 'required|array',
            'job_ids.*' => 'string'
        ]);

        $jobs = JobProgress::whereIn('job_id', $request->job_ids)
            ->where('user_id', auth()->id())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'status' => $job->status,
                    'progress_percentage' => $job->progress_percentage,
                    'processed_items' => $job->processed_items,
                    'failed_items' => $job->failed_items,
                    'error_message' => $job->error_message,
                    'updated_at' => $job->updated_at?->toISOString()
                ];
            })
        ]);
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string'
        ]);

        $jobProgress = JobProgress::where('job_id', $request->job_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$jobProgress) {
            return response()->json([
                'success' => false,
                'message' => 'Job tidak ditemukan'
            ], 404);
        }

        if (!in_array($jobProgress->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Job tidak dapat dibatalkan karena sudah selesai'
            ], 400);
        }

        try {
            // Try to delete from queue
            Queue::deleteAndRelease($request->job_id);
            
            // Update job progress status
            $jobProgress->update([
                'status' => 'cancelled',
                'error_message' => 'Job dibatalkan oleh user',
                'completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job berhasil dibatalkan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry a failed job
     */
    public function retryJob(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string'
        ]);

        $jobProgress = JobProgress::where('job_id', $request->job_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$jobProgress) {
            return response()->json([
                'success' => false,
                'message' => 'Job tidak ditemukan'
            ], 404);
        }

        if ($jobProgress->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya job yang gagal yang dapat di-retry'
            ], 400);
        }

        try {
            // Reset job progress
            $jobProgress->update([
                'status' => 'pending',
                'processed_items' => 0,
                'failed_items' => 0,
                'progress_percentage' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null
            ]);

            // Re-dispatch the job based on type
            $this->redispatchJob($jobProgress);

            return response()->json([
                'success' => true,
                'message' => 'Job berhasil dijadwalkan ulang'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjadwalkan ulang job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed job information
     */
    public function getJobDetails(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string'
        ]);

        $jobProgress = JobProgress::where('job_id', $request->job_id)
            ->where('user_id', auth()->id())
            ->with('user')
            ->first();

        if (!$jobProgress) {
            return response()->json([
                'success' => false,
                'message' => 'Job tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $jobProgress->job_id,
                'job_type' => $jobProgress->job_type,
                'title' => $jobProgress->title,
                'status' => $jobProgress->status,
                'status_display' => $jobProgress->status_display,
                'progress_percentage' => $jobProgress->progress_percentage,
                'total_items' => $jobProgress->total_items,
                'processed_items' => $jobProgress->processed_items,
                'failed_items' => $jobProgress->failed_items,
                'success_rate' => $jobProgress->success_rate,
                'duration_formatted' => $jobProgress->duration_formatted,
                'metadata' => $jobProgress->metadata,
                'results' => $jobProgress->results,
                'error_message' => $jobProgress->error_message,
                'user_name' => $jobProgress->user->name,
                'created_at' => $jobProgress->created_at?->format('d/m/Y H:i:s'),
                'started_at' => $jobProgress->started_at?->format('d/m/Y H:i:s'),
                'completed_at' => $jobProgress->completed_at?->format('d/m/Y H:i:s')
            ]
        ]);
    }

    /**
     * Get job statistics for dashboard
     */
    public function getStats()
    {
        $userStats = JobProgress::getStats(auth()->id());
        $globalStats = JobProgress::getStats();

        return response()->json([
            'success' => true,
            'data' => [
                'user_stats' => $userStats,
                'global_stats' => $globalStats,
                'queue_size' => $this->getQueueSize(),
                'active_workers' => $this->getActiveWorkers()
            ]
        ]);
    }

    /**
     * Re-dispatch a job based on its type and metadata
     */
    private function redispatchJob(JobProgress $jobProgress)
    {
        $metadata = $jobProgress->metadata;
        
        switch ($jobProgress->job_type) {
            case 'bulk_status_update':
                $job = new \App\Jobs\Warehouse\BulkStatusUpdateJob(
                    $metadata['box_ids'] ?? [],
                    $metadata['new_status_id'],
                    $metadata['address'] ?? null,
                    $metadata['notes'] ?? null,
                    $metadata['location'] ?? null,
                    $metadata['documentation_path'] ?? null,
                    $jobProgress->user_id
                );
                break;
                
            case 'bulk_box_processing':
                $job = new \App\Jobs\Warehouse\BulkBoxProcessingJob(
                    $metadata['box_codes'] ?? [],
                    $metadata['operation'],
                    $metadata['parameters'] ?? [],
                    $jobProgress->user_id
                );
                break;
                
            case 'bulk_item_assignment':
                $job = new \App\Jobs\Warehouse\BulkItemAssignmentJob(
                    $metadata['pengiriman_ids'] ?? [],
                    $metadata['target_task_id'] ?? null,
                    $metadata['assignment_type'] ?? 'auto_assign',
                    $metadata['parameters'] ?? [],
                    $jobProgress->user_id
                );
                break;
                
            case 'certificate_generation':
                $job = new \App\Jobs\Warehouse\CertificateGenerationJob(
                    $metadata['donatur_ids'] ?? [],
                    $metadata['certificate_type'] ?? 'wakaf',
                    $metadata['parameters'] ?? [],
                    $jobProgress->user_id
                );
                break;
                
            default:
                throw new \Exception("Unknown job type: {$jobProgress->job_type}");
        }

        dispatch($job);
    }

    /**
     * Get current queue size
     */
    private function getQueueSize()
    {
        try {
            return Queue::size();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get number of active workers (simplified)
     */
    private function getActiveWorkers()
    {
        // This is a simplified implementation
        // In production, you might want to use a more sophisticated approach
        return JobProgress::processing()->count();
    }
}
