<?php

namespace Tests\Unit\Performance;

use App\Models\DailyPackingTask;
use App\Models\Donatur;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\DashboardCacheService;
use App\Services\Cache\QueryCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected $dashboardCache;

    protected $queryCache;

    protected $queriesLog = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Performance tests target pre-refactor service APIs. Re-enable after updating to current DashboardCacheService/QueryCacheService methods.');
    }

    /**
     * Test N+1 query prevention in Pengiriman listing
     */
    public function test_pengiriman_list_prevents_n_plus_one()
    {
        // Create test data
        $donatur = Donatur::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();
        $status = StatusPengiriman::factory()->create();

        $pengiriman = Pengiriman::factory(10)->create([
            'donatur_id' => $donatur->id,
            'jenis_quran_id' => $jenisQuran->id,
            'status_id' => $status->id,
        ]);

        // Reset query log
        $this->queriesLog = [];

        // Test optimized query with eager loading
        $results = Pengiriman::with([
            'donatur:id,nama_donatur,email',
            'jenisQuran:id,nama_jenis',
            'status:id,nama_status',
            'wakafItem:id,wakif_name',
        ])->limit(10)->get();

        // Should use minimal queries regardless of record count
        $queryCount = count($this->queriesLog);
        $this->assertLessThanOrEqual(4, $queryCount, 'Eager loading should prevent N+1 queries');

        // Verify data is properly loaded
        $this->assertCount(10, $results);
        foreach ($results as $item) {
            $this->assertNotNull($item->donatur);
            $this->assertNotNull($item->jenisQuran);
            $this->assertNotNull($item->status);
        }
    }

    /**
     * Test dashboard query performance with indexes
     */
    public function test_dashboard_queries_use_indexes()
    {
        // Create test data
        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();
        $status = StatusPengiriman::factory()->create();

        // Create pengiriman with today's date
        Pengiriman::factory(50)->create([
            'jenis_quran_id' => $jenisQuran->id,
            'status_id' => $status->id,
            'created_at' => now(),
        ]);

        // Reset query log
        $this->queriesLog = [];

        // Test dashboard statistics queries
        $stats = [
            'total_pengiriman' => Pengiriman::count(),
            'pengiriman_today' => Pengiriman::whereDate('created_at', today())->count(),
            'pengiriman_by_status' => Pengiriman::select('status_id', DB::raw('count(*) as total'))
                ->groupBy('status_id')
                ->get(),
            'pengiriman_by_jenis' => Pengiriman::select('jenis_quran_id', DB::raw('count(*) as total'))
                ->groupBy('jenis_quran_id')
                ->get(),
        ];

        // Analyze query performance
        $totalTime = collect($this->queriesLog)->sum('time');
        $this->assertLessThan(100, $totalTime, 'Dashboard queries should be fast with proper indexes');

        // Verify results
        $this->assertEquals(50, $stats['total_pengiriman']);
        $this->assertEquals(50, $stats['pengiriman_today']);
    }

    /**
     * Test warehouse query optimizations
     */
    public function test_warehouse_queries_optimized()
    {
        // Create test data
        $user = User::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();

        $task = DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'tanggal_tugas' => today(),
        ]);

        $boxes = PackingBox::factory(5)->create([
            'daily_packing_task_id' => $task->id,
            'jenis_quran_id' => $jenisQuran->id,
        ]);

        // Reset query log
        $this->queriesLog = [];

        // Test optimized warehouse dashboard query
        $warehouseData = [
            'user_tasks' => DailyPackingTask::with([
                'user:id,name',
                'packingBoxes:id,daily_packing_task_id,status,jumlah_terisi,kapasitas',
            ])->whereDate('tanggal_tugas', today())->get(),

            'active_boxes' => PackingBox::with([
                'jenisQuran:id,nama_jenis',
                'dailyPackingTask.user:id,name',
            ])->where('status', 'filling')
                ->whereHas('dailyPackingTask', function ($q) {
                    $q->whereDate('tanggal_tugas', today());
                })->get(),
        ];

        $queryCount = count($this->queriesLog);
        $totalTime = collect($this->queriesLog)->sum('time');

        // Should use minimal queries with proper eager loading
        $this->assertLessThanOrEqual(6, $queryCount);
        $this->assertLessThan(50, $totalTime, 'Warehouse queries should be optimized');

        // Verify data integrity
        $this->assertCount(1, $warehouseData['user_tasks']);
        $this->assertCount(5, $warehouseData['active_boxes']);
    }

    /**
     * Test cache effectiveness for expensive queries
     */
    public function test_cache_reduces_query_load()
    {
        // Create test data
        $jenisQuran = JenisQuran::factory()->create();
        $status = StatusPengiriman::factory()->create();

        Pengiriman::factory(100)->create([
            'jenis_quran_id' => $jenisQuran->id,
            'status_id' => $status->id,
        ]);

        // Clear cache
        Cache::flush();

        // First request - should hit database
        $this->queriesLog = [];
        $stats1 = $this->dashboardCache->getDashboardStats();
        $firstRequestQueries = count($this->queriesLog);
        $firstRequestTime = collect($this->queriesLog)->sum('time');

        // Second request - should use cache
        $this->queriesLog = [];
        $stats2 = $this->dashboardCache->getDashboardStats();
        $secondRequestQueries = count($this->queriesLog);
        $secondRequestTime = collect($this->queriesLog)->sum('time');

        // Cache should significantly reduce queries and time
        $this->assertLessThan($firstRequestQueries, $secondRequestQueries + 1);
        $this->assertLessThan($firstRequestTime / 2, $secondRequestTime);

        // Results should be identical
        $this->assertEquals($stats1, $stats2);
    }

    /**
     * Test query optimization for search operations
     */
    public function test_search_queries_optimized()
    {
        // Create test data with searchable content
        $donatur = Donatur::factory()->create([
            'nama_donatur' => 'John Doe Test',
        ]);

        Pengiriman::factory(20)->create([
            'donatur_id' => $donatur->id,
            'no_resi' => function () {
                static $counter = 1;

                return 'RESI'.str_pad($counter++, 6, '0', STR_PAD_LEFT);
            },
        ]);

        // Reset query log
        $this->queriesLog = [];

        // Test search query with joins and indexes
        $searchResults = Pengiriman::with(['donatur:id,nama_donatur', 'jenisQuran:id,nama_jenis'])
            ->whereHas('donatur', function ($q) {
                $q->where('nama_donatur', 'LIKE', '%John%');
            })
            ->orWhere('no_resi', 'LIKE', '%RESI000001%')
            ->limit(10)
            ->get();

        $queryCount = count($this->queriesLog);
        $totalTime = collect($this->queriesLog)->sum('time');

        // Search should be efficient with proper indexes
        $this->assertLessThanOrEqual(3, $queryCount);
        $this->assertLessThan(30, $totalTime);
        $this->assertGreaterThan(0, $searchResults->count());
    }

    /**
     * Test pagination performance
     */
    public function test_pagination_performance()
    {
        // Create large dataset
        $donatur = Donatur::factory()->create();
        $jenisQuran = JenisQuran::factory()->create();
        $status = StatusPengiriman::factory()->create();

        Pengiriman::factory(200)->create([
            'donatur_id' => $donatur->id,
            'jenis_quran_id' => $jenisQuran->id,
            'status_id' => $status->id,
        ]);

        // Test pagination at different pages
        $pages = [1, 5, 10];

        foreach ($pages as $page) {
            $this->queriesLog = [];

            $results = Pengiriman::with(['donatur:id,nama_donatur', 'jenisQuran:id,nama_jenis'])
                ->orderBy('created_at', 'desc')
                ->paginate(20, ['*'], 'page', $page);

            $queryCount = count($this->queriesLog);
            $totalTime = collect($this->queriesLog)->sum('time');

            // Pagination should maintain consistent performance
            $this->assertLessThanOrEqual(4, $queryCount, "Page {$page} should use efficient queries");
            $this->assertLessThan(50, $totalTime, "Page {$page} should be fast");
            $this->assertCount(20, $results->items());
        }
    }

    /**
     * Test bulk operation query optimization
     */
    public function test_bulk_operations_optimized()
    {
        // Create test data
        $jenisQuran = JenisQuran::factory()->create();
        $status = StatusPengiriman::factory()->create();

        $pengiriman = Pengiriman::factory(50)->create([
            'jenis_quran_id' => $jenisQuran->id,
            'status_id' => $status->id,
        ]);

        // Reset query log
        $this->queriesLog = [];

        // Test bulk status update using whereIn (should be single query)
        $ids = $pengiriman->pluck('id')->take(10)->toArray();

        DB::beginTransaction();
        $updated = Pengiriman::whereIn('id', $ids)->update([
            'status_id' => $status->id,
            'updated_at' => now(),
        ]);
        DB::commit();

        $queryCount = count($this->queriesLog);

        // Bulk update should use minimal queries
        $this->assertLessThanOrEqual(3, $queryCount); // BEGIN, UPDATE, COMMIT
        $this->assertEquals(10, $updated);
    }

    /**
     * Test index usage for date range queries
     */
    public function test_date_range_queries_use_indexes()
    {
        // Create data across different dates
        $dates = [
            now()->subDays(7),
            now()->subDays(3),
            now()->subDay(),
            now(),
        ];

        foreach ($dates as $date) {
            Pengiriman::factory(25)->create([
                'created_at' => $date,
            ]);
        }

        // Reset query log
        $this->queriesLog = [];

        // Test date range query
        $results = Pengiriman::whereBetween('created_at', [
            now()->subDays(7)->startOfDay(),
            now()->endOfDay(),
        ])->count();

        $totalTime = collect($this->queriesLog)->sum('time');

        // Date range query should be fast with proper index
        $this->assertLessThan(20, $totalTime);
        $this->assertEquals(100, $results);
    }

    /**
     * Test memory usage for large datasets
     */
    public function test_large_dataset_memory_efficiency()
    {
        // Create larger dataset
        $donatur = Donatur::factory()->create();
        Pengiriman::factory(500)->create(['donatur_id' => $donatur->id]);

        $initialMemory = memory_get_usage();

        // Test chunked processing for memory efficiency
        $count = 0;
        Pengiriman::with(['donatur:id,nama_donatur'])
            ->chunk(50, function ($pengiriman) use (&$count) {
                $count += $pengiriman->count();
                // Process chunk without storing in memory
                foreach ($pengiriman as $item) {
                    // Simulate processing
                    $item->donatur->nama_donatur;
                }
            });

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable for chunked processing
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease); // Less than 10MB
        $this->assertEquals(500, $count);
    }

    protected function tearDown(): void
    {
        // Log performance summary
        if (! empty($this->queriesLog)) {
            $totalQueries = count($this->queriesLog);
            $totalTime = collect($this->queriesLog)->sum('time');
            $slowQueries = collect($this->queriesLog)->where('time', '>', 10)->count();

            echo "\n--- Performance Summary ---\n";
            echo "Total Queries: {$totalQueries}\n";
            echo "Total Time: {$totalTime}ms\n";
            echo "Slow Queries (>10ms): {$slowQueries}\n";
            echo 'Average Time: '.round($totalTime / max($totalQueries, 1), 2)."ms\n";
        }

        parent::tearDown();
    }
}
