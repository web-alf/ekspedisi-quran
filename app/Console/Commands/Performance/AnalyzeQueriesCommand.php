<?php

namespace App\Console\Commands\Performance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeQueriesCommand extends Command
{
    protected $signature = 'performance:analyze-queries 
                            {--output=console : Output format (console, json)}
                            {--slow-threshold=100 : Threshold for slow queries in ms}';

    protected $description = 'Analyze database queries for performance issues';

    private $queries = [];

    private $slowQueries = [];

    public function handle()
    {
        $this->info('🔍 Analyzing Database Query Performance...');

        $slowThreshold = $this->option('slow-threshold');

        // Enable query logging
        DB::enableQueryLog();

        // Run critical application queries
        $this->runCriticalQueries();

        // Analyze the queries
        $this->queries = DB::getQueryLog();
        $this->analyzeQueries($slowThreshold);

        // Output results
        if ($this->option('output') === 'json') {
            $this->outputJson();
        } else {
            $this->outputConsole();
        }

        return 0;
    }

    private function runCriticalQueries(): void
    {
        $this->info('Running critical application queries...');

        // Dashboard queries
        DB::table('pengiriman')->count();
        DB::table('pengiriman')->whereDate('created_at', today())->count();

        // Complex joins
        DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->join('jenis_quran', 'pengiriman.jenis_quran_id', '=', 'jenis_quran.id')
            ->select('pengiriman.*', 'donatur.nama_donatur', 'jenis_quran.nama_jenis')
            ->limit(100)
            ->get();

        // Aggregations
        DB::table('pengiriman')
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->get();

        // Search queries
        DB::table('pengiriman')
            ->join('donatur', 'pengiriman.donatur_id', '=', 'donatur.id')
            ->where('donatur.nama_donatur', 'LIKE', '%test%')
            ->limit(20)
            ->get();

        // Date range queries
        DB::table('pengiriman')
            ->whereBetween('created_at', [now()->subDays(30), now()])
            ->count();

        // Warehouse queries
        DB::table('daily_packing_tasks')
            ->join('users', 'daily_packing_tasks.user_id', '=', 'users.id')
            ->whereDate('tanggal_tugas', today())
            ->select('daily_packing_tasks.*', 'users.name')
            ->get();

        // Performance queries
        DB::table('user_performance')
            ->whereDate('tanggal', today())
            ->get();
    }

    private function analyzeQueries(float $slowThreshold): void
    {
        foreach ($this->queries as $query) {
            if ($query['time'] > $slowThreshold) {
                $this->slowQueries[] = [
                    'sql' => $query['sql'],
                    'bindings' => $query['bindings'],
                    'time' => $query['time'],
                    'analysis' => $this->analyzeQuery($query),
                ];
            }
        }
    }

    private function analyzeQuery(array $query): array
    {
        $analysis = [
            'issues' => [],
            'recommendations' => [],
            'severity' => 'low',
        ];

        $sql = strtolower($query['sql']);

        // Check for common performance issues
        if (strpos($sql, 'select *') !== false) {
            $analysis['issues'][] = 'Using SELECT * - should specify columns';
            $analysis['recommendations'][] = 'Specify only required columns in SELECT';
        }

        if (strpos($sql, 'like') !== false && count($query['bindings']) > 0) {
            foreach ($query['bindings'] as $binding) {
                if (is_string($binding) && strpos($binding, '%') === 0) {
                    $analysis['issues'][] = 'Leading wildcard in LIKE query prevents index usage';
                    $analysis['recommendations'][] = 'Consider full-text search or avoid leading wildcards';
                }
            }
        }

        if (strpos($sql, 'order by') !== false && strpos($sql, 'limit') === false) {
            $analysis['issues'][] = 'ORDER BY without LIMIT may be expensive';
            $analysis['recommendations'][] = 'Add LIMIT clause or ensure proper indexing';
        }

        if (preg_match_all('/join/i', $sql) > 3) {
            $analysis['issues'][] = 'Multiple JOINs detected - may impact performance';
            $analysis['recommendations'][] = 'Consider denormalization or caching for complex joins';
        }

        if (strpos($sql, 'group by') !== false && strpos($sql, 'having') === false) {
            $analysis['issues'][] = 'GROUP BY without proper indexing may be slow';
            $analysis['recommendations'][] = 'Ensure GROUP BY columns are indexed';
        }

        // Determine severity
        if ($query['time'] > 1000) {
            $analysis['severity'] = 'critical';
        } elseif ($query['time'] > 500) {
            $analysis['severity'] = 'high';
        } elseif ($query['time'] > 200) {
            $analysis['severity'] = 'medium';
        }

        return $analysis;
    }

    private function outputConsole(): void
    {
        $this->info("\n📊 Query Performance Analysis");
        $this->info('==============================');

        $totalQueries = count($this->queries);
        $totalTime = array_sum(array_column($this->queries, 'time'));
        $avgTime = $totalTime / max($totalQueries, 1);

        $this->info("\n📈 Overview:");
        $this->line("  Total queries: {$totalQueries}");
        $this->line('  Total time: '.round($totalTime, 2).'ms');
        $this->line('  Average time: '.round($avgTime, 2).'ms');
        $this->line('  Slow queries: '.count($this->slowQueries));

        if (! empty($this->slowQueries)) {
            $this->warn("\n⚠️  Slow Queries Detected:");
            foreach ($this->slowQueries as $index => $slowQuery) {
                $this->warn("\n".($index + 1).'. '.$this->getSeverityIcon($slowQuery['analysis']['severity']).' '.round($slowQuery['time'], 2).'ms');
                $this->line('   SQL: '.$this->truncateSql($slowQuery['sql']));

                if (! empty($slowQuery['analysis']['issues'])) {
                    $this->line('   Issues:');
                    foreach ($slowQuery['analysis']['issues'] as $issue) {
                        $this->line('     • '.$issue);
                    }
                }

                if (! empty($slowQuery['analysis']['recommendations'])) {
                    $this->line('   Recommendations:');
                    foreach ($slowQuery['analysis']['recommendations'] as $rec) {
                        $this->line('     ▶ '.$rec);
                    }
                }
            }
        } else {
            $this->info("\n✅ No slow queries detected!");
        }

        $this->generateSummaryRecommendations();
    }

    private function outputJson(): void
    {
        $output = [
            'timestamp' => now()->toISOString(),
            'analysis' => [
                'total_queries' => count($this->queries),
                'total_time' => array_sum(array_column($this->queries, 'time')),
                'average_time' => array_sum(array_column($this->queries, 'time')) / max(count($this->queries), 1),
                'slow_queries_count' => count($this->slowQueries),
                'slow_threshold' => $this->option('slow-threshold'),
            ],
            'slow_queries' => $this->slowQueries,
            'recommendations' => $this->generateGlobalRecommendations(),
            'performance_grade' => $this->calculateQueryGrade(),
        ];

        echo json_encode($output, JSON_PRETTY_PRINT);
    }

    private function getSeverityIcon(string $severity): string
    {
        switch ($severity) {
            case 'critical': return '🔴';
            case 'high': return '🟡';
            case 'medium': return '🟠';
            default: return '🟢';
        }
    }

    private function truncateSql(string $sql): string
    {
        return strlen($sql) > 100 ? substr($sql, 0, 100).'...' : $sql;
    }

    private function generateSummaryRecommendations(): void
    {
        $this->info("\n💡 Summary Recommendations:");

        $recommendations = $this->generateGlobalRecommendations();
        foreach ($recommendations as $rec) {
            $this->line('  • '.$rec);
        }
    }

    private function generateGlobalRecommendations(): array
    {
        $recommendations = [];

        if (count($this->slowQueries) > 0) {
            $recommendations[] = 'Review and optimize '.count($this->slowQueries).' slow queries';
        }

        $criticalQueries = array_filter($this->slowQueries, fn ($q) => $q['analysis']['severity'] === 'critical');
        if (count($criticalQueries) > 0) {
            $recommendations[] = 'Immediately address '.count($criticalQueries).' critical queries';
        }

        $selectStarQueries = array_filter($this->slowQueries, fn ($q) => in_array('Using SELECT * - should specify columns', $q['analysis']['issues'])
        );
        if (count($selectStarQueries) > 0) {
            $recommendations[] = 'Replace SELECT * with specific columns in '.count($selectStarQueries).' queries';
        }

        $likeQueries = array_filter($this->slowQueries, fn ($q) => array_filter($q['analysis']['issues'], fn ($issue) => strpos($issue, 'LIKE') !== false)
        );
        if (count($likeQueries) > 0) {
            $recommendations[] = 'Optimize LIKE queries with better indexing strategy';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Query performance is within acceptable ranges';
        }

        return $recommendations;
    }

    private function calculateQueryGrade(): string
    {
        $score = 100;

        $avgTime = array_sum(array_column($this->queries, 'time')) / max(count($this->queries), 1);
        if ($avgTime > 50) {
            $score -= 20;
        } elseif ($avgTime > 25) {
            $score -= 10;
        } elseif ($avgTime > 10) {
            $score -= 5;
        }

        $slowQueryRatio = count($this->slowQueries) / max(count($this->queries), 1);
        if ($slowQueryRatio > 0.5) {
            $score -= 30;
        } elseif ($slowQueryRatio > 0.3) {
            $score -= 20;
        } elseif ($slowQueryRatio > 0.1) {
            $score -= 10;
        }

        $criticalQueries = array_filter($this->slowQueries, fn ($q) => $q['analysis']['severity'] === 'critical');
        if (count($criticalQueries) > 0) {
            $score -= 25;
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
}
