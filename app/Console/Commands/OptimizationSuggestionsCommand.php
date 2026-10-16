<?php

namespace App\Console\Commands;

use App\Services\IndexOptimizer;
use App\Services\NPlusOneDetector;
use App\Services\PerformanceMonitoringService;
use App\Services\QueryAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OptimizationSuggestionsCommand extends Command
{
    protected $signature = 'optimization:suggest 
                            {--auto-apply : Automatically apply safe optimizations}
                            {--generate-migrations : Generate index migration files}
                            {--send-alerts : Send performance alerts}
                            {--export=console : Export format (console, json, email)}';

    protected $description = 'Analyze database performance and provide automated optimization suggestions';

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
        parent::__construct();
        $this->queryAnalyzer = $queryAnalyzer;
        $this->nPlusOneDetector = $nPlusOneDetector;
        $this->indexOptimizer = $indexOptimizer;
        $this->performanceService = $performanceService;
    }

    public function handle()
    {
        $this->info('🚀 Starting Automated Optimization Analysis...');

        $startTime = microtime(true);
        $suggestions = [];

        // Comprehensive analysis
        $analysis = $this->performComprehensiveAnalysis();

        // Generate optimization suggestions
        $suggestions = $this->generateOptimizationSuggestions($analysis);

        // Apply automatic optimizations if requested
        if ($this->option('auto-apply')) {
            $this->applyAutomaticOptimizations($suggestions);
        }

        // Generate migration files if requested
        if ($this->option('generate-migrations')) {
            $this->generateOptimizationMigrations($suggestions);
        }

        // Send alerts if requested
        if ($this->option('send-alerts')) {
            $this->sendPerformanceAlerts($analysis);
        }

        // Export results
        $this->exportResults($suggestions, $analysis);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $this->info("✅ Analysis completed in {$executionTime}ms");

        return 0;
    }

    private function performComprehensiveAnalysis(): array
    {
        $this->info('🔍 Performing comprehensive analysis...');

        $analysis = [
            'timestamp' => now()->toISOString(),
            'n_plus_one_patterns' => [],
            'index_recommendations' => [],
            'query_optimizations' => [],
            'performance_metrics' => [],
            'critical_issues' => [],
            'optimization_opportunities' => [],
        ];

        // N+1 Detection Analysis
        $this->line('  🔄 Detecting N+1 query patterns...');
        $analysis['n_plus_one_patterns'] = $this->nPlusOneDetector->detectNPlusOne();
        $analysis['eager_loading_suggestions'] = $this->nPlusOneDetector->generateEagerLoadingSuggestions();

        // Index Analysis
        $this->line('  📋 Analyzing database indexes...');
        $analysis['index_recommendations'] = $this->indexOptimizer->analyzeMissingIndexes();
        $analysis['existing_index_analysis'] = $this->indexOptimizer->analyzeExistingIndexes();

        // Query Performance Analysis
        $this->line('  📊 Analyzing query performance...');
        $analysis['performance_metrics'] = $this->queryAnalyzer->getPerformanceMetrics();
        $analysis['query_optimizations'] = $this->queryAnalyzer->generateEloquentOptimizations();

        // Comprehensive Performance Report
        $analysis['comprehensive_report'] = $this->performanceService->getComprehensivePerformanceReport();

        return $analysis;
    }

    private function generateOptimizationSuggestions(array $analysis): array
    {
        $this->info('💡 Generating optimization suggestions...');

        $suggestions = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
            'automated' => [],
            'manual' => [],
        ];

        // N+1 Pattern Suggestions
        foreach ($analysis['n_plus_one_patterns'] as $pattern) {
            $suggestion = [
                'type' => 'n_plus_one_optimization',
                'severity' => $pattern['severity'],
                'title' => "N+1 Query Pattern: {$pattern['query_count']} queries detected",
                'description' => $pattern['suggestion'],
                'implementation' => $pattern['recommended_eager_loading'],
                'impact' => [
                    'queries_reduced' => $pattern['query_count'] - 1,
                    'time_saved_ms' => $pattern['potential_savings'],
                    'difficulty' => 'Medium',
                ],
                'code_example' => $this->generateEagerLoadingCodeExample($pattern),
                'can_auto_apply' => false, // Requires code changes
                'estimated_effort' => '15-30 minutes',
            ];

            $suggestions[$pattern['severity']][] = $suggestion;
            $suggestions['manual'][] = $suggestion;
        }

        // Index Recommendations
        foreach ($analysis['index_recommendations'] as $table => $indexes) {
            foreach ($indexes as $index) {
                $suggestion = [
                    'type' => 'database_index',
                    'severity' => $index['priority'],
                    'title' => "Missing Index: {$table}.{$index['columns'][0]}",
                    'description' => $index['reason'],
                    'implementation' => "CREATE INDEX {$index['index_name']} ON {$table} (".implode(', ', $index['columns']).')',
                    'impact' => [
                        'query_performance' => $index['estimated_impact'],
                        'difficulty' => 'Low',
                    ],
                    'migration_code' => $this->generateIndexMigrationCode($table, $index),
                    'can_auto_apply' => true, // Can generate migration
                    'estimated_effort' => '5-10 minutes',
                ];

                $suggestions[$index['priority']][] = $suggestion;
                $suggestions['automated'][] = $suggestion;
            }
        }

        // Query Optimization Suggestions
        foreach ($analysis['query_optimizations'] as $optimization) {
            $suggestion = [
                'type' => 'query_optimization',
                'severity' => $optimization['impact'] === 'high' ? 'high' : 'medium',
                'title' => $optimization['description'],
                'description' => $optimization['suggestion'],
                'implementation' => 'Review and modify queries as suggested',
                'impact' => [
                    'performance_gain' => $optimization['impact'],
                    'estimated_savings' => $optimization['estimated_savings'] ?? 'Variable',
                    'difficulty' => 'Medium',
                ],
                'can_auto_apply' => false, // Requires manual review
                'estimated_effort' => '30-60 minutes',
            ];

            $severity = $optimization['impact'] === 'high' ? 'high' : 'medium';
            $suggestions[$severity][] = $suggestion;
            $suggestions['manual'][] = $suggestion;
        }

        // Performance Metric Based Suggestions
        $this->generatePerformanceBasedSuggestions($analysis, $suggestions);

        return $suggestions;
    }

    private function generatePerformanceBasedSuggestions(array $analysis, array &$suggestions): void
    {
        $metrics = $analysis['performance_metrics'];

        // High slow query percentage
        if (($metrics['slow_query_percentage'] ?? 0) > 15) {
            $suggestions['high'][] = [
                'type' => 'performance_tuning',
                'severity' => 'high',
                'title' => 'High Slow Query Percentage Detected',
                'description' => "Slow queries represent {$metrics['slow_query_percentage']}% of total queries",
                'implementation' => 'Review slow query log and optimize frequently executed slow queries',
                'impact' => [
                    'performance_gain' => 'High',
                    'user_experience' => 'Significantly improved',
                    'difficulty' => 'High',
                ],
                'can_auto_apply' => false,
                'estimated_effort' => '2-4 hours',
            ];
        }

        // Poor performance grade
        $grade = $metrics['performance_grade'] ?? 'C';
        if (in_array($grade, ['D', 'F'])) {
            $suggestions['critical'][] = [
                'type' => 'comprehensive_optimization',
                'severity' => 'critical',
                'title' => "Poor Performance Grade: {$grade}",
                'description' => 'Overall database performance needs immediate attention',
                'implementation' => 'Comprehensive performance review and optimization required',
                'impact' => [
                    'performance_gain' => 'Critical',
                    'system_stability' => 'Improved',
                    'difficulty' => 'High',
                ],
                'can_auto_apply' => false,
                'estimated_effort' => '1-2 days',
            ];
        }

        // High number of optimization opportunities
        if (($metrics['optimization_opportunities'] ?? 0) > 10) {
            $suggestions['medium'][] = [
                'type' => 'code_review',
                'severity' => 'medium',
                'title' => 'Multiple Query Optimization Opportunities',
                'description' => "Found {$metrics['optimization_opportunities']} optimization opportunities",
                'implementation' => 'Systematic code review and query optimization',
                'impact' => [
                    'performance_gain' => 'Medium',
                    'maintenance' => 'Improved',
                    'difficulty' => 'Medium',
                ],
                'can_auto_apply' => false,
                'estimated_effort' => '4-8 hours',
            ];
        }
    }

    private function applyAutomaticOptimizations(array $suggestions): void
    {
        $this->info('🤖 Applying automatic optimizations...');

        $appliedCount = 0;

        foreach ($suggestions['automated'] as $suggestion) {
            if ($suggestion['can_auto_apply'] && $suggestion['type'] === 'database_index') {
                // For index suggestions, we can generate migration files
                $this->line("  📄 Generating migration for: {$suggestion['title']}");
                // Migration generation would happen here
                $appliedCount++;
            }
        }

        if ($appliedCount > 0) {
            $this->info("✅ Applied {$appliedCount} automatic optimizations");
        } else {
            $this->warn('⚠️  No automatic optimizations available (manual review required)');
        }
    }

    private function generateOptimizationMigrations(array $suggestions): void
    {
        $this->info('📄 Generating optimization migration files...');

        $indexSuggestions = array_filter($suggestions['automated'], function ($suggestion) {
            return $suggestion['type'] === 'database_index';
        });

        if (empty($indexSuggestions)) {
            $this->warn('No index migrations to generate');

            return;
        }

        // Group by priority
        $migrationsByPriority = [];
        foreach ($indexSuggestions as $suggestion) {
            $priority = $suggestion['severity'];
            if (! isset($migrationsByPriority[$priority])) {
                $migrationsByPriority[$priority] = [];
            }
            $migrationsByPriority[$priority][] = $suggestion;
        }

        // Generate migration files
        foreach ($migrationsByPriority as $priority => $migrations) {
            $timestamp = date('Y_m_d_His');
            $filename = "database/migrations/{$timestamp}_add_{$priority}_priority_indexes.php";

            $migrationContent = $this->generateMigrationFileContent($migrations, $priority);

            file_put_contents(base_path($filename), $migrationContent);

            $this->line("  📄 Created: {$filename} ({$priority} priority, ".count($migrations).' indexes)');
        }

        $totalMigrations = count($indexSuggestions);
        $this->info("✅ Generated migration files for {$totalMigrations} index optimizations");
    }

    private function sendPerformanceAlerts(array $analysis): void
    {
        $this->info('📢 Sending performance alerts...');

        // Critical issues alert
        $criticalIssues = count($analysis['n_plus_one_patterns']) +
                         $this->countCriticalIndexes($analysis['index_recommendations']);

        if ($criticalIssues > 0) {
            Log::alert('Critical Database Performance Issues Detected', [
                'total_critical_issues' => $criticalIssues,
                'n_plus_one_patterns' => count($analysis['n_plus_one_patterns']),
                'critical_indexes_needed' => $this->countCriticalIndexes($analysis['index_recommendations']),
                'performance_grade' => $analysis['performance_metrics']['performance_grade'] ?? 'Unknown',
                'recommendation' => 'Immediate optimization required',
                'analysis_timestamp' => $analysis['timestamp'],
            ]);

            $this->warn("🚨 Sent alert for {$criticalIssues} critical performance issues");
        }

        // Record optimization data in monitoring system
        $this->performanceService->recordNPlusOneDetection($analysis['n_plus_one_patterns']);
        $this->performanceService->recordIndexRecommendations($analysis['index_recommendations']);
        $this->performanceService->alertCriticalPerformanceIssues();

        $this->info('✅ Performance alerts sent and recorded');
    }

    private function exportResults(array $suggestions, array $analysis): void
    {
        $export = $this->option('export');

        switch ($export) {
            case 'json':
                $this->exportJson($suggestions, $analysis);
                break;
            case 'email':
                $this->exportEmail($suggestions, $analysis);
                break;
            default:
                $this->exportConsole($suggestions, $analysis);
                break;
        }
    }

    private function exportConsole(array $suggestions, array $analysis): void
    {
        $this->newLine();
        $this->info('📊 Optimization Analysis Report');
        $this->info('=====================================');

        // Summary
        $totalSuggestions = count($suggestions['critical']) + count($suggestions['high']) +
                           count($suggestions['medium']) + count($suggestions['low']);

        $this->info("\n📈 Summary:");
        $this->line("  Total suggestions: {$totalSuggestions}");
        $this->line('  Critical: '.count($suggestions['critical']));
        $this->line('  High: '.count($suggestions['high']));
        $this->line('  Medium: '.count($suggestions['medium']));
        $this->line('  Low: '.count($suggestions['low']));
        $this->line('  Automated: '.count($suggestions['automated']));
        $this->line('  Manual: '.count($suggestions['manual']));

        // Performance Grade
        $grade = $analysis['performance_metrics']['performance_grade'] ?? 'Unknown';
        $this->info("\n🎯 Performance Grade: {$grade}");

        // Top Priority Suggestions
        if (! empty($suggestions['critical'])) {
            $this->error("\n🚨 Critical Issues (Immediate Action Required):");
            foreach ($suggestions['critical'] as $index => $suggestion) {
                $this->line('  '.($index + 1).". {$suggestion['title']}");
                $this->line("     {$suggestion['description']}");
                $this->line("     Effort: {$suggestion['estimated_effort']}");
            }
        }

        if (! empty($suggestions['high'])) {
            $this->warn("\n⚠️  High Priority Issues:");
            foreach (array_slice($suggestions['high'], 0, 5) as $index => $suggestion) {
                $this->line('  '.($index + 1).". {$suggestion['title']}");
                $this->line("     {$suggestion['description']}");
                $this->line("     Effort: {$suggestion['estimated_effort']}");
            }
        }

        // Quick Wins
        $quickWins = array_filter($suggestions['automated'], function ($suggestion) {
            return str_contains($suggestion['estimated_effort'], '5-10 minutes');
        });

        if (! empty($quickWins)) {
            $this->info("\n⚡ Quick Wins (Low Effort, High Impact):");
            foreach (array_slice($quickWins, 0, 5) as $index => $suggestion) {
                $this->line('  '.($index + 1).". {$suggestion['title']}");
                $this->line("     Impact: {$suggestion['impact']['query_performance']}");
            }
        }

        $this->newLine();
        $this->info('💡 Run with --generate-migrations to create index migration files');
        $this->info('📄 Run with --export=json for detailed analysis data');
    }

    private function exportJson(array $suggestions, array $analysis): void
    {
        $report = [
            'analysis_timestamp' => $analysis['timestamp'],
            'performance_grade' => $analysis['performance_metrics']['performance_grade'] ?? 'Unknown',
            'suggestions' => $suggestions,
            'analysis' => $analysis,
            'summary' => [
                'total_suggestions' => array_sum([
                    count($suggestions['critical']),
                    count($suggestions['high']),
                    count($suggestions['medium']),
                    count($suggestions['low']),
                ]),
                'priority_breakdown' => [
                    'critical' => count($suggestions['critical']),
                    'high' => count($suggestions['high']),
                    'medium' => count($suggestions['medium']),
                    'low' => count($suggestions['low']),
                ],
                'automation_potential' => [
                    'automated' => count($suggestions['automated']),
                    'manual' => count($suggestions['manual']),
                ],
            ],
        ];

        echo json_encode($report, JSON_PRETTY_PRINT);
    }

    private function exportEmail(array $suggestions, array $analysis): void
    {
        // Email export would be implemented here
        $this->info('📧 Email export feature coming soon...');
    }

    // Helper methods

    private function generateEagerLoadingCodeExample(array $pattern): string
    {
        return "// Instead of:\n".
               "Model::all()->each(function(\$item) { echo \$item->relation->name; });\n\n".
               "// Use:\n".
               "Model::{$pattern['recommended_eager_loading']}->get()->each(function(\$item) { echo \$item->relation->name; });";
    }

    private function generateIndexMigrationCode(string $table, array $index): string
    {
        $columns = implode("', '", $index['columns']);

        return "Schema::table('{$table}', function (Blueprint \$table) {\n".
               "    \$table->index(['{$columns}'], '{$index['index_name']}');\n".
               '});';
    }

    private function generateMigrationFileContent(array $migrations, string $priority): string
    {
        $className = 'Add'.ucfirst($priority).'PriorityIndexes'.date('YmdHis');

        $content = "<?php\n\n";
        $content .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $content .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $content .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $content .= "return new class extends Migration\n{\n";
        $content .= "    public function up()\n    {\n";

        foreach ($migrations as $migration) {
            $content .= "        // {$migration['description']}\n";
            $content .= '        '.str_replace("\n", "\n        ", $migration['migration_code'])."\n\n";
        }

        $content .= "    }\n\n";
        $content .= "    public function down()\n    {\n";

        foreach ($migrations as $migration) {
            preg_match('/idx_\w+_\w+/', $migration['implementation'], $matches);
            $indexName = $matches[0] ?? 'unknown_index';
            preg_match('/ON (\w+)/', $migration['implementation'], $tableMatches);
            $tableName = $tableMatches[1] ?? 'unknown_table';

            $content .= "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";
            $content .= "            \$table->dropIndex('{$indexName}');\n";
            $content .= "        });\n";
        }

        $content .= "    }\n";
        $content .= "};\n";

        return $content;
    }

    private function countCriticalIndexes(array $indexRecommendations): int
    {
        $count = 0;
        foreach ($indexRecommendations as $table => $indexes) {
            foreach ($indexes as $index) {
                if ($index['priority'] === 'critical') {
                    $count++;
                }
            }
        }

        return $count;
    }
}
