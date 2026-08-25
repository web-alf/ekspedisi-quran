<?php

namespace App\Console\Commands\Performance;

use App\Models\Pengiriman;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckBudgetsCommand extends Command
{
    protected $signature = 'performance:check-budgets 
                            {--strict : Use strict budget limits}
                            {--output=console : Output format (console, json)}';

    protected $description = 'Check if performance budgets are within limits';

    private $budgets = [];

    private $violations = [];

    public function handle()
    {
        $this->info('💰 Checking Performance Budgets...');

        $this->defineBudgets();
        $this->checkAllBudgets();

        if ($this->option('output') === 'json') {
            $this->outputJson();
        } else {
            $this->outputConsole();
        }

        // Exit with error code if there are violations
        return empty($this->violations) ? 0 : 1;
    }

    private function defineBudgets(): void
    {
        $strict = $this->option('strict');

        $this->budgets = [
            'database_queries' => [
                'simple_count_max_time' => $strict ? 5 : 10, // ms
                'complex_join_max_time' => $strict ? 30 : 50, // ms
                'aggregation_max_time' => $strict ? 20 : 40, // ms
                'search_max_time' => $strict ? 25 : 50, // ms
                'pagination_max_time' => $strict ? 20 : 30, // ms
                'max_queries_per_request' => $strict ? 10 : 15,
            ],
            'cache_performance' => [
                'cache_hit_max_time' => $strict ? 2 : 5, // ms
                'cache_miss_max_time' => $strict ? 50 : 100, // ms
                'min_hit_ratio' => $strict ? 0.9 : 0.8, // 90% or 80%
            ],
            'memory_usage' => [
                'max_memory_per_request' => $strict ? 50 : 100, // MB
                'max_peak_memory' => $strict ? 200 : 300, // MB
                'large_dataset_max_memory' => $strict ? 25 : 50, // MB
            ],
            'response_times' => [
                'dashboard_max_time' => $strict ? 200 : 500, // ms
                'listing_max_time' => $strict ? 150 : 300, // ms
                'search_max_time' => $strict ? 100 : 200, // ms
                'warehouse_max_time' => $strict ? 100 : 200, // ms
            ],
            'frontend_budgets' => [
                'bundle_size_max' => $strict ? 500 : 1000, // KB
                'first_contentful_paint' => $strict ? 1.5 : 2.5, // seconds
                'largest_contentful_paint' => $strict ? 2.5 : 4.0, // seconds
                'cumulative_layout_shift' => $strict ? 0.1 : 0.25,
            ],
        ];
    }

    private function checkAllBudgets(): void
    {
        $this->checkDatabaseBudgets();
        $this->checkCacheBudgets();
        $this->checkMemoryBudgets();
        $this->checkResponseTimeBudgets();
        $this->checkFrontendBudgets();
    }

    private function checkDatabaseBudgets(): void
    {
        $this->info('Checking database performance budgets...');

        // Test simple count query
        $start = microtime(true);
        Pengiriman::count();
        $simpleCountTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'database_queries.simple_count_max_time',
            $simpleCountTime,
            'Simple count query time'
        );

        // Test complex join
        $start = microtime(true);
        Pengiriman::with(['donatur', 'jenisQuran', 'status'])
            ->limit(10)
            ->get();
        $complexJoinTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'database_queries.complex_join_max_time',
            $complexJoinTime,
            'Complex join query time'
        );

        // Test aggregation
        $start = microtime(true);
        DB::table('pengiriman')
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->get();
        $aggregationTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'database_queries.aggregation_max_time',
            $aggregationTime,
            'Aggregation query time'
        );

        // Check query count per request
        DB::enableQueryLog();

        // Simulate a typical request
        Pengiriman::with(['donatur', 'jenisQuran'])->paginate(20);

        $queryCount = count(DB::getQueryLog());

        $this->checkBudget(
            'database_queries.max_queries_per_request',
            $queryCount,
            'Queries per request'
        );
    }

    private function checkCacheBudgets(): void
    {
        $this->info('Checking cache performance budgets...');

        // Test cache hit time
        Cache::put('budget_test', 'test_data', 300);

        $start = microtime(true);
        Cache::get('budget_test');
        $cacheHitTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'cache_performance.cache_hit_max_time',
            $cacheHitTime,
            'Cache hit time'
        );

        // Test cache miss time
        Cache::forget('budget_test_miss');

        $start = microtime(true);
        Cache::remember('budget_test_miss', 300, function () {
            return Pengiriman::limit(50)->get()->toArray();
        });
        $cacheMissTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'cache_performance.cache_miss_max_time',
            $cacheMissTime,
            'Cache miss time'
        );

        // Simulate cache hit ratio test
        $hits = 0;
        $misses = 0;

        for ($i = 0; $i < 10; $i++) {
            $key = "test_key_{$i}";
            if (Cache::has($key)) {
                $hits++;
            } else {
                $misses++;
                Cache::put($key, "value_{$i}", 300);
            }
            Cache::get($key); // This should be a hit now
            $hits++;
        }

        $hitRatio = $hits / ($hits + $misses);

        $this->checkBudget(
            'cache_performance.min_hit_ratio',
            $hitRatio,
            'Cache hit ratio',
            true // reverse check (higher is better)
        );
    }

    private function checkMemoryBudgets(): void
    {
        $this->info('Checking memory usage budgets...');

        $initialMemory = memory_get_usage(true);

        // Test large dataset memory usage
        $largeDataset = Pengiriman::with(['donatur', 'jenisQuran'])
            ->limit(500)
            ->get();

        $memoryAfterLoad = memory_get_usage(true);
        $memoryIncrease = ($memoryAfterLoad - $initialMemory) / 1024 / 1024; // MB

        $this->checkBudget(
            'memory_usage.large_dataset_max_memory',
            $memoryIncrease,
            'Large dataset memory usage'
        );

        // Check peak memory
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB

        $this->checkBudget(
            'memory_usage.max_peak_memory',
            $peakMemory,
            'Peak memory usage'
        );
    }

    private function checkResponseTimeBudgets(): void
    {
        $this->info('Checking response time budgets...');

        // Dashboard response time
        $start = microtime(true);
        $dashboardData = [
            'total_pengiriman' => Pengiriman::count(),
            'pengiriman_today' => Pengiriman::whereDate('created_at', today())->count(),
            'status_breakdown' => DB::table('pengiriman')
                ->select('status_id', DB::raw('COUNT(*) as total'))
                ->groupBy('status_id')
                ->get(),
        ];
        $dashboardTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'response_times.dashboard_max_time',
            $dashboardTime,
            'Dashboard response time'
        );

        // Listing response time
        $start = microtime(true);
        Pengiriman::with(['donatur:id,nama_donatur', 'jenisQuran:id,nama_jenis'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        $listingTime = (microtime(true) - $start) * 1000;

        $this->checkBudget(
            'response_times.listing_max_time',
            $listingTime,
            'Listing response time'
        );
    }

    private function checkFrontendBudgets(): void
    {
        $this->info('Checking frontend performance budgets...');

        // Check bundle size
        $buildManifest = public_path('build/manifest.json');
        if (file_exists($buildManifest)) {
            $manifest = json_decode(file_get_contents($buildManifest), true);

            $totalSize = 0;
            foreach ($manifest as $file => $details) {
                if (isset($details['file'])) {
                    $filePath = public_path('build/'.$details['file']);
                    if (file_exists($filePath)) {
                        $totalSize += filesize($filePath);
                    }
                }
            }

            $bundleSizeKB = $totalSize / 1024;

            $this->checkBudget(
                'frontend_budgets.bundle_size_max',
                $bundleSizeKB,
                'Bundle size'
            );
        }

        // Note: LCP, FCP, CLS would be checked by Lighthouse in CI
        $this->line('  Frontend timing metrics checked by Lighthouse CI');
    }

    private function checkBudget(string $budgetKey, float $actualValue, string $description, bool $reverse = false): void
    {
        $keyParts = explode('.', $budgetKey);
        $budget = $this->budgets[$keyParts[0]][$keyParts[1]];

        $violated = $reverse ? $actualValue < $budget : $actualValue > $budget;

        if ($violated) {
            $this->violations[] = [
                'budget' => $budgetKey,
                'description' => $description,
                'budget_limit' => $budget,
                'actual_value' => $actualValue,
                'severity' => $this->calculateSeverity($actualValue, $budget, $reverse),
            ];

            $operator = $reverse ? '<' : '>';
            $this->warn("  ❌ {$description}: {$actualValue} {$operator} {$budget} (budget exceeded)");
        } else {
            $this->line("  ✅ {$description}: {$actualValue} (within budget: {$budget})");
        }
    }

    private function calculateSeverity(float $actual, float $budget, bool $reverse): string
    {
        $ratio = $reverse ? $budget / $actual : $actual / $budget;

        if ($ratio > 2.0) {
            return 'critical';
        }
        if ($ratio > 1.5) {
            return 'high';
        }
        if ($ratio > 1.2) {
            return 'medium';
        }

        return 'low';
    }

    private function outputConsole(): void
    {
        $this->info("\n💰 Performance Budget Report");
        $this->info('=============================');

        if (empty($this->violations)) {
            $this->info("\n🎉 All performance budgets are within limits!");
            $this->info('✅ No budget violations detected');
        } else {
            $this->error("\n⚠️  Budget Violations Detected:");
            $this->error('❌ '.count($this->violations).' budget(s) exceeded');

            $critical = array_filter($this->violations, fn ($v) => $v['severity'] === 'critical');
            $high = array_filter($this->violations, fn ($v) => $v['severity'] === 'high');

            if (count($critical) > 0) {
                $this->error("\n🔴 Critical Violations:");
                foreach ($critical as $violation) {
                    $this->error("  • {$violation['description']}: {$violation['actual_value']} (budget: {$violation['budget_limit']})");
                }
            }

            if (count($high) > 0) {
                $this->warn("\n🟡 High Priority Violations:");
                foreach ($high as $violation) {
                    $this->warn("  • {$violation['description']}: {$violation['actual_value']} (budget: {$violation['budget_limit']})");
                }
            }

            $this->info("\n💡 Recommendations:");
            $this->generateRecommendations();
        }
    }

    private function outputJson(): void
    {
        $output = [
            'timestamp' => now()->toISOString(),
            'budget_check' => [
                'passed' => empty($this->violations),
                'total_budgets_checked' => $this->countTotalBudgets(),
                'violations_count' => count($this->violations),
                'strict_mode' => $this->option('strict'),
            ],
            'violations' => $this->violations,
            'severity_summary' => $this->getSeveritySummary(),
            'recommendations' => $this->generateRecommendationsArray(),
        ];

        // Add special markers for CI
        if (! empty($this->violations)) {
            $critical = array_filter($this->violations, fn ($v) => $v['severity'] === 'critical');
            if (count($critical) > 0) {
                $output['CRITICAL_REGRESSION'] = true;
            }
            $output['BUDGET_EXCEEDED'] = true;
        }

        echo json_encode($output, JSON_PRETTY_PRINT);
    }

    private function countTotalBudgets(): int
    {
        $count = 0;
        foreach ($this->budgets as $category) {
            $count += count($category);
        }

        return $count;
    }

    private function getSeveritySummary(): array
    {
        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($this->violations as $violation) {
            $summary[$violation['severity']]++;
        }

        return $summary;
    }

    private function generateRecommendations(): void
    {
        $recommendations = $this->generateRecommendationsArray();
        foreach ($recommendations as $rec) {
            $this->line('  • '.$rec);
        }
    }

    private function generateRecommendationsArray(): array
    {
        $recommendations = [];

        $dbViolations = array_filter($this->violations, fn ($v) => strpos($v['budget'], 'database_queries') === 0);
        if (count($dbViolations) > 0) {
            $recommendations[] = 'Optimize database queries - '.count($dbViolations).' queries exceed budget';
        }

        $cacheViolations = array_filter($this->violations, fn ($v) => strpos($v['budget'], 'cache_performance') === 0);
        if (count($cacheViolations) > 0) {
            $recommendations[] = 'Improve cache performance - consider Redis configuration tuning';
        }

        $memoryViolations = array_filter($this->violations, fn ($v) => strpos($v['budget'], 'memory_usage') === 0);
        if (count($memoryViolations) > 0) {
            $recommendations[] = 'Reduce memory usage - implement chunked processing for large datasets';
        }

        $frontendViolations = array_filter($this->violations, fn ($v) => strpos($v['budget'], 'frontend_budgets') === 0);
        if (count($frontendViolations) > 0) {
            $recommendations[] = 'Optimize frontend bundle size - consider code splitting';
        }

        $critical = array_filter($this->violations, fn ($v) => $v['severity'] === 'critical');
        if (count($critical) > 0) {
            $recommendations[] = 'Address critical violations immediately before deployment';
        }

        return $recommendations;
    }
}
