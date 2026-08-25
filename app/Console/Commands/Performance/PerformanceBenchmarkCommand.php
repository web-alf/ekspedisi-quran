<?php

namespace App\Console\Commands\Performance;

use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceBenchmarkCommand extends Command
{
    protected $signature = 'performance:benchmark 
                            {--output=console : Output format (console, json)}
                            {--iterations=5 : Number of iterations to run}
                            {--warmup=1 : Number of warmup iterations}';

    protected $description = 'Run comprehensive performance benchmarks';

    private $results = [];

    public function handle()
    {
        $this->info('🚀 Starting Performance Benchmark Suite...');

        $iterations = $this->option('iterations');
        $warmup = $this->option('warmup');

        // Warmup
        $this->info("🔥 Running {$warmup} warmup iteration(s)...");
        for ($i = 0; $i < $warmup; $i++) {
            $this->runBenchmarkSuite();
        }

        // Actual benchmarks
        $this->info("📊 Running {$iterations} benchmark iteration(s)...");
        $allResults = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->line('  Iteration '.($i + 1)."/{$iterations}");
            $allResults[] = $this->runBenchmarkSuite();
        }

        // Calculate averages
        $this->results = $this->calculateAverages($allResults);

        // Output results
        if ($this->option('output') === 'json') {
            $this->outputJson();
        } else {
            $this->outputConsole();
        }

        return 0;
    }

    private function runBenchmarkSuite(): array
    {
        return [
            'database' => $this->benchmarkDatabaseOperations(),
            'cache' => $this->benchmarkCacheOperations(),
            'queries' => $this->benchmarkQueryPerformance(),
            'memory' => $this->benchmarkMemoryUsage(),
            'warehouse' => $this->benchmarkWarehouseOperations(),
        ];
    }

    private function benchmarkDatabaseOperations(): array
    {
        $results = [];

        // Simple count query
        $start = microtime(true);
        $count = Pengiriman::count();
        $results['simple_count'] = (microtime(true) - $start) * 1000;

        // Complex join query
        $start = microtime(true);
        $data = Pengiriman::with(['donatur', 'jenisQuran', 'status'])
            ->limit(100)
            ->get();
        $results['complex_join'] = (microtime(true) - $start) * 1000;

        // Aggregation query
        $start = microtime(true);
        $stats = Pengiriman::selectRaw('
            COUNT(*) as total,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week
        ')->first();
        $results['aggregation'] = (microtime(true) - $start) * 1000;

        // Search query
        $start = microtime(true);
        $search = Pengiriman::whereHas('donatur', function ($q) {
            $q->where('nama_donatur', 'LIKE', '%test%');
        })->limit(50)->get();
        $results['search'] = (microtime(true) - $start) * 1000;

        // Pagination query
        $start = microtime(true);
        $paginated = Pengiriman::with(['donatur:id,nama_donatur'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        $results['pagination'] = (microtime(true) - $start) * 1000;

        return $results;
    }

    private function benchmarkCacheOperations(): array
    {
        $results = [];

        // Cache write performance
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::put("benchmark_key_{$i}", ['data' => "value_{$i}"], 300);
        }
        $results['write_100_keys'] = (microtime(true) - $start) * 1000;

        // Cache read performance
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::get("benchmark_key_{$i}");
        }
        $results['read_100_keys'] = (microtime(true) - $start) * 1000;

        // Cache remember performance (miss)
        Cache::forget('benchmark_remember');
        $start = microtime(true);
        $data = Cache::remember('benchmark_remember', 300, function () {
            return Pengiriman::with(['donatur'])->limit(50)->get()->toArray();
        });
        $results['remember_miss'] = (microtime(true) - $start) * 1000;

        // Cache remember performance (hit)
        $start = microtime(true);
        $cachedData = Cache::remember('benchmark_remember', 300, function () {
            return Pengiriman::with(['donatur'])->limit(50)->get()->toArray();
        });
        $results['remember_hit'] = (microtime(true) - $start) * 1000;

        // Cache tags performance
        $start = microtime(true);
        Cache::tags(['pengiriman', 'benchmark'])->put('tagged_data', $data, 300);
        $results['tagged_write'] = (microtime(true) - $start) * 1000;

        $start = microtime(true);
        $taggedData = Cache::tags(['pengiriman', 'benchmark'])->get('tagged_data');
        $results['tagged_read'] = (microtime(true) - $start) * 1000;

        // Cleanup
        for ($i = 0; $i < 100; $i++) {
            Cache::forget("benchmark_key_{$i}");
        }
        Cache::tags(['benchmark'])->flush();

        return $results;
    }

    private function benchmarkQueryPerformance(): array
    {
        $results = [];

        // N+1 query test (bad)
        $start = microtime(true);
        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        $pengiriman = Pengiriman::limit(10)->get();
        foreach ($pengiriman as $p) {
            $p->donatur; // This would cause N+1 without eager loading
        }

        $results['n_plus_one_queries'] = $queryCount;
        $results['n_plus_one_time'] = (microtime(true) - $start) * 1000;

        // Optimized query (good)
        $start = microtime(true);
        $queryCount = 0;

        $pengirimanOptimized = Pengiriman::with(['donatur'])->limit(10)->get();
        foreach ($pengirimanOptimized as $p) {
            $p->donatur;
        }

        $results['optimized_queries'] = $queryCount;
        $results['optimized_time'] = (microtime(true) - $start) * 1000;

        // Index usage test
        $start = microtime(true);
        $dateRangeCount = Pengiriman::whereBetween('created_at', [
            now()->subDays(30),
            now(),
        ])->count();
        $results['date_range_index'] = (microtime(true) - $start) * 1000;

        return $results;
    }

    private function benchmarkMemoryUsage(): array
    {
        $results = [];
        $initialMemory = memory_get_usage(true);

        // Load large dataset in memory
        $start = microtime(true);
        $largeDataset = Pengiriman::with(['donatur', 'jenisQuran'])->limit(1000)->get();
        $memoryAfterLoad = memory_get_usage(true);
        $results['large_dataset_time'] = (microtime(true) - $start) * 1000;
        $results['large_dataset_memory'] = ($memoryAfterLoad - $initialMemory) / 1024 / 1024; // MB

        // Chunked processing
        $chunkStart = microtime(true);
        $maxMemory = $initialMemory;
        $processedCount = 0;

        Pengiriman::with(['donatur'])->chunk(100, function ($chunk) use (&$maxMemory, &$processedCount) {
            $processedCount += $chunk->count();
            $currentMemory = memory_get_usage(true);
            $maxMemory = max($maxMemory, $currentMemory);

            // Simulate processing
            foreach ($chunk as $item) {
                $item->donatur->nama_donatur;
            }
        });

        $results['chunked_processing_time'] = (microtime(true) - $chunkStart) * 1000;
        $results['chunked_max_memory'] = ($maxMemory - $initialMemory) / 1024 / 1024; // MB
        $results['processed_records'] = $processedCount;

        // Memory efficiency ratio
        $results['memory_efficiency'] = $results['large_dataset_memory'] / $results['chunked_max_memory'];

        return $results;
    }

    private function benchmarkWarehouseOperations(): array
    {
        $results = [];

        // Warehouse dashboard query
        $start = microtime(true);
        $warehouseStats = DailyPackingTask::with([
            'user:id,name',
            'packingBoxes' => function ($q) {
                $q->select('id', 'daily_packing_task_id', 'status', 'jumlah_terisi', 'kapasitas');
            },
        ])->whereDate('tanggal_tugas', today())->get();
        $results['warehouse_dashboard'] = (microtime(true) - $start) * 1000;

        // Box scanning simulation
        $start = microtime(true);
        $boxes = PackingBox::with(['jenisQuran', 'packingItems'])
            ->where('status', 'filling')
            ->limit(50)
            ->get();
        $results['box_scanning'] = (microtime(true) - $start) * 1000;

        // Performance metrics calculation
        $start = microtime(true);
        $performanceData = DB::table('user_performance')
            ->join('users', 'user_performance.user_id', '=', 'users.id')
            ->select('users.name', 'user_performance.*')
            ->whereDate('user_performance.tanggal', today())
            ->get();
        $results['performance_metrics'] = (microtime(true) - $start) * 1000;

        return $results;
    }

    private function calculateAverages(array $allResults): array
    {
        $averages = [];
        $categories = array_keys($allResults[0]);

        foreach ($categories as $category) {
            $averages[$category] = [];
            $metrics = array_keys($allResults[0][$category]);

            foreach ($metrics as $metric) {
                $values = array_column(array_column($allResults, $category), $metric);
                $averages[$category][$metric] = [
                    'avg' => array_sum($values) / count($values),
                    'min' => min($values),
                    'max' => max($values),
                    'std' => $this->standardDeviation($values),
                ];
            }
        }

        return $averages;
    }

    private function standardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values);

        return sqrt(array_sum($squaredDifferences) / count($values));
    }

    private function outputConsole(): void
    {
        $this->info("\n📊 Performance Benchmark Results");
        $this->info('====================================');

        foreach ($this->results as $category => $metrics) {
            $this->info("\n📈 ".strtoupper($category).' Performance:');

            foreach ($metrics as $metric => $stats) {
                $unit = $this->getUnit($metric);
                $this->line("  {$metric}: ".
                    round($stats['avg'], 2).$unit.
                    ' (±'.round($stats['std'], 2).$unit.')');
            }
        }

        $this->info("\n🎯 Performance Summary:");
        $this->evaluatePerformance();
    }

    private function outputJson(): void
    {
        $output = [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => config('database.default'),
            'cache' => config('cache.default'),
            'results' => $this->results,
            'performance_grade' => $this->calculatePerformanceGrade(),
            'recommendations' => $this->generateRecommendations(),
        ];

        echo json_encode($output, JSON_PRETTY_PRINT);
    }

    private function getUnit(string $metric): string
    {
        if (strpos($metric, 'time') !== false) {
            return 'ms';
        }
        if (strpos($metric, 'memory') !== false) {
            return 'MB';
        }
        if (strpos($metric, 'queries') !== false) {
            return ' queries';
        }

        return '';
    }

    private function evaluatePerformance(): void
    {
        $warnings = [];

        // Check database performance
        if ($this->results['database']['simple_count']['avg'] > 10) {
            $warnings[] = "Simple count query is slow (>{$this->results['database']['simple_count']['avg']}ms)";
        }

        if ($this->results['database']['complex_join']['avg'] > 100) {
            $warnings[] = "Complex join query is slow (>{$this->results['database']['complex_join']['avg']}ms)";
        }

        // Check cache performance
        if ($this->results['cache']['remember_hit']['avg'] > 5) {
            $warnings[] = "Cache hit is slow (>{$this->results['cache']['remember_hit']['avg']}ms)";
        }

        // Check memory usage
        if ($this->results['memory']['large_dataset_memory']['avg'] > 100) {
            $warnings[] = "Large dataset uses too much memory (>{$this->results['memory']['large_dataset_memory']['avg']}MB)";
        }

        if (empty($warnings)) {
            $this->info('✅ All performance metrics are within acceptable ranges!');
        } else {
            $this->warn('⚠️  Performance concerns detected:');
            foreach ($warnings as $warning) {
                $this->warn('  • '.$warning);
            }
        }
    }

    private function calculatePerformanceGrade(): string
    {
        $score = 100;

        // Database penalties
        if ($this->results['database']['simple_count']['avg'] > 10) {
            $score -= 5;
        }
        if ($this->results['database']['complex_join']['avg'] > 100) {
            $score -= 10;
        }
        if ($this->results['database']['pagination']['avg'] > 50) {
            $score -= 5;
        }

        // Cache penalties
        if ($this->results['cache']['remember_hit']['avg'] > 5) {
            $score -= 10;
        }
        if ($this->results['cache']['read_100_keys']['avg'] > 20) {
            $score -= 5;
        }

        // Memory penalties
        if ($this->results['memory']['large_dataset_memory']['avg'] > 100) {
            $score -= 15;
        }
        if ($this->results['memory']['memory_efficiency']['avg'] < 2) {
            $score -= 10;
        }

        // Query optimization penalties
        if ($this->results['queries']['n_plus_one_queries']['avg'] > 15) {
            $score -= 20;
        }

        if ($score >= 95) {
            return 'A+';
        }
        if ($score >= 90) {
            return 'A';
        }
        if ($score >= 80) {
            return 'B';
        }
        if ($score >= 70) {
            return 'C';
        }
        if ($score >= 60) {
            return 'D';
        }

        return 'F';
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        if ($this->results['database']['complex_join']['avg'] > 100) {
            $recommendations[] = 'Consider adding database indexes for complex join queries';
        }

        if ($this->results['cache']['remember_hit']['avg'] > 5) {
            $recommendations[] = 'Cache performance could be improved - consider Redis optimization';
        }

        if ($this->results['memory']['memory_efficiency']['avg'] < 2) {
            $recommendations[] = 'Use chunked processing for large datasets to improve memory efficiency';
        }

        if ($this->results['queries']['n_plus_one_queries']['avg'] > 15) {
            $recommendations[] = 'Implement eager loading to prevent N+1 query problems';
        }

        return $recommendations;
    }
}
