<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceBenchmarkService
{
    protected array $benchmarks = [];

    protected array $baselineMetrics = [];

    public function __construct()
    {
        $this->loadBaselines();
    }

    /**
     * Run comprehensive performance benchmarks
     */
    public function runBenchmarks(): array
    {
        $results = [
            'timestamp' => now()->toISOString(),
            'benchmarks' => [],
        ];

        // Database performance benchmark
        $results['benchmarks']['database'] = $this->benchmarkDatabase();

        // Cache performance benchmark
        $results['benchmarks']['cache'] = $this->benchmarkCache();

        // File I/O benchmark
        $results['benchmarks']['file_io'] = $this->benchmarkFileIO();

        // Memory performance benchmark
        $results['benchmarks']['memory'] = $this->benchmarkMemory();

        // CPU performance benchmark
        $results['benchmarks']['cpu'] = $this->benchmarkCPU();

        // Network performance benchmark
        $results['benchmarks']['network'] = $this->benchmarkNetwork();

        // Calculate performance scores
        $results['scores'] = $this->calculatePerformanceScores($results['benchmarks']);

        // Compare with baselines
        $results['comparison'] = $this->compareWithBaselines($results['benchmarks']);

        // Store results for trending
        $this->storeBenchmarkResults($results);

        return $results;
    }

    /**
     * Benchmark database performance
     */
    protected function benchmarkDatabase(): array
    {
        $results = [];

        // Simple query benchmark
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            DB::select('SELECT 1');
        }
        $results['simple_query_100x'] = round((microtime(true) - $start) * 1000, 2);

        // Insert benchmark
        $start = microtime(true);
        try {
            DB::beginTransaction();
            for ($i = 0; $i < 10; $i++) {
                DB::table('benchmark_test')->insert([
                    'test_data' => 'benchmark_'.$i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::rollback(); // Don't actually insert data
            $results['insert_10x'] = round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            $results['insert_10x'] = 'N/A (table missing)';
        }

        // Complex query benchmark (if tables exist)
        try {
            $start = microtime(true);
            DB::table('pengiriman')
                ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
                ->select('pengiriman.*', 'status_pengiriman.nama')
                ->limit(100)
                ->get();
            $results['complex_query'] = round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            $results['complex_query'] = 'N/A';
        }

        // Connection overhead
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            DB::connection()->getPdo();
        }
        $results['connection_overhead_10x'] = round((microtime(true) - $start) * 1000, 2);

        return $results;
    }

    /**
     * Benchmark cache performance
     */
    protected function benchmarkCache(): array
    {
        $results = [];

        // Write benchmark
        $testData = str_repeat('x', 1024); // 1KB data
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::put("benchmark_write_{$i}", $testData, 60);
        }
        $results['write_1kb_100x'] = round((microtime(true) - $start) * 1000, 2);

        // Read benchmark
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::get("benchmark_write_{$i}");
        }
        $results['read_1kb_100x'] = round((microtime(true) - $start) * 1000, 2);

        // Large data benchmark
        $largeData = str_repeat('x', 100 * 1024); // 100KB data
        $start = microtime(true);
        Cache::put('benchmark_large', $largeData, 60);
        $results['write_100kb'] = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        Cache::get('benchmark_large');
        $results['read_100kb'] = round((microtime(true) - $start) * 1000, 2);

        // Cleanup
        for ($i = 0; $i < 100; $i++) {
            Cache::forget("benchmark_write_{$i}");
        }
        Cache::forget('benchmark_large');

        // Delete benchmark
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::forget("benchmark_delete_{$i}");
        }
        $results['delete_100x'] = round((microtime(true) - $start) * 1000, 2);

        return $results;
    }

    /**
     * Benchmark file I/O performance
     */
    protected function benchmarkFileIO(): array
    {
        $results = [];
        $benchmarkDir = storage_path('benchmarks');

        if (! is_dir($benchmarkDir)) {
            mkdir($benchmarkDir, 0755, true);
        }

        // Write benchmark
        $testData = str_repeat('x', 1024); // 1KB data
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            file_put_contents("{$benchmarkDir}/test_{$i}.txt", $testData);
        }
        $results['write_1kb_100_files'] = round((microtime(true) - $start) * 1000, 2);

        // Read benchmark
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            file_get_contents("{$benchmarkDir}/test_{$i}.txt");
        }
        $results['read_1kb_100_files'] = round((microtime(true) - $start) * 1000, 2);

        // Large file benchmark
        $largeData = str_repeat('x', 1024 * 1024); // 1MB data
        $start = microtime(true);
        file_put_contents("{$benchmarkDir}/large_test.txt", $largeData);
        $results['write_1mb'] = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        file_get_contents("{$benchmarkDir}/large_test.txt");
        $results['read_1mb'] = round((microtime(true) - $start) * 1000, 2);

        // Directory operations
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            is_file("{$benchmarkDir}/test_{$i}.txt");
        }
        $results['file_exists_100x'] = round((microtime(true) - $start) * 1000, 2);

        // Cleanup
        for ($i = 0; $i < 100; $i++) {
            @unlink("{$benchmarkDir}/test_{$i}.txt");
        }
        @unlink("{$benchmarkDir}/large_test.txt");
        @rmdir($benchmarkDir);

        return $results;
    }

    /**
     * Benchmark memory operations
     */
    protected function benchmarkMemory(): array
    {
        $results = [];

        // Array operations benchmark
        $start = microtime(true);
        $array = [];
        for ($i = 0; $i < 100000; $i++) {
            $array[] = $i;
        }
        $results['array_populate_100k'] = round((microtime(true) - $start) * 1000, 2);

        // Array search benchmark
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            in_array(rand(0, 99999), $array);
        }
        $results['array_search_1k'] = round((microtime(true) - $start) * 1000, 2);

        // String operations benchmark
        $start = microtime(true);
        $string = '';
        for ($i = 0; $i < 10000; $i++) {
            $string .= 'x';
        }
        $results['string_concatenation_10k'] = round((microtime(true) - $start) * 1000, 2);

        // JSON operations benchmark
        $data = array_fill(0, 1000, ['id' => 1, 'name' => 'test', 'data' => str_repeat('x', 100)]);

        $start = microtime(true);
        $json = json_encode($data);
        $results['json_encode_1k_objects'] = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        json_decode($json, true);
        $results['json_decode_1k_objects'] = round((microtime(true) - $start) * 1000, 2);

        // Memory usage
        $results['memory_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 2);
        $results['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        return $results;
    }

    /**
     * Benchmark CPU performance
     */
    protected function benchmarkCPU(): array
    {
        $results = [];

        // Math operations benchmark
        $start = microtime(true);
        for ($i = 0; $i < 100000; $i++) {
            sqrt($i) + sin($i) + cos($i);
        }
        $results['math_operations_100k'] = round((microtime(true) - $start) * 1000, 2);

        // String manipulation benchmark
        $start = microtime(true);
        for ($i = 0; $i < 10000; $i++) {
            $str = "test string {$i}";
            strtoupper($str);
            str_replace('test', 'benchmark', $str);
            substr($str, 0, 10);
        }
        $results['string_manipulation_10k'] = round((microtime(true) - $start) * 1000, 2);

        // Regex benchmark
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            preg_match('/test\d+/', "test{$i}string");
        }
        $results['regex_operations_1k'] = round((microtime(true) - $start) * 1000, 2);

        // Hash operations benchmark
        $start = microtime(true);
        for ($i = 0; $i < 10000; $i++) {
            md5("test string {$i}");
        }
        $results['md5_hash_10k'] = round((microtime(true) - $start) * 1000, 2);

        return $results;
    }

    /**
     * Benchmark network performance (if applicable)
     */
    protected function benchmarkNetwork(): array
    {
        $results = [];

        // DNS lookup benchmark
        $start = microtime(true);
        try {
            gethostbyname('google.com');
            $results['dns_lookup'] = round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            $results['dns_lookup'] = 'N/A';
        }

        // HTTP request benchmark (to localhost if possible)
        $start = microtime(true);
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                ],
            ]);
            @file_get_contents('http://localhost', false, $context);
            $results['http_localhost'] = round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            $results['http_localhost'] = 'N/A';
        }

        return $results;
    }

    /**
     * Calculate performance scores
     */
    protected function calculatePerformanceScores(array $benchmarks): array
    {
        $scores = [];

        // Database score (lower is better)
        $dbTime = ($benchmarks['database']['simple_query_100x'] ?? 1000) / 10; // Normalize to ~100ms baseline
        $scores['database'] = max(0, 100 - $dbTime);

        // Cache score (lower is better)
        $cacheTime = ($benchmarks['cache']['read_1kb_100x'] ?? 100) / 1; // Normalize to ~10ms baseline
        $scores['cache'] = max(0, 100 - $cacheTime);

        // File I/O score (lower is better)
        $fileTime = ($benchmarks['file_io']['read_1kb_100_files'] ?? 500) / 5; // Normalize to ~50ms baseline
        $scores['file_io'] = max(0, 100 - $fileTime);

        // Memory score (based on efficiency)
        $memUsage = $benchmarks['memory']['memory_usage_mb'] ?? 50;
        $scores['memory'] = max(0, 100 - ($memUsage / 5)); // Penalty after 500MB

        // CPU score (lower is better)
        $cpuTime = ($benchmarks['cpu']['math_operations_100k'] ?? 1000) / 10; // Normalize to ~100ms baseline
        $scores['cpu'] = max(0, 100 - $cpuTime);

        // Overall score
        $scores['overall'] = round(array_sum($scores) / count($scores), 1);

        return $scores;
    }

    /**
     * Compare with baseline metrics
     */
    protected function compareWithBaselines(array $benchmarks): array
    {
        $comparison = [];

        foreach ($benchmarks as $category => $metrics) {
            $comparison[$category] = [];
            foreach ($metrics as $metric => $value) {
                $baseline = $this->baselineMetrics[$category][$metric] ?? null;

                if ($baseline && is_numeric($value) && is_numeric($baseline)) {
                    $change = (($value - $baseline) / $baseline) * 100;
                    $comparison[$category][$metric] = [
                        'current' => $value,
                        'baseline' => $baseline,
                        'change_percent' => round($change, 2),
                        'status' => $this->getPerformanceStatus($change),
                    ];
                }
            }
        }

        return $comparison;
    }

    /**
     * Get performance status based on change percentage
     */
    protected function getPerformanceStatus(float $changePercent): string
    {
        if ($changePercent <= -10) {
            return 'improved';
        }
        if ($changePercent <= 10) {
            return 'stable';
        }
        if ($changePercent <= 25) {
            return 'degraded';
        }

        return 'critical';
    }

    /**
     * Store benchmark results for trending
     */
    protected function storeBenchmarkResults(array $results): void
    {
        $key = 'performance_benchmarks:'.now()->format('Y-m-d-H');

        try {
            $existing = Cache::get($key, []);
            $existing[] = [
                'timestamp' => $results['timestamp'],
                'benchmarks' => $results['benchmarks'],
                'scores' => $results['scores'],
            ];

            // Keep only last 24 entries per hour
            if (count($existing) > 24) {
                $existing = array_slice($existing, -24);
            }

            Cache::put($key, $existing, 86400 * 7); // Keep for 7 days
        } catch (\Exception $e) {
            Log::warning('Failed to store benchmark results', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Load baseline metrics
     */
    protected function loadBaselines(): void
    {
        $this->baselineMetrics = Cache::get('performance_baselines', [
            'database' => [
                'simple_query_100x' => 100,
                'complex_query' => 50,
                'connection_overhead_10x' => 20,
            ],
            'cache' => [
                'read_1kb_100x' => 10,
                'write_1kb_100x' => 15,
                'read_100kb' => 5,
                'write_100kb' => 8,
            ],
            'file_io' => [
                'read_1kb_100_files' => 50,
                'write_1kb_100_files' => 75,
                'read_1mb' => 20,
                'write_1mb' => 30,
            ],
            'memory' => [
                'array_populate_100k' => 50,
                'string_concatenation_10k' => 25,
                'json_encode_1k_objects' => 15,
                'json_decode_1k_objects' => 20,
            ],
            'cpu' => [
                'math_operations_100k' => 100,
                'string_manipulation_10k' => 75,
                'regex_operations_1k' => 50,
                'md5_hash_10k' => 25,
            ],
        ]);
    }

    /**
     * Set baseline metrics from current run
     */
    public function setBaselines(array $benchmarks): void
    {
        $this->baselineMetrics = $benchmarks;
        Cache::put('performance_baselines', $benchmarks, 86400 * 30); // Keep for 30 days
    }

    /**
     * Get benchmark history
     */
    public function getBenchmarkHistory(int $hours = 24): array
    {
        $history = [];

        for ($i = 0; $i < $hours; $i++) {
            $timestamp = now()->subHours($i);
            $key = 'performance_benchmarks:'.$timestamp->format('Y-m-d-H');
            $hourData = Cache::get($key, []);

            foreach ($hourData as $entry) {
                $history[] = $entry;
            }
        }

        // Sort by timestamp
        usort($history, fn ($a, $b) => strtotime($a['timestamp']) - strtotime($b['timestamp']));

        return $history;
    }

    /**
     * Generate performance report
     */
    public function generateReport(): array
    {
        $benchmarks = $this->runBenchmarks();
        $history = $this->getBenchmarkHistory(24);

        // Calculate trends
        $trends = $this->calculateTrends($history);

        return [
            'current_benchmarks' => $benchmarks,
            'trends' => $trends,
            'recommendations' => $this->getPerformanceRecommendations($benchmarks),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate performance trends
     */
    protected function calculateTrends(array $history): array
    {
        if (count($history) < 2) {
            return ['status' => 'insufficient_data'];
        }

        $latest = end($history);
        $oldest = reset($history);

        $trends = [];

        foreach ($latest['scores'] as $metric => $currentScore) {
            $oldScore = $oldest['scores'][$metric] ?? $currentScore;
            $change = $currentScore - $oldScore;

            $trends[$metric] = [
                'current_score' => $currentScore,
                'previous_score' => $oldScore,
                'change' => round($change, 2),
                'trend' => $change > 5 ? 'improving' : ($change < -5 ? 'declining' : 'stable'),
            ];
        }

        return $trends;
    }

    /**
     * Get performance recommendations
     */
    protected function getPerformanceRecommendations(array $benchmarks): array
    {
        $recommendations = [];

        // Database recommendations
        if (($benchmarks['scores']['database'] ?? 100) < 70) {
            $recommendations[] = [
                'category' => 'database',
                'priority' => 'high',
                'title' => 'Optimize Database Performance',
                'description' => 'Database operations are slower than expected',
                'actions' => [
                    'Review and optimize slow queries',
                    'Check database indexes',
                    'Consider database query caching',
                    'Monitor database connection pool',
                ],
            ];
        }

        // Cache recommendations
        if (($benchmarks['scores']['cache'] ?? 100) < 70) {
            $recommendations[] = [
                'category' => 'cache',
                'priority' => 'medium',
                'title' => 'Improve Cache Performance',
                'description' => 'Cache operations are underperforming',
                'actions' => [
                    'Check cache driver configuration',
                    'Monitor cache hit rates',
                    'Consider Redis optimization',
                    'Review cache TTL settings',
                ],
            ];
        }

        // Memory recommendations
        if (($benchmarks['scores']['memory'] ?? 100) < 70) {
            $recommendations[] = [
                'category' => 'memory',
                'priority' => 'high',
                'title' => 'Optimize Memory Usage',
                'description' => 'Memory usage is higher than expected',
                'actions' => [
                    'Review memory-intensive operations',
                    'Check for memory leaks',
                    'Optimize large data processing',
                    'Consider increasing memory limits',
                ],
            ];
        }

        // CPU recommendations
        if (($benchmarks['scores']['cpu'] ?? 100) < 70) {
            $recommendations[] = [
                'category' => 'cpu',
                'priority' => 'medium',
                'title' => 'Optimize CPU Performance',
                'description' => 'CPU-intensive operations are slow',
                'actions' => [
                    'Profile CPU-intensive code',
                    'Optimize algorithms',
                    'Consider background job processing',
                    'Review regular expression usage',
                ],
            ];
        }

        return $recommendations;
    }
}
