<?php

namespace Tests\Unit\Performance;

use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Services\Cache\DashboardCacheService;
use App\Services\Cache\GeographicCacheService;
use App\Services\Cache\QueryCacheService;
use App\Services\Cache\ReferenceDataCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CachePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $dashboardCache;

    protected $queryCache;

    protected $referenceCache;

    protected $geoCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardCache = app(DashboardCacheService::class);
        $this->queryCache = app(QueryCacheService::class);
        $this->referenceCache = app(ReferenceDataCacheService::class);
        $this->geoCache = app(GeographicCacheService::class);
    }

    /**
     * Test cache hit rates for dashboard data
     */
    public function test_dashboard_cache_hit_rates()
    {
        // Create test data
        $this->createTestData();

        // Clear cache to start fresh
        Cache::flush();

        // First request - cache miss
        $start1 = microtime(true);
        $stats1 = $this->dashboardCache->getDashboardStats();
        $time1 = (microtime(true) - $start1) * 1000;

        // Second request - cache hit
        $start2 = microtime(true);
        $stats2 = $this->dashboardCache->getDashboardStats();
        $time2 = (microtime(true) - $start2) * 1000;

        // Third request - cache hit
        $start3 = microtime(true);
        $stats3 = $this->dashboardCache->getDashboardStats();
        $time3 = (microtime(true) - $start3) * 1000;

        // Cache hits should be significantly faster
        $this->assertLessThan($time1 / 5, $time2, 'Second request should be much faster (cache hit)');
        $this->assertLessThan($time1 / 5, $time3, 'Third request should be much faster (cache hit)');

        // Data should be consistent
        $this->assertEquals($stats1, $stats2);
        $this->assertEquals($stats1, $stats3);

        echo "\nCache Performance:\n";
        echo 'First request (miss): '.round($time1, 2)."ms\n";
        echo 'Second request (hit): '.round($time2, 2)."ms\n";
        echo 'Third request (hit): '.round($time3, 2)."ms\n";
        echo 'Speed improvement: '.round($time1 / $time2, 1)."x\n";
    }

    /**
     * Test cache invalidation works correctly
     */
    public function test_cache_invalidation()
    {
        $this->createTestData();

        // Warm up cache
        $initialStats = $this->dashboardCache->getDashboardStats();
        $initialCount = $initialStats['total_pengiriman'] ?? 0;

        // Add new data
        Pengiriman::factory()->create();

        // Cache should still return old data
        $cachedStats = $this->dashboardCache->getDashboardStats();
        $this->assertEquals($initialCount, $cachedStats['total_pengiriman'] ?? 0);

        // Invalidate cache
        $this->dashboardCache->invalidateCache();

        // Should return updated data
        $freshStats = $this->dashboardCache->getDashboardStats();
        $this->assertEquals($initialCount + 1, $freshStats['total_pengiriman'] ?? 0);
    }

    /**
     * Test hierarchical cache invalidation
     */
    public function test_hierarchical_cache_invalidation()
    {
        $this->createTestData();

        // Warm up all cache levels
        $dashboardStats = $this->dashboardCache->getDashboardStats();
        $jenisQuranData = $this->referenceCache->getJenisQuranData();
        $statusData = $this->referenceCache->getStatusPengirimanData();

        // Create new pengiriman - should invalidate related caches
        $pengiriman = Pengiriman::factory()->create();

        // Dashboard cache should be invalidated
        Cache::forget('dashboard_stats');

        // Reference data should still be cached (not related to pengiriman creation)
        $this->assertTrue(Cache::has('reference_jenis_quran'));
        $this->assertTrue(Cache::has('reference_status_pengiriman'));

        // But geographic cache might be affected if location-related
        // Test that unrelated caches remain intact
        $freshJenisData = $this->referenceCache->getJenisQuranData();
        $this->assertEquals($jenisQuranData, $freshJenisData);
    }

    /**
     * Test cache performance under load
     */
    public function test_cache_performance_under_load()
    {
        $this->createTestData();
        Cache::flush();

        $requests = 20;
        $times = [];

        // Simulate multiple concurrent requests
        for ($i = 0; $i < $requests; $i++) {
            $start = microtime(true);
            $stats = $this->dashboardCache->getDashboardStats();
            $times[] = (microtime(true) - $start) * 1000;
        }

        // First request should be slowest (cache miss)
        $this->assertGreaterThan($times[1], $times[0]);

        // Subsequent requests should be consistently fast
        $cacheHitTimes = array_slice($times, 1);
        $avgCacheHitTime = array_sum($cacheHitTimes) / count($cacheHitTimes);
        $maxCacheHitTime = max($cacheHitTimes);

        $this->assertLessThan(5, $avgCacheHitTime, 'Cache hits should average < 5ms');
        $this->assertLessThan(20, $maxCacheHitTime, 'Max cache hit time should be < 20ms');

        echo "\nLoad Test Results:\n";
        echo 'Cache miss time: '.round($times[0], 2)."ms\n";
        echo 'Average cache hit time: '.round($avgCacheHitTime, 2)."ms\n";
        echo 'Max cache hit time: '.round($maxCacheHitTime, 2)."ms\n";
    }

    /**
     * Test cache memory usage
     */
    public function test_cache_memory_efficiency()
    {
        // Create large dataset
        $this->createLargeTestData();

        // Measure cache memory usage
        $initialMemory = memory_get_usage();

        // Warm up various caches
        $this->dashboardCache->getDashboardStats();
        $this->referenceCache->getJenisQuranData();
        $this->referenceCache->getStatusPengirimanData();

        // Check geographic cache with province data
        $provinces = $this->geoCache->getProvinces();

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Cache should not use excessive memory
        $this->assertLessThan(5 * 1024 * 1024, $memoryIncrease, 'Cache should use < 5MB memory');

        echo "\nMemory Usage:\n";
        echo 'Memory increase: '.round($memoryIncrease / 1024 / 1024, 2)."MB\n";
    }

    /**
     * Test cache expiration and refresh
     */
    public function test_cache_expiration_and_refresh()
    {
        $this->createTestData();

        // Set short-lived cache
        $key = 'test_expiration';
        $data = ['test' => 'data', 'timestamp' => time()];
        Cache::put($key, $data, 1); // 1 second

        // Should be available immediately
        $this->assertTrue(Cache::has($key));
        $this->assertEquals($data, Cache::get($key));

        // Wait for expiration
        sleep(2);

        // Should be expired
        $this->assertFalse(Cache::has($key));
        $this->assertNull(Cache::get($key));
    }

    /**
     * Test query result caching
     */
    public function test_query_result_caching()
    {
        $this->createTestData();

        $queryKey = 'test_expensive_query';
        $cacheTime = 60; // 60 seconds

        // First execution - should hit database
        $start1 = microtime(true);
        $result1 = $this->queryCache->remember($queryKey, $cacheTime, function () {
            return Pengiriman::with(['donatur', 'jenisQuran', 'status'])
                ->where('created_at', '>=', now()->subDays(7))
                ->get();
        });
        $time1 = (microtime(true) - $start1) * 1000;

        // Second execution - should use cache
        $start2 = microtime(true);
        $result2 = $this->queryCache->remember($queryKey, $cacheTime, function () {
            return Pengiriman::with(['donatur', 'jenisQuran', 'status'])
                ->where('created_at', '>=', now()->subDays(7))
                ->get();
        });
        $time2 = (microtime(true) - $start2) * 1000;

        // Cache should be significantly faster
        $this->assertLessThan($time1 / 3, $time2);
        $this->assertEquals($result1->count(), $result2->count());

        echo "\nQuery Cache Performance:\n";
        echo 'Database query: '.round($time1, 2)."ms\n";
        echo 'Cached query: '.round($time2, 2)."ms\n";
        echo 'Speed improvement: '.round($time1 / $time2, 1)."x\n";
    }

    /**
     * Test cache tagging and selective invalidation
     */
    public function test_cache_tagging()
    {
        $this->createTestData();

        // Cache data with tags
        Cache::tags(['pengiriman', 'dashboard'])->put('pengiriman_stats', [
            'total' => 100,
            'today' => 10,
        ], 3600);

        Cache::tags(['reference', 'static'])->put('jenis_quran_list', [
            'Al-Quran A5',
            'Al-Quran A4',
        ], 7200);

        // Both should be cached
        $this->assertTrue(Cache::tags(['pengiriman', 'dashboard'])->has('pengiriman_stats'));
        $this->assertTrue(Cache::tags(['reference', 'static'])->has('jenis_quran_list'));

        // Flush only pengiriman-related cache
        Cache::tags(['pengiriman'])->flush();

        // Pengiriman cache should be gone
        $this->assertFalse(Cache::tags(['pengiriman', 'dashboard'])->has('pengiriman_stats'));

        // Reference cache should remain
        $this->assertTrue(Cache::tags(['reference', 'static'])->has('jenis_quran_list'));
    }

    /**
     * Test cache warming strategies
     */
    public function test_cache_warming()
    {
        $this->createTestData();
        Cache::flush();

        // Warm critical caches
        $start = microtime(true);

        // Warm dashboard cache
        $this->dashboardCache->warmCache();

        // Warm reference data
        $this->referenceCache->warmCache();

        $warmingTime = (microtime(true) - $start) * 1000;

        // Verify caches are warmed
        $this->assertTrue(Cache::has('dashboard_stats'));
        $this->assertTrue(Cache::has('reference_jenis_quran'));
        $this->assertTrue(Cache::has('reference_status_pengiriman'));

        // Test performance after warming
        $start = microtime(true);
        $stats = $this->dashboardCache->getDashboardStats();
        $cachedTime = (microtime(true) - $start) * 1000;

        $this->assertLessThan(5, $cachedTime, 'Warmed cache should be very fast');

        echo "\nCache Warming:\n";
        echo 'Warming time: '.round($warmingTime, 2)."ms\n";
        echo 'Post-warming access: '.round($cachedTime, 2)."ms\n";
    }

    private function createTestData()
    {
        $donatur = Donatur::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();
        $status = StatusPengiriman::factory()->create();

        Pengiriman::factory(50)->create([
            'donatur_id' => $donatur->id,
            'jenis_quran_id' => $jenisQuran->id,
            'status_id' => $status->id,
        ]);
    }

    private function createLargeTestData()
    {
        $donatur = Donatur::factory(5)->create();
        $jenisQuran = JenisQuran::factory(3)->create();
        $status = StatusPengiriman::factory(5)->create();

        Pengiriman::factory(200)->create()->each(function ($pengiriman) use ($donatur, $jenisQuran, $status) {
            $pengiriman->update([
                'donatur_id' => $donatur->random()->id,
                'jenis_quran_id' => $jenisQuran->random()->id,
                'status_id' => $status->random()->id,
            ]);
        });
    }
}
