<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class QueryAnalyzer
{
    private const SLOW_QUERY_THRESHOLD = 100; // 100ms

    private const CRITICAL_THRESHOLD = 1000; // 1 second

    private const N_PLUS_ONE_DETECTION_LIMIT = 5; // Detect if same query pattern runs more than 5 times

    private array $queryLog = [];

    private array $queryPatterns = [];

    private array $recommendations = [];

    /**
     * Analyze a single executed query
     */
    public function analyzeQuery(QueryExecuted $query): array
    {
        $analysis = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'execution_time' => $query->time,
            'connection' => $query->connectionName,
            'issues' => [],
            'recommendations' => [],
            'severity' => $this->calculateSeverity($query->time),
            'optimization_score' => 100,
        ];

        // Detect common performance issues
        $this->detectSelectStarIssues($analysis);
        $this->detectMissingIndexHints($analysis);
        $this->detectInefficiientJoins($analysis);
        $this->detectSuboptimalWhereClauses($analysis);
        $this->detectLikePatternIssues($analysis);
        $this->detectOrderByWithoutLimit($analysis);
        $this->detectGroupByIssues($analysis);
        $this->detectCartesianProducts($analysis);

        // Calculate optimization score
        $analysis['optimization_score'] = $this->calculateOptimizationScore($analysis);

        // Store for N+1 detection
        $this->storeQueryPattern($analysis);

        return $analysis;
    }

    /**
     * Detect N+1 query patterns in real-time
     */
    public function detectNPlusOnePatterns(): array
    {
        $nPlusOnePatterns = [];

        foreach ($this->queryPatterns as $pattern => $occurrences) {
            if (count($occurrences) >= self::N_PLUS_ONE_DETECTION_LIMIT) {
                $nPlusOnePatterns[] = [
                    'pattern' => $pattern,
                    'occurrences' => count($occurrences),
                    'total_time' => array_sum(array_column($occurrences, 'time')),
                    'avg_time' => array_sum(array_column($occurrences, 'time')) / count($occurrences),
                    'first_query' => $occurrences[0]['sql'],
                    'suggestion' => $this->generateEagerLoadingSuggestion($pattern),
                    'severity' => 'high',
                ];
            }
        }

        return $nPlusOnePatterns;
    }

    /**
     * Analyze missing indexes based on query patterns
     */
    public function suggestMissingIndexes(): array
    {
        $indexSuggestions = [];

        foreach ($this->queryLog as $query) {
            $sql = strtolower($query['sql']);

            // Detect WHERE clauses that might benefit from indexes
            if (preg_match_all('/where\s+(\w+)\.(\w+)\s*[=<>]/', $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $table = $match[1];
                    $column = $match[2];

                    $indexSuggestions[] = [
                        'table' => $table,
                        'column' => $column,
                        'reason' => 'WHERE clause filtering',
                        'query_time' => $query['execution_time'],
                        'suggestion' => "ADD INDEX idx_{$table}_{$column} ({$column})",
                        'priority' => $query['execution_time'] > self::SLOW_QUERY_THRESHOLD ? 'high' : 'medium',
                    ];
                }
            }

            // Detect JOIN conditions that might need indexes
            if (preg_match_all('/join\s+(\w+)\s+on\s+\w+\.(\w+)\s*=\s*\w+\.(\w+)/', $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $table = $match[1];
                    $column = $match[2];

                    $indexSuggestions[] = [
                        'table' => $table,
                        'column' => $column,
                        'reason' => 'JOIN condition',
                        'query_time' => $query['execution_time'],
                        'suggestion' => "ADD INDEX idx_{$table}_{$column} ({$column})",
                        'priority' => 'high',
                    ];
                }
            }

            // Detect ORDER BY clauses that might need indexes
            if (preg_match_all('/order\s+by\s+(\w+)\.(\w+)/', $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $table = $match[1];
                    $column = $match[2];

                    $indexSuggestions[] = [
                        'table' => $table,
                        'column' => $column,
                        'reason' => 'ORDER BY clause',
                        'query_time' => $query['execution_time'],
                        'suggestion' => "ADD INDEX idx_{$table}_{$column} ({$column})",
                        'priority' => $query['execution_time'] > self::SLOW_QUERY_THRESHOLD ? 'high' : 'low',
                    ];
                }
            }
        }

        // Remove duplicates and group by table
        return $this->consolidateIndexSuggestions($indexSuggestions);
    }

    /**
     * Generate optimization recommendations for Eloquent queries
     */
    public function generateEloquentOptimizations(): array
    {
        $optimizations = [];

        // Analyze for eager loading opportunities
        $nPlusOnes = $this->detectNPlusOnePatterns();
        foreach ($nPlusOnes as $pattern) {
            $optimizations[] = [
                'type' => 'eager_loading',
                'description' => 'N+1 query detected - use eager loading',
                'suggestion' => $pattern['suggestion'],
                'impact' => 'high',
                'queries_saved' => $pattern['occurrences'] - 1,
                'time_saved' => $pattern['total_time'] * 0.8, // Estimated 80% time savings
            ];
        }

        // Analyze for select optimization
        foreach ($this->queryLog as $query) {
            if (stripos($query['sql'], 'select *') !== false) {
                $optimizations[] = [
                    'type' => 'select_optimization',
                    'description' => 'SELECT * detected - specify only needed columns',
                    'suggestion' => 'Use ->select([\'column1\', \'column2\']) instead of selecting all columns',
                    'impact' => 'medium',
                    'estimated_savings' => '20-40% memory reduction',
                ];
            }
        }

        return $optimizations;
    }

    /**
     * Get real-time query performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $totalQueries = count($this->queryLog);
        $slowQueries = array_filter($this->queryLog, fn ($q) => $q['execution_time'] > self::SLOW_QUERY_THRESHOLD);
        $criticalQueries = array_filter($this->queryLog, fn ($q) => $q['execution_time'] > self::CRITICAL_THRESHOLD);

        $totalTime = array_sum(array_column($this->queryLog, 'execution_time'));
        $avgTime = $totalQueries > 0 ? $totalTime / $totalQueries : 0;

        return [
            'total_queries' => $totalQueries,
            'slow_queries' => count($slowQueries),
            'critical_queries' => count($criticalQueries),
            'total_execution_time' => round($totalTime, 2),
            'average_execution_time' => round($avgTime, 2),
            'slow_query_percentage' => $totalQueries > 0 ? round((count($slowQueries) / $totalQueries) * 100, 2) : 0,
            'n_plus_one_patterns' => count($this->detectNPlusOnePatterns()),
            'performance_grade' => $this->calculatePerformanceGrade(),
            'optimization_opportunities' => count($this->generateEloquentOptimizations()),
        ];
    }

    /**
     * Private helper methods
     */
    private function calculateSeverity(float $executionTime): string
    {
        if ($executionTime >= self::CRITICAL_THRESHOLD) {
            return 'critical';
        }
        if ($executionTime >= self::SLOW_QUERY_THRESHOLD * 5) {
            return 'high';
        }
        if ($executionTime >= self::SLOW_QUERY_THRESHOLD) {
            return 'medium';
        }

        return 'low';
    }

    private function detectSelectStarIssues(array &$analysis): void
    {
        if (stripos($analysis['sql'], 'select *') !== false) {
            $analysis['issues'][] = 'Using SELECT * - transfers unnecessary data';
            $analysis['recommendations'][] = 'Specify only required columns to reduce memory usage and network transfer';
            $analysis['optimization_score'] -= 15;
        }
    }

    private function detectMissingIndexHints(array &$analysis): void
    {
        $sql = strtolower($analysis['sql']);

        // Check for WHERE clauses without indexes (heuristic)
        if (preg_match('/where.*like.*%.*%/', $sql)) {
            $analysis['issues'][] = 'Full-text search pattern detected';
            $analysis['recommendations'][] = 'Consider using full-text indexes or search engines for LIKE queries with leading wildcards';
            $analysis['optimization_score'] -= 20;
        }

        if (preg_match('/where.*in\s*\(.*\)/', $sql) && $analysis['execution_time'] > self::SLOW_QUERY_THRESHOLD) {
            $analysis['issues'][] = 'Large IN clause detected in slow query';
            $analysis['recommendations'][] = 'Consider breaking large IN clauses into smaller chunks or using EXISTS subqueries';
            $analysis['optimization_score'] -= 10;
        }
    }

    private function detectInefficiientJoins(array &$analysis): void
    {
        $sql = strtolower($analysis['sql']);
        $joinCount = preg_match_all('/\bjoin\b/', $sql);

        if ($joinCount >= 4) {
            $analysis['issues'][] = "Multiple JOINs detected ({$joinCount} joins)";
            $analysis['recommendations'][] = 'Consider denormalization or caching for complex joins';
            $analysis['optimization_score'] -= $joinCount * 5;
        }

        if (strpos($sql, 'left join') !== false && strpos($sql, 'where') !== false) {
            $analysis['issues'][] = 'LEFT JOIN with WHERE clause - might be convertible to INNER JOIN';
            $analysis['recommendations'][] = 'Review if LEFT JOIN can be converted to INNER JOIN for better performance';
            $analysis['optimization_score'] -= 5;
        }
    }

    private function detectSuboptimalWhereClauses(array &$analysis): void
    {
        $sql = strtolower($analysis['sql']);

        if (preg_match('/where.*or.*or/', $sql)) {
            $analysis['issues'][] = 'Multiple OR conditions detected';
            $analysis['recommendations'][] = 'Consider using UNION or IN clauses instead of multiple OR conditions';
            $analysis['optimization_score'] -= 10;
        }

        if (preg_match('/where.*function\s*\(/', $sql)) {
            $analysis['issues'][] = 'Function in WHERE clause prevents index usage';
            $analysis['recommendations'][] = 'Avoid functions in WHERE clauses or create functional indexes';
            $analysis['optimization_score'] -= 15;
        }
    }

    private function detectLikePatternIssues(array &$analysis): void
    {
        foreach ($analysis['bindings'] as $binding) {
            if (is_string($binding) && strpos($binding, '%') === 0) {
                $analysis['issues'][] = 'Leading wildcard in LIKE query prevents index usage';
                $analysis['recommendations'][] = 'Avoid leading wildcards in LIKE queries or use full-text search';
                $analysis['optimization_score'] -= 20;
                break;
            }
        }
    }

    private function detectOrderByWithoutLimit(array &$analysis): void
    {
        $sql = strtolower($analysis['sql']);

        if (strpos($sql, 'order by') !== false && strpos($sql, 'limit') === false) {
            $analysis['issues'][] = 'ORDER BY without LIMIT can be expensive';
            $analysis['recommendations'][] = 'Add LIMIT clause or ensure ORDER BY columns are properly indexed';
            $analysis['optimization_score'] -= 10;
        }
    }

    private function detectGroupByIssues(array &$analysis): void
    {
        $sql = strtolower($analysis['sql']);

        if (strpos($sql, 'group by') !== false) {
            $analysis['issues'][] = 'GROUP BY operation detected';
            $analysis['recommendations'][] = 'Ensure GROUP BY columns are indexed for optimal performance';
            $analysis['optimization_score'] -= 5;
        }
    }

    private function detectCartesianProducts(array &$analysis): void
    {
        $sql = strtolower($analysis['sql']);

        if (strpos($sql, 'join') !== false && strpos($sql, 'on') === false && strpos($sql, 'using') === false) {
            $analysis['issues'][] = 'Possible Cartesian product detected (JOIN without ON clause)';
            $analysis['recommendations'][] = 'Ensure all JOINs have proper ON conditions to avoid Cartesian products';
            $analysis['optimization_score'] -= 30;
        }
    }

    private function calculateOptimizationScore(array $analysis): int
    {
        return max(0, min(100, $analysis['optimization_score']));
    }

    private function storeQueryPattern(array $analysis): void
    {
        // Create a pattern from the SQL query by removing specific values
        $pattern = preg_replace('/\?/', 'PLACEHOLDER', $analysis['sql']);
        $pattern = preg_replace('/\d+/', 'NUMBER', $pattern);
        $pattern = preg_replace('/\'[^\']*\'/', 'STRING', $pattern);

        if (! isset($this->queryPatterns[$pattern])) {
            $this->queryPatterns[$pattern] = [];
        }

        $this->queryPatterns[$pattern][] = [
            'sql' => $analysis['sql'],
            'time' => $analysis['execution_time'],
            'timestamp' => now(),
        ];

        $this->queryLog[] = $analysis;
    }

    private function generateEagerLoadingSuggestion(string $pattern): string
    {
        // Analyze the pattern to suggest appropriate eager loading
        if (strpos($pattern, 'select * from donatur where') !== false) {
            return "Use ->with('donatur') in the initial query to avoid N+1";
        }

        if (strpos($pattern, 'select * from jenis_quran where') !== false) {
            return "Use ->with('jenisQuran') in the initial query to avoid N+1";
        }

        if (strpos($pattern, 'select * from status_pengiriman where') !== false) {
            return "Use ->with('status') in the initial query to avoid N+1";
        }

        return 'Consider using eager loading with ->with() to avoid N+1 queries';
    }

    private function consolidateIndexSuggestions(array $suggestions): array
    {
        $consolidated = [];

        foreach ($suggestions as $suggestion) {
            $key = $suggestion['table'].'.'.$suggestion['column'];

            if (! isset($consolidated[$key])) {
                $consolidated[$key] = $suggestion;
                $consolidated[$key]['query_count'] = 1;
            } else {
                $consolidated[$key]['query_count']++;
                if ($suggestion['query_time'] > $consolidated[$key]['query_time']) {
                    $consolidated[$key]['query_time'] = $suggestion['query_time'];
                    $consolidated[$key]['priority'] = $suggestion['priority'];
                }
            }
        }

        // Sort by priority and query time
        uasort($consolidated, function ($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aPriority = $priorityOrder[$a['priority']] ?? 0;
            $bPriority = $priorityOrder[$b['priority']] ?? 0;

            if ($aPriority === $bPriority) {
                return $b['query_time'] <=> $a['query_time'];
            }

            return $bPriority <=> $aPriority;
        });

        return array_values($consolidated);
    }

    private function calculatePerformanceGrade(): string
    {
        $metrics = $this->getPerformanceMetrics();

        $score = 100;

        // Deduct points for slow queries
        if ($metrics['slow_query_percentage'] > 20) {
            $score -= 30;
        } elseif ($metrics['slow_query_percentage'] > 10) {
            $score -= 15;
        } elseif ($metrics['slow_query_percentage'] > 5) {
            $score -= 10;
        }

        // Deduct points for N+1 patterns
        if ($metrics['n_plus_one_patterns'] > 0) {
            $score -= $metrics['n_plus_one_patterns'] * 15;
        }

        // Deduct points for average execution time
        if ($metrics['average_execution_time'] > 100) {
            $score -= 20;
        } elseif ($metrics['average_execution_time'] > 50) {
            $score -= 10;
        } elseif ($metrics['average_execution_time'] > 25) {
            $score -= 5;
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

    /**
     * Reset query log for new analysis session
     */
    public function resetAnalysis(): void
    {
        $this->queryLog = [];
        $this->queryPatterns = [];
        $this->recommendations = [];
    }

    /**
     * Get detailed analysis report
     */
    public function getDetailedReport(): array
    {
        return [
            'performance_metrics' => $this->getPerformanceMetrics(),
            'n_plus_one_patterns' => $this->detectNPlusOnePatterns(),
            'index_suggestions' => $this->suggestMissingIndexes(),
            'eloquent_optimizations' => $this->generateEloquentOptimizations(),
            'query_log' => $this->queryLog,
            'timestamp' => now()->toISOString(),
        ];
    }
}
