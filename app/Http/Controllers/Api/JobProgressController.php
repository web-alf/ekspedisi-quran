<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class JobProgressController extends Controller
{
    /**
     * Get active jobs for the authenticated user
     */
    public function getActiveJobs(Request $request): JsonResponse
    {
        try {
            // Check if user is authenticated
            if (! Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User session expired. Please login again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            $userId = Auth::id();

            $activeJobs = JobProgress::active()
                ->byUser($userId)
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'job_id' => $job->job_id,
                        'job_type' => $job->job_type,
                        'title' => $job->title,
                        'status' => $job->status,
                        'status_display' => $job->status_display,
                        'progress_percentage' => $job->progress_percentage,
                        'total_items' => $job->total_items,
                        'processed_items' => $job->processed_items,
                        'failed_items' => $job->failed_items,
                        'success_rate' => $job->success_rate,
                        'duration_formatted' => $job->duration_formatted,
                        'metadata' => $job->metadata,
                        'created_at' => $job->created_at->diffForHumans(),
                        'started_at' => $job->started_at ? $job->started_at->diffForHumans() : null,
                        'estimated_completion' => $this->estimateCompletion($job),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'jobs' => $activeJobs,
                    'count' => $activeJobs->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch active jobs', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data pekerjaan aktif',
            ], 500);
        }
    }

    /**
     * Get job progress by ID
     */
    public function getJobProgress(Request $request, $jobId): JsonResponse
    {
        try {
            // Check if user is authenticated
            if (! Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User session expired. Please login again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            $userId = Auth::id();

            $job = JobProgress::where('id', $jobId)
                ->byUser($userId)
                ->with('user:id,name')
                ->first();

            if (! $job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan tidak ditemukan',
                ], 404);
            }

            $jobData = [
                'id' => $job->id,
                'job_id' => $job->job_id,
                'job_type' => $job->job_type,
                'title' => $job->title,
                'status' => $job->status,
                'status_display' => $job->status_display,
                'progress_percentage' => $job->progress_percentage,
                'total_items' => $job->total_items,
                'processed_items' => $job->processed_items,
                'failed_items' => $job->failed_items,
                'success_rate' => $job->success_rate,
                'duration' => $job->duration,
                'duration_formatted' => $job->duration_formatted,
                'metadata' => $job->metadata,
                'results' => $job->results,
                'error_message' => $job->error_message,
                'created_at' => $job->created_at,
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'estimated_completion' => $this->estimateCompletion($job),
                'user' => $job->user,
            ];

            return response()->json([
                'success' => true,
                'data' => $jobData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch job progress', [
                'job_id' => $jobId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat progress pekerjaan',
            ], 500);
        }
    }

    /**
     * Get recent jobs for the authenticated user
     */
    public function getRecentJobs(Request $request): JsonResponse
    {
        try {
            // Check if user is authenticated
            if (! Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User session expired. Please login again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            $userId = Auth::id();
            $limit = $request->get('limit', 20);

            $recentJobs = JobProgress::byUser($userId)
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'job_id' => $job->job_id,
                        'job_type' => $job->job_type,
                        'title' => $job->title,
                        'status' => $job->status,
                        'status_display' => $job->status_display,
                        'progress_percentage' => $job->progress_percentage,
                        'total_items' => $job->total_items,
                        'processed_items' => $job->processed_items,
                        'failed_items' => $job->failed_items,
                        'success_rate' => $job->success_rate,
                        'duration_formatted' => $job->duration_formatted,
                        'metadata' => $job->metadata,
                        'results' => $job->results,
                        'error_message' => $job->error_message,
                        'created_at' => $job->created_at->diffForHumans(),
                        'completed_at' => $job->completed_at ? $job->completed_at->diffForHumans() : null,
                        'can_retry' => $job->status === 'failed' && $this->canRetryJob($job),
                        'can_cancel' => $job->status === 'processing' && $this->canCancelJob($job),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'jobs' => $recentJobs,
                    'count' => $recentJobs->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch recent jobs', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat riwayat pekerjaan',
            ], 500);
        }
    }

    /**
     * Get job statistics for the authenticated user
     */
    public function getJobStats(Request $request): JsonResponse
    {
        try {
            // Check if user is authenticated
            if (! Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User session expired. Please login again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            $userId = Auth::id();
            $days = $request->get('days', 7);

            $stats = JobProgress::getStats($userId);

            // Add certificate-specific stats
            $certificateStats = [
                'certificate_jobs' => JobProgress::byUser($userId)
                    ->recent($days)
                    ->where('job_type', 'LIKE', '%certificate%')
                    ->count(),
                'certificates_generated_today' => JobProgress::byUser($userId)
                    ->where('created_at', '>=', today())
                    ->where('status', 'completed')
                    ->where('job_type', 'LIKE', '%certificate%')
                    ->sum('processed_items'),
                'active_certificate_jobs' => JobProgress::byUser($userId)
                    ->active()
                    ->where('job_type', 'LIKE', '%certificate%')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'general' => $stats,
                    'certificates' => $certificateStats,
                    'period_days' => $days,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch job stats', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat statistik pekerjaan',
            ], 500);
        }
    }

    /**
     * Cancel a job (if supported and in processing state)
     */
    public function cancelJob(Request $request, $jobId): JsonResponse
    {
        try {
            // Check if user is authenticated
            if (! Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User session expired. Please login again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            $userId = Auth::id();

            $job = JobProgress::where('id', $jobId)
                ->byUser($userId)
                ->where('status', 'processing')
                ->first();

            if (! $job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan tidak ditemukan atau tidak dapat dibatalkan',
                ], 404);
            }

            if (! $this->canCancelJob($job)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan ini tidak dapat dibatalkan',
                ], 400);
            }

            // Mark as failed with cancellation message
            $job->markAsFailed('Dibatalkan oleh pengguna');

            Log::info('Job cancelled by user', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'job_type' => $job->job_type,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pekerjaan berhasil dibatalkan',
                'data' => [
                    'job_id' => $job->id,
                    'status' => $job->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel job', [
                'job_id' => $jobId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan pekerjaan',
            ], 500);
        }
    }

    /**
     * Delete completed or failed job records
     */
    public function deleteJob(Request $request, $jobId): JsonResponse
    {
        try {
            // Check if user is authenticated
            if (! Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User session expired. Please login again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }

            $userId = Auth::id();

            $job = JobProgress::where('id', $jobId)
                ->byUser($userId)
                ->whereIn('status', ['completed', 'failed'])
                ->first();

            if (! $job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pekerjaan tidak ditemukan atau tidak dapat dihapus',
                ], 404);
            }

            $jobTitle = $job->title;
            $job->delete();

            Log::info('Job record deleted by user', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'job_title' => $jobTitle,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Riwayat pekerjaan berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete job', [
                'job_id' => $jobId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus riwayat pekerjaan',
            ], 500);
        }
    }

    /**
     * Estimate completion time for active jobs
     */
    private function estimateCompletion(JobProgress $job): ?array
    {
        if ($job->status !== 'processing' || ! $job->started_at) {
            return null;
        }

        $elapsed = $job->started_at->diffInSeconds(now());
        $processedItems = $job->processed_items;
        $totalItems = $job->total_items;

        if ($processedItems <= 0 || $totalItems <= 0) {
            return null;
        }

        $itemsPerSecond = $processedItems / $elapsed;
        if ($itemsPerSecond <= 0) {
            return null;
        }

        $remainingItems = $totalItems - $processedItems;
        $estimatedSecondsRemaining = $remainingItems / $itemsPerSecond;

        $estimatedCompletionTime = now()->addSeconds($estimatedSecondsRemaining);

        return [
            'estimated_seconds_remaining' => (int) $estimatedSecondsRemaining,
            'estimated_completion_time' => $estimatedCompletionTime,
            'estimated_completion_formatted' => $estimatedCompletionTime->diffForHumans(),
            'processing_speed' => round($itemsPerSecond * 60, 2), // items per minute
        ];
    }

    /**
     * Check if a job can be retried
     */
    private function canRetryJob(JobProgress $job): bool
    {
        return $job->status === 'failed' &&
               in_array($job->job_type, ['single_certificate_generation', 'bulk_certificate_generation', 'consolidated_certificate_generation']);
    }

    /**
     * Check if a job can be cancelled
     */
    private function canCancelJob(JobProgress $job): bool
    {
        return $job->status === 'processing' &&
               in_array($job->job_type, ['bulk_certificate_generation', 'consolidated_certificate_generation']);
    }
}
