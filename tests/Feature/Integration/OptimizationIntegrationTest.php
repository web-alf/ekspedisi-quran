<?php

namespace Tests\Feature\Integration;

use App\Models\DailyPackingTask;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\Sertifikat;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Models\WakafBatch;
use App\Services\Cache\DashboardCacheService;
use App\Services\PerformanceMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OptimizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $performanceMonitor;

    protected $dashboardCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceMonitor = app(PerformanceMonitoringService::class);
        $this->dashboardCache = app(DashboardCacheService::class);
    }

    /**
     * Test complete workflow optimization from request to response
     */
    public function test_complete_workflow_optimization()
    {
        // Create realistic test data
        $this->createRealisticTestData();

        // Test admin dashboard load time
        $user = User::factory()->create();
        $user->assignRole('admin');

        $start = microtime(true);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $loadTime = (microtime(true) - $start) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $loadTime, 'Dashboard should load in < 500ms');

        echo "\nWorkflow Performance:\n";
        echo 'Dashboard load time: '.round($loadTime, 2)."ms\n";
    }

    /**
     * Test cache and database optimization integration
     */
    public function test_cache_database_integration()
    {
        $this->createRealisticTestData();

        // Clear cache and measure cold performance
        Cache::flush();

        $start1 = microtime(true);
        $stats1 = $this->dashboardCache->getDashboardStats();
        $coldTime = (microtime(true) - $start1) * 1000;

        // Measure warm cache performance
        $start2 = microtime(true);
        $stats2 = $this->dashboardCache->getDashboardStats();
        $warmTime = (microtime(true) - $start2) * 1000;

        // Test database query optimization
        $start3 = microtime(true);
        $pengiriman = Pengiriman::with([
            'donatur:id,nama_donatur',
            'jenisQuran:id,nama_jenis',
            'status:id,nama_status',
        ])->paginate(20);
        $queryTime = (microtime(true) - $start3) * 1000;

        // Performance assertions
        $this->assertLessThan(100, $coldTime, 'Cold cache should be < 100ms');
        $this->assertLessThan(5, $warmTime, 'Warm cache should be < 5ms');
        $this->assertLessThan(50, $queryTime, 'Optimized queries should be < 50ms');
        $this->assertEquals($stats1, $stats2);

        echo "\nCache-Database Integration:\n";
        echo 'Cold cache time: '.round($coldTime, 2)."ms\n";
        echo 'Warm cache time: '.round($warmTime, 2)."ms\n";
        echo 'Optimized query time: '.round($queryTime, 2)."ms\n";
        echo 'Cache speed improvement: '.round($coldTime / $warmTime, 1)."x\n";
    }

    /**
     * Test queue and database optimization integration
     */
    public function test_queue_database_integration()
    {
        // Use database queue for testing
        config(['queue.default' => 'database']);

        $this->createRealisticTestData();

        // Test bulk certificate generation
        $wakafBatch = WakafBatch::factory()->create();
        $certificates = Sertifikat::factory(20)->create(['wakaf_batch_id' => $wakafBatch->id]);

        $start = microtime(true);

        // Dispatch bulk job
        \App\Jobs\Certificate\GenerateBulkCertificatesJob::dispatch($wakafBatch->id);

        // Process jobs
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--timeout' => 60,
        ]);

        $totalTime = (microtime(true) - $start) * 1000;

        // Verify jobs completed successfully
        $remainingJobs = DB::table('jobs')->count();
        $this->assertEquals(0, $remainingJobs);

        // Performance should be reasonable for bulk operations
        $avgTimePerCert = $totalTime / 20;
        $this->assertLessThan(5000, $avgTimePerCert, 'Bulk certificate generation should be < 5s per certificate');

        echo "\nQueue-Database Integration:\n";
        echo 'Bulk operation time: '.round($totalTime, 2)."ms\n";
        echo 'Average per certificate: '.round($avgTimePerCert, 2)."ms\n";
    }

    /**
     * Test warehouse operations under concurrent load
     */
    public function test_warehouse_concurrent_operations()
    {
        $this->createWarehouseTestData();

        $users = User::factory(3)->create()->each(function ($user) {
            $user->assignRole('warehouse');
        });

        $pengiriman = Pengiriman::factory(30)->create();

        // Simulate concurrent warehouse operations
        $results = [];
        $start = microtime(true);

        foreach ($users as $index => $user) {
            $userPengiriman = $pengiriman->slice($index * 10, 10);

            $task = DailyPackingTask::factory()->create([
                'user_id' => $user->id,
                'tanggal_tugas' => today(),
                'total_target' => 10,
            ]);

            foreach ($userPengiriman as $item) {
                try {
                    // Simulate box assignment and packing
                    $box = $task->getOrActivateBoxForJenis($item->jenis_quran_id);
                    $packingItem = $box->addItemSafe($item, $user->id);
                    $results[] = 'success';
                } catch (\Exception $e) {
                    $results[] = 'error: '.$e->getMessage();
                }
            }
        }

        $concurrentTime = (microtime(true) - $start) * 1000;

        // Analyze results
        $successCount = count(array_filter($results, fn ($r) => $r === 'success'));
        $errorCount = count($results) - $successCount;

        // Most operations should succeed without conflicts
        $this->assertGreaterThan(25, $successCount, 'Most concurrent operations should succeed');
        $this->assertLessThan(5, $errorCount, 'Minimal errors should occur with proper locking');
        $this->assertLessThan(2000, $concurrentTime, 'Concurrent operations should complete quickly');

        echo "\nConcurrent Warehouse Operations:\n";
        echo 'Total operations: '.count($results)."\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo 'Total time: '.round($concurrentTime, 2)."ms\n";
        echo 'Average per operation: '.round($concurrentTime / count($results), 2)."ms\n";
    }

    /**
     * Test system performance monitoring integration
     */
    public function test_performance_monitoring_integration()
    {
        $this->createRealisticTestData();

        // Clear existing metrics
        DB::table('performance_metrics')->truncate();

        // Perform various operations that should trigger monitoring
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Dashboard access
        $this->actingAs($user)->get('/admin/dashboard');

        // Pengiriman listing
        $this->actingAs($user)->get('/admin/pengiriman');

        // Search operation
        $this->actingAs($user)->get('/admin/pengiriman?search=test');

        // Check that metrics were collected
        $metricsCount = DB::table('performance_metrics')->count();
        $this->assertGreaterThan(0, $metricsCount, 'Performance metrics should be collected');

        // Test metric analysis
        $metrics = DB::table('performance_metrics')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();

        $avgResponseTime = $metrics->avg('response_time');
        $maxResponseTime = $metrics->max('response_time');

        $this->assertLessThan(1000, $avgResponseTime, 'Average response time should be < 1s');
        $this->assertLessThan(3000, $maxResponseTime, 'Max response time should be < 3s');

        echo "\nPerformance Monitoring:\n";
        echo "Metrics collected: {$metricsCount}\n";
        echo 'Average response time: '.round($avgResponseTime, 2)."ms\n";
        echo 'Max response time: '.round($maxResponseTime, 2)."ms\n";
    }

    /**
     * Test rate limiting integration with optimized responses
     */
    public function test_rate_limiting_with_optimization()
    {
        $this->createRealisticTestData();

        // Test tracking endpoint with rate limiting
        $responses = [];
        $start = microtime(true);

        // Make multiple requests up to the limit
        for ($i = 0; $i < 25; $i++) {
            $response = $this->get("/tracking/TEST{$i}");
            $responses[] = $response->status();
        }

        $requestTime = (microtime(true) - $start) * 1000;

        // Most requests should succeed (404 for non-existent tracking)
        $successfulRequests = count(array_filter($responses, fn ($status) => $status !== 429));
        $this->assertGreaterThan(20, $successfulRequests, 'Most requests should succeed before rate limit');

        // Test rate limited request
        $rateLimitedResponse = $this->get('/tracking/TESTLIMIT');
        $this->assertEquals(429, $rateLimitedResponse->status());

        // Performance should remain good even with rate limiting
        $avgTimePerRequest = $requestTime / 25;
        $this->assertLessThan(50, $avgTimePerRequest, 'Rate limited responses should still be fast');

        echo "\nRate Limiting Integration:\n";
        echo "Successful requests: {$successfulRequests}/25\n";
        echo 'Total request time: '.round($requestTime, 2)."ms\n";
        echo 'Average per request: '.round($avgTimePerRequest, 2)."ms\n";
    }

    /**
     * Test complete optimization stack under load
     */
    public function test_optimization_stack_under_load()
    {
        $this->createLargeTestData();

        $start = microtime(true);
        $operations = 0;

        // Simulate mixed load operations
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Multiple dashboard requests (should use cache)
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->get('/admin/dashboard');
            $operations++;
        }

        // Pengiriman operations
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->get('/admin/pengiriman?page='.($i + 1));
            $operations++;
        }

        // Search operations
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->get('/admin/pengiriman?search=donatur');
            $operations++;
        }

        $totalTime = (microtime(true) - $start) * 1000;
        $avgTimePerOperation = $totalTime / $operations;

        // System should handle mixed load efficiently
        $this->assertLessThan(200, $avgTimePerOperation, 'Mixed operations should average < 200ms');
        $this->assertLessThan(10000, $totalTime, 'Total load test should complete < 10s');

        echo "\nOptimization Stack Under Load:\n";
        echo "Total operations: {$operations}\n";
        echo 'Total time: '.round($totalTime, 2)."ms\n";
        echo 'Average per operation: '.round($avgTimePerOperation, 2)."ms\n";
        echo 'Operations per second: '.round($operations / ($totalTime / 1000), 2)."\n";
    }

    /**
     * Test fallback mechanisms when optimizations fail
     */
    public function test_optimization_fallback_mechanisms()
    {
        $this->createRealisticTestData();

        // Test cache fallback
        Cache::flush();

        // Simulate cache failure by disabling cache store temporarily
        $originalCacheStore = config('cache.default');
        config(['cache.default' => 'null']); // Use null cache driver

        $start = microtime(true);
        $stats = $this->dashboardCache->getDashboardStats();
        $fallbackTime = (microtime(true) - $start) * 1000;

        // Restore cache
        config(['cache.default' => $originalCacheStore]);

        // Fallback should still work, just slower
        $this->assertNotEmpty($stats);
        $this->assertLessThan(1000, $fallbackTime, 'Fallback should complete within reasonable time');

        echo "\nFallback Mechanisms:\n";
        echo 'Cache fallback time: '.round($fallbackTime, 2)."ms\n";
    }

    private function createRealisticTestData()
    {
        $donatur = Donatur::factory(10)->create();
        $jenisQuran = JenisQuran::factory(3)->create();
        $status = StatusPengiriman::factory(5)->create();

        Pengiriman::factory(100)->create()->each(function ($pengiriman) use ($donatur, $jenisQuran, $status) {
            $pengiriman->update([
                'donatur_id' => $donatur->random()->id,
                'jenis_quran_id' => $jenisQuran->random()->id,
                'status_id' => $status->random()->id,
            ]);
        });
    }

    private function createWarehouseTestData()
    {
        $jenisQuran = JenisQuran::factory(2)->create();
        $status = StatusPengiriman::where('slug', 'packing')->first()
                  ?? StatusPengiriman::factory()->create(['slug' => 'packing']);

        Pengiriman::factory(30)->create([
            'status_id' => $status->id,
        ])->each(function ($pengiriman) use ($jenisQuran) {
            $pengiriman->update([
                'jenis_quran_id' => $jenisQuran->random()->id,
            ]);
        });
    }

    private function createLargeTestData()
    {
        $donatur = Donatur::factory(20)->create();
        $jenisQuran = JenisQuran::factory(5)->create();
        $status = StatusPengiriman::factory(8)->create();

        Pengiriman::factory(500)->create()->each(function ($pengiriman) use ($donatur, $jenisQuran, $status) {
            $pengiriman->update([
                'donatur_id' => $donatur->random()->id,
                'jenis_quran_id' => $jenisQuran->random()->id,
                'status_id' => $status->random()->id,
            ]);
        });
    }
}
