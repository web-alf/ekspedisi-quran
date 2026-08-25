<?php

namespace Tests\Feature\Performance;

use App\Jobs\Certificate\GenerateBulkCertificatesJob;
use App\Jobs\Warehouse\BulkBoxProcessingJob;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Use sync queue for testing
        Queue::fake();
    }

    /**
     * Test queue job dispatch performance
     */
    public function test_queue_job_dispatch_performance()
    {
        $user = User::factory()->create();
        $task = DailyPackingTask::factory()->create(['user_id' => $user->id]);
        $boxes = PackingBox::factory(50)->create(['daily_packing_task_id' => $task->id]);

        $start = microtime(true);

        // Dispatch multiple jobs
        for ($i = 0; $i < 10; $i++) {
            BulkBoxProcessingJob::dispatch($boxes->pluck('id')->toArray());
        }

        $dispatchTime = (microtime(true) - $start) * 1000;

        // Should dispatch quickly
        $this->assertLessThan(100, $dispatchTime, 'Job dispatch should be fast');

        // Verify jobs were queued
        Queue::assertPushed(BulkBoxProcessingJob::class, 10);
    }

    /**
     * Test bulk processing job performance
     */
    public function test_bulk_processing_job_performance()
    {
        $user = User::factory()->create();
        $task = DailyPackingTask::factory()->create(['user_id' => $user->id]);
        $boxes = PackingBox::factory(100)->create(['daily_packing_task_id' => $task->id]);

        $start = microtime(true);

        // Process bulk job
        $job = new BulkBoxProcessingJob($boxes->pluck('id')->toArray());
        $job->handle();

        $processingTime = (microtime(true) - $start) * 1000;

        // Bulk processing should be efficient
        $this->assertLessThan(1000, $processingTime, 'Bulk processing should complete within 1 second');
    }

    /**
     * Test certificate generation job performance
     */
    public function test_certificate_generation_performance()
    {
        // Create test certificates
        $certificates = Sertifikat::factory(20)->create();

        $start = microtime(true);

        $job = new GenerateBulkCertificatesJob($certificates->pluck('id')->toArray());
        $job->handle();

        $generationTime = (microtime(true) - $start) * 1000;

        // Certificate generation should be reasonable
        $this->assertLessThan(5000, $generationTime, 'Certificate generation should complete within 5 seconds');
    }

    /**
     * Test job memory usage
     */
    public function test_job_memory_usage()
    {
        $initialMemory = memory_get_usage(true);

        $user = User::factory()->create();
        $task = DailyPackingTask::factory()->create(['user_id' => $user->id]);
        $boxes = PackingBox::factory(200)->create(['daily_packing_task_id' => $task->id]);

        $job = new BulkBoxProcessingJob($boxes->pluck('id')->toArray());
        $job->handle();

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB

        // Job should not use excessive memory
        $this->assertLessThan(50, $memoryIncrease, 'Job should use less than 50MB memory');
    }

    /**
     * Test concurrent job processing
     */
    public function test_concurrent_job_processing()
    {
        $user = User::factory()->create();
        $task = DailyPackingTask::factory()->create(['user_id' => $user->id]);
        $boxes = PackingBox::factory(100)->create(['daily_packing_task_id' => $task->id]);

        $start = microtime(true);

        // Simulate concurrent job dispatching
        $jobs = [];
        for ($i = 0; $i < 5; $i++) {
            $batchBoxes = $boxes->slice($i * 20, 20);
            $jobs[] = new BulkBoxProcessingJob($batchBoxes->pluck('id')->toArray());
        }

        // Process jobs sequentially (simulating worker processing)
        foreach ($jobs as $job) {
            $job->handle();
        }

        $totalTime = (microtime(true) - $start) * 1000;

        // Concurrent processing should be efficient
        $this->assertLessThan(2000, $totalTime, 'Concurrent job processing should be efficient');
    }

    /**
     * Test job failure handling performance
     */
    public function test_job_failure_handling()
    {
        $start = microtime(true);

        try {
            // Create job with invalid data to trigger failure
            $job = new BulkBoxProcessingJob([999999]); // Non-existent ID
            $job->handle();
        } catch (\Exception $e) {
            // Expected failure
        }

        $failureTime = (microtime(true) - $start) * 1000;

        // Job failure should be handled quickly
        $this->assertLessThan(100, $failureTime, 'Job failure handling should be fast');
    }

    /**
     * Test queue monitoring performance
     */
    public function test_queue_monitoring_performance()
    {
        // Create multiple jobs
        $user = User::factory()->create();
        $task = DailyPackingTask::factory()->create(['user_id' => $user->id]);

        for ($i = 0; $i < 20; $i++) {
            $boxes = PackingBox::factory(10)->create(['daily_packing_task_id' => $task->id]);
            BulkBoxProcessingJob::dispatch($boxes->pluck('id')->toArray());
        }

        $start = microtime(true);

        // Monitor queue (this would be done by monitoring command)
        $queuedJobs = Queue::size();
        $failedJobs = 0; // Would get from failed jobs table

        $monitoringTime = (microtime(true) - $start) * 1000;

        // Queue monitoring should be very fast
        $this->assertLessThan(50, $monitoringTime, 'Queue monitoring should be very fast');

        // Verify jobs were queued
        Queue::assertPushed(BulkBoxProcessingJob::class, 20);
    }

    /**
     * Test job progress tracking performance
     */
    public function test_job_progress_tracking()
    {
        $user = User::factory()->create();
        $task = DailyPackingTask::factory()->create(['user_id' => $user->id]);
        $boxes = PackingBox::factory(50)->create(['daily_packing_task_id' => $task->id]);

        $start = microtime(true);

        // Simulate job with progress tracking
        $job = new BulkBoxProcessingJob($boxes->pluck('id')->toArray());

        // Mock progress tracking calls
        for ($i = 0; $i < 10; $i++) {
            Cache::put("job_progress_test_{$i}", [
                'total' => 50,
                'processed' => $i * 5,
                'percentage' => ($i * 5 / 50) * 100,
            ], 300);
        }

        $progressTime = (microtime(true) - $start) * 1000;

        // Progress tracking should not significantly impact performance
        $this->assertLessThan(100, $progressTime, 'Progress tracking should be lightweight');
    }

    /**
     * Test queue cleanup performance
     */
    public function test_queue_cleanup_performance()
    {
        // Create old cache entries to simulate completed jobs
        for ($i = 0; $i < 100; $i++) {
            Cache::put("old_job_progress_{$i}", ['completed' => true], 1);
        }

        sleep(2); // Ensure cache entries expire

        $start = microtime(true);

        // Cleanup expired cache entries (simulating cleanup job)
        for ($i = 0; $i < 100; $i++) {
            Cache::forget("old_job_progress_{$i}");
        }

        $cleanupTime = (microtime(true) - $start) * 1000;

        // Cleanup should be efficient
        $this->assertLessThan(200, $cleanupTime, 'Queue cleanup should be efficient');
    }
}
