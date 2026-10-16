<?php

/**
 * Performance Validation Script
 * 
 * This script validates all the optimizations we've implemented
 */

echo "🚀 Performance Optimization Validation\n";
echo "=====================================\n\n";

// Database Query Performance Tests
echo "📊 Testing Database Query Performance...\n";

$start = microtime(true);
$pengirimanCount = DB::table('pengiriman')->count();
$simpleQueryTime = (microtime(true) - $start) * 1000;

$start = microtime(true);
$complexQuery = DB::table('pengiriman')
    ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
    ->join('jenis_quran', 'pengiriman.jenis_quran_id', '=', 'jenis_quran.id')
    ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
    ->select('pengiriman.*', 'donatur.nama_donatur', 'jenis_quran.nama_jenis', 'status_pengiriman.nama_status')
    ->limit(50)
    ->get();
$complexQueryTime = (microtime(true) - $start) * 1000;

echo "✓ Simple count query: " . round($simpleQueryTime, 2) . "ms\n";
echo "✓ Complex join query (50 records): " . round($complexQueryTime, 2) . "ms\n";

// Index Performance Test
echo "\n📈 Testing Index Performance...\n";

$start = microtime(true);
$dateRangeQuery = DB::table('pengiriman')
    ->whereBetween('created_at', [
        now()->subDays(30)->format('Y-m-d'),
        now()->format('Y-m-d')
    ])
    ->count();
$dateRangeTime = (microtime(true) - $start) * 1000;

echo "✓ Date range query: " . round($dateRangeTime, 2) . "ms\n";

// Cache Performance Test
echo "\n⚡ Testing Cache Performance...\n";

// Clear cache to start fresh
Cache::flush();

$start = microtime(true);
$cacheData = Cache::remember('test_cache', 3600, function() {
    return DB::table('pengiriman')
        ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
        ->select('pengiriman.id', 'donatur.nama_donatur')
        ->limit(100)
        ->get();
});
$cacheMissTime = (microtime(true) - $start) * 1000;

$start = microtime(true);
$cachedData = Cache::get('test_cache');
$cacheHitTime = (microtime(true) - $start) * 1000;

echo "✓ Cache miss: " . round($cacheMissTime, 2) . "ms\n";
echo "✓ Cache hit: " . round($cacheHitTime, 2) . "ms\n";
echo "✓ Speed improvement: " . round($cacheMissTime / max($cacheHitTime, 0.01), 1) . "x\n";

// Memory Usage Test
echo "\n💾 Testing Memory Usage...\n";

$initialMemory = memory_get_usage();

// Load dataset
$dataset = DB::table('pengiriman')
    ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
    ->select('pengiriman.*', 'donatur.nama_donatur')
    ->limit(500)
    ->get();

$afterLoadMemory = memory_get_usage();
$memoryIncrease = ($afterLoadMemory - $initialMemory) / 1024 / 1024;

echo "✓ Memory usage for 500 records: " . round($memoryIncrease, 2) . "MB\n";

// Performance Summary
echo "\n🎯 Performance Summary\n";
echo "=====================\n";

$performance = [
    'simple_query' => $simpleQueryTime,
    'complex_query' => $complexQueryTime,
    'date_range_query' => $dateRangeTime,
    'cache_miss' => $cacheMissTime,
    'cache_hit' => $cacheHitTime,
    'memory_usage' => $memoryIncrease
];

$grade = 100;

// Scoring system
if ($performance['simple_query'] > 10) $grade -= 10;
if ($performance['complex_query'] > 100) $grade -= 15;
if ($performance['date_range_query'] > 20) $grade -= 10;
if ($performance['cache_hit'] > 5) $grade -= 10;
if ($performance['memory_usage'] > 20) $grade -= 15;

if ($grade >= 90) $gradeText = 'A+ (Excellent)';
elseif ($grade >= 80) $gradeText = 'A (Very Good)';
elseif ($grade >= 70) $gradeText = 'B (Good)';
elseif ($grade >= 60) $gradeText = 'C (Acceptable)';
else $gradeText = 'D (Needs Improvement)';

echo "Overall Performance Grade: {$gradeText}\n";
echo "Score: {$grade}/100\n\n";

// Recommendations
echo "📋 Optimization Status\n";
echo "======================\n";

if ($performance['simple_query'] < 10) {
    echo "✅ Database indexes are working properly\n";
} else {
    echo "⚠️  Database queries may need optimization\n";
}

if ($performance['cache_hit'] < 5 && $performance['cache_miss'] > $performance['cache_hit'] * 5) {
    echo "✅ Cache system is performing well\n";
} else {
    echo "⚠️  Cache system may need tuning\n";
}

if ($performance['memory_usage'] < 10) {
    echo "✅ Memory usage is efficient\n";
} else {
    echo "⚠️  Memory usage could be optimized\n";
}

// Save results
$reportData = [
    'timestamp' => now()->toISOString(),
    'performance_metrics' => $performance,
    'grade' => $gradeText,
    'score' => $grade
];

$filename = 'validation-report-' . now()->format('Y-m-d-H-i-s') . '.json';
$filepath = __DIR__ . '/reports/' . $filename;

if (!is_dir(dirname($filepath))) {
    mkdir(dirname($filepath), 0755, true);
}

file_put_contents($filepath, json_encode($reportData, JSON_PRETTY_PRINT));

echo "\n📁 Report saved to: performance/reports/{$filename}\n";
echo "✅ Performance validation completed!\n";
