<script>
  import { onMount, onDestroy } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  
  // Props
  export const errors = {};
  export const flash = {};
  export const auth = {};
  export let todayStats = {};
  export let weeklyTrend = [];
  export let topPerformers = [];
  export let recentOperations = [];
  export let boxOperationsSummary = [];
  
  // State
  let autoRefresh = true;
  let refreshInterval;
  let realtimeStats = {};
  let isLoading = false;
  
  onMount(() => {
    loadRealtimeStats();
    if (autoRefresh) {
      refreshInterval = setInterval(() => {
        loadRealtimeStats();
      }, 30000); // Refresh every 30 seconds
    }
    
    return () => {
      if (refreshInterval) clearInterval(refreshInterval);
    };
  });
  
  onDestroy(() => {
    if (refreshInterval) clearInterval(refreshInterval);
  });
  
  async function loadRealtimeStats() {
    try {
      const response = await fetch('/admin/bulk-operations/realtime-stats');
      const data = await response.json();
      realtimeStats = data;
    } catch (error) {
      console.error('Failed to load realtime stats:', error);
    }
  }
  
  function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    if (autoRefresh) {
      refreshInterval = setInterval(() => {
        loadRealtimeStats();
      }, 30000);
    } else {
      if (refreshInterval) clearInterval(refreshInterval);
    }
  }
  
  function formatProcessingTime(ms) {
    if (!ms) return 'N/A';
    if (ms < 1000) return Math.round(ms) + 'ms';
    return Math.round(ms / 1000 * 100) / 100 + 's';
  }
  
  function getOperationTypeColor(type) {
    const colors = {
      'status': 'bg-blue-100 text-blue-800',
      'address': 'bg-green-100 text-green-800', 
      'both': 'bg-purple-100 text-purple-800'
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
  }
  
  function exportReport(format) {
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 7);
    const endDate = new Date();
    
    const url = `/admin/bulk-operations/export-report?start_date=${startDate.toISOString().split('T')[0]}&end_date=${endDate.toISOString().split('T')[0]}&format=${format}`;
    window.open(url, '_blank');
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto space-y-6">
    
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Bulk Operations Dashboard</h1>
          <p class="text-gray-600">Monitor and analyze box bulk operations performance</p>
        </div>
        <div class="flex items-center space-x-4">
          <label class="flex items-center space-x-2">
            <input 
              type="checkbox" 
              bind:checked={autoRefresh}
              on:change={toggleAutoRefresh}
              class="rounded border-gray-300"
            />
            <span class="text-sm text-gray-600">Auto refresh</span>
          </label>
          <button 
            on:click={() => router.reload()}
            class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
          >
            <HeroIcon name="arrow-path" class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Real-time Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-blue-100 rounded-lg">
            <HeroIcon name="clipboard-document-list" class="w-6 h-6 text-blue-600" />
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Today's Operations</p>
            <p class="text-2xl font-bold text-gray-900">{realtimeStats.today_operations || todayStats.total_operations || 0}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-green-100 rounded-lg">
            <HeroIcon name="cube" class="w-6 h-6 text-green-600" />
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Items Processed</p>
            <p class="text-2xl font-bold text-gray-900">{realtimeStats.today_items || todayStats.total_items_affected || 0}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-yellow-100 rounded-lg">
            <HeroIcon name="user-group" class="w-6 h-6 text-yellow-600" />
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Active Users</p>
            <p class="text-2xl font-bold text-gray-900">{realtimeStats.active_users || 0}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-purple-100 rounded-lg">
            <HeroIcon name="bolt" class="w-6 h-6 text-purple-600" />
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Avg Processing</p>
            <p class="text-2xl font-bold text-gray-900">{formatProcessingTime(realtimeStats.avg_processing_time)}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Operation Types Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Today's Operations by Type</h2>
        {#if todayStats.by_type && Object.keys(todayStats.by_type).length > 0}
          <div class="space-y-3">
            {#each Object.entries(todayStats.by_type) as [type, data]}
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <span class="px-2 py-1 rounded-full text-xs font-medium {getOperationTypeColor(type)}">
                    {type === 'status' ? 'Status Update' : type === 'address' ? 'Address Update' : 'Both'}
                  </span>
                  <span class="text-sm text-gray-600">{data.operations} operations</span>
                </div>
                <span class="text-sm font-medium text-gray-900">{data.items} items</span>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-8 text-gray-500">
            <p>No operations recorded today</p>
          </div>
        {/if}
      </div>
      
      <!-- Weekly Trend Chart -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Weekly Trend</h2>
        {#if weeklyTrend.length > 0}
          <div class="space-y-2">
            {#each weeklyTrend as day}
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">{new Date(day.date).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })}</span>
                <div class="flex items-center space-x-2">
                  <span class="text-gray-900">{day.operations} ops</span>
                  <span class="text-gray-500">({day.items} items)</span>
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-8 text-gray-500">
            <p>No trend data available</p>
          </div>
        {/if}
      </div>
    </div>

    <!-- Top Performers & Recent Operations -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Top Performers -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Top Performers (This Month)</h2>
        {#if topPerformers.length > 0}
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="text-left text-xs font-medium text-gray-500 uppercase py-2">User</th>
                  <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Operations</th>
                  <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Items</th>
                  <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Efficiency</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                {#each topPerformers as performer, index}
                  <tr>
                    <td class="py-3">
                      <div class="flex items-center">
                        <div class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-xs font-medium mr-2">
                          {index + 1}
                        </div>
                        <span class="text-sm font-medium text-gray-900">{performer.user_name}</span>
                      </div>
                    </td>
                    <td class="py-3 text-right text-sm text-gray-900">{performer.total_operations}</td>
                    <td class="py-3 text-right text-sm text-gray-900">{performer.total_items}</td>
                    <td class="py-3 text-right text-sm text-gray-900">{Math.round(performer.efficiency_score * 10) / 10}</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {:else}
          <div class="text-center py-8 text-gray-500">
            <p>No performance data available</p>
          </div>
        {/if}
      </div>
      
      <!-- Recent Operations -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Operations</h2>
        {#if recentOperations.length > 0}
          <div class="space-y-3 max-h-96 overflow-y-auto">
            {#each recentOperations as operation}
              <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                <div class="flex-shrink-0">
                  <span class="px-2 py-1 rounded-full text-xs font-medium {getOperationTypeColor(operation.operation_type.toLowerCase().split(' ')[0])}">
                    {operation.operation_type}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900">{operation.box_code}</p>
                    <p class="text-xs text-gray-500">{operation.timestamp}</p>
                  </div>
                  <p class="text-xs text-gray-600 mt-1">
                    By {operation.user_name} • {operation.items_count} items
                    {#if operation.processing_time}
                      • {operation.processing_time}
                    {/if}
                  </p>
                  {#if operation.notes}
                    <p class="text-xs text-gray-500 mt-1 italic">"{operation.notes}"</p>
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-8 text-gray-500">
            <p>No recent operations</p>
          </div>
        {/if}
      </div>
    </div>

    <!-- Box Operations Summary -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Most Active Boxes (This Week)</h2>
        <div class="flex space-x-2">
          <button
            on:click={() => exportReport('csv')}
            class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
          >
            Export CSV
          </button>
          <button
            on:click={() => exportReport('excel')}
            class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
          >
            Export Excel
          </button>
        </div>
      </div>
      
      {#if boxOperationsSummary.length > 0}
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Box Code</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Operations</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Items</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Last Operation</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each boxOperationsSummary as box}
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-mono text-gray-900">{box.box_code}</td>
                  <td class="px-4 py-3 text-right text-sm text-gray-900">{box.operations}</td>
                  <td class="px-4 py-3 text-right text-sm text-gray-900">{box.total_items}</td>
                  <td class="px-4 py-3 text-right text-sm text-gray-500">{box.last_operation}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {:else}
        <div class="text-center py-8 text-gray-500">
          <p>No box operations data available</p>
        </div>
      {/if}
    </div>

    <!-- Last Operation Info -->
    {#if realtimeStats.last_operation}
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center">
          <HeroIcon name="information-circle" class="w-5 h-5 text-blue-500 mr-2" />
          <div class="text-sm text-blue-800">
            <strong>Last Operation:</strong> 
            {realtimeStats.last_operation.user_name} processed {realtimeStats.last_operation.items_count} items 
            from box {realtimeStats.last_operation.box_code} at {realtimeStats.last_operation.timestamp}
          </div>
        </div>
      </div>
    {/if}

  </div>
</AdminLayout>