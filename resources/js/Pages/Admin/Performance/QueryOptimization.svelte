<script>
    import { onMount, onDestroy } from 'svelte';
    import AdminLayout from '../../../Layouts/AdminLayout.svelte';
    import LoadingSpinner from '../../../Components/UI/LoadingSpinner.svelte';
    import Toast from '../../../Components/Toast.svelte';

    export let performanceData = {};
    export let optimizationOpportunities = {};
    export let recentAnalysis = {};
    export let indexRecommendations = {};
    export let timeframe = '24h';

    let selectedTab = 'overview';
    let isLoading = false;
    let toastMessage = '';
    let toastType = 'info';
    let realTimeEnabled = false;
    let autoRefreshInterval;
    let nPlusOneData = null;
    let indexData = null;
    let selectedIndexes = [];
    let analyzingQuery = false;
    let customQuery = '';
    let queryAnalysisResult = null;

    const tabs = [
        { id: 'overview', label: 'Overview', icon: '📊' },
        { id: 'n-plus-one', label: 'N+1 Detection', icon: '🔄' },
        { id: 'indexes', label: 'Index Optimization', icon: '🗂️' },
        { id: 'query-analyzer', label: 'Query Analyzer', icon: '🔍' },
        { id: 'trends', label: 'Performance Trends', icon: '📈' }
    ];

    const priorityColors = {
        'critical': 'text-red-600 bg-red-100',
        'high': 'text-orange-600 bg-orange-100',
        'medium': 'text-yellow-600 bg-yellow-100',
        'low': 'text-blue-600 bg-blue-100'
    };

    const gradeColors = {
        'A+': 'text-green-700 bg-green-100',
        'A': 'text-green-600 bg-green-50',
        'B': 'text-yellow-600 bg-yellow-50',
        'C': 'text-orange-600 bg-orange-50',
        'D': 'text-red-600 bg-red-50',
        'F': 'text-red-700 bg-red-100'
    };

    onMount(() => {
        loadNPlusOneData();
        loadIndexData();
        
        // Set up auto-refresh every 30 seconds for real-time data
        if (realTimeEnabled) {
            autoRefreshInterval = setInterval(() => {
                refreshData();
            }, 30000);
        }
    });

    onDestroy(() => {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    });

    async function loadNPlusOneData() {
        try {
            const response = await fetch('/admin/query-optimization/n-plus-one', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                nPlusOneData = await response.json();
            }
        } catch (error) {
            console.error('Failed to load N+1 data:', error);
        }
    }

    async function loadIndexData() {
        try {
            const response = await fetch('/admin/query-optimization/index-recommendations', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                indexData = await response.json();
            }
        } catch (error) {
            console.error('Failed to load index data:', error);
        }
    }

    async function refreshData() {
        try {
            const response = await fetch(`/admin/query-optimization/dashboard?timeframe=${timeframe}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                performanceData = data.performanceData;
                optimizationOpportunities = data.optimizationOpportunities;
                recentAnalysis = data.recentAnalysis;
                indexRecommendations = data.indexRecommendations;
            }
        } catch (error) {
            console.error('Failed to refresh data:', error);
        }
    }

    async function toggleRealTimeAnalysis() {
        if (realTimeEnabled) {
            realTimeEnabled = false;
            clearInterval(autoRefreshInterval);
            showToast('Real-time analysis disabled', 'info');
        } else {
            try {
                const response = await fetch('/admin/query-optimization/real-time', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    realTimeEnabled = true;
                    autoRefreshInterval = setInterval(refreshData, 5000); // More frequent updates
                    showToast('Real-time analysis enabled', 'success');
                } else {
                    showToast('Failed to enable real-time analysis', 'error');
                }
            } catch (error) {
                console.error('Failed to toggle real-time analysis:', error);
                showToast('Error enabling real-time analysis', 'error');
            }
        }
    }

    async function generateIndexMigration() {
        if (selectedIndexes.length === 0) {
            showToast('Please select at least one index to generate migration', 'warning');
            return;
        }

        isLoading = true;
        try {
            const response = await fetch('/admin/query-optimization/generate-migration', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    selected_indexes: selectedIndexes
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                showToast(`Migration file created: ${result.migration_file}`, 'success');
                
                // Download the migration file content
                const blob = new Blob([result.migration_content], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = result.migration_file.split('/').pop();
                a.click();
                window.URL.revokeObjectURL(url);
            } else {
                const error = await response.json();
                showToast(error.error || 'Failed to generate migration', 'error');
            }
        } catch (error) {
            console.error('Failed to generate migration:', error);
            showToast('Error generating migration file', 'error');
        } finally {
            isLoading = false;
        }
    }

    async function analyzeCustomQuery() {
        if (!customQuery.trim()) {
            showToast('Please enter a SQL query to analyze', 'warning');
            return;
        }

        analyzingQuery = true;
        try {
            const response = await fetch('/admin/query-optimization/analyze-query', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    sql: customQuery,
                    bindings: []
                })
            });
            
            if (response.ok) {
                queryAnalysisResult = await response.json();
                showToast('Query analyzed successfully', 'success');
            } else {
                showToast('Failed to analyze query', 'error');
            }
        } catch (error) {
            console.error('Failed to analyze query:', error);
            showToast('Error analyzing query', 'error');
        } finally {
            analyzingQuery = false;
        }
    }

    function toggleIndexSelection(table, indexName) {
        const index = selectedIndexes.findIndex(item => 
            item.table === table && item.index_name === indexName
        );
        
        if (index >= 0) {
            selectedIndexes = selectedIndexes.filter((_, i) => i !== index);
        } else {
            selectedIndexes = [...selectedIndexes, { table, index_name: indexName }];
        }
    }

    function showToast(message, type = 'info') {
        toastMessage = message;
        toastType = type;
        setTimeout(() => {
            toastMessage = '';
        }, 5000);
    }

    function getOptimizationScoreColor(score) {
        if (score >= 90) return 'text-green-600';
        if (score >= 80) return 'text-yellow-600';
        if (score >= 70) return 'text-orange-600';
        return 'text-red-600';
    }

    function formatExecutionTime(time) {
        if (time < 1000) return `${time.toFixed(2)}ms`;
        return `${(time / 1000).toFixed(2)}s`;
    }
</script>

<AdminLayout>
    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Query Optimization Dashboard</h1>
                <p class="text-gray-600 mt-1">Monitor and optimize database query performance</p>
            </div>
            
            <div class="flex space-x-3">
                <button
                    on:click={toggleRealTimeAnalysis}
                    class="px-4 py-2 {realTimeEnabled ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'} text-white rounded-lg transition-colors"
                >
                    {realTimeEnabled ? '⏹️ Stop' : '▶️ Start'} Real-time Analysis
                </button>
                
                <button
                    on:click={refreshData}
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                >
                    🔄 Refresh
                </button>
            </div>
        </div>

        <!-- Performance Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Optimization Score</p>
                        <p class="text-3xl font-bold {getOptimizationScoreColor(optimizationOpportunities.optimization_score)}">
                            {optimizationOpportunities.optimization_score || 'N/A'}
                        </p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <span class="text-blue-600 text-xl">📊</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">N+1 Patterns</p>
                        <p class="text-3xl font-bold text-orange-600">
                            {optimizationOpportunities.n_plus_one_count || 0}
                        </p>
                    </div>
                    <div class="p-3 bg-orange-100 rounded-full">
                        <span class="text-orange-600 text-xl">🔄</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Missing Indexes</p>
                        <p class="text-3xl font-bold text-red-600">
                            {optimizationOpportunities.missing_indexes_count || 0}
                        </p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full">
                        <span class="text-red-600 text-xl">🗂️</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Slow Queries</p>
                        <p class="text-3xl font-bold text-yellow-600">
                            {optimizationOpportunities.slow_queries_count || 0}
                        </p>
                    </div>
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <span class="text-yellow-600 text-xl">🐌</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8">
                {#each tabs as tab}
                    <button
                        on:click={() => selectedTab = tab.id}
                        class="py-2 px-1 border-b-2 font-medium text-sm {selectedTab === tab.id 
                            ? 'border-blue-500 text-blue-600' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
                    >
                        <span class="mr-2">{tab.icon}</span>
                        {tab.label}
                    </button>
                {/each}
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="bg-white rounded-lg shadow-sm border">
            {#if selectedTab === 'overview'}
                <div class="p-6">
                    <h2 class="text-xl font-semibold mb-6">Performance Overview</h2>
                    
                    <!-- Performance Grade -->
                    {#if performanceData.query_metrics}
                        <div class="mb-6">
                            <div class="flex items-center space-x-4">
                                <div class="text-sm text-gray-600">Performance Grade:</div>
                                <span class="px-3 py-1 rounded-full text-sm font-medium {gradeColors[performanceData.query_metrics.performance_grade] || 'text-gray-600 bg-gray-100'}">
                                    {performanceData.query_metrics.performance_grade || 'N/A'}
                                </span>
                            </div>
                        </div>
                    {/if}

                    <!-- Recent Performance Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">Query Statistics</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Queries:</span>
                                    <span class="font-medium">{performanceData.query_metrics?.total_queries || 0}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Avg Execution Time:</span>
                                    <span class="font-medium">{performanceData.query_metrics?.average_execution_time || 0}ms</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Slow Query %:</span>
                                    <span class="font-medium">{performanceData.query_metrics?.slow_query_percentage || 0}%</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">System Health</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Memory Usage:</span>
                                    <span class="font-medium">{performanceData.overview?.system_health?.memory_usage?.current_mb || 0}MB</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Cache Hit Rate:</span>
                                    <span class="font-medium">{performanceData.overview?.system_health?.cache_hit_rate || 0}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">DB Connections:</span>
                                    <span class="font-medium">{performanceData.overview?.system_health?.database_connections || 0}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">Optimization Status</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Opportunities:</span>
                                    <span class="font-medium">{performanceData.query_metrics?.optimization_opportunities || 0}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">N+1 Patterns:</span>
                                    <span class="font-medium">{performanceData.query_metrics?.n_plus_one_patterns || 0}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Last Analysis:</span>
                                    <span class="font-medium">{recentAnalysis.last_analysis || 'Never'}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Slow Queries -->
                    {#if performanceData.recent_slow_queries && performanceData.recent_slow_queries.length > 0}
                        <div>
                            <h3 class="text-lg font-medium mb-4">Recent Slow Queries</h3>
                            <div class="space-y-3">
                                {#each performanceData.recent_slow_queries.slice(0, 5) as query}
                                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                        <div class="flex justify-between items-start mb-2">
                                            <code class="text-sm text-gray-800 flex-1">{query.sql?.substring(0, 100)}...</code>
                                            <span class="text-sm font-medium text-yellow-700 ml-4">
                                                {formatExecutionTime(query.execution_time)}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-600">{query.timestamp}</div>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}
                </div>

            {:else if selectedTab === 'n-plus-one'}
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold">N+1 Query Detection</h2>
                        <button
                            on:click={loadNPlusOneData}
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                        >
                            🔄 Refresh Detection
                        </button>
                    </div>

                    {#if nPlusOneData}
                        <!-- Detection Statistics -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                <h4 class="font-medium text-orange-900 mb-2">Detected Patterns</h4>
                                <p class="text-2xl font-bold text-orange-600">
                                    {nPlusOneData.n_plus_one_patterns?.length || 0}
                                </p>
                            </div>

                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <h4 class="font-medium text-blue-900 mb-2">Suggestions Available</h4>
                                <p class="text-2xl font-bold text-blue-600">
                                    {nPlusOneData.eager_loading_suggestions?.length || 0}
                                </p>
                            </div>

                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <h4 class="font-medium text-green-900 mb-2">Tracked Patterns</h4>
                                <p class="text-2xl font-bold text-green-600">
                                    {nPlusOneData.statistics?.tracked_patterns || 0}
                                </p>
                            </div>
                        </div>

                        <!-- Detected N+1 Patterns -->
                        {#if nPlusOneData.n_plus_one_patterns && nPlusOneData.n_plus_one_patterns.length > 0}
                            <div class="mb-6">
                                <h3 class="text-lg font-medium mb-4">🚨 Detected N+1 Patterns</h3>
                                <div class="space-y-4">
                                    {#each nPlusOneData.n_plus_one_patterns as pattern}
                                        <div class="p-4 border border-red-200 bg-red-50 rounded-lg">
                                            <div class="flex justify-between items-start mb-3">
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-2 mb-2">
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full {priorityColors[pattern.severity]}">
                                                            {pattern.severity.toUpperCase()}
                                                        </span>
                                                        <span class="text-sm text-gray-600">
                                                            {pattern.query_count} queries in {pattern.time_span}s
                                                        </span>
                                                    </div>
                                                    <code class="text-sm text-gray-800">{pattern.example_sql}</code>
                                                </div>
                                                <div class="text-right text-sm">
                                                    <div class="font-medium text-red-600">
                                                        {formatExecutionTime(pattern.total_time)}
                                                    </div>
                                                    <div class="text-gray-600">
                                                        Potential savings: {formatExecutionTime(pattern.potential_savings)}
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3 p-3 bg-white rounded border">
                                                <h5 class="font-medium text-gray-900 mb-2">💡 Suggestion:</h5>
                                                <p class="text-sm text-gray-700 mb-2">{pattern.suggestion}</p>
                                                <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                                    {pattern.recommended_eager_loading}
                                                </code>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        <!-- Eager Loading Suggestions -->
                        {#if nPlusOneData.eager_loading_suggestions && nPlusOneData.eager_loading_suggestions.length > 0}
                            <div>
                                <h3 class="text-lg font-medium mb-4">💡 Eager Loading Suggestions</h3>
                                <div class="space-y-4">
                                    {#each nPlusOneData.eager_loading_suggestions as suggestion}
                                        <div class="p-4 border border-blue-200 bg-blue-50 rounded-lg">
                                            <div class="mb-3">
                                                <h4 class="font-medium text-blue-900">
                                                    {suggestion.model} → {suggestion.relationship}
                                                </h4>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                                <div>
                                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Current Code:</h5>
                                                    <pre class="text-xs bg-red-100 p-2 rounded border overflow-x-auto">{suggestion.current_code}</pre>
                                                </div>
                                                <div>
                                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Optimized Code:</h5>
                                                    <pre class="text-xs bg-green-100 p-2 rounded border overflow-x-auto">{suggestion.optimized_code}</pre>
                                                </div>
                                            </div>
                                            
                                            <div class="flex justify-between items-center text-sm">
                                                <div class="space-x-4">
                                                    <span class="text-green-600">
                                                        ↓ {suggestion.impact.queries_reduced} queries
                                                    </span>
                                                    <span class="text-blue-600">
                                                        ⚡ {formatExecutionTime(suggestion.impact.time_saved_ms)} saved
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    {:else}
                        <div class="text-center py-8">
                            <LoadingSpinner />
                            <p class="text-gray-600 mt-2">Loading N+1 detection data...</p>
                        </div>
                    {/if}
                </div>

            {:else if selectedTab === 'indexes'}
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold">Index Optimization</h2>
                        <div class="space-x-3">
                            {#if selectedIndexes.length > 0}
                                <button
                                    on:click={generateIndexMigration}
                                    disabled={isLoading}
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg transition-colors"
                                >
                                    {#if isLoading}
                                        <LoadingSpinner size="small" />
                                    {:else}
                                        📄 Generate Migration ({selectedIndexes.length})
                                    {/if}
                                </button>
                            {/if}
                            <button
                                on:click={loadIndexData}
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                            >
                                🔄 Refresh Analysis
                            </button>
                        </div>
                    </div>

                    {#if indexData}
                        <!-- Index Summary -->
                        {#if indexData.recommendations_summary}
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                                <div class="p-4 bg-gray-50 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">Total Recommendations</h4>
                                    <p class="text-2xl font-bold text-blue-600">
                                        {indexData.recommendations_summary.total_recommendations}
                                    </p>
                                </div>

                                <div class="p-4 bg-red-50 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">High Priority</h4>
                                    <p class="text-2xl font-bold text-red-600">
                                        {indexData.recommendations_summary.priority_breakdown.high || 0}
                                    </p>
                                </div>

                                <div class="p-4 bg-yellow-50 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">Medium Priority</h4>
                                    <p class="text-2xl font-bold text-yellow-600">
                                        {indexData.recommendations_summary.priority_breakdown.medium || 0}
                                    </p>
                                </div>

                                <div class="p-4 bg-green-50 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">Affected Tables</h4>
                                    <p class="text-2xl font-bold text-green-600">
                                        {indexData.recommendations_summary.affected_tables}
                                    </p>
                                </div>
                            </div>
                        {/if}

                        <!-- Missing Indexes -->
                        {#if indexData.missing_indexes}
                            <div class="mb-6">
                                <h3 class="text-lg font-medium mb-4">🗂️ Recommended Indexes</h3>
                                <div class="space-y-3">
                                    {#each Object.entries(indexData.missing_indexes) as [table, indexes]}
                                        <div class="border rounded-lg overflow-hidden">
                                            <div class="bg-gray-50 px-4 py-3 border-b">
                                                <h4 class="font-medium text-gray-900">Table: {table}</h4>
                                            </div>
                                            <div class="divide-y">
                                                {#each indexes as index}
                                                    <div class="p-4 hover:bg-gray-50">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center space-x-3">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={selectedIndexes.some(item => 
                                                                        item.table === table && item.index_name === index.index_name
                                                                    )}
                                                                    on:change={() => toggleIndexSelection(table, index.index_name)}
                                                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded"
                                                                />
                                                                <div>
                                                                    <div class="flex items-center space-x-2">
                                                                        <code class="text-sm font-medium">{index.index_name}</code>
                                                                        <span class="px-2 py-1 text-xs font-medium rounded-full {priorityColors[index.priority]}">
                                                                            {index.priority.toUpperCase()}
                                                                        </span>
                                                                    </div>
                                                                    <p class="text-sm text-gray-600 mt-1">{index.reason}</p>
                                                                    <p class="text-xs text-gray-500 mt-1">
                                                                        Columns: {index.columns.join(', ')}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="text-right">
                                                                <p class="text-sm font-medium text-gray-900">
                                                                    {index.estimated_impact}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                {/each}
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        <!-- Generated SQL -->
                        {#if indexData.migration_sql && indexData.migration_sql.length > 0}
                            <div>
                                <h3 class="text-lg font-medium mb-4">📄 Generated SQL</h3>
                                <div class="space-y-3">
                                    {#each indexData.migration_sql.slice(0, 10) as sql}
                                        <div class="p-4 bg-gray-50 rounded-lg">
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm font-medium">{sql.table}.{sql.index_name}</span>
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full {priorityColors[sql.priority]}">
                                                        {sql.priority.toUpperCase()}
                                                    </span>
                                                </div>
                                                <span class="text-xs text-gray-500">{sql.estimated_impact}</span>
                                            </div>
                                            <code class="text-sm text-gray-800 block bg-white p-2 rounded border">
                                                {sql.sql}
                                            </code>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    {:else}
                        <div class="text-center py-8">
                            <LoadingSpinner />
                            <p class="text-gray-600 mt-2">Loading index analysis...</p>
                        </div>
                    {/if}
                </div>

            {:else if selectedTab === 'query-analyzer'}
                <div class="p-6">
                    <h2 class="text-xl font-semibold mb-6">Query Analyzer</h2>
                    
                    <div class="space-y-6">
                        <!-- Query Input -->
                        <div>
                            <label for="custom-query" class="block text-sm font-medium text-gray-700 mb-2">
                                SQL Query to Analyze
                            </label>
                            <textarea
                                id="custom-query"
                                bind:value={customQuery}
                                rows="6"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter your SQL query here..."
                            ></textarea>
                            <div class="mt-2">
                                <button
                                    on:click={analyzeCustomQuery}
                                    disabled={analyzingQuery || !customQuery.trim()}
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg transition-colors"
                                >
                                    {#if analyzingQuery}
                                        <LoadingSpinner size="small" />
                                    {:else}
                                        🔍 Analyze Query
                                    {/if}
                                </button>
                            </div>
                        </div>

                        <!-- Analysis Results -->
                        {#if queryAnalysisResult}
                            <div class="border-t pt-6">
                                <h3 class="text-lg font-medium mb-4">Analysis Results</h3>
                                
                                <!-- Optimization Score -->
                                <div class="mb-6">
                                    <div class="flex items-center space-x-4">
                                        <span class="text-sm font-medium text-gray-700">Optimization Score:</span>
                                        <span class="text-2xl font-bold {getOptimizationScoreColor(queryAnalysisResult.analysis.optimization_score)}">
                                            {queryAnalysisResult.analysis.optimization_score}/100
                                        </span>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium {gradeColors[queryAnalysisResult.analysis.severity] || 'text-gray-600 bg-gray-100'}">
                                            {queryAnalysisResult.analysis.severity.toUpperCase()}
                                        </span>
                                    </div>
                                </div>

                                <!-- Issues -->
                                {#if queryAnalysisResult.analysis.issues.length > 0}
                                    <div class="mb-6">
                                        <h4 class="font-medium text-red-900 mb-3">⚠️ Issues Detected</h4>
                                        <ul class="space-y-2">
                                            {#each queryAnalysisResult.analysis.issues as issue}
                                                <li class="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-800">
                                                    {issue}
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                {/if}

                                <!-- Recommendations -->
                                {#if queryAnalysisResult.suggestions.length > 0}
                                    <div class="mb-6">
                                        <h4 class="font-medium text-blue-900 mb-3">💡 Recommendations</h4>
                                        <ul class="space-y-2">
                                            {#each queryAnalysisResult.suggestions as suggestion}
                                                <li class="p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800">
                                                    {suggestion}
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                {/if}

                                <!-- EXPLAIN Results -->
                                {#if queryAnalysisResult.explain && !queryAnalysisResult.explain.error}
                                    <div>
                                        <h4 class="font-medium text-gray-900 mb-3">📊 EXPLAIN Results</h4>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 border">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Key</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rows</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Extra</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    {#each queryAnalysisResult.explain as row}
                                                        <tr>
                                                            <td class="px-3 py-2 text-sm text-gray-900">{row.type || '-'}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-900">{row.table || '-'}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-900">{row.key || '-'}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-900">{row.rows || '-'}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-900">{row.Extra || '-'}</td>
                                                        </tr>
                                                    {/each}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>

            {:else if selectedTab === 'trends'}
                <div class="p-6">
                    <h2 class="text-xl font-semibold mb-6">Performance Trends</h2>
                    <div class="text-center py-8 text-gray-600">
                        <p>📈 Performance trend visualization coming soon...</p>
                        <p class="text-sm mt-2">This will show query performance over time, trend analysis, and predictive insights.</p>
                    </div>
                </div>
            {/if}
        </div>
    </div>

    <!-- Toast Notification -->
    {#if toastMessage}
        <Toast message={toastMessage} type={toastType} />
    {/if}
</AdminLayout>