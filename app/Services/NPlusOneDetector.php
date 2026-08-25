<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class NPlusOneDetector
{
    private const DETECTION_THRESHOLD = 3; // Minimum queries to consider as N+1

    private const TIME_WINDOW = 5; // Time window in seconds to group queries

    private const MAX_TRACKED_PATTERNS = 100; // Maximum patterns to track in memory

    private array $queryGroups = [];

    private array $detectedPatterns = [];

    private array $eagerLoadingSuggestions = [];

    /**
     * Track a query execution for N+1 detection
     */
    public function trackQuery(QueryExecuted $query): void
    {
        $pattern = $this->extractQueryPattern($query->sql);
        $timestamp = microtime(true);

        // Initialize pattern tracking if not exists
        if (! isset($this->queryGroups[$pattern])) {
            $this->queryGroups[$pattern] = [
                'queries' => [],
                'first_seen' => $timestamp,
                'last_seen' => $timestamp,
                'total_time' => 0,
                'original_sql' => $query->sql,
            ];
        }

        // Add query to pattern group
        $this->queryGroups[$pattern]['queries'][] = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'timestamp' => $timestamp,
            'connection' => $query->connectionName,
        ];

        $this->queryGroups[$pattern]['last_seen'] = $timestamp;
        $this->queryGroups[$pattern]['total_time'] += $query->time;

        // Check for N+1 pattern
        $this->checkForNPlusOnePattern($pattern);

        // Clean up old patterns to prevent memory leaks
        $this->cleanupOldPatterns();
    }

    /**
     * Detect N+1 query patterns in real-time
     */
    public function detectNPlusOne(): array
    {
        $detectedPatterns = [];

        foreach ($this->queryGroups as $pattern => $group) {
            $queryCount = count($group['queries']);

            // Check if this looks like an N+1 pattern
            if ($this->isNPlusOnePattern($group, $queryCount)) {
                $suggestion = $this->generateEagerLoadingSuggestion($pattern, $group);

                $detectedPatterns[] = [
                    'pattern' => $pattern,
                    'query_count' => $queryCount,
                    'total_time' => round($group['total_time'], 2),
                    'average_time' => round($group['total_time'] / $queryCount, 2),
                    'time_span' => round($group['last_seen'] - $group['first_seen'], 2),
                    'example_sql' => $group['original_sql'],
                    'suggestion' => $suggestion,
                    'severity' => $this->calculateSeverity($queryCount, $group['total_time']),
                    'potential_savings' => $this->calculatePotentialSavings($group),
                    'affected_models' => $this->extractAffectedModels($pattern),
                    'recommended_eager_loading' => $this->getRecommendedEagerLoading($pattern),
                ];
            }
        }

        return $detectedPatterns;
    }

    /**
     * Generate specific eager loading suggestions for detected patterns
     */
    public function generateEagerLoadingSuggestions(): array
    {
        $suggestions = [];
        $nPlusOnePatterns = $this->detectNPlusOne();

        foreach ($nPlusOnePatterns as $pattern) {
            $modelName = $this->extractModelFromPattern($pattern['pattern']);
            $relationship = $this->extractRelationshipFromPattern($pattern['pattern']);

            if ($modelName && $relationship) {
                $suggestions[] = [
                    'model' => $modelName,
                    'relationship' => $relationship,
                    'current_code' => $this->generateCurrentCodeExample($modelName),
                    'optimized_code' => $this->generateOptimizedCodeExample($modelName, $relationship),
                    'impact' => [
                        'queries_reduced' => $pattern['query_count'] - 1,
                        'time_saved_ms' => round($pattern['potential_savings'], 2),
                        'memory_impact' => 'Reduced by avoiding multiple small queries',
                    ],
                    'implementation_notes' => $this->getImplementationNotes($modelName, $relationship),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get relationship mapping suggestions for Eloquent models
     */
    public function getRelationshipMappings(): array
    {
        $mappings = [
            'pengiriman' => [
                'belongs_to' => ['donatur', 'jenis_quran', 'status_pengiriman', 'mushaf_request'],
                'has_many' => ['tracking_history', 'status_histories', 'packing_items'],
                'has_one' => ['sertifikat'],
            ],
            'donatur' => [
                'has_many' => ['pengiriman', 'wakaf_batches', 'wakaf_items'],
                'has_many_through' => ['sertifikat'],
            ],
            'wakaf_batch' => [
                'belongs_to' => ['donatur'],
                'has_many' => ['wakaf_items', 'pengiriman'],
                'has_one' => ['sertifikat'],
            ],
            'daily_packing_task' => [
                'belongs_to' => ['user'],
                'has_many' => ['daily_packing_task_items', 'packing_boxes'],
            ],
            'packing_box' => [
                'belongs_to' => ['daily_packing_task', 'jenis_quran'],
                'has_many' => ['packing_items'],
            ],
            'packing_item' => [
                'belongs_to' => ['packing_box', 'pengiriman', 'user'],
            ],
        ];

        return $mappings;
    }

    /**
     * Analyze query patterns and suggest optimal eager loading strategies
     */
    public function analyzeEagerLoadingOpportunities(): array
    {
        $opportunities = [];
        $relationshipMappings = $this->getRelationshipMappings();

        foreach ($this->detectNPlusOne() as $pattern) {
            $tableName = $this->extractTableFromPattern($pattern['pattern']);

            if (isset($relationshipMappings[$tableName])) {
                $opportunities[] = [
                    'base_model' => $this->tableToModel($tableName),
                    'table' => $tableName,
                    'detected_pattern' => $pattern['pattern'],
                    'suggested_relationships' => $relationshipMappings[$tableName],
                    'eager_loading_strategy' => $this->suggestEagerLoadingStrategy($pattern, $relationshipMappings[$tableName]),
                    'performance_impact' => [
                        'current_queries' => $pattern['query_count'],
                        'optimized_queries' => 1,
                        'time_savings' => $pattern['potential_savings'],
                        'efficiency_gain' => round((($pattern['query_count'] - 1) / $pattern['query_count']) * 100, 1).'%',
                    ],
                ];
            }
        }

        return $opportunities;
    }

    /**
     * Get development-time query optimization alerts
     */
    public function getDevelopmentAlerts(): array
    {
        $alerts = [];

        foreach ($this->detectNPlusOne() as $pattern) {
            if ($pattern['severity'] === 'high' || $pattern['severity'] === 'critical') {
                $alerts[] = [
                    'type' => 'n_plus_one_detected',
                    'severity' => $pattern['severity'],
                    'message' => "N+1 query detected: {$pattern['query_count']} queries in {$pattern['time_span']}s",
                    'suggestion' => $pattern['suggestion'],
                    'quick_fix' => $pattern['recommended_eager_loading'],
                    'file_hint' => $this->generateFileHint($pattern['pattern']),
                    'performance_impact' => "Potential savings: {$pattern['potential_savings']}ms",
                ];
            }
        }

        return $alerts;
    }

    /**
     * Private helper methods
     */
    private function extractQueryPattern(string $sql): string
    {
        // Normalize SQL to detect patterns
        $pattern = strtolower(trim($sql));

        // Replace placeholders with generic markers
        $pattern = preg_replace('/\?/', 'PLACEHOLDER', $pattern);

        // Replace specific values with generic markers
        $pattern = preg_replace('/\b\d+\b/', 'ID', $pattern);
        $pattern = preg_replace('/"[^"]*"/', 'STRING', $pattern);
        $pattern = preg_replace("/'[^']*'/", 'STRING', $pattern);

        // Remove extra whitespace
        $pattern = preg_replace('/\s+/', ' ', $pattern);

        return $pattern;
    }

    private function isNPlusOnePattern(array $group, int $queryCount): bool
    {
        // Must have enough queries to be considered N+1
        if ($queryCount < self::DETECTION_THRESHOLD) {
            return false;
        }

        // Queries should happen within a reasonable time window
        $timeSpan = $group['last_seen'] - $group['first_seen'];
        if ($timeSpan > self::TIME_WINDOW) {
            return false;
        }

        // Look for patterns typical of N+1 queries
        $firstQuery = $group['queries'][0]['sql'];
        $isSelectSingle = preg_match('/select.*from\s+\w+\s+where\s+\w+\s*=\s*\?/i', $firstQuery);

        return $isSelectSingle && $queryCount >= self::DETECTION_THRESHOLD;
    }

    private function calculateSeverity(int $queryCount, float $totalTime): string
    {
        if ($queryCount >= 20 || $totalTime >= 1000) {
            return 'critical';
        }
        if ($queryCount >= 10 || $totalTime >= 500) {
            return 'high';
        }
        if ($queryCount >= 5 || $totalTime >= 200) {
            return 'medium';
        }

        return 'low';
    }

    private function calculatePotentialSavings(array $group): float
    {
        // Estimate time savings by reducing N queries to 1 optimized query
        $queryCount = count($group['queries']);

        return $group['total_time'] * 0.8; // Assume 80% time savings with eager loading
    }

    private function extractAffectedModels(string $pattern): array
    {
        $models = [];

        // Extract table names from the pattern
        if (preg_match('/from\s+(\w+)/', $pattern, $matches)) {
            $tableName = $matches[1];
            $models[] = $this->tableToModel($tableName);
        }

        return array_unique($models);
    }

    private function getRecommendedEagerLoading(string $pattern): string
    {
        $tableName = $this->extractTableFromPattern($pattern);

        switch ($tableName) {
            case 'donatur':
                return "->with('donatur')";
            case 'jenis_quran':
                return "->with('jenisQuran')";
            case 'status_pengiriman':
                return "->with('status')";
            case 'wakaf_batch':
                return "->with('wakafBatch')";
            case 'users':
                return "->with('user')";
            default:
                return "->with('{$this->tableToRelationship($tableName)}')";
        }
    }

    private function extractTableFromPattern(string $pattern): string
    {
        if (preg_match('/from\s+(\w+)/', $pattern, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    private function tableToModel(string $tableName): string
    {
        $modelMappings = [
            'pengiriman' => 'Pengiriman',
            'donatur' => 'Donatur',
            'jenis_quran' => 'JenisQuran',
            'status_pengiriman' => 'StatusPengiriman',
            'wakaf_batch' => 'WakafBatch',
            'wakaf_items' => 'WakafItem',
            'daily_packing_tasks' => 'DailyPackingTask',
            'packing_boxes' => 'PackingBox',
            'packing_items' => 'PackingItem',
            'users' => 'User',
            'sertifikat' => 'Sertifikat',
        ];

        return $modelMappings[$tableName] ?? ucfirst($tableName);
    }

    private function tableToRelationship(string $tableName): string
    {
        $relationshipMappings = [
            'donatur' => 'donatur',
            'jenis_quran' => 'jenisQuran',
            'status_pengiriman' => 'status',
            'wakaf_batch' => 'wakafBatch',
            'users' => 'user',
            'sertifikat' => 'sertifikat',
        ];

        return $relationshipMappings[$tableName] ?? $tableName;
    }

    private function generateEagerLoadingSuggestion(string $pattern, array $group): string
    {
        $tableName = $this->extractTableFromPattern($pattern);
        $relationship = $this->tableToRelationship($tableName);

        return "Consider using eager loading: ->with('{$relationship}') to reduce {$group['queries']} queries to 1";
    }

    private function extractModelFromPattern(string $pattern): string
    {
        $tableName = $this->extractTableFromPattern($pattern);

        return $this->tableToModel($tableName);
    }

    private function extractRelationshipFromPattern(string $pattern): string
    {
        $tableName = $this->extractTableFromPattern($pattern);

        return $this->tableToRelationship($tableName);
    }

    private function generateCurrentCodeExample(string $modelName): string
    {
        return "// Current code causing N+1\n{$modelName}::all()->each(function (\$item) {\n    echo \$item->relationship->name;\n});";
    }

    private function generateOptimizedCodeExample(string $modelName, string $relationship): string
    {
        return "// Optimized with eager loading\n{$modelName}::with('{$relationship}')->get()->each(function (\$item) {\n    echo \$item->{$relationship}->name;\n});";
    }

    private function getImplementationNotes(string $modelName, string $relationship): array
    {
        return [
            "Add ->with('{$relationship}') to your query",
            "Ensure the relationship is properly defined in the {$modelName} model",
            'Consider using ->select() to load only required columns',
            'Test the optimization with Laravel Debugbar or Telescope',
        ];
    }

    private function suggestEagerLoadingStrategy(array $pattern, array $relationships): array
    {
        $strategies = [];

        // Suggest based on detected patterns
        foreach ($relationships['belongs_to'] ?? [] as $relation) {
            $strategies[] = [
                'type' => 'eager_loading',
                'relationship' => $relation,
                'code' => "->with('{$this->tableToRelationship($relation)}')",
            ];
        }

        // Suggest selective loading for has_many relationships
        foreach ($relationships['has_many'] ?? [] as $relation) {
            $strategies[] = [
                'type' => 'selective_eager_loading',
                'relationship' => $relation,
                'code' => "->with(['{$this->tableToRelationship($relation)}' => function(\$query) { \$query->select('id', 'foreign_key', 'name'); }])",
            ];
        }

        return $strategies;
    }

    private function generateFileHint(string $pattern): string
    {
        // Try to identify likely controller or service based on table names
        $tableName = $this->extractTableFromPattern($pattern);

        switch ($tableName) {
            case 'pengiriman':
                return 'Check PengirimanController or related services';
            case 'donatur':
                return 'Check DonaturController or related services';
            case 'daily_packing_tasks':
            case 'packing_boxes':
            case 'packing_items':
                return 'Check Warehouse controllers or PackingController';
            default:
                return "Check controllers/services using {$this->tableToModel($tableName)} model";
        }
    }

    private function checkForNPlusOnePattern(string $pattern): void
    {
        $group = $this->queryGroups[$pattern];
        $queryCount = count($group['queries']);

        if ($queryCount === self::DETECTION_THRESHOLD) {
            // Log when we first detect a potential N+1
            Log::warning('Potential N+1 query pattern detected', [
                'pattern' => $pattern,
                'query_count' => $queryCount,
                'total_time' => $group['total_time'],
                'suggestion' => $this->generateEagerLoadingSuggestion($pattern, $group),
            ]);
        }
    }

    private function cleanupOldPatterns(): void
    {
        $currentTime = microtime(true);
        $cutoffTime = $currentTime - (self::TIME_WINDOW * 2); // Clean patterns older than 2x time window

        foreach ($this->queryGroups as $pattern => $group) {
            if ($group['last_seen'] < $cutoffTime) {
                unset($this->queryGroups[$pattern]);
            }
        }

        // Also limit total patterns to prevent memory issues
        if (count($this->queryGroups) > self::MAX_TRACKED_PATTERNS) {
            // Remove oldest patterns
            uasort($this->queryGroups, function ($a, $b) {
                return $a['last_seen'] <=> $b['last_seen'];
            });

            $this->queryGroups = array_slice($this->queryGroups, -self::MAX_TRACKED_PATTERNS, true);
        }
    }

    /**
     * Reset detection state
     */
    public function reset(): void
    {
        $this->queryGroups = [];
        $this->detectedPatterns = [];
        $this->eagerLoadingSuggestions = [];
    }

    /**
     * Get current detection statistics
     */
    public function getStatistics(): array
    {
        return [
            'tracked_patterns' => count($this->queryGroups),
            'detected_n_plus_one' => count($this->detectNPlusOne()),
            'total_queries_tracked' => array_sum(array_map(fn ($group) => count($group['queries']), $this->queryGroups)),
            'memory_usage' => memory_get_usage(true),
            'analysis_timestamp' => now()->toISOString(),
        ];
    }
}
