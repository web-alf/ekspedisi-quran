<?php

namespace App\Console\Commands\Performance;

use Illuminate\Console\Command;

class GenerateReportCommand extends Command
{
    protected $signature = 'performance:generate-report 
                            {--format=markdown : Output format (markdown, json, html)}
                            {--output= : Output file path}
                            {--include-recommendations : Include recommendations in report}';

    protected $description = 'Generate comprehensive performance report';

    private $reportData = [];

    public function handle()
    {
        $this->info('📊 Generating Performance Report...');

        $this->collectReportData();
        $this->generateReport();

        return 0;
    }

    private function collectReportData(): void
    {
        $this->reportData = [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'summary' => $this->generateSummary(),
            'benchmark_results' => $this->loadBenchmarkResults(),
            'query_analysis' => $this->loadQueryAnalysis(),
            'budget_status' => $this->loadBudgetStatus(),
            'regression_analysis' => $this->loadRegressionAnalysis(),
            'recommendations' => $this->option('include-recommendations') ? $this->generateRecommendations() : [],
        ];
    }

    private function generateSummary(): array
    {
        // Load all available performance data
        $benchmark = $this->loadBenchmarkResults();
        $queries = $this->loadQueryAnalysis();
        $budgets = $this->loadBudgetStatus();

        $summary = [
            'overall_grade' => 'A',
            'performance_score' => 95,
            'critical_issues' => 0,
            'improvements_detected' => 0,
            'budget_violations' => 0,
            'key_metrics' => [],
        ];

        // Calculate overall grade based on available data
        if ($benchmark && isset($benchmark['performance_grade'])) {
            $summary['overall_grade'] = $benchmark['performance_grade'];
        }

        // Count budget violations
        if ($budgets && isset($budgets['violations'])) {
            $summary['budget_violations'] = count($budgets['violations']);
            $critical = array_filter($budgets['violations'], fn ($v) => $v['severity'] === 'critical');
            $summary['critical_issues'] += count($critical);
        }

        // Add key metrics
        if ($benchmark && isset($benchmark['results'])) {
            $results = $benchmark['results'];
            if (isset($results['database']['simple_count']['avg'])) {
                $summary['key_metrics']['simple_query_time'] = round($results['database']['simple_count']['avg'], 2);
            }
            if (isset($results['cache']['remember_hit']['avg'])) {
                $summary['key_metrics']['cache_hit_time'] = round($results['cache']['remember_hit']['avg'], 2);
            }
            if (isset($results['memory']['large_dataset_memory']['avg'])) {
                $summary['key_metrics']['memory_usage'] = round($results['memory']['large_dataset_memory']['avg'], 2);
            }
        }

        if ($queries && isset($queries['analysis'])) {
            $summary['key_metrics']['avg_query_time'] = round($queries['analysis']['average_time'] ?? 0, 2);
            $summary['key_metrics']['slow_queries'] = $queries['analysis']['slow_queries_count'] ?? 0;
        }

        return $summary;
    }

    private function loadBenchmarkResults(): ?array
    {
        $file = 'benchmark-results.json';

        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    private function loadQueryAnalysis(): ?array
    {
        $file = 'query-analysis.json';

        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    private function loadBudgetStatus(): ?array
    {
        // This would be populated by the budget check command
        $file = 'budget-check.json';

        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    private function loadRegressionAnalysis(): ?array
    {
        $file = 'regression-analysis.json';

        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    private function generateRecommendations(): array
    {
        $recommendations = [
            'immediate' => [],
            'next_sprint' => [],
            'long_term' => [],
        ];

        $budgets = $this->loadBudgetStatus();
        $queries = $this->loadQueryAnalysis();
        $benchmark = $this->loadBenchmarkResults();

        // Immediate actions based on critical issues
        if ($budgets && isset($budgets['violations'])) {
            $critical = array_filter($budgets['violations'], fn ($v) => $v['severity'] === 'critical');
            if (count($critical) > 0) {
                $recommendations['immediate'][] = 'Fix '.count($critical).' critical performance budget violations';
            }
        }

        if ($queries && isset($queries['slow_queries'])) {
            $criticalQueries = array_filter($queries['slow_queries'], fn ($q) => $q['analysis']['severity'] === 'critical');
            if (count($criticalQueries) > 0) {
                $recommendations['immediate'][] = 'Optimize '.count($criticalQueries).' critical slow queries';
            }
        }

        // Next sprint recommendations
        if ($benchmark && isset($benchmark['recommendations'])) {
            $recommendations['next_sprint'] = array_merge(
                $recommendations['next_sprint'],
                array_slice($benchmark['recommendations'], 0, 3)
            );
        }

        // Long-term recommendations
        $recommendations['long_term'][] = 'Implement continuous performance monitoring';
        $recommendations['long_term'][] = 'Set up automated performance regression testing';
        $recommendations['long_term'][] = 'Consider implementing query caching for expensive operations';

        return $recommendations;
    }

    private function generateReport(): void
    {
        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $content = $this->generateJsonReport();
                break;
            case 'html':
                $content = $this->generateHtmlReport();
                break;
            case 'markdown':
            default:
                $content = $this->generateMarkdownReport();
                break;
        }

        $outputFile = $this->option('output');
        if ($outputFile) {
            file_put_contents($outputFile, $content);
            $this->info("Report saved to: {$outputFile}");
        } else {
            echo $content;
        }
    }

    private function generateMarkdownReport(): string
    {
        $data = $this->reportData;
        $summary = $data['summary'];

        $report = '# Performance Report – '.now()->format('Y-m-d H:i:s')."\n\n";

        // Executive Summary
        $report .= "## Executive Summary\n\n";
        $report .= "| Metric | Value |\n";
        $report .= "|--------|-------|\n";
        $report .= "| Overall Grade | {$summary['overall_grade']} |\n";
        $report .= "| Critical Issues | {$summary['critical_issues']} |\n";
        $report .= "| Budget Violations | {$summary['budget_violations']} |\n";

        if (! empty($summary['key_metrics'])) {
            foreach ($summary['key_metrics'] as $metric => $value) {
                $unit = $this->getMetricUnit($metric);
                $report .= '| '.ucfirst(str_replace('_', ' ', $metric))." | {$value}{$unit} |\n";
            }
        }

        $report .= "\n";

        // Performance Results
        if ($data['benchmark_results']) {
            $report .= "## Performance Benchmark Results\n\n";
            $results = $data['benchmark_results']['results'] ?? [];

            foreach ($results as $category => $metrics) {
                $report .= '### '.ucfirst($category)." Performance\n\n";
                $report .= "| Metric | Average | Min | Max | Std Dev |\n";
                $report .= "|--------|---------|-----|-----|--------|\n";

                foreach ($metrics as $metric => $stats) {
                    $unit = $this->getMetricUnit($metric);
                    $report .= "| {$metric} | ".round($stats['avg'], 2).$unit.
                              ' | '.round($stats['min'], 2).$unit.
                              ' | '.round($stats['max'], 2).$unit.
                              ' | '.round($stats['std'], 2).$unit." |\n";
                }
                $report .= "\n";
            }
        }

        // Query Analysis
        if ($data['query_analysis']) {
            $report .= "## Query Performance Analysis\n\n";
            $analysis = $data['query_analysis']['analysis'] ?? [];

            $report .= '- **Total Queries**: '.($analysis['total_queries'] ?? 'N/A')."\n";
            $report .= '- **Average Time**: '.round($analysis['average_time'] ?? 0, 2)."ms\n";
            $report .= '- **Slow Queries**: '.($analysis['slow_queries_count'] ?? 0)."\n";
            $report .= '- **Query Grade**: '.($data['query_analysis']['performance_grade'] ?? 'N/A')."\n\n";
        }

        // Budget Status
        if ($data['budget_status'] && isset($data['budget_status']['violations'])) {
            $violations = $data['budget_status']['violations'];
            if (! empty($violations)) {
                $report .= "## Budget Violations\n\n";

                foreach ($violations as $violation) {
                    $severity = $this->getSeverityIcon($violation['severity']);
                    $report .= "- {$severity} **{$violation['description']}**: ".
                              $violation['actual_value']." (budget: {$violation['budget_limit']})\n";
                }
                $report .= "\n";
            }
        }

        // Recommendations
        if (! empty($data['recommendations'])) {
            $report .= "## Recommendations\n\n";

            if (! empty($data['recommendations']['immediate'])) {
                $report .= "### Immediate Actions\n";
                foreach ($data['recommendations']['immediate'] as $rec) {
                    $report .= "- 🚨 {$rec}\n";
                }
                $report .= "\n";
            }

            if (! empty($data['recommendations']['next_sprint'])) {
                $report .= "### Next Sprint\n";
                foreach ($data['recommendations']['next_sprint'] as $rec) {
                    $report .= "- 📋 {$rec}\n";
                }
                $report .= "\n";
            }

            if (! empty($data['recommendations']['long_term'])) {
                $report .= "### Long Term\n";
                foreach ($data['recommendations']['long_term'] as $rec) {
                    $report .= "- 🎯 {$rec}\n";
                }
                $report .= "\n";
            }
        }

        $report .= "---\n\n";
        $report .= '*Report generated on '.$data['timestamp']."*\n";

        return $report;
    }

    private function generateJsonReport(): string
    {
        return json_encode($this->reportData, JSON_PRETTY_PRINT);
    }

    private function generateHtmlReport(): string
    {
        $data = $this->reportData;
        $summary = $data['summary'];

        $html = "<!DOCTYPE html>\n<html>\n<head>\n";
        $html .= '<title>Performance Report - '.now()->format('Y-m-d H:i:s')."</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: Arial, sans-serif; margin: 20px; }\n";
        $html .= "table { border-collapse: collapse; width: 100%; margin: 20px 0; }\n";
        $html .= "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\n";
        $html .= "th { background-color: #f2f2f2; }\n";
        $html .= ".critical { color: #d32f2f; }\n";
        $html .= ".warning { color: #f57c00; }\n";
        $html .= ".success { color: #388e3c; }\n";
        $html .= ".grade-a { color: #4caf50; font-weight: bold; }\n";
        $html .= ".grade-b { color: #ff9800; font-weight: bold; }\n";
        $html .= ".grade-c { color: #f44336; font-weight: bold; }\n";
        $html .= "</style>\n</head>\n<body>\n";

        $html .= "<h1>Performance Report</h1>\n";
        $html .= '<p><strong>Generated:</strong> '.$data['timestamp']."</p>\n";
        $html .= '<p><strong>Environment:</strong> '.$data['environment']."</p>\n";

        // Executive Summary
        $html .= "<h2>Executive Summary</h2>\n";
        $html .= "<table>\n<tr><th>Metric</th><th>Value</th></tr>\n";
        $gradeClass = strtolower($summary['overall_grade']);
        $html .= "<tr><td>Overall Grade</td><td class=\"grade-{$gradeClass}\">{$summary['overall_grade']}</td></tr>\n";
        $html .= '<tr><td>Critical Issues</td><td'.($summary['critical_issues'] > 0 ? ' class="critical"' : '').">{$summary['critical_issues']}</td></tr>\n";
        $html .= '<tr><td>Budget Violations</td><td'.($summary['budget_violations'] > 0 ? ' class="warning"' : '').">{$summary['budget_violations']}</td></tr>\n";
        $html .= "</table>\n";

        // Add more sections as needed...

        $html .= "</body>\n</html>";

        return $html;
    }

    private function getMetricUnit(string $metric): string
    {
        if (strpos($metric, 'time') !== false) {
            return 'ms';
        }
        if (strpos($metric, 'memory') !== false) {
            return 'MB';
        }
        if (strpos($metric, 'queries') !== false) {
            return '';
        }

        return '';
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
}
