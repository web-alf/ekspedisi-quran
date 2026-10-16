<?php

namespace App\Console\Commands\Performance;

use Illuminate\Console\Command;

class AnalyzeResultsCommand extends Command
{
    protected $signature = 'performance:analyze-results 
                            {--compare-baseline= : Path to baseline results for comparison}
                            {--output=console : Output format (console, json)}
                            {--threshold=20 : Regression threshold percentage}';

    protected $description = 'Analyze performance results and detect regressions';

    private $currentResults = [];

    private $baselineResults = [];

    private $regressions = [];

    private $improvements = [];

    public function handle()
    {
        $this->info('📊 Analyzing Performance Results...');

        $this->loadCurrentResults();
        $this->loadBaselineResults();
        $this->compareResults();

        if ($this->option('output') === 'json') {
            $this->outputJson();
        } else {
            $this->outputConsole();
        }

        // Return error code if critical regressions found
        $criticalRegressions = array_filter($this->regressions, fn ($r) => $r['severity'] === 'critical');

        return count($criticalRegressions) > 0 ? 1 : 0;
    }

    private function loadCurrentResults(): void
    {
        // Load current benchmark results
        $benchmarkFile = 'benchmark-results.json';
        if (file_exists($benchmarkFile)) {
            $this->currentResults['benchmark'] = json_decode(file_get_contents($benchmarkFile), true);
        }

        // Load query analysis results
        $queryFile = 'query-analysis.json';
        if (file_exists($queryFile)) {
            $this->currentResults['queries'] = json_decode(file_get_contents($queryFile), true);
        }

        // Load memory test results
        $memoryFile = 'memory-test.json';
        if (file_exists($memoryFile)) {
            $this->currentResults['memory'] = json_decode(file_get_contents($memoryFile), true);
        }
    }

    private function loadBaselineResults(): void
    {
        $baselinePath = $this->option('compare-baseline');

        if (! $baselinePath || ! is_dir($baselinePath)) {
            $this->warn('No baseline found for comparison');

            return;
        }

        // Load baseline benchmark results
        $baselineBenchmark = $baselinePath.'/benchmark-results.json';
        if (file_exists($baselineBenchmark)) {
            $this->baselineResults['benchmark'] = json_decode(file_get_contents($baselineBenchmark), true);
        }

        // Load baseline query analysis
        $baselineQuery = $baselinePath.'/query-analysis.json';
        if (file_exists($baselineQuery)) {
            $this->baselineResults['queries'] = json_decode(file_get_contents($baselineQuery), true);
        }

        // Load baseline memory test
        $baselineMemory = $baselinePath.'/memory-test.json';
        if (file_exists($baselineMemory)) {
            $this->baselineResults['memory'] = json_decode(file_get_contents($baselineMemory), true);
        }
    }

    private function compareResults(): void
    {
        if (empty($this->baselineResults)) {
            $this->info('No baseline available - skipping regression analysis');

            return;
        }

        $threshold = $this->option('threshold') / 100; // Convert percentage to decimal

        $this->compareBenchmarkResults($threshold);
        $this->compareQueryResults($threshold);
        $this->compareMemoryResults($threshold);
    }

    private function compareBenchmarkResults(float $threshold): void
    {
        if (! isset($this->currentResults['benchmark']) || ! isset($this->baselineResults['benchmark'])) {
            return;
        }

        $current = $this->currentResults['benchmark']['results'] ?? [];
        $baseline = $this->baselineResults['benchmark']['results'] ?? [];

        foreach ($current as $category => $metrics) {
            if (! isset($baseline[$category])) {
                continue;
            }

            foreach ($metrics as $metric => $currentStats) {
                if (! isset($baseline[$category][$metric])) {
                    continue;
                }

                $currentValue = $currentStats['avg'];
                $baselineValue = $baseline[$category][$metric]['avg'];

                $changeRatio = ($currentValue - $baselineValue) / $baselineValue;
                $changePercent = $changeRatio * 100;

                if (abs($changeRatio) > $threshold) {
                    $item = [
                        'category' => $category,
                        'metric' => $metric,
                        'current_value' => $currentValue,
                        'baseline_value' => $baselineValue,
                        'change_percent' => $changePercent,
                        'severity' => $this->calculateSeverity($changeRatio, $threshold),
                        'unit' => $this->getMetricUnit($metric),
                    ];

                    if ($changeRatio > 0) {
                        $this->regressions[] = $item;
                    } else {
                        $this->improvements[] = $item;
                    }
                }
            }
        }
    }

    private function compareQueryResults(float $threshold): void
    {
        if (! isset($this->currentResults['queries']) || ! isset($this->baselineResults['queries'])) {
            return;
        }

        $currentAnalysis = $this->currentResults['queries']['analysis'] ?? [];
        $baselineAnalysis = $this->baselineResults['queries']['analysis'] ?? [];

        // Compare average query time
        if (isset($currentAnalysis['average_time']) && isset($baselineAnalysis['average_time'])) {
            $current = $currentAnalysis['average_time'];
            $baseline = $baselineAnalysis['average_time'];
            $changeRatio = ($current - $baseline) / $baseline;

            if (abs($changeRatio) > $threshold) {
                $item = [
                    'category' => 'queries',
                    'metric' => 'average_time',
                    'current_value' => $current,
                    'baseline_value' => $baseline,
                    'change_percent' => $changeRatio * 100,
                    'severity' => $this->calculateSeverity($changeRatio, $threshold),
                    'unit' => 'ms',
                ];

                if ($changeRatio > 0) {
                    $this->regressions[] = $item;
                } else {
                    $this->improvements[] = $item;
                }
            }
        }

        // Compare slow query count
        if (isset($currentAnalysis['slow_queries_count']) && isset($baselineAnalysis['slow_queries_count'])) {
            $current = $currentAnalysis['slow_queries_count'];
            $baseline = $baselineAnalysis['slow_queries_count'];

            if ($current > $baseline) {
                $this->regressions[] = [
                    'category' => 'queries',
                    'metric' => 'slow_queries_count',
                    'current_value' => $current,
                    'baseline_value' => $baseline,
                    'change_percent' => (($current - $baseline) / max($baseline, 1)) * 100,
                    'severity' => $current > $baseline * 2 ? 'critical' : 'high',
                    'unit' => 'queries',
                ];
            } elseif ($current < $baseline) {
                $this->improvements[] = [
                    'category' => 'queries',
                    'metric' => 'slow_queries_count',
                    'current_value' => $current,
                    'baseline_value' => $baseline,
                    'change_percent' => (($baseline - $current) / max($baseline, 1)) * 100,
                    'severity' => 'improvement',
                    'unit' => 'queries',
                ];
            }
        }
    }

    private function compareMemoryResults(float $threshold): void
    {
        // Implementation would depend on memory test structure
        // This is a placeholder for memory comparison logic
    }

    private function calculateSeverity(float $changeRatio, float $threshold): string
    {
        $absChange = abs($changeRatio);

        if ($absChange > $threshold * 3) {
            return 'critical';
        }
        if ($absChange > $threshold * 2) {
            return 'high';
        }
        if ($absChange > $threshold * 1.5) {
            return 'medium';
        }

        return 'low';
    }

    private function getMetricUnit(string $metric): string
    {
        if (strpos($metric, 'time') !== false) {
            return 'ms';
        }
        if (strpos($metric, 'memory') !== false) {
            return 'MB';
        }
        if (strpos($metric, 'count') !== false) {
            return '';
        }
        if (strpos($metric, 'ratio') !== false) {
            return '%';
        }

        return '';
    }

    private function outputConsole(): void
    {
        $this->info("\n📊 Performance Analysis Report");
        $this->info('===============================');

        if (empty($this->baselineResults)) {
            $this->info("\n📈 Current Performance Summary:");
            $this->displayCurrentResults();

            return;
        }

        $this->info("\n🔍 Regression Analysis:");

        if (empty($this->regressions) && empty($this->improvements)) {
            $this->info('✅ No significant performance changes detected');
        } else {
            if (! empty($this->regressions)) {
                $this->displayRegressions();
            }

            if (! empty($this->improvements)) {
                $this->displayImprovements();
            }
        }

        $this->generateSummary();
    }

    private function outputJson(): void
    {
        $output = [
            'timestamp' => now()->toISOString(),
            'analysis_summary' => [
                'has_baseline' => ! empty($this->baselineResults),
                'regressions_count' => count($this->regressions),
                'improvements_count' => count($this->improvements),
                'threshold_percent' => $this->option('threshold'),
            ],
            'regressions' => $this->regressions,
            'improvements' => $this->improvements,
            'current_results' => $this->currentResults,
            'severity_summary' => $this->getSeveritySummary(),
            'recommendations' => $this->generateRecommendations(),
        ];

        // Add CI markers
        $criticalRegressions = array_filter($this->regressions, fn ($r) => $r['severity'] === 'critical');
        if (count($criticalRegressions) > 0) {
            $output['CRITICAL_REGRESSION'] = true;
        }

        echo json_encode($output, JSON_PRETTY_PRINT);
    }

    private function displayCurrentResults(): void
    {
        if (isset($this->currentResults['benchmark'])) {
            $results = $this->currentResults['benchmark']['results'] ?? [];

            foreach ($results as $category => $metrics) {
                $this->info("\n📈 ".strtoupper($category).':');
                foreach ($metrics as $metric => $stats) {
                    $unit = $this->getMetricUnit($metric);
                    $this->line("  {$metric}: ".round($stats['avg'], 2).$unit);
                }
            }
        }

        if (isset($this->currentResults['queries'])) {
            $analysis = $this->currentResults['queries']['analysis'] ?? [];
            $this->info("\n🔍 QUERY ANALYSIS:");
            $this->line('  Average time: '.round($analysis['average_time'] ?? 0, 2).'ms');
            $this->line('  Slow queries: '.($analysis['slow_queries_count'] ?? 0));
        }
    }

    private function displayRegressions(): void
    {
        $this->error("\n⚠️  Performance Regressions Detected:");

        $critical = array_filter($this->regressions, fn ($r) => $r['severity'] === 'critical');
        $high = array_filter($this->regressions, fn ($r) => $r['severity'] === 'high');
        $others = array_filter($this->regressions, fn ($r) => ! in_array($r['severity'], ['critical', 'high']));

        if (! empty($critical)) {
            $this->error("\n🔴 Critical Regressions:");
            foreach ($critical as $regression) {
                $this->displayRegression($regression);
            }
        }

        if (! empty($high)) {
            $this->warn("\n🟡 High Priority Regressions:");
            foreach ($high as $regression) {
                $this->displayRegression($regression);
            }
        }

        if (! empty($others)) {
            $this->info("\n🟠 Other Regressions:");
            foreach ($others as $regression) {
                $this->displayRegression($regression);
            }
        }
    }

    private function displayImprovements(): void
    {
        $this->info("\n✅ Performance Improvements:");

        foreach ($this->improvements as $improvement) {
            $this->line("  {$improvement['category']}.{$improvement['metric']}: ".
                round($improvement['baseline_value'], 2).$improvement['unit'].' → '.
                round($improvement['current_value'], 2).$improvement['unit'].' '.
                '('.round(abs($improvement['change_percent']), 1).'% better)');
        }
    }

    private function displayRegression(array $regression): void
    {
        $this->line("  {$regression['category']}.{$regression['metric']}: ".
            round($regression['baseline_value'], 2).$regression['unit'].' → '.
            round($regression['current_value'], 2).$regression['unit'].' '.
            '(+'.round($regression['change_percent'], 1).'% worse)');
    }

    private function generateSummary(): void
    {
        $this->info("\n📋 Summary:");

        $criticalCount = count(array_filter($this->regressions, fn ($r) => $r['severity'] === 'critical'));
        $highCount = count(array_filter($this->regressions, fn ($r) => $r['severity'] === 'high'));
        $improvementCount = count($this->improvements);

        if ($criticalCount > 0) {
            $this->error("  🔴 {$criticalCount} critical regression(s) - immediate action required");
        }

        if ($highCount > 0) {
            $this->warn("  🟡 {$highCount} high priority regression(s) - should be addressed");
        }

        if ($improvementCount > 0) {
            $this->info("  ✅ {$improvementCount} improvement(s) detected");
        }

        if ($criticalCount === 0 && $highCount === 0) {
            $this->info('  🎉 No critical performance regressions detected!');
        }
    }

    private function getSeveritySummary(): array
    {
        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($this->regressions as $regression) {
            $summary[$regression['severity']]++;
        }

        return $summary;
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        $criticalRegressions = array_filter($this->regressions, fn ($r) => $r['severity'] === 'critical');
        if (count($criticalRegressions) > 0) {
            $recommendations[] = 'Address '.count($criticalRegressions).' critical performance regressions before deployment';
        }

        $dbRegressions = array_filter($this->regressions, fn ($r) => $r['category'] === 'database');
        if (count($dbRegressions) > 0) {
            $recommendations[] = 'Review database query optimizations - '.count($dbRegressions).' database metrics regressed';
        }

        $cacheRegressions = array_filter($this->regressions, fn ($r) => $r['category'] === 'cache');
        if (count($cacheRegressions) > 0) {
            $recommendations[] = 'Check cache configuration - cache performance has regressed';
        }

        $queryRegressions = array_filter($this->regressions, fn ($r) => $r['category'] === 'queries');
        if (count($queryRegressions) > 0) {
            $recommendations[] = 'Investigate slow query increases - review recent query changes';
        }

        if (count($this->improvements) > 0) {
            $recommendations[] = 'Good work! '.count($this->improvements).' performance improvements detected';
        }

        return $recommendations;
    }
}
