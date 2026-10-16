<?php

namespace App\Http\Middleware;

use App\Services\NPlusOneDetector;
use App\Services\PerformanceMonitoringService;
use App\Services\QueryAnalyzer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryPerformanceMiddleware
{
    protected QueryAnalyzer $queryAnalyzer;

    protected NPlusOneDetector $nPlusOneDetector;

    protected PerformanceMonitoringService $performanceService;

    private const ANALYSIS_CACHE_TTL = 300; // 5 minutes

    private const ALERT_THRESHOLD = 1000; // 1 second

    private const DEVELOPMENT_MODE_QUERY_LOG_LIMIT = 50;

    public function __construct(
        QueryAnalyzer $queryAnalyzer,
        NPlusOneDetector $nPlusOneDetector,
        PerformanceMonitoringService $performanceService
    ) {
        $this->queryAnalyzer = $queryAnalyzer;
        $this->nPlusOneDetector = $nPlusOneDetector;
        $this->performanceService = $performanceService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $startQueryCount = $this->getQueryCount();

        // Enable query logging for development
        $isDevMode = app()->environment('local', 'development');
        if ($isDevMode) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        // Set up query listener for real-time analysis
        $this->setupQueryListener();

        $response = $next($request);

        // Analyze performance after request completion
        $this->analyzeRequestPerformance($request, $response, [
            'start_time' => $startTime,
            'start_memory' => $startMemory,
            'start_query_count' => $startQueryCount,
            'is_dev_mode' => $isDevMode,
        ]);

        return $response;
    }

    /**
     * Set up real-time query listener for analysis
     */
    private function setupQueryListener(): void
    {
        DB::listen(function ($query) {
            // Track query for N+1 detection
            $this->nPlusOneDetector->trackQuery($query);

            // Analyze individual query
            $analysis = $this->queryAnalyzer->analyzeQuery($query);

            // Log slow queries immediately in development
            if (app()->environment('local', 'development') && $query->time > 100) {
                $this->logSlowQueryInDevelopment($query, $analysis);
            }

            // Alert on critical queries in production
            if (app()->environment('production') && $query->time > self::ALERT_THRESHOLD) {
                $this->alertCriticalQuery($query, $analysis);
            }
        });
    }

    /**
     * Analyze overall request performance
     */
    private function analyzeRequestPerformance(Request $request, $response, array $startMetrics): void
    {
        $endTime = microtime(true);
        $responseTime = $endTime - $startMetrics['start_time'];
        $memoryUsed = memory_get_usage() - $startMetrics['start_memory'];
        $endQueryCount = $this->getQueryCount();
        $queryCount = $endQueryCount - $startMetrics['start_query_count'];

        $endpoint = $request->getPathInfo();
        $method = $request->getMethod();

        // Get query log for analysis
        $queryLog = $startMetrics['is_dev_mode'] ? DB::getQueryLog() : [];

        // Compile performance metrics
        $performanceData = [
            'endpoint' => $endpoint,
            'method' => $method,
            'response_time' => round($responseTime * 1000, 2), // ms
            'memory_used' => $this->formatBytes($memoryUsed),
            'query_count' => $queryCount,
            'status_code' => $response->getStatusCode(),
            'timestamp' => now()->toISOString(),
        ];

        // Analyze queries if in development mode
        if ($startMetrics['is_dev_mode'] && ! empty($queryLog)) {
            $analysisResults = $this->performDetailedQueryAnalysis($queryLog, $endpoint);
            $performanceData = array_merge($performanceData, $analysisResults);
        }

        // Record API performance
        $this->performanceService->monitorApiResponse(
            $endpoint,
            $responseTime,
            $response->getStatusCode()
        );

        // Log performance data
        $this->logPerformanceData($performanceData);

        // Check for performance issues and generate alerts
        $this->checkPerformanceThresholds($performanceData, $endpoint);

        // Cache performance metrics for dashboard
        $this->cachePerformanceMetrics($endpoint, $performanceData);
    }

    /**
     * Perform detailed query analysis for development mode
     */
    private function performDetailedQueryAnalysis(array $queryLog, string $endpoint): array
    {
        $analysisResults = [];

        // Get N+1 detection results
        $nPlusOnePatterns = $this->nPlusOneDetector->detectNPlusOne();

        // Get query analyzer metrics
        $performanceMetrics = $this->queryAnalyzer->getPerformanceMetrics();

        // Get eager loading suggestions
        $eagerLoadingSuggestions = $this->nPlusOneDetector->generateEagerLoadingSuggestions();

        $analysisResults['query_analysis'] = [
            'total_queries' => count($queryLog),
            'slow_queries' => $performanceMetrics['slow_queries'],
            'critical_queries' => $performanceMetrics['critical_queries'],
            'performance_grade' => $performanceMetrics['performance_grade'],
            'n_plus_one_patterns' => count($nPlusOnePatterns),
            'optimization_opportunities' => $performanceMetrics['optimization_opportunities'],
        ];

        // Include detailed suggestions for development
        if (! empty($nPlusOnePatterns)) {
            $analysisResults['n_plus_one_detected'] = $nPlusOnePatterns;
        }

        if (! empty($eagerLoadingSuggestions)) {
            $analysisResults['eager_loading_suggestions'] = $eagerLoadingSuggestions;
        }

        // Log development alerts
        $this->logDevelopmentAlerts($nPlusOnePatterns, $eagerLoadingSuggestions, $endpoint);

        return $analysisResults;
    }

    /**
     * Log slow query in development with suggestions
     */
    private function logSlowQueryInDevelopment($query, array $analysis): void
    {
        Log::channel('daily')->warning('🐌 Slow Query Detected in Development', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'execution_time_ms' => round($query->time, 2),
            'optimization_score' => $analysis['optimization_score'],
            'issues' => $analysis['issues'],
            'recommendations' => $analysis['recommendations'],
            'severity' => $analysis['severity'],
        ]);

        // Also output to browser console in development
        if (app()->environment('local')) {
            $this->addToDevConsole([
                'type' => 'slow_query',
                'time' => round($query->time, 2),
                'sql' => substr($query->sql, 0, 100).'...',
                'suggestions' => $analysis['recommendations'],
            ]);
        }
    }

    /**
     * Alert on critical queries in production
     */
    private function alertCriticalQuery($query, array $analysis): void
    {
        Log::alert('🚨 Critical Query Performance Issue', [
            'sql' => substr($query->sql, 0, 200),
            'execution_time_ms' => round($query->time, 2),
            'connection' => $query->connectionName,
            'optimization_score' => $analysis['optimization_score'],
            'severity' => $analysis['severity'],
            'environment' => app()->environment(),
        ]);

        // Could integrate with alerting service here
        // $this->sendSlackAlert($query, $analysis);
    }

    /**
     * Log development alerts for N+1 and optimization opportunities
     */
    private function logDevelopmentAlerts(array $nPlusOnePatterns, array $eagerLoadingSuggestions, string $endpoint): void
    {
        if (! empty($nPlusOnePatterns)) {
            foreach ($nPlusOnePatterns as $pattern) {
                if ($pattern['severity'] === 'high' || $pattern['severity'] === 'critical') {
                    Log::channel('daily')->warning('🔄 N+1 Query Pattern Detected', [
                        'endpoint' => $endpoint,
                        'pattern' => $pattern['pattern'],
                        'query_count' => $pattern['query_count'],
                        'total_time_ms' => $pattern['total_time'],
                        'suggestion' => $pattern['suggestion'],
                        'potential_savings_ms' => $pattern['potential_savings'],
                    ]);

                    $this->addToDevConsole([
                        'type' => 'n_plus_one',
                        'queries' => $pattern['query_count'],
                        'suggestion' => $pattern['recommended_eager_loading'],
                        'savings' => round($pattern['potential_savings'], 2).'ms',
                    ]);
                }
            }
        }

        if (! empty($eagerLoadingSuggestions)) {
            Log::channel('daily')->info('💡 Eager Loading Optimization Opportunities', [
                'endpoint' => $endpoint,
                'suggestions' => array_map(function ($suggestion) {
                    return [
                        'model' => $suggestion['model'],
                        'relationship' => $suggestion['relationship'],
                        'queries_reduced' => $suggestion['impact']['queries_reduced'],
                        'time_saved_ms' => $suggestion['impact']['time_saved_ms'],
                    ];
                }, $eagerLoadingSuggestions),
            ]);
        }
    }

    /**
     * Log performance data with appropriate level
     */
    private function logPerformanceData(array $performanceData): void
    {
        $logLevel = 'info';
        $message = '📊 Request Performance Metrics';

        // Determine log level based on performance
        if ($performanceData['response_time'] > 2000) {
            $logLevel = 'error';
            $message = '🚨 Very Slow Request Detected';
        } elseif ($performanceData['response_time'] > 1000) {
            $logLevel = 'warning';
            $message = '⚠️ Slow Request Detected';
        } elseif ($performanceData['query_count'] > 20) {
            $logLevel = 'warning';
            $message = '🔍 High Query Count Detected';
        }

        Log::channel('daily')->{$logLevel}($message, $performanceData);
    }

    /**
     * Check performance thresholds and generate alerts
     */
    private function checkPerformanceThresholds(array $performanceData, string $endpoint): void
    {
        $cacheKey = "performance_alerts:{$endpoint}:".now()->format('Y-m-d-H');
        $alertCount = Cache::get($cacheKey, 0);

        // Alert thresholds
        $shouldAlert = false;
        $alertReason = '';

        if ($performanceData['response_time'] > 2000) {
            $shouldAlert = true;
            $alertReason = 'Response time exceeded 2 seconds';
        } elseif ($performanceData['query_count'] > 50) {
            $shouldAlert = true;
            $alertReason = 'Query count exceeded 50 queries per request';
        } elseif (isset($performanceData['query_analysis']['critical_queries']) &&
                  $performanceData['query_analysis']['critical_queries'] > 0) {
            $shouldAlert = true;
            $alertReason = 'Critical queries detected';
        }

        if ($shouldAlert && $alertCount < 3) { // Limit alerts per hour
            $this->sendPerformanceAlert($endpoint, $performanceData, $alertReason);
            Cache::put($cacheKey, $alertCount + 1, now()->addHour());
        }
    }

    /**
     * Cache performance metrics for dashboard
     */
    private function cachePerformanceMetrics(string $endpoint, array $performanceData): void
    {
        $cacheKey = 'performance_metrics:'.md5($endpoint).':'.now()->format('Y-m-d-H-i');

        $metrics = [
            'endpoint' => $endpoint,
            'response_time' => $performanceData['response_time'],
            'query_count' => $performanceData['query_count'],
            'memory_used' => $performanceData['memory_used'],
            'timestamp' => $performanceData['timestamp'],
        ];

        if (isset($performanceData['query_analysis'])) {
            $metrics['query_analysis'] = $performanceData['query_analysis'];
        }

        Cache::put($cacheKey, $metrics, now()->addMinutes(self::ANALYSIS_CACHE_TTL));

        // Also update endpoint summary
        $this->updateEndpointSummary($endpoint, $performanceData);
    }

    /**
     * Update endpoint performance summary
     */
    private function updateEndpointSummary(string $endpoint, array $performanceData): void
    {
        $summaryKey = 'endpoint_summary:'.md5($endpoint);
        $summary = Cache::get($summaryKey, [
            'endpoint' => $endpoint,
            'total_requests' => 0,
            'total_response_time' => 0,
            'total_queries' => 0,
            'slow_requests' => 0,
            'last_updated' => now()->toISOString(),
        ]);

        $summary['total_requests']++;
        $summary['total_response_time'] += $performanceData['response_time'];
        $summary['total_queries'] += $performanceData['query_count'];

        if ($performanceData['response_time'] > 1000) {
            $summary['slow_requests']++;
        }

        $summary['avg_response_time'] = $summary['total_response_time'] / $summary['total_requests'];
        $summary['avg_query_count'] = $summary['total_queries'] / $summary['total_requests'];
        $summary['slow_request_percentage'] = ($summary['slow_requests'] / $summary['total_requests']) * 100;
        $summary['last_updated'] = now()->toISOString();

        Cache::put($summaryKey, $summary, now()->addDay());
    }

    /**
     * Send performance alert
     */
    private function sendPerformanceAlert(string $endpoint, array $performanceData, string $reason): void
    {
        Log::alert('🚨 Performance Alert', [
            'endpoint' => $endpoint,
            'reason' => $reason,
            'response_time_ms' => $performanceData['response_time'],
            'query_count' => $performanceData['query_count'],
            'timestamp' => $performanceData['timestamp'],
        ]);

        // Integration point for external alerting systems
        // $this->notificationService->sendPerformanceAlert($endpoint, $performanceData, $reason);
    }

    /**
     * Add data to development console (for frontend debugging)
     */
    private function addToDevConsole(array $data): void
    {
        $consoleKey = 'dev_console_'.request()->ip();
        $consoleData = Cache::get($consoleKey, []);
        $consoleData[] = array_merge($data, ['timestamp' => now()->toISOString()]);

        // Keep only last 20 entries
        if (count($consoleData) > 20) {
            $consoleData = array_slice($consoleData, -20);
        }

        Cache::put($consoleKey, $consoleData, now()->addMinutes(10));
    }

    /**
     * Get current query count
     */
    private function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Get development console data for frontend
     */
    public static function getDevConsoleData(Request $request): array
    {
        if (! app()->environment('local', 'development')) {
            return [];
        }

        $consoleKey = 'dev_console_'.$request->ip();

        return Cache::get($consoleKey, []);
    }

    /**
     * Reset analysis state (useful for testing)
     */
    public function resetAnalysis(): void
    {
        $this->queryAnalyzer->resetAnalysis();
        $this->nPlusOneDetector->reset();
    }
}
