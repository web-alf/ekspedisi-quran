<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IndexOptimizer;
use App\Services\NPlusOneDetector;
use App\Services\PerformanceMonitoringService;
use App\Services\QueryAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QueryOptimizationController extends Controller
{
    private QueryAnalyzer $queryAnalyzer;

    private NPlusOneDetector $nPlusOneDetector;

    private IndexOptimizer $indexOptimizer;

    private PerformanceMonitoringService $performanceService;

    public function __construct(
        QueryAnalyzer $queryAnalyzer,
        NPlusOneDetector $nPlusOneDetector,
        IndexOptimizer $indexOptimizer,
        PerformanceMonitoringService $performanceService
    ) {
        $this->queryAnalyzer = $queryAnalyzer;
        $this->nPlusOneDetector = $nPlusOneDetector;
        $this->indexOptimizer = $indexOptimizer;
        $this->performanceService = $performanceService;
    }

    /**
     * Display the query optimization dashboard
     */
    public function dashboard(Request $request)
    {
        $timeframe = $request->get('timeframe', '24h');

        // Get cached performance data
        $performanceData = $this->getPerformanceData($timeframe);

        // Get current optimization opportunities
        $optimizationOpportunities = $this->getOptimizationOpportunities();

        // Get recent query analysis
        $recentAnalysis = $this->getRecentQueryAnalysis();

        // Get index recommendations
        $indexRecommendations = $this->getIndexRecommendations();

        return Inertia::render('Admin/Performance/QueryOptimization', [
            'performanceData' => $performanceData,
            'optimizationOpportunities' => $optimizationOpportunities,
            'recentAnalysis' => $recentAnalysis,
            'indexRecommendations' => $indexRecommendations,
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * Get real-time query analysis
     */
    public function realTimeAnalysis(Request $request)
    {
        // Enable query logging for this analysis session
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Reset analyzers
        $this->queryAnalyzer->resetAnalysis();
        $this->nPlusOneDetector->reset();

        // Set up real-time listener
        $analysisData = [];

        DB::listen(function ($query) use (&$analysisData) {
            $analysis = $this->queryAnalyzer->analyzeQuery($query);
            $this->nPlusOneDetector->trackQuery($query);

            $analysisData[] = [
                'sql' => $query->sql,
                'time' => $query->time,
                'analysis' => $analysis,
                'timestamp' => now()->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'analysis_started',
            'message' => 'Real-time analysis enabled. Execute queries to see results.',
            'session_id' => uniqid('analysis_'),
        ]);
    }

    /**
     * Get N+1 query detection results
     */
    public function nPlusOneDetection(Request $request)
    {
        $nPlusOnePatterns = $this->nPlusOneDetector->detectNPlusOne();
        $eagerLoadingSuggestions = $this->nPlusOneDetector->generateEagerLoadingSuggestions();
        $developmentAlerts = $this->nPlusOneDetector->getDevelopmentAlerts();

        return response()->json([
            'n_plus_one_patterns' => $nPlusOnePatterns,
            'eager_loading_suggestions' => $eagerLoadingSuggestions,
            'development_alerts' => $developmentAlerts,
            'statistics' => $this->nPlusOneDetector->getStatistics(),
        ]);
    }

    /**
     * Get index optimization recommendations
     */
    public function indexRecommendations(Request $request)
    {
        $includeAnalysis = $request->boolean('include_analysis', false);

        $recommendations = [];

        // Get missing index recommendations
        $missingIndexes = $this->indexOptimizer->analyzeMissingIndexes();

        // Get existing index analysis
        $existingIndexAnalysis = $this->indexOptimizer->analyzeExistingIndexes();

        // Get index usage statistics
        $indexUsage = $this->indexOptimizer->getIndexUsageStatistics();

        // Generate migration SQL
        $migrationSQL = $this->indexOptimizer->generateIndexCreationSQL($missingIndexes);

        $response = [
            'missing_indexes' => $missingIndexes,
            'migration_sql' => $migrationSQL,
            'recommendations_summary' => $this->getIndexRecommendationsSummary($missingIndexes),
        ];

        if ($includeAnalysis) {
            $response['existing_index_analysis'] = $existingIndexAnalysis;
            $response['index_usage_statistics'] = $indexUsage;
            $response['comprehensive_analysis'] = $this->indexOptimizer->getComprehensiveAnalysis();
        }

        return response()->json($response);
    }

    /**
     * Generate index migration file
     */
    public function generateIndexMigration(Request $request)
    {
        $selectedIndexes = $request->get('selected_indexes', []);

        if (empty($selectedIndexes)) {
            return response()->json([
                'error' => 'No indexes selected for migration',
            ], 400);
        }

        // Filter recommendations to selected ones
        $allRecommendations = $this->indexOptimizer->analyzeMissingIndexes();
        $filteredRecommendations = [];

        foreach ($selectedIndexes as $selection) {
            $table = $selection['table'];
            $indexName = $selection['index_name'];

            if (isset($allRecommendations[$table])) {
                foreach ($allRecommendations[$table] as $index) {
                    if ($index['index_name'] === $indexName) {
                        if (! isset($filteredRecommendations[$table])) {
                            $filteredRecommendations[$table] = [];
                        }
                        $filteredRecommendations[$table][] = $index;
                        break;
                    }
                }
            }
        }

        // Generate migration content
        $migrationContent = $this->indexOptimizer->generateIndexMigration($filteredRecommendations);

        // Save migration file
        $timestamp = date('Y_m_d_His');
        $filename = "database/migrations/{$timestamp}_add_performance_indexes.php";
        $fullPath = base_path($filename);

        file_put_contents($fullPath, $migrationContent);

        return response()->json([
            'success' => true,
            'migration_file' => $filename,
            'migration_content' => $migrationContent,
            'indexes_count' => count($selectedIndexes),
        ]);
    }

    /**
     * Analyze specific query
     */
    public function analyzeQuery(Request $request)
    {
        $request->validate([
            'sql' => 'required|string',
            'bindings' => 'array',
        ]);

        $sql = $request->get('sql');
        $bindings = $request->get('bindings', []);

        // Create a mock query executed event
        $mockQuery = new class($sql, $bindings, 0, 'mysql')
        {
            public function __construct($sql, $bindings, $time, $connectionName)
            {
                $this->sql = $sql;
                $this->bindings = $bindings;
                $this->time = $time;
                $this->connectionName = $connectionName;
            }
        };

        // Analyze the query
        $analysis = $this->queryAnalyzer->analyzeQuery($mockQuery);

        // Get EXPLAIN output if possible
        $explainResult = $this->getQueryExplain($sql, $bindings);

        return response()->json([
            'analysis' => $analysis,
            'explain' => $explainResult,
            'suggestions' => $this->generateQuerySpecificSuggestions($analysis, $explainResult),
        ]);
    }

    /**
     * Get performance trends
     */
    public function performanceTrends(Request $request)
    {
        $timeframe = $request->get('timeframe', '24h');
        $endpoint = $request->get('endpoint');

        $trends = $this->getPerformanceTrends($timeframe, $endpoint);

        return response()->json($trends);
    }

    /**
     * Private helper methods
     */
    private function getPerformanceData(string $timeframe): array
    {
        $cacheKey = "performance_dashboard_data_{$timeframe}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return [
                'overview' => $this->performanceService->getDashboardMetrics(),
                'query_metrics' => $this->queryAnalyzer->getPerformanceMetrics(),
                'recent_slow_queries' => $this->getRecentSlowQueries(),
                'endpoint_performance' => $this->getEndpointPerformance(),
            ];
        });
    }

    private function getOptimizationOpportunities(): array
    {
        return [
            'n_plus_one_count' => count($this->nPlusOneDetector->detectNPlusOne()),
            'missing_indexes_count' => $this->getMissingIndexesCount(),
            'slow_queries_count' => $this->getSlowQueriesCount(),
            'optimization_score' => $this->calculateOptimizationScore(),
        ];
    }

    private function getRecentQueryAnalysis(): array
    {
        return Cache::get('recent_query_analysis', [
            'analyzed_queries' => 0,
            'performance_issues' => 0,
            'last_analysis' => null,
        ]);
    }

    private function getIndexRecommendations(): array
    {
        $cacheKey = 'index_recommendations_summary';

        return Cache::remember($cacheKey, now()->addHours(2), function () {
            $missingIndexes = $this->indexOptimizer->analyzeMissingIndexes();

            $recommendations = [];
            $totalRecommendations = 0;
            $highPriorityCount = 0;

            foreach ($missingIndexes as $table => $indexes) {
                foreach ($indexes as $index) {
                    $totalRecommendations++;
                    if ($index['priority'] === 'high' || $index['priority'] === 'critical') {
                        $highPriorityCount++;
                    }

                    $recommendations[] = [
                        'table' => $table,
                        'column' => implode(', ', $index['columns']),
                        'priority' => $index['priority'],
                        'reason' => $index['reason'],
                        'index_name' => $index['index_name'],
                    ];
                }
            }

            return [
                'total_recommendations' => $totalRecommendations,
                'high_priority_count' => $highPriorityCount,
                'recommendations' => array_slice($recommendations, 0, 10), // Top 10
            ];
        });
    }

    private function getIndexRecommendationsSummary(array $missingIndexes): array
    {
        $totalRecommendations = 0;
        $priorityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $affectedTables = count($missingIndexes);

        foreach ($missingIndexes as $table => $indexes) {
            foreach ($indexes as $index) {
                $totalRecommendations++;
                $priorityCounts[$index['priority']]++;
            }
        }

        return [
            'total_recommendations' => $totalRecommendations,
            'priority_breakdown' => $priorityCounts,
            'affected_tables' => $affectedTables,
            'estimated_impact' => $this->estimateIndexImpact($priorityCounts),
        ];
    }

    private function estimateIndexImpact(array $priorityCounts): string
    {
        if ($priorityCounts['critical'] > 0) {
            return 'Critical - Immediate action required';
        }
        if ($priorityCounts['high'] > 3) {
            return 'High - Significant performance gains expected';
        }
        if ($priorityCounts['high'] > 0 || $priorityCounts['medium'] > 5) {
            return 'Medium - Noticeable improvements expected';
        }

        return 'Low - Minor optimizations available';
    }

    private function getQueryExplain(string $sql, array $bindings): ?array
    {
        try {
            // Only try EXPLAIN for SELECT queries
            if (! str_starts_with(trim(strtolower($sql)), 'select')) {
                return null;
            }

            $explainSql = 'EXPLAIN '.$sql;
            $result = DB::select($explainSql, $bindings);

            return array_map(function ($row) {
                return (array) $row;
            }, $result);
        } catch (\Exception $e) {
            return ['error' => 'Could not execute EXPLAIN: '.$e->getMessage()];
        }
    }

    private function generateQuerySpecificSuggestions(array $analysis, ?array $explainResult): array
    {
        $suggestions = $analysis['recommendations'];

        if ($explainResult && ! isset($explainResult['error'])) {
            // Analyze EXPLAIN output
            foreach ($explainResult as $row) {
                if (isset($row['type']) && $row['type'] === 'ALL') {
                    $suggestions[] = 'Full table scan detected - consider adding indexes on filtered columns';
                }

                if (isset($row['Extra']) && str_contains($row['Extra'], 'Using filesort')) {
                    $suggestions[] = 'File sort detected - consider adding index on ORDER BY columns';
                }

                if (isset($row['Extra']) && str_contains($row['Extra'], 'Using temporary')) {
                    $suggestions[] = 'Temporary table created - consider optimizing GROUP BY or DISTINCT clauses';
                }
            }
        }

        return $suggestions;
    }

    private function getRecentSlowQueries(): array
    {
        return Cache::get('recent_slow_queries', []);
    }

    private function getEndpointPerformance(): array
    {
        // Get endpoint performance summaries from cache
        $pattern = 'endpoint_summary:*';
        $endpointKeys = Cache::getMultiple([]);

        $endpointPerformance = [];

        // This would need implementation specific to your cache driver
        // For now, return mock data
        return [
            [
                'endpoint' => '/admin/pengiriman',
                'avg_response_time' => 245.5,
                'avg_query_count' => 12,
                'slow_request_percentage' => 15.2,
            ],
            [
                'endpoint' => '/admin/donatur',
                'avg_response_time' => 189.3,
                'avg_query_count' => 8,
                'slow_request_percentage' => 8.1,
            ],
        ];
    }

    private function getMissingIndexesCount(): int
    {
        $missingIndexes = $this->indexOptimizer->analyzeMissingIndexes();

        $count = 0;
        foreach ($missingIndexes as $table => $indexes) {
            $count += count($indexes);
        }

        return $count;
    }

    private function getSlowQueriesCount(): int
    {
        $metrics = $this->queryAnalyzer->getPerformanceMetrics();

        return $metrics['slow_queries'] ?? 0;
    }

    private function calculateOptimizationScore(): int
    {
        $metrics = $this->queryAnalyzer->getPerformanceMetrics();
        $grade = $metrics['performance_grade'] ?? 'C';

        $scores = [
            'A+' => 95, 'A' => 90, 'B' => 80, 'C' => 70, 'D' => 60, 'F' => 50,
        ];

        return $scores[$grade] ?? 70;
    }

    private function getPerformanceTrends(string $timeframe, ?string $endpoint): array
    {
        // This would analyze cached performance data over time
        // For now, return mock trend data
        return [
            'response_times' => [
                ['timestamp' => now()->subHours(1)->toISOString(), 'value' => 245],
                ['timestamp' => now()->subMinutes(30)->toISOString(), 'value' => 280],
                ['timestamp' => now()->toISOString(), 'value' => 225],
            ],
            'query_counts' => [
                ['timestamp' => now()->subHours(1)->toISOString(), 'value' => 12],
                ['timestamp' => now()->subMinutes(30)->toISOString(), 'value' => 15],
                ['timestamp' => now()->toISOString(), 'value' => 11],
            ],
            'slow_query_rates' => [
                ['timestamp' => now()->subHours(1)->toISOString(), 'value' => 8.5],
                ['timestamp' => now()->subMinutes(30)->toISOString(), 'value' => 12.1],
                ['timestamp' => now()->toISOString(), 'value' => 6.8],
            ],
        ];
    }
}
