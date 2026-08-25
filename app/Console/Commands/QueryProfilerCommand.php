<?php

namespace App\Console\Commands;

use App\Services\IndexOptimizer;
use App\Services\NPlusOneDetector;
use App\Services\QueryAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueryProfilerCommand extends Command
{
    protected $signature = 'query:profile 
                            {--endpoint= : Specific endpoint to profile}
                            {--duration=60 : Duration in seconds to profile}
                            {--output=console : Output format (console, json, file)}
                            {--analyze-indexes : Include index analysis}
                            {--detect-n-plus-one : Enable N+1 detection}
                            {--suggestions : Include optimization suggestions}';

    protected $description = 'Profile database queries and provide optimization suggestions';

    private QueryAnalyzer $queryAnalyzer;

    private NPlusOneDetector $nPlusOneDetector;

    private IndexOptimizer $indexOptimizer;

    private array $profileData = [];

    public function __construct(
        QueryAnalyzer $queryAnalyzer,
        NPlusOneDetector $nPlusOneDetector,
        IndexOptimizer $indexOptimizer
    ) {
        parent::__construct();
        $this->queryAnalyzer = $queryAnalyzer;
        $this->nPlusOneDetector = $nPlusOneDetector;
        $this->indexOptimizer = $indexOptimizer;
    }

    public function handle()
    {
        $this->info('🔍 Starting Query Profiler...');

        $duration = (int) $this->option('duration');
        $endpoint = $this->option('endpoint');
        $analyzeIndexes = $this->option('analyze-indexes');
        $detectNPlusOne = $this->option('detect-n-plus-one');
        $includeSuggestions = $this->option('suggestions');

        if ($endpoint) {
            $this->info("📊 Profiling endpoint: {$endpoint}");
        } else {
            $this->info("📊 Profiling all database queries for {$duration} seconds...");
        }

        // Set up query listener
        $this->setupQueryProfiling($detectNPlusOne);

        // Profile for specified duration
        $this->profileQueries($duration, $endpoint);

        // Analyze collected data
        $this->analyzeProfileData($analyzeIndexes, $includeSuggestions);

        // Output results
        $this->outputResults();

        $this->info('✅ Profiling complete!');

        return 0;
    }

    private function setupQueryProfiling(bool $detectNPlusOne): void
    {
        // Reset analyzers
        $this->queryAnalyzer->resetAnalysis();
        if ($detectNPlusOne) {
            $this->nPlusOneDetector->reset();
        }

        // Enable query logging
        DB::enableQueryLog();

        // Set up query listener
        DB::listen(function ($query) use ($detectNPlusOne) {
            // Analyze query
            $analysis = $this->queryAnalyzer->analyzeQuery($query);

            // Track for N+1 detection if enabled
            if ($detectNPlusOne) {
                $this->nPlusOneDetector->trackQuery($query);
            }

            // Store query data
            $this->profileData['queries'][] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'connection' => $query->connectionName,
                'analysis' => $analysis,
                'timestamp' => microtime(true),
            ];
        });
    }

    private function profileQueries(int $duration, ?string $targetEndpoint): void
    {
        $this->profileData = [
            'start_time' => microtime(true),
            'duration' => $duration,
            'target_endpoint' => $targetEndpoint,
            'queries' => [],
            'metrics' => [],
        ];

        $bar = $this->output->createProgressBar($duration);
        $bar->setFormat('[%bar%] %current%/%max% seconds - Queries: %message%');
        $bar->setMessage('0');

        $startTime = time();

        while ((time() - $startTime) < $duration) {
            sleep(1);
            $bar->advance();
            $bar->setMessage((string) count($this->profileData['queries']));
        }

        $bar->finish();
        $this->newLine();

        $this->profileData['end_time'] = microtime(true);
        $this->profileData['total_queries'] = count($this->profileData['queries']);
    }

    private function analyzeProfileData(bool $analyzeIndexes, bool $includeSuggestions): void
    {
        $this->info('🔬 Analyzing collected data...');

        // Get performance metrics
        $this->profileData['performance_metrics'] = $this->queryAnalyzer->getPerformanceMetrics();

        // Get N+1 detection results
        $this->profileData['n_plus_one_patterns'] = $this->nPlusOneDetector->detectNPlusOne();

        // Get eager loading suggestions
        $this->profileData['eager_loading_suggestions'] = $this->nPlusOneDetector->generateEagerLoadingSuggestions();

        // Analyze indexes if requested
        if ($analyzeIndexes) {
            $this->info('📋 Analyzing database indexes...');
            $this->profileData['index_analysis'] = $this->indexOptimizer->getComprehensiveAnalysis();
            $this->profileData['index_suggestions'] = $this->indexOptimizer->analyzeQueryPatternsForIndexes(
                $this->profileData['queries']
            );
        }

        // Generate optimization suggestions if requested
        if ($includeSuggestions) {
            $this->profileData['optimization_suggestions'] = $this->generateOptimizationSuggestions();
        }

        // Calculate summary statistics
        $this->calculateSummaryStatistics();
    }

    private function generateOptimizationSuggestions(): array
    {
        $suggestions = [];

        // Eloquent optimization suggestions
        $eloquentSuggestions = $this->queryAnalyzer->generateEloquentOptimizations();
        if (! empty($eloquentSuggestions)) {
            $suggestions['eloquent'] = $eloquentSuggestions;
        }

        // Query-specific suggestions
        $slowQueries = array_filter($this->profileData['queries'], function ($query) {
            return $query['time'] > 100; // 100ms threshold
        });

        if (! empty($slowQueries)) {
            $suggestions['slow_queries'] = array_map(function ($query) {
                return [
                    'sql' => substr($query['sql'], 0, 100).'...',
                    'time' => $query['time'],
                    'issues' => $query['analysis']['issues'],
                    'recommendations' => $query['analysis']['recommendations'],
                    'optimization_score' => $query['analysis']['optimization_score'],
                ];
            }, array_slice($slowQueries, 0, 10)); // Top 10 slow queries
        }

        // Memory optimization suggestions
        $highMemoryQueries = array_filter($this->profileData['queries'], function ($query) {
            return stripos($query['sql'], 'select *') !== false;
        });

        if (! empty($highMemoryQueries)) {
            $suggestions['memory_optimization'] = [
                'issue' => 'SELECT * queries detected',
                'count' => count($highMemoryQueries),
                'recommendation' => 'Specify only required columns to reduce memory usage',
                'examples' => array_slice(array_map(function ($query) {
                    return substr($query['sql'], 0, 100).'...';
                }, $highMemoryQueries), 0, 5),
            ];
        }

        return $suggestions;
    }

    private function calculateSummaryStatistics(): void
    {
        $queries = $this->profileData['queries'];
        $totalQueries = count($queries);

        if ($totalQueries === 0) {
            $this->profileData['summary'] = [
                'message' => 'No queries were executed during the profiling period',
            ];

            return;
        }

        $totalTime = array_sum(array_column($queries, 'time'));
        $slowQueries = array_filter($queries, fn ($q) => $q['time'] > 100);
        $criticalQueries = array_filter($queries, fn ($q) => $q['time'] > 1000);

        // Calculate percentiles
        $times = array_column($queries, 'time');
        sort($times);

        $this->profileData['summary'] = [
            'total_queries' => $totalQueries,
            'total_time_ms' => round($totalTime, 2),
            'average_time_ms' => round($totalTime / $totalQueries, 2),
            'slow_queries' => count($slowQueries),
            'critical_queries' => count($criticalQueries),
            'slow_query_percentage' => round((count($slowQueries) / $totalQueries) * 100, 2),
            'percentiles' => [
                'p50' => $this->getPercentile($times, 50),
                'p90' => $this->getPercentile($times, 90),
                'p95' => $this->getPercentile($times, 95),
                'p99' => $this->getPercentile($times, 99),
            ],
            'queries_per_second' => round($totalQueries / $this->profileData['duration'], 2),
            'performance_grade' => $this->profileData['performance_metrics']['performance_grade'],
        ];
    }

    private function getPercentile(array $sortedValues, int $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower === $upper) {
            return round($sortedValues[$lower], 2);
        }

        $weight = $index - $lower;

        return round($sortedValues[$lower] * (1 - $weight) + $sortedValues[$upper] * $weight, 2);
    }

    private function outputResults(): void
    {
        $output = $this->option('output');

        switch ($output) {
            case 'json':
                $this->outputJson();
                break;
            case 'file':
                $this->outputToFile();
                break;
            default:
                $this->outputConsole();
                break;
        }
    }

    private function outputConsole(): void
    {
        $this->newLine();
        $this->info('📊 Query Profiling Results');
        $this->info('==========================');

        $summary = $this->profileData['summary'];

        if (isset($summary['message'])) {
            $this->warn($summary['message']);

            return;
        }

        // Summary statistics
        $this->info("\n📈 Summary Statistics:");
        $this->line("  Duration: {$this->profileData['duration']} seconds");
        $this->line("  Total queries: {$summary['total_queries']}");
        $this->line("  Queries per second: {$summary['queries_per_second']}");
        $this->line("  Total execution time: {$summary['total_time_ms']}ms");
        $this->line("  Average query time: {$summary['average_time_ms']}ms");
        $this->line("  Performance grade: {$summary['performance_grade']}");

        // Performance breakdown
        $this->info("\n⚡ Performance Breakdown:");
        $this->line("  Slow queries (>100ms): {$summary['slow_queries']} ({$summary['slow_query_percentage']}%)");
        $this->line("  Critical queries (>1s): {$summary['critical_queries']}");

        // Percentiles
        $this->info("\n📊 Response Time Percentiles:");
        foreach ($summary['percentiles'] as $percentile => $value) {
            $this->line("  {$percentile}: {$value}ms");
        }

        // N+1 patterns
        if (! empty($this->profileData['n_plus_one_patterns'])) {
            $this->warn("\n🔄 N+1 Query Patterns Detected:");
            foreach ($this->profileData['n_plus_one_patterns'] as $pattern) {
                $this->line("  • {$pattern['query_count']} queries, {$pattern['total_time']}ms total");
                $this->line("    Suggestion: {$pattern['suggestion']}");
            }
        }

        // Optimization suggestions
        if (! empty($this->profileData['optimization_suggestions'])) {
            $this->info("\n💡 Optimization Suggestions:");

            if (isset($this->profileData['optimization_suggestions']['eloquent'])) {
                foreach ($this->profileData['optimization_suggestions']['eloquent'] as $suggestion) {
                    $this->line("  • {$suggestion['description']}");
                    $this->line("    Impact: {$suggestion['impact']}");
                }
            }

            if (isset($this->profileData['optimization_suggestions']['memory_optimization'])) {
                $memory = $this->profileData['optimization_suggestions']['memory_optimization'];
                $this->line("  • {$memory['issue']}: {$memory['count']} instances");
                $this->line("    {$memory['recommendation']}");
            }
        }

        // Index suggestions
        if (! empty($this->profileData['index_suggestions'])) {
            $this->info("\n🗂️ Index Recommendations:");
            foreach (array_slice($this->profileData['index_suggestions'], 0, 5) as $suggestion) {
                $this->line("  • {$suggestion['table']}.{$suggestion['column']} ({$suggestion['priority']} priority)");
                $this->line("    Reason: {$suggestion['reasons'][0]}");
            }
        }

        $this->newLine();
        $this->info('💡 For detailed analysis, use --output=json or --output=file');
    }

    private function outputJson(): void
    {
        echo json_encode($this->profileData, JSON_PRETTY_PRINT);
    }

    private function outputToFile(): void
    {
        $filename = 'query-profile-'.date('Y-m-d-H-i-s').'.json';
        $path = storage_path('logs/'.$filename);

        file_put_contents($path, json_encode($this->profileData, JSON_PRETTY_PRINT));

        $this->info("📄 Results saved to: {$path}");
    }
}
