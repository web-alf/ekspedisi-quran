<script>
  import { writable } from 'svelte/store';
  import { onMount } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  
  export let metrics = {};
  export let alerts = [];
  export let recommendations = [];

  let refreshInterval;
  let isRefreshing = false;

  const performanceData = writable({
    metrics,
    alerts,
    recommendations
  });

  onMount(() => {
    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(refreshData, 30000);
    return () => clearInterval(refreshInterval);
  });

  async function refreshData() {
    if (isRefreshing) return;
    isRefreshing = true;
    
    try {
      await router.reload({ only: ['metrics', 'alerts', 'recommendations'] });
    } catch (error) {
      console.error('Failed to refresh data:', error);
    }
    
    isRefreshing = false;
  }

  async function clearCache() {
    try {
      await fetch('/admin/performance/clear-cache', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/json'
        }
      });
      await refreshData();
    } catch (error) {
      console.error('Failed to clear cache:', error);
    }
  }

  function getAlertBadgeClass(level) {
    return level === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800';
  }

  function getPriorityBadgeClass(priority) {
    const classes = {
      'high': 'bg-red-100 text-red-800',
      'medium': 'bg-yellow-100 text-yellow-800',
      'low': 'bg-blue-100 text-blue-800'
    };
    return classes[priority] || 'bg-gray-100 text-gray-800';
  }

  function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
  }
</script>

<svelte:head>
  <title>Performance Dashboard - Admin</title>
  <meta name="csrf-token" content="{document.querySelector('meta[name=csrf-token]')?.content || ''}" />
</svelte:head>

<AdminLayout>
<div class="px-6 py-8">
  <!-- Header -->
  <div class="flex justify-between items-center mb-6">
    <div>
      <h1 class="text-3xl font-bold text-gray-900">Performance Dashboard</h1>
      <p class="text-gray-600 mt-1">Real-time system monitoring and analytics</p>
    </div>
    
    <div class="flex space-x-3">
      <button 
        on:click={refreshData}
        disabled={isRefreshing}
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
      >
        {isRefreshing ? 'Refreshing...' : 'Refresh'}
      </button>
      
      <button 
        on:click={clearCache}
        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
      >
        Clear Cache
      </button>
    </div>
  </div>

  <!-- Alerts Section -->
  {#if alerts && alerts.length > 0}
  <div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Active Alerts</h2>
    <div class="space-y-2">
      {#each alerts as alert}
      <div class="bg-white border-l-4 border-yellow-400 p-4 rounded-lg shadow-sm">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {getAlertBadgeClass(alert.level)}">
              {alert.level}
            </span>
            <span class="ml-3 text-sm text-gray-900">{alert.message}</span>
          </div>
          <span class="text-xs text-gray-500">{new Date(alert.timestamp).toLocaleTimeString()}</span>
        </div>
      </div>
      {/each}
    </div>
  </div>
  {/if}

  <!-- System Health Overview -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    {#if metrics.system_health}
    {#each Object.entries(metrics.system_health) as [component, health]}
    <div class="bg-white rounded-lg shadow-sm p-6 border">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-gray-500 capitalize">{component}</h3>
        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {health.status === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
          {health.status}
        </span>
      </div>
      {#if health.response_time_ms}
      <p class="text-xs text-gray-500 mt-1">Response: {health.response_time_ms}ms</p>
      {/if}
      {#if health.usage_mb}
      <p class="text-xs text-gray-500 mt-1">Usage: {health.usage_mb}MB</p>
      {/if}
    </div>
    {/each}
    {/if}
  </div>

  <!-- Key Metrics Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <!-- API Performance -->
    {#if metrics.api_stats}
    <div class="bg-white rounded-lg shadow-sm p-6 border">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">API Performance</h3>
      <div class="space-y-3">
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Avg Response Time</span>
          <span class="text-sm font-medium">{metrics.api_stats.avg_response_time || 0}ms</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Total Requests</span>
          <span class="text-sm font-medium">{metrics.api_stats.total_requests || 0}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Error Rate</span>
          <span class="text-sm font-medium">{metrics.api_stats.error_rate || 0}%</span>
        </div>
      </div>
    </div>
    {/if}

    <!-- Database Performance -->
    {#if metrics.database_status}
    <div class="bg-white rounded-lg shadow-sm p-6 border">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Database</h3>
      <div class="space-y-3">
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Response Time</span>
          <span class="text-sm font-medium">{metrics.database_status.response_time_ms || 0}ms</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Tables</span>
          <span class="text-sm font-medium">{metrics.database_status.total_tables || 0}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Size</span>
          <span class="text-sm font-medium">{metrics.database_status.database_size_mb || 0}MB</span>
        </div>
      </div>
    </div>
    {/if}

    <!-- Memory Usage -->
    {#if metrics.memory_stats}
    <div class="bg-white rounded-lg shadow-sm p-6 border">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Memory Usage</h3>
      <div class="space-y-3">
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Current Usage</span>
          <span class="text-sm font-medium">{formatBytes(metrics.memory_stats.current_usage || 0)}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Peak Usage</span>
          <span class="text-sm font-medium">{formatBytes(metrics.memory_stats.peak_usage || 0)}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-600">Limit</span>
          <span class="text-sm font-medium">{formatBytes(metrics.memory_stats.memory_limit || 0)}</span>
        </div>
      </div>
    </div>
    {/if}
  </div>

  <!-- Recent Activities -->
  {#if metrics.recent_activities}
  <div class="bg-white rounded-lg shadow-sm p-6 border mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activities</h3>
    
    <!-- Slow Queries -->
    {#if metrics.recent_activities.slow_queries && metrics.recent_activities.slow_queries.length > 0}
    <div class="mb-6">
      <h4 class="text-md font-medium text-gray-800 mb-3">Recent Slow Queries</h4>
      <div class="space-y-2">
        {#each metrics.recent_activities.slow_queries as query}
        <div class="bg-gray-50 p-3 rounded-lg">
          <div class="flex justify-between items-start">
            <code class="text-xs text-gray-700 flex-1 mr-4">{query.sql}</code>
            <span class="text-xs text-red-600 font-medium">{query.execution_time}ms</span>
          </div>
          <div class="text-xs text-gray-500 mt-1">{new Date(query.timestamp).toLocaleString()}</div>
        </div>
        {/each}
      </div>
    </div>
    {/if}

    <!-- High Memory Operations -->
    {#if metrics.recent_activities.high_memory_operations && metrics.recent_activities.high_memory_operations.length > 0}
    <div>
      <h4 class="text-md font-medium text-gray-800 mb-3">High Memory Operations</h4>
      <div class="space-y-2">
        {#each metrics.recent_activities.high_memory_operations as operation}
        <div class="bg-yellow-50 p-3 rounded-lg">
          <div class="flex justify-between items-center">
            <span class="text-sm text-gray-700">{operation.operation}</span>
            <span class="text-sm text-yellow-600 font-medium">{formatBytes(operation.memory_used)}</span>
          </div>
          <div class="text-xs text-gray-500 mt-1">{new Date(operation.timestamp).toLocaleString()}</div>
        </div>
        {/each}
      </div>
    </div>
    {/if}
  </div>
  {/if}

  <!-- Recommendations -->
  {#if recommendations && recommendations.length > 0}
  <div class="bg-white rounded-lg shadow-sm p-6 border">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Recommendations</h3>
    <div class="space-y-4">
      {#each recommendations as rec}
      <div class="border-l-4 border-blue-400 pl-4 py-2">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-md font-medium text-gray-900">{rec.title}</h4>
          <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {getPriorityBadgeClass(rec.priority)}">
            {rec.priority}
          </span>
        </div>
        <p class="text-sm text-gray-600 mb-2">{rec.description}</p>
        <p class="text-xs text-blue-600 font-medium">Action: {rec.action}</p>
      </div>
      {/each}
    </div>
  </div>
  {/if}
</div>
</AdminLayout>