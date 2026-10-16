<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndexOptimizer
{
    private const SLOW_QUERY_THRESHOLD = 100; // 100ms

    private const HIGH_CARDINALITY_THRESHOLD = 0.8; // 80% unique values

    private const INDEX_ANALYSIS_SAMPLE_SIZE = 1000;

    private array $existingIndexes = [];

    private array $tableStats = [];

    private array $queryPatterns = [];

    /**
     * Analyze database for missing index opportunities
     */
    public function analyzeMissingIndexes(): array
    {
        $recommendations = [];

        // Get all tables in the database
        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            // Skip system tables
            if ($this->isSystemTable($table)) {
                continue;
            }

            // Analyze table for index opportunities
            $tableRecommendations = $this->analyzeTableIndexes($table);

            if (! empty($tableRecommendations)) {
                $recommendations[$table] = $tableRecommendations;
            }
        }

        return $recommendations;
    }

    /**
     * Analyze query patterns to suggest indexes
     */
    public function analyzeQueryPatternsForIndexes(array $queryLog): array
    {
        $indexSuggestions = [];

        foreach ($queryLog as $query) {
            if ($query['execution_time'] > self::SLOW_QUERY_THRESHOLD) {
                $suggestions = $this->analyzeSlowQueryForIndexes($query);
                $indexSuggestions = array_merge($indexSuggestions, $suggestions);
            }
        }

        return $this->consolidateIndexSuggestions($indexSuggestions);
    }

    /**
     * Generate specific index creation SQL statements
     */
    public function generateIndexCreationSQL(array $recommendations): array
    {
        $sqlStatements = [];

        foreach ($recommendations as $table => $indexes) {
            foreach ($indexes as $index) {
                $sql = $this->generateIndexSQL($table, $index);
                if ($sql) {
                    $sqlStatements[] = [
                        'table' => $table,
                        'index_name' => $index['index_name'],
                        'sql' => $sql,
                        'priority' => $index['priority'],
                        'estimated_impact' => $index['estimated_impact'],
                    ];
                }
            }
        }

        // Sort by priority
        usort($sqlStatements, function ($a, $b) {
            $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $aPriority = $priorityOrder[$a['priority']] ?? 0;
            $bPriority = $priorityOrder[$b['priority']] ?? 0;

            return $bPriority <=> $aPriority;
        });

        return $sqlStatements;
    }

    /**
     * Analyze existing indexes for redundancy and optimization
     */
    public function analyzeExistingIndexes(): array
    {
        $analysis = [];
        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            if ($this->isSystemTable($table)) {
                continue;
            }

            $indexes = $this->getTableIndexes($table);
            $tableAnalysis = $this->analyzeTableIndexOptimization($table, $indexes);

            if (! empty($tableAnalysis)) {
                $analysis[$table] = $tableAnalysis;
            }
        }

        return $analysis;
    }

    /**
     * Check index usage statistics
     */
    public function getIndexUsageStatistics(): array
    {
        $statistics = [];
        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            if ($this->isSystemTable($table)) {
                continue;
            }

            $indexes = $this->getTableIndexes($table);
            $usage = $this->analyzeIndexUsage($table, $indexes);

            if (! empty($usage)) {
                $statistics[$table] = $usage;
            }
        }

        return $statistics;
    }

    /**
     * Generate migration file for recommended indexes
     */
    public function generateIndexMigration(array $recommendations): string
    {
        $migrationName = 'add_performance_indexes_'.date('Y_m_d_His');
        $className = 'AddPerformanceIndexes'.date('YmdHis');

        $migration = "<?php\n\n";
        $migration .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $migration .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $migration .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $migration .= "return new class extends Migration\n{\n";
        $migration .= "    public function up()\n    {\n";

        foreach ($recommendations as $table => $indexes) {
            foreach ($indexes as $index) {
                $migration .= "        // {$index['reason']}\n";
                $migration .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";

                if ($index['type'] === 'single') {
                    $migration .= "            \$table->index('{$index['columns'][0]}', '{$index['index_name']}');\n";
                } elseif ($index['type'] === 'composite') {
                    $columns = implode("', '", $index['columns']);
                    $migration .= "            \$table->index(['{$columns}'], '{$index['index_name']}');\n";
                } elseif ($index['type'] === 'unique') {
                    $migration .= "            \$table->unique('{$index['columns'][0]}', '{$index['index_name']}');\n";
                }

                $migration .= "        });\n\n";
            }
        }

        $migration .= "    }\n\n";
        $migration .= "    public function down()\n    {\n";

        foreach ($recommendations as $table => $indexes) {
            foreach ($indexes as $index) {
                $migration .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";
                $migration .= "            \$table->dropIndex('{$index['index_name']}');\n";
                $migration .= "        });\n";
            }
        }

        $migration .= "    }\n";
        $migration .= "};\n";

        return $migration;
    }

    /**
     * Private helper methods
     */
    private function getAllTables(): array
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $tableColumn = 'Tables_in_'.config('database.connections.mysql.database');

            return array_map(function ($table) use ($tableColumn) {
                return $table->$tableColumn;
            }, $tables);
        } catch (\Exception $e) {
            Log::warning('Could not retrieve table list: '.$e->getMessage());

            return [];
        }
    }

    private function isSystemTable(string $table): bool
    {
        $systemTables = [
            'cache', 'cache_locks', 'failed_jobs', 'jobs', 'job_batches',
            'migrations', 'password_reset_tokens', 'sessions',
        ];

        return in_array($table, $systemTables);
    }

    private function analyzeTableIndexes(string $table): array
    {
        $recommendations = [];
        $existingIndexes = $this->getTableIndexes($table);
        $columns = $this->getTableColumns($table);

        // Analyze foreign key columns
        $fkRecommendations = $this->analyzeForeignKeyIndexes($table, $columns, $existingIndexes);
        $recommendations = array_merge($recommendations, $fkRecommendations);

        // Analyze frequently searched columns
        $searchRecommendations = $this->analyzeSearchColumnIndexes($table, $columns, $existingIndexes);
        $recommendations = array_merge($recommendations, $searchRecommendations);

        // Analyze date/timestamp columns
        $dateRecommendations = $this->analyzeDateColumnIndexes($table, $columns, $existingIndexes);
        $recommendations = array_merge($recommendations, $dateRecommendations);

        // Analyze status/enum columns
        $statusRecommendations = $this->analyzeStatusColumnIndexes($table, $columns, $existingIndexes);
        $recommendations = array_merge($recommendations, $statusRecommendations);

        return $recommendations;
    }

    private function getTableIndexes(string $table): array
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}`");
            $indexMap = [];

            foreach ($indexes as $index) {
                $indexName = $index->Key_name;
                if (! isset($indexMap[$indexName])) {
                    $indexMap[$indexName] = [
                        'name' => $indexName,
                        'unique' => $index->Non_unique == 0,
                        'columns' => [],
                    ];
                }
                $indexMap[$indexName]['columns'][] = $index->Column_name;
            }

            return array_values($indexMap);
        } catch (\Exception $e) {
            Log::warning("Could not retrieve indexes for table {$table}: ".$e->getMessage());

            return [];
        }
    }

    private function getTableColumns(string $table): array
    {
        try {
            $columns = DB::select("DESCRIBE `{$table}`");

            return array_map(function ($column) {
                return [
                    'name' => $column->Field,
                    'type' => $column->Type,
                    'null' => $column->Null === 'YES',
                    'key' => $column->Key,
                    'default' => $column->Default,
                    'extra' => $column->Extra,
                ];
            }, $columns);
        } catch (\Exception $e) {
            Log::warning("Could not retrieve columns for table {$table}: ".$e->getMessage());

            return [];
        }
    }

    private function analyzeForeignKeyIndexes(string $table, array $columns, array $existingIndexes): array
    {
        $recommendations = [];
        $existingIndexColumns = $this->getExistingIndexColumns($existingIndexes);

        foreach ($columns as $column) {
            // Look for foreign key patterns
            if (str_ends_with($column['name'], '_id') && ! in_array($column['name'], $existingIndexColumns)) {
                $recommendations[] = [
                    'type' => 'single',
                    'columns' => [$column['name']],
                    'index_name' => "idx_{$table}_{$column['name']}",
                    'reason' => "Foreign key column {$column['name']} should be indexed for JOIN performance",
                    'priority' => 'high',
                    'estimated_impact' => 'High - improves JOIN and WHERE clause performance',
                ];
            }
        }

        return $recommendations;
    }

    private function analyzeSearchColumnIndexes(string $table, array $columns, array $existingIndexes): array
    {
        $recommendations = [];
        $existingIndexColumns = $this->getExistingIndexColumns($existingIndexes);

        $searchableColumns = ['name', 'nama', 'email', 'phone', 'alamat', 'kode', 'no_resi'];

        foreach ($columns as $column) {
            $columnName = $column['name'];

            // Check if this looks like a searchable column
            $isSearchable = false;
            foreach ($searchableColumns as $pattern) {
                if (str_contains($columnName, $pattern)) {
                    $isSearchable = true;
                    break;
                }
            }

            if ($isSearchable && ! in_array($columnName, $existingIndexColumns)) {
                $cardinality = $this->estimateColumnCardinality($table, $columnName);

                if ($cardinality > self::HIGH_CARDINALITY_THRESHOLD) {
                    $recommendations[] = [
                        'type' => 'single',
                        'columns' => [$columnName],
                        'index_name' => "idx_{$table}_{$columnName}",
                        'reason' => "Searchable column {$columnName} with high cardinality should be indexed",
                        'priority' => 'medium',
                        'estimated_impact' => 'Medium - improves search and filter performance',
                    ];
                }
            }
        }

        return $recommendations;
    }

    private function analyzeDateColumnIndexes(string $table, array $columns, array $existingIndexes): array
    {
        $recommendations = [];
        $existingIndexColumns = $this->getExistingIndexColumns($existingIndexes);

        foreach ($columns as $column) {
            $columnType = strtolower($column['type']);
            $columnName = $column['name'];

            // Check for date/timestamp columns
            if (str_contains($columnType, 'timestamp') || str_contains($columnType, 'datetime') || str_contains($columnType, 'date')) {
                if (! in_array($columnName, $existingIndexColumns)) {
                    $priority = ($columnName === 'created_at' || $columnName === 'updated_at') ? 'high' : 'medium';

                    $recommendations[] = [
                        'type' => 'single',
                        'columns' => [$columnName],
                        'index_name' => "idx_{$table}_{$columnName}",
                        'reason' => "Date/timestamp column {$columnName} commonly used in range queries",
                        'priority' => $priority,
                        'estimated_impact' => 'High - improves date range and sorting performance',
                    ];
                }
            }
        }

        return $recommendations;
    }

    private function analyzeStatusColumnIndexes(string $table, array $columns, array $existingIndexes): array
    {
        $recommendations = [];
        $existingIndexColumns = $this->getExistingIndexColumns($existingIndexes);

        foreach ($columns as $column) {
            $columnName = $column['name'];

            // Look for status-like columns
            if (str_contains($columnName, 'status') || str_contains($columnName, 'active') ||
                str_contains($columnName, 'enabled') || str_contains($columnName, 'type')) {

                if (! in_array($columnName, $existingIndexColumns)) {
                    $cardinality = $this->estimateColumnCardinality($table, $columnName);

                    // Status columns typically have low cardinality but are frequently filtered
                    if ($cardinality < 0.5) { // Less than 50% unique values
                        $recommendations[] = [
                            'type' => 'single',
                            'columns' => [$columnName],
                            'index_name' => "idx_{$table}_{$columnName}",
                            'reason' => "Status column {$columnName} frequently used in WHERE clauses",
                            'priority' => 'medium',
                            'estimated_impact' => 'Medium - improves filtering by status',
                        ];
                    }
                }
            }
        }

        return $recommendations;
    }

    private function analyzeSlowQueryForIndexes(array $query): array
    {
        $suggestions = [];
        $sql = strtolower($query['sql']);

        // Analyze WHERE clauses
        if (preg_match_all('/where\s+(\w+)\.(\w+)\s*[=<>!]/', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = $match[1];
                $column = $match[2];

                $suggestions[] = [
                    'table' => $table,
                    'column' => $column,
                    'reason' => 'WHERE clause in slow query',
                    'query_time' => $query['execution_time'],
                    'priority' => $query['execution_time'] > 500 ? 'high' : 'medium',
                ];
            }
        }

        // Analyze JOIN conditions
        if (preg_match_all('/join\s+(\w+)\s+(?:\w+\s+)?on\s+\w+\.(\w+)\s*=\s*\w+\.(\w+)/', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = $match[1];
                $column = $match[2];

                $suggestions[] = [
                    'table' => $table,
                    'column' => $column,
                    'reason' => 'JOIN condition in slow query',
                    'query_time' => $query['execution_time'],
                    'priority' => 'high',
                ];
            }
        }

        // Analyze ORDER BY clauses
        if (preg_match_all('/order\s+by\s+(\w+)\.(\w+)/', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = $match[1];
                $column = $match[2];

                $suggestions[] = [
                    'table' => $table,
                    'column' => $column,
                    'reason' => 'ORDER BY clause in slow query',
                    'query_time' => $query['execution_time'],
                    'priority' => 'medium',
                ];
            }
        }

        return $suggestions;
    }

    private function estimateColumnCardinality(string $table, string $column): float
    {
        try {
            $total = DB::table($table)->count();
            if ($total === 0) {
                return 0;
            }

            $distinct = DB::table($table)->distinct($column)->count($column);

            return $distinct / $total;
        } catch (\Exception $e) {
            Log::warning("Could not estimate cardinality for {$table}.{$column}: ".$e->getMessage());

            return 0.5; // Default moderate cardinality
        }
    }

    private function getExistingIndexColumns(array $indexes): array
    {
        $columns = [];
        foreach ($indexes as $index) {
            $columns = array_merge($columns, $index['columns']);
        }

        return array_unique($columns);
    }

    private function consolidateIndexSuggestions(array $suggestions): array
    {
        $consolidated = [];

        foreach ($suggestions as $suggestion) {
            $key = $suggestion['table'].'.'.$suggestion['column'];

            if (! isset($consolidated[$key])) {
                $consolidated[$key] = [
                    'table' => $suggestion['table'],
                    'column' => $suggestion['column'],
                    'reasons' => [$suggestion['reason']],
                    'query_count' => 1,
                    'max_query_time' => $suggestion['query_time'],
                    'priority' => $suggestion['priority'],
                ];
            } else {
                $consolidated[$key]['reasons'][] = $suggestion['reason'];
                $consolidated[$key]['query_count']++;
                $consolidated[$key]['max_query_time'] = max(
                    $consolidated[$key]['max_query_time'],
                    $suggestion['query_time']
                );

                // Upgrade priority if needed
                $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
                $currentPriority = $priorityOrder[$consolidated[$key]['priority']] ?? 0;
                $newPriority = $priorityOrder[$suggestion['priority']] ?? 0;

                if ($newPriority > $currentPriority) {
                    $consolidated[$key]['priority'] = $suggestion['priority'];
                }
            }
        }

        return array_values($consolidated);
    }

    private function generateIndexSQL(string $table, array $index): string
    {
        switch ($index['type']) {
            case 'single':
                return "CREATE INDEX {$index['index_name']} ON `{$table}` (`{$index['columns'][0]}`);";

            case 'composite':
                $columns = implode('`, `', $index['columns']);

                return "CREATE INDEX {$index['index_name']} ON `{$table}` (`{$columns}`);";

            case 'unique':
                return "CREATE UNIQUE INDEX {$index['index_name']} ON `{$table}` (`{$index['columns'][0]}`);";

            default:
                return null;
        }
    }

    private function analyzeTableIndexOptimization(string $table, array $indexes): array
    {
        $analysis = [];

        foreach ($indexes as $index) {
            if ($index['name'] === 'PRIMARY') {
                continue; // Skip primary key analysis
            }

            // Check for redundant indexes
            $redundancy = $this->checkIndexRedundancy($table, $index, $indexes);
            if ($redundancy) {
                $analysis[] = [
                    'type' => 'redundant_index',
                    'index_name' => $index['name'],
                    'issue' => $redundancy,
                    'recommendation' => 'Consider dropping this redundant index',
                ];
            }

            // Check for unused indexes (would need actual usage statistics)
            // This is a simplified heuristic
            if (count($index['columns']) > 3) {
                $analysis[] = [
                    'type' => 'complex_index',
                    'index_name' => $index['name'],
                    'issue' => 'Index has many columns which might indicate over-indexing',
                    'recommendation' => 'Review if all columns in this composite index are necessary',
                ];
            }
        }

        return $analysis;
    }

    private function checkIndexRedundancy(string $table, array $targetIndex, array $allIndexes): ?string
    {
        foreach ($allIndexes as $index) {
            if ($index['name'] === $targetIndex['name']) {
                continue;
            }

            // Check if target index is a prefix of another index
            if (count($targetIndex['columns']) < count($index['columns'])) {
                $isPrefix = true;
                for ($i = 0; $i < count($targetIndex['columns']); $i++) {
                    if ($targetIndex['columns'][$i] !== $index['columns'][$i]) {
                        $isPrefix = false;
                        break;
                    }
                }

                if ($isPrefix) {
                    return "This index is redundant - covered by index '{$index['name']}'";
                }
            }
        }

        return null;
    }

    private function analyzeIndexUsage(string $table, array $indexes): array
    {
        // This would require access to MySQL's performance schema
        // For now, return basic analysis
        $usage = [];

        foreach ($indexes as $index) {
            $usage[] = [
                'index_name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'estimated_selectivity' => $this->estimateIndexSelectivity($table, $index['columns']),
            ];
        }

        return $usage;
    }

    private function estimateIndexSelectivity(string $table, array $columns): float
    {
        try {
            $total = DB::table($table)->count();
            if ($total === 0) {
                return 0;
            }

            // For composite indexes, use the first column as an approximation
            $column = $columns[0];
            $distinct = DB::table($table)->distinct($column)->count($column);

            return $distinct / $total;
        } catch (\Exception $e) {
            return 0.5; // Default selectivity
        }
    }

    /**
     * Get comprehensive index analysis report
     */
    public function getComprehensiveAnalysis(): array
    {
        return [
            'missing_indexes' => $this->analyzeMissingIndexes(),
            'existing_index_analysis' => $this->analyzeExistingIndexes(),
            'index_usage_statistics' => $this->getIndexUsageStatistics(),
            'recommendations_summary' => $this->getRecommendationsSummary(),
            'analysis_timestamp' => now()->toISOString(),
        ];
    }

    private function getRecommendationsSummary(): array
    {
        $missingIndexes = $this->analyzeMissingIndexes();

        $totalRecommendations = 0;
        $priorityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($missingIndexes as $table => $indexes) {
            foreach ($indexes as $index) {
                $totalRecommendations++;
                $priorityCounts[$index['priority']]++;
            }
        }

        return [
            'total_recommendations' => $totalRecommendations,
            'priority_breakdown' => $priorityCounts,
            'affected_tables' => count($missingIndexes),
            'estimated_performance_impact' => $this->estimateOverallPerformanceImpact($missingIndexes),
        ];
    }

    private function estimateOverallPerformanceImpact(array $missingIndexes): string
    {
        $totalRecommendations = 0;
        $highPriorityCount = 0;

        foreach ($missingIndexes as $table => $indexes) {
            foreach ($indexes as $index) {
                $totalRecommendations++;
                if (in_array($index['priority'], ['critical', 'high'])) {
                    $highPriorityCount++;
                }
            }
        }

        if ($highPriorityCount > 5) {
            return 'High - Significant performance improvements expected';
        }
        if ($highPriorityCount > 2) {
            return 'Medium - Noticeable performance improvements expected';
        }
        if ($totalRecommendations > 0) {
            return 'Low - Minor performance improvements expected';
        }

        return 'Minimal - Database appears well-indexed';
    }
}
