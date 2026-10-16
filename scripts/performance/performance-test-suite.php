#!/usr/bin/env php
<?php

/**
 * Comprehensive Performance Test Suite
 * 
 * This script runs various performance tests to validate all optimizations
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__, 2)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

class PerformanceTestSuite
{
    private $results = [];
    private $startTime;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
        echo "🚀 Starting Performance Test Suite\n";
        echo "================================\n\n";
    }
    
    public function runAllTests()
    {
        $this->testDatabasePerformance();
        $this->testCachePerformance();
        $this->testQueryOptimizations();
        $this->testIndexEfficiency();
        $this->testMemoryUsage();
        $this->testConcurrentOperations();
        
        $this->generateReport();
    }
    
    private function testDatabasePerformance()
    {
        echo "📊 Testing Database Performance...\n";
        
        // Test 1: Simple queries
        $start = microtime(true);
        $pengirimanCount = DB::table('pengiriman')->count();
        $simpleQueryTime = (microtime(true) - $start) * 1000;
        
        // Test 2: Complex joins
        $start = microtime(true);
        $complexQuery = DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->join('jenis_quran', 'pengiriman.jenis_quran_id', '=', 'jenis_quran.id')
            ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
            ->select('pengiriman.*', 'donatur.nama_donatur', 'jenis_quran.nama_jenis', 'status_pengiriman.nama_status')
            ->limit(100)
            ->get();
        $complexQueryTime = (microtime(true) - $start) * 1000;
        
        // Test 3: Aggregation queries
        $start = microtime(true);
        $aggregationQuery = DB::table('pengiriman')
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->get();
        $aggregationTime = (microtime(true) - $start) * 1000;
        
        $this->results['database'] = [
            'simple_query_time' => $simpleQueryTime,
            'complex_query_time' => $complexQueryTime,
            'aggregation_time' => $aggregationTime,
            'total_pengiriman' => $pengirimanCount
        ];
        
        echo "  ✓ Simple query: " . round($simpleQueryTime, 2) . "ms\n";
        echo "  ✓ Complex join: " . round($complexQueryTime, 2) . "ms\n";
        echo "  ✓ Aggregation: " . round($aggregationTime, 2) . "ms\n\n";
    }
    
    private function testCachePerformance()
    {
        echo "⚡ Testing Cache Performance...\n";
        
        // Clear cache
        Cache::flush();
        
        // Test 1: Cache miss
        $start = microtime(true);
        $data = Cache::remember('performance_test', 3600, function() {
            return DB::table('pengiriman')
                ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
                ->select('pengiriman.id', 'donatur.nama_donatur')
                ->limit(100)
                ->get();
        });
        $cacheMissTime = (microtime(true) - $start) * 1000;
        
        // Test 2: Cache hit
        $start = microtime(true);
        $cachedData = Cache::get('performance_test');
        $cacheHitTime = (microtime(true) - $start) * 1000;
        
        // Test 3: Multiple cache operations
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::put("test_key_{$i}", "test_value_{$i}", 60);
        }
        $cacheWriteTime = (microtime(true) - $start) * 1000;
        
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::get("test_key_{$i}");
        }
        $cacheReadTime = (microtime(true) - $start) * 1000;
        
        $this->results['cache'] = [
            'cache_miss_time' => $cacheMissTime,
            'cache_hit_time' => $cacheHitTime,
            'write_100_keys_time' => $cacheWriteTime,
            'read_100_keys_time' => $cacheReadTime,
            'speed_improvement' => round($cacheMissTime / max($cacheHitTime, 0.01), 1)
        ];
        
        echo "  ✓ Cache miss: " . round($cacheMissTime, 2) . "ms\n";
        echo "  ✓ Cache hit: " . round($cacheHitTime, 2) . "ms\n";
        echo "  ✓ Write 100 keys: " . round($cacheWriteTime, 2) . "ms\n";
        echo "  ✓ Read 100 keys: " . round($cacheReadTime, 2) . "ms\n";
        echo "  ✓ Speed improvement: " . $this->results['cache']['speed_improvement'] . "x\n\n";
    }
    
    private function testQueryOptimizations()
    {
        echo "🔍 Testing Query Optimizations...\n";
        
        // Test N+1 prevention
        $start = microtime(true);
        $pengiriman = DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->join('jenis_quran', 'pengiriman.jenis_quran_id', '=', 'jenis_quran.id')
            ->select('pengiriman.*', 'donatur.nama_donatur', 'jenis_quran.nama_jenis')
            ->limit(50)
            ->get();
        $optimizedQueryTime = (microtime(true) - $start) * 1000;
        
        // Test pagination performance
        $start = microtime(true);
        $paginatedResults = DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->select('pengiriman.*', 'donatur.nama_donatur')
            ->orderBy('pengiriman.created_at', 'desc')
            ->offset(100)
            ->limit(20)
            ->get();
        $paginationTime = (microtime(true) - $start) * 1000;
        
        // Test search performance
        $start = microtime(true);
        $searchResults = DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->where('donatur.nama_donatur', 'LIKE', '%test%')
            ->orWhere('pengiriman.no_resi', 'LIKE', '%test%')
            ->select('pengiriman.*', 'donatur.nama_donatur')
            ->limit(20)
            ->get();
        $searchTime = (microtime(true) - $start) * 1000;
        
        $this->results['query_optimization'] = [
            'optimized_query_time' => $optimizedQueryTime,
            'pagination_time' => $paginationTime,
            'search_time' => $searchTime
        ];
        
        echo "  ✓ Optimized query (50 records): " . round($optimizedQueryTime, 2) . "ms\n";
        echo "  ✓ Pagination query: " . round($paginationTime, 2) . "ms\n";
        echo "  ✓ Search query: " . round($searchTime, 2) . "ms\n\n";
    }
    
    private function testIndexEfficiency()
    {
        echo "📈 Testing Index Efficiency...\n";
        
        // Test date range queries (should use indexes)
        $start = microtime(true);
        $dateRangeResults = DB::table('pengiriman')
            ->whereBetween('created_at', [
                now()->subDays(30)->format('Y-m-d'),
                now()->format('Y-m-d')
            ])
            ->count();
        $dateRangeTime = (microtime(true) - $start) * 1000;
        
        // Test foreign key lookups
        $start = microtime(true);
        $fkResults = DB::table('pengiriman')
            ->where('donatur_id', 1)
            ->count();
        $fkTime = (microtime(true) - $start) * 1000;
        
        // Test status filtering
        $start = microtime(true);
        $statusResults = DB::table('pengiriman')
            ->where('status_id', 1)
            ->count();
        $statusTime = (microtime(true) - $start) * 1000;
        
        $this->results['index_efficiency'] = [
            'date_range_time' => $dateRangeTime,
            'foreign_key_time' => $fkTime,
            'status_filter_time' => $statusTime,
            'date_range_count' => $dateRangeResults,
            'fk_count' => $fkResults,
            'status_count' => $statusResults
        ];
        
        echo "  ✓ Date range query: " . round($dateRangeTime, 2) . "ms ({$dateRangeResults} records)\n";
        echo "  ✓ Foreign key lookup: " . round($fkTime, 2) . "ms ({$fkResults} records)\n";
        echo "  ✓ Status filter: " . round($statusTime, 2) . "ms ({$statusResults} records)\n\n";
    }
    
    private function testMemoryUsage()
    {
        echo "💾 Testing Memory Usage...\n";
        
        $initialMemory = memory_get_usage();
        
        // Load large dataset
        $largeDataset = DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->select('pengiriman.*', 'donatur.nama_donatur')
            ->limit(1000)
            ->get();
        
        $afterLoadMemory = memory_get_usage();
        $memoryIncrease = $afterLoadMemory - $initialMemory;
        
        // Test chunked processing
        $chunkMemoryPeak = $initialMemory;
        
        DB::table('pengiriman')->chunk(100, function($chunk) use (&$chunkMemoryPeak) {
            $currentMemory = memory_get_usage();
            $chunkMemoryPeak = max($chunkMemoryPeak, $currentMemory);
        });
        
        $chunkMemoryIncrease = $chunkMemoryPeak - $initialMemory;
        
        $this->results['memory'] = [
            'initial_memory' => round($initialMemory / 1024 / 1024, 2),
            'large_dataset_increase' => round($memoryIncrease / 1024 / 1024, 2),
            'chunk_processing_increase' => round($chunkMemoryIncrease / 1024 / 1024, 2),
            'records_loaded' => count($largeDataset)
        ];
        
        echo "  ✓ Initial memory: " . $this->results['memory']['initial_memory'] . "MB\n";
        echo "  ✓ Large dataset increase: " . $this->results['memory']['large_dataset_increase'] . "MB\n";
        echo "  ✓ Chunk processing increase: " . $this->results['memory']['chunk_processing_increase'] . "MB\n";
        echo "  ✓ Records processed: " . $this->results['memory']['records_loaded'] . "\n\n";
    }
    
    private function testConcurrentOperations()
    {
        echo "🔄 Testing Concurrent Operations...\n";
        
        // Simulate concurrent reads
        $start = microtime(true);
        
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = DB::table('pengiriman')
                ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
                ->select('pengiriman.id', 'donatur.nama_donatur')
                ->offset($i * 10)
                ->limit(10)
                ->get();
        }
        
        $concurrentReadTime = (microtime(true) - $start) * 1000;
        
        // Test transaction performance
        $start = microtime(true);
        
        DB::transaction(function() {
            for ($i = 0; $i < 5; $i++) {
                DB::table('pengiriman')
                    ->where('id', $i + 1)
                    ->update(['updated_at' => now()]);
            }
        });
        
        $transactionTime = (microtime(true) - $start) * 1000;
        
        $this->results['concurrent'] = [
            'concurrent_read_time' => $concurrentReadTime,
            'transaction_time' => $transactionTime,
            'avg_read_time' => round($concurrentReadTime / 10, 2)
        ];
        
        echo "  ✓ 10 concurrent reads: " . round($concurrentReadTime, 2) . "ms\n";
        echo "  ✓ Average per read: " . $this->results['concurrent']['avg_read_time'] . "ms\n";
        echo "  ✓ Transaction (5 updates): " . round($transactionTime, 2) . "ms\n\n";
    }
    
    private function generateReport()
    {
        $totalTime = (microtime(true) - $this->startTime) * 1000;
        
        echo "📋 Performance Test Report\n";
        echo "==========================\n\n";
        
        echo "🎯 Overall Results:\n";
        echo "  Total test time: " . round($totalTime, 2) . "ms\n\n";
        
        echo "📊 Database Performance:\n";
        foreach ($this->results['database'] as $metric => $value) {
            if (is_numeric($value)) {
                echo "  {$metric}: " . (strpos($metric, 'time') ? round($value, 2) . 'ms' : $value) . "\n";
            }
        }
        echo "\n";
        
        echo "⚡ Cache Performance:\n";
        foreach ($this->results['cache'] as $metric => $value) {
            if (is_numeric($value)) {
                $unit = strpos($metric, 'time') ? 'ms' : (strpos($metric, 'improvement') ? 'x' : '');
                echo "  {$metric}: " . round($value, 2) . $unit . "\n";
            }
        }
        echo "\n";
        
        echo "🎉 Performance Grade:\n";
        $grade = $this->calculatePerformanceGrade();
        echo "  Overall Grade: {$grade}\n\n";
        
        $this->saveResultsToFile();
    }
    
    private function calculatePerformanceGrade()
    {
        $score = 100;
        
        // Deduct points for slow operations
        if ($this->results['database']['simple_query_time'] > 10) $score -= 10;
        if ($this->results['database']['complex_query_time'] > 50) $score -= 15;
        if ($this->results['cache']['cache_hit_time'] > 5) $score -= 10;
        if ($this->results['cache']['speed_improvement'] < 5) $score -= 10;
        if ($this->results['memory']['large_dataset_increase'] > 50) $score -= 15;
        if ($this->results['concurrent']['avg_read_time'] > 20) $score -= 10;
        
        if ($score >= 90) return 'A+ (Excellent)';
        if ($score >= 80) return 'A (Very Good)';
        if ($score >= 70) return 'B (Good)';
        if ($score >= 60) return 'C (Acceptable)';
        return 'D (Needs Improvement)';
    }
    
    private function saveResultsToFile()
    {
        $reportData = [
            'timestamp' => now()->toISOString(),
            'environment' => config('app.env'),
            'database' => config('database.default'),
            'cache' => config('cache.default'),
            'results' => $this->results,
            'grade' => $this->calculatePerformanceGrade()
        ];
        
        $filename = 'performance-report-' . now()->format('Y-m-d-H-i-s') . '.json';
        $filepath = __DIR__ . '/reports/' . $filename;
        
        // Create reports directory if it doesn't exist
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, json_encode($reportData, JSON_PRETTY_PRINT));
        
        echo "📁 Report saved to: {$filename}\n";
    }
}

// Run the test suite
$testSuite = new PerformanceTestSuite();
$testSuite->runAllTests();

echo "✅ Performance testing completed!\n";
