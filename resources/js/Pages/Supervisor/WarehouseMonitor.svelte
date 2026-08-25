<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { onMount } from 'svelte'
  import { router } from '@inertiajs/svelte'
  import axios from 'axios'
  import { toast } from '../../utils/notifications.js'
  
  export let warehouseUsers = []
  export let dailyTasks = []
  export let recentActivities = []
  export let summary = {
    total_active_tasks: 0,
    total_packed_today: 0,
    total_packed_this_month: 0,
    total_boxes_today: 0,
    average_per_user: 0,
    total_carry_over: 0
  }
  export let stockInfo = {
    details: [],
    totals: {},
    last_updated: ''
  }
  export let performanceStats = []
  export let userPerformance = {
    summary: {
      total_tasks: 0,
      completed_tasks: 0,
      total_mushaf: 0,
      completion_rate: 0
    }
  }

  let selectedMonth = new Date().toISOString().slice(0, 7)
  let loading = false
  let assignTarget = { user_id: '', target: 80 }
  let showAssignTargetModal = false
  let isSubmitting = false
  let showExportModal = false
  let isExporting = false
  let exportForm = {
    start_date: '',
    end_date: '',
    format: 'excel'
  }
  let showStockModal = false

  let refreshInterval;
  let isInitialLoad = true;
  let refreshing = false;
  let retryCount = 0;
  const maxRetries = 3;

  onMount(() => {
    // All data including performance is already loaded from server
    // Just start auto-refresh cycle
    refreshInterval = setInterval(() => {
      refreshData()
    }, 30000)

    return () => {
      if (refreshInterval) {
        clearInterval(refreshInterval)
      }
    }
  })

  async function refreshData() {
    if (refreshing) return; // Prevent concurrent refreshes
    
    refreshing = true;
    try {
      const response = await axios.get('/admin/supervisor/warehouse-monitor/data')
      
      // Only update if we actually got data
      if (response.data) {
        // Check for error in response
        if (response.data.error) {
          console.warn('Data refresh warning:', response.data.error);
          // Don't update data if there's an error, keep existing data
          return;
        }
        
        // Update data only if we have valid responses
        if (Array.isArray(response.data.warehouseUsers)) {
          warehouseUsers = response.data.warehouseUsers;
        }
        if (Array.isArray(response.data.dailyTasks)) {
          dailyTasks = response.data.dailyTasks;
        }
        if (Array.isArray(response.data.recentActivities)) {
          recentActivities = response.data.recentActivities;
        }
        if (response.data.summary && typeof response.data.summary === 'object') {
          summary = { ...summary, ...response.data.summary };
        }
        if (response.data.stockInfo && typeof response.data.stockInfo === 'object') {
          stockInfo = response.data.stockInfo;
        }
        
        // Update performance data for current month only if month changed
        if (selectedMonth === new Date().toISOString().slice(0, 7)) {
          loadPerformanceData()
        }
      }
    } catch (error) {
      console.error('Error refreshing data:', error)
      // Retry logic
      if (retryCount < maxRetries) {
        retryCount++;
        console.log(`Retrying data refresh (${retryCount}/${maxRetries})`);
        setTimeout(() => refreshData(), 2000 * retryCount); // Exponential backoff
      } else {
        console.warn('Max retries reached, will try again in next cycle');
        retryCount = 0; // Reset for next cycle
      }
    } finally {
      refreshing = false;
      isInitialLoad = false;
    }
  }

  // Manual refresh function
  async function manualRefresh() {
    retryCount = 0; // Reset retry count for manual refresh
    await refreshData();
  }

  let performanceLoading = false;

  async function loadPerformanceData() {
    if (performanceLoading) return; // Prevent concurrent loads
    
    performanceLoading = true;
    loading = true;
    try {
      const response = await axios.get(`/admin/supervisor/warehouse-monitor/performance?month=${selectedMonth}`)
      
      if (response.data) {
        // Check for error in response
        if (response.data.error) {
          console.warn('Performance data warning:', response.data.error);
          return;
        }
        
        // Update performance data only if valid
        if (Array.isArray(response.data.stats)) {
          performanceStats = response.data.stats;
        }
        if (response.data.userPerformance && typeof response.data.userPerformance === 'object') {
          userPerformance = response.data.userPerformance;
        }
      }
    } catch (error) {
      console.error('Error loading performance data:', error)
      // Don't clear existing data on error
    } finally {
      loading = false;
      performanceLoading = false;
    }
  }

  function getStatusColor(status) {
    const colors = {
      'active': 'bg-green-100 text-green-800',
      'completed': 'bg-blue-100 text-blue-800',
      'expired': 'bg-gray-100 text-gray-800'
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
  }

  function getProgressColor(percentage) {
    if (percentage >= 80) return 'bg-green-500'
    if (percentage >= 50) return 'bg-yellow-500'
    return 'bg-red-500'
  }

  function formatTime(dateString) {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  }

  function formatDate(dateString) {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', { 
      day: 'numeric', 
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num || 0)
  }

  function getActivityIcon(type) {
    const icons = {
      'box_created': '📦',
      'box_sealed': '✅',
      'item_packed': '📋',
      'task_assigned': '🎯',
      'task_completed': '🏆'
    }
    return icons[type] || '📌'
  }

  function openAssignModal(userId = null) {
    if (userId) {
      assignTarget.user_id = userId.toString()
    }
    showAssignTargetModal = true
  }

  async function handleAssignTarget() {
    if (!assignTarget.user_id || !assignTarget.target) return

    isSubmitting = true
    try {
      const response = await axios.post('/admin/supervisor/assign-target', {
        user_id: parseInt(assignTarget.user_id),
        target: parseInt(assignTarget.target)
      })

      // Refresh data
      await refreshData()

      // Reset form and close modal
      assignTarget = { user_id: '', target: 80 }
      showAssignTargetModal = false

      // Show success message from server
      toast.success(response.data.message || 'Target berhasil ditetapkan!')
    } catch (error) {
      console.error('Error assigning target:', error)
      toast.error('Gagal menetapkan target: ' + (error.response?.data?.message || 'Unknown error'))
    } finally {
      isSubmitting = false
    }
  }

  async function exportPerformanceData() {
    if (!exportForm.start_date || !exportForm.end_date) return
    
    isExporting = true
    try {
      const response = await axios.post(
        '/admin/supervisor/warehouse-monitor/export', 
        exportForm,
        { responseType: 'blob' }
      )
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      
      // Get filename from response headers or use default
      const contentDisposition = response.headers['content-disposition']
      let filename = 'warehouse_performance.xlsx'
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/)
        if (filenameMatch) filename = filenameMatch[1]
      }
      
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      
      // Close modal
      showExportModal = false
      exportForm = { start_date: '', end_date: '', format: 'excel' }
    } catch (error) {
      console.error('Error exporting data:', error)
      toast.error('Gagal export data: ' + (error.response?.data?.message || 'Unknown error'))
    } finally {
      isExporting = false
    }
  }

  // Reactive statement for selected month
  $: if (selectedMonth) {
    loadPerformanceData()
  }
</script>

<AdminLayout>
  <!-- Page Header with consistent styling -->
  <div class="mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        <div class="flex items-center">
          <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Warehouse Monitor</h1>
            <p class="text-sm sm:text-base text-gray-500 mt-1">Monitor aktivitas packing warehouse real-time</p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 sm:gap-3">
          <button 
            on:click={manualRefresh}
            disabled={refreshing}
            class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm sm:text-base font-medium touch-manipulation"
            title="Refresh data manually"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5 {refreshing ? 'animate-spin' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span class="hidden sm:inline">{refreshing ? 'Refreshing...' : 'Refresh'}</span>
            <span class="sm:hidden">↻</span>
          </button>
          <button 
            on:click={() => showExportModal = true}
            class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm sm:text-base font-medium touch-manipulation"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="hidden sm:inline">Export Data</span>
            <span class="sm:hidden">Export</span>
          </button>
          <button 
            on:click={() => showStockModal = true}
            class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm sm:text-base font-medium touch-manipulation"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span class="hidden sm:inline">Stok Gudang</span>
            <span class="sm:hidden">Stok</span>
          </button>
          <button 
            on:click={() => openAssignModal()}
            class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors text-sm sm:text-base font-medium touch-manipulation"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span class="hidden sm:inline">Assign Target</span>
            <span class="sm:hidden">Target</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Summary Cards with consistent spacing and responsive design -->
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 md:gap-6 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between mb-4">
        <div class="text-xl md:text-2xl">📋</div>
        <span class="text-xs md:text-sm font-medium text-gray-600">Task Aktif</span>
      </div>
      <div>
        <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-1">{formatNumber(summary.total_active_tasks)}</h3>
        <p class="text-xs md:text-sm text-gray-600">Task Aktif</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between mb-4">
        <div class="text-xl md:text-2xl">📦</div>
        <span class="text-xs md:text-sm font-medium text-green-600">+0%</span>
      </div>
      <div>
        <h3 class="text-xl md:text-2xl font-bold text-green-600 mb-1">{formatNumber(summary.total_packed_today)}</h3>
        <p class="text-xs md:text-sm text-gray-600">Pack Hari Ini</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between mb-4">
        <div class="text-xl md:text-2xl">📊</div>
        <span class="text-xs md:text-sm font-medium text-gray-600">Bulan ini</span>
      </div>
      <div>
        <h3 class="text-xl md:text-2xl font-bold text-blue-600 mb-1">{formatNumber(summary.total_packed_this_month)}</h3>
        <p class="text-xs md:text-sm text-gray-600">Total Bulan</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between mb-4">
        <div class="text-xl md:text-2xl">📤</div>
        <span class="text-xs md:text-sm font-medium text-gray-600">Hari ini</span>
      </div>
      <div>
        <h3 class="text-xl md:text-2xl font-bold text-purple-600 mb-1">{formatNumber(summary.total_boxes_today)}</h3>
        <p class="text-xs md:text-sm text-gray-600">Box Hari Ini</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between mb-4">
        <div class="text-xl md:text-2xl">👤</div>
        <span class="text-xs md:text-sm font-medium text-gray-600">Avg</span>
      </div>
      <div>
        <h3 class="text-xl md:text-2xl font-bold text-indigo-600 mb-1">{formatNumber(Math.round(summary.average_per_user))}</h3>
        <p class="text-xs md:text-sm text-gray-600">Rata-rata/User</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between mb-4">
        <div class="text-xl md:text-2xl">⏳</div>
        <span class="text-xs md:text-sm font-medium text-orange-600">Carry over</span>
      </div>
      <div>
        <h3 class="text-xl md:text-2xl font-bold text-orange-600 mb-1">{formatNumber(summary.total_carry_over)}</h3>
        <p class="text-xs md:text-sm text-gray-600">Sisa Kemarin</p>
      </div>
    </div>
  </div>

  <!-- Main Content Grid with consistent spacing -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6 sm:mb-8">
    <!-- Active Tasks -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-4 sm:p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="text-lg md:text-xl font-semibold text-gray-900">Active Tasks Today</h2>
          {#if refreshing}
            <div class="flex items-center text-sm text-blue-600">
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Updating...
            </div>
          {/if}
        </div>
      </div>
      <div class="p-4 sm:p-6">
        {#if dailyTasks.length > 0}
          <div class="space-y-4">
            {#each dailyTasks as task}
              <div class="border border-gray-200 rounded-lg p-4 sm:p-5 hover:bg-gray-50 transition-colors">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-2 sm:space-y-0">
                  <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 text-base sm:text-lg">{task.user.name}</h3>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 text-sm text-gray-600 mt-1">
                      <span>Target: {task.target_quantity} mushaf</span>
                      {#if task.carry_over_quantity > 0}
                        <span class="text-orange-600 font-medium">+{task.carry_over_quantity} carry over</span>
                      {/if}
                    </div>
                  </div>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <span class={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(task.status)}`}>
                      {task.status}
                    </span>
                    <button
                      on:click={() => openAssignModal(task.user_id)}
                      class="text-blue-600 hover:text-blue-800 p-1 rounded transition-colors"
                      title="Update target"
                    >
                      <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                  </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="mb-4">
                  <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600 font-medium">Progress</span>
                    <span class="font-semibold text-gray-900">{task.packed_quantity} / {task.total_target}</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-3">
                    <div 
                      class={`h-3 rounded-full transition-all duration-300 ${getProgressColor(task.progress_percentage)}`}
                      style="width: {task.progress_percentage}%"
                    ></div>
                  </div>
                  <div class="text-right text-sm font-medium text-gray-700 mt-2">
                    {task.progress_percentage}%
                  </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-3 text-sm">
                  <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <div class="text-gray-600 text-xs mb-1">Boxes</div>
                    <div class="font-bold text-gray-900">{task.total_boxes}</div>
                  </div>
                  <div class="text-center p-3 bg-green-50 rounded-lg">
                    <div class="text-gray-600 text-xs mb-1">Sealed</div>
                    <div class="font-bold text-green-600">{task.sealed_boxes}</div>
                  </div>
                  <div class="text-center p-3 bg-orange-50 rounded-lg">
                    <div class="text-gray-600 text-xs mb-1">Remaining</div>
                    <div class="font-bold text-orange-600">{task.total_target - task.packed_quantity}</div>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-12 text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-lg font-medium text-gray-600 mb-2">Belum ada task aktif hari ini</p>
            <p class="text-sm text-gray-500 mb-6">Mulai dengan menetapkan target untuk warehouse staff</p>
            <button 
              on:click={() => openAssignModal()}
              class="inline-flex items-center gap-2 px-6 py-3 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors font-medium"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Assign Target Sekarang
            </button>
          </div>
        {/if}
      </div>
    </div>

    <!-- Recent Activities -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-4 sm:p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="text-lg md:text-xl font-semibold text-gray-900">Recent Activities</h2>
          {#if refreshing}
            <div class="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          {/if}
        </div>
      </div>
      <div class="p-4 sm:p-6">
        {#if recentActivities.length > 0}
          <div class="space-y-3 max-h-80 sm:max-h-96 overflow-y-auto">
            {#each recentActivities as activity}
              <div class="flex items-start gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                <span class="text-xl sm:text-2xl flex-shrink-0">{getActivityIcon(activity.type)}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-900 leading-relaxed">{activity.description}</p>
                  <p class="text-xs text-gray-500 mt-1">{formatDate(activity.created_at)}</p>
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-12 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-base text-gray-600">Belum ada aktivitas</p>
            <p class="text-sm text-gray-500 mt-1">Aktivitas warehouse akan muncul di sini</p>
          </div>
        {/if}
      </div>
    </div>
  </div>

  <!-- Performance Stats -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-4 sm:p-6 border-b border-gray-200">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-3 sm:space-y-0">
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">Performance Statistics</h2>
        <div class="flex items-center gap-2">
          <label for="month-period" class="text-sm text-gray-600 font-medium">Periode:</label>
          <input 
            id="month-period"
            type="month" 
            bind:value={selectedMonth}
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            max={new Date().toISOString().slice(0, 7)}
          >
        </div>
      </div>
    </div>
    <div class="p-4 sm:p-6">
      {#if loading}
        <div class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#eb3434]"></div>
          <p class="text-gray-500 mt-3 font-medium">Loading performance data...</p>
        </div>
      {:else if performanceStats.length > 0}
        <div class="overflow-x-auto -mx-4 sm:mx-0">
          <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                  <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Packed</th>
                  <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Boxes</th>
                  <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Avg/Day</th>
                  <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Best Day</th>
                  <th class="px-4 sm:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Achievement</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                {#each performanceStats as stat}
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{stat.user_name}</div>
                    </td>
                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                      <span class="text-sm font-bold text-green-600">{formatNumber(stat.total_packed)}</span>
                    </td>
                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                      {formatNumber(stat.total_boxes)}
                    </td>
                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                      {formatNumber(Math.round(stat.avg_per_day))}
                    </td>
                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                      <div class="text-sm">
                        <div class="font-medium text-gray-900">{formatNumber(stat.best_day_quantity)}</div>
                        <div class="text-xs text-gray-500">
                          {stat.best_day_date ? new Date(stat.best_day_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '-'}
                        </div>
                      </div>
                    </td>
                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center">
                      <div class="flex items-center justify-center">
                        {#if stat.achievement_rate >= 100}
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            🏆 {Math.round(stat.achievement_rate)}%
                          </span>
                        {:else if stat.achievement_rate >= 80}
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            ⭐ {Math.round(stat.achievement_rate)}%
                          </span>
                        {:else}
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            {Math.round(stat.achievement_rate)}%
                          </span>
                        {/if}
                      </div>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Monthly Summary -->
        {#if userPerformance.summary}
          <div class="mt-6 p-4 sm:p-6 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Monthly Summary</h3>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
              <div class="text-center">
                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-2">Total Tasks</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900">{formatNumber(userPerformance.summary.total_tasks)}</p>
              </div>
              <div class="text-center">
                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-2">Completed</p>
                <p class="text-xl sm:text-2xl font-bold text-green-600">{formatNumber(userPerformance.summary.completed_tasks)}</p>
              </div>
              <div class="text-center">
                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-2">Total Mushaf</p>
                <p class="text-xl sm:text-2xl font-bold text-blue-600">{formatNumber(userPerformance.summary.total_mushaf)}</p>
              </div>
              <div class="text-center">
                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-2">Completion Rate</p>
                <p class="text-xl sm:text-2xl font-bold text-purple-600">{Math.round(userPerformance.summary.completion_rate)}%</p>
              </div>
            </div>
          </div>
        {/if}
      {:else}
        <div class="text-center py-12 text-gray-500">
          <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p class="text-lg font-medium text-gray-600 mb-2">Tidak ada data performance</p>
          <p class="text-sm text-gray-500">Data untuk bulan yang dipilih belum tersedia</p>
        </div>
      {/if}
    </div>
  </div>

  <!-- Export Modal -->
  {#if showExportModal}
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-[9998] p-4" role="button" tabindex="0" aria-label="Tutup export modal" on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showExportModal = false; } }}>
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-semibold text-gray-900">Export Performance Data</h3>
          <button 
            on:click={() => showExportModal = false}
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <div class="space-y-5">
          <div>
            <label for="export-start-date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
            <input 
              id="export-start-date"
              type="date" 
              bind:value={exportForm.start_date}
              max={new Date().toISOString().split('T')[0]}
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
            >
          </div>
          
          <div>
            <label for="export-end-date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
            <input 
              id="export-end-date"
              type="date" 
              bind:value={exportForm.end_date}
              max={new Date().toISOString().split('T')[0]}
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
            >
          </div>
          
          <div>
            <label for="export-format" class="block text-sm font-medium text-gray-700 mb-2">Format</label>
            <select 
              id="export-format"
              bind:value={exportForm.format}
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
            >
              <option value="excel">Excel (.xlsx)</option>
              <option value="csv">CSV (.csv)</option>
            </select>
          </div>
        </div>
        
        <div class="mt-8 flex gap-3">
          <button 
            on:click={() => showExportModal = false}
            class="flex-1 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium"
            disabled={isExporting}
          >
            Batal
          </button>
          <button
            on:click={exportPerformanceData}
            disabled={!exportForm.start_date || !exportForm.end_date || isExporting}
            class="flex-1 py-3 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
          >
            {isExporting ? 'Exporting...' : 'Export Data'}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Assign Target Modal -->
  {#if showAssignTargetModal}
    <div class="fixed inset-0 z-[9998] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div 
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
          role="button"
          tabindex="0"
          aria-label="Tutup assign target"
          on:click={() => showAssignTargetModal = false}
          on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showAssignTargetModal = false; } }}
        ></div>

        <!-- Modal panel -->
        <div class="relative inline-block align-bottom bg-white rounded-xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-8">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
              <div class="flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mr-4">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-semibold text-gray-900">
                  Assign Target Harian
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                  Berikan target packing harian untuk warehouse staff
                </p>
              </div>
            </div>
            <button 
              on:click={() => showAssignTargetModal = false}
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <p class="text-sm text-blue-700">
              📝 <strong>Catatan:</strong> Sistem akan otomatis menambahkan carry-over dari task sebelumnya jika ada.
            </p>
          </div>

          <form on:submit|preventDefault={handleAssignTarget} class="space-y-6">
            <!-- User Selection -->
            <div>
              <label for="assign-user" class="block text-sm font-medium text-gray-700 mb-2">
                Pilih User
              </label>
              <select 
                id="assign-user"
                bind:value={assignTarget.user_id}
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors text-base"
                required
              >
                <option value="" class="text-gray-500">-- Pilih User --</option>
                {#each warehouseUsers as user}
                  <option value={user.id}>{user.name}</option>
                {/each}
              </select>
            </div>

            <!-- Target Input -->
            <div>
              <label for="target-mushaf" class="block text-sm font-medium text-gray-700 mb-2">
                Target Mushaf
              </label>
              <input 
                id="target-mushaf"
                type="number" 
                bind:value={assignTarget.target}
                min="1"
                max="200"
                placeholder="80"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors text-base"
                required
              >
              <div class="mt-2 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-xs text-gray-600">
                  Target standar: 80 mushaf per hari
                </p>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 pt-4">
              <button
                type="button"
                on:click={() => showAssignTargetModal = false}
                class="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors font-medium"
                disabled={isSubmitting}
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={isSubmitting || !assignTarget.user_id || !assignTarget.target}
                class="flex-1 px-6 py-3 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
              >
                {isSubmitting ? 'Menyimpan...' : 'Simpan Target'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  {/if}

  <!-- Stock Modal -->
  {#if showStockModal}
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-[9998] p-4">
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-6xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 rounded-lg">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <div>
              <h3 class="text-2xl font-semibold text-gray-900">Stok Gudang & Kebutuhan Pembelian</h3>
              <p class="text-sm text-gray-600 mt-1">Monitor ketersediaan stok dan rekomendasi pembelian</p>
            </div>
          </div>
          <button 
            on:click={() => showStockModal = false}
            class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-lg"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        {#if stockInfo.details && stockInfo.details.length > 0}
          <!-- Stock Table -->
          <div class="overflow-x-auto mb-8 -mx-6 sm:mx-0">
            <div class="inline-block min-w-full align-middle">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Siap Pack</th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Siap Distribusi</th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Terdistribusi</th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Terhimpun</th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rekomendasi Beli</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                {#each stockInfo.details as item}
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{item.jenis_name}</div>
                      <div class="text-sm text-gray-500">{item.jenis_code}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <span class={`text-sm font-bold ${item.is_low_stock ? 'text-red-600' : 'text-green-600'}`}>
                        {formatNumber(item.current_stock)}
                      </span>
                      {#if item.is_low_stock}
                        <div class="text-xs text-red-500">Stok Rendah!</div>
                      {/if}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-blue-600 font-medium">
                      {formatNumber(item.in_process || 0)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                      {formatNumber(item.distributed)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-medium">
                      {formatNumber(item.total_collected)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      {#if item.recommended_purchase > 0}
                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                          {formatNumber(item.recommended_purchase)}
                        </span>
                      {:else}
                        <span class="text-sm text-gray-500">-</span>
                      {/if}
                    </td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-gray-100">
                <tr>
                  <td class="px-6 py-3 font-medium text-gray-900">Total</td>
                  <td class="px-6 py-3 text-center font-bold text-gray-900">
                    {formatNumber(stockInfo.totals.total_stock)}
                  </td>
                  <td class="px-6 py-3 text-center font-medium text-blue-600">
                    {formatNumber(stockInfo.totals.total_in_process || 0)}
                  </td>
                  <td class="px-6 py-3 text-center font-medium text-gray-600">
                    {formatNumber(stockInfo.totals.total_distributed)}
                  </td>
                  <td class="px-6 py-3 text-center font-medium text-gray-900">
                    {formatNumber(stockInfo.totals.total_collected)}
                  </td>
                  <td class="px-6 py-3 text-center">
                    {#if stockInfo.totals.total_recommended_purchase > 0}
                      <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                        {formatNumber(stockInfo.totals.total_recommended_purchase)}
                      </span>
                    {:else}
                      <span class="text-sm text-gray-500">-</span>
                    {/if}
                  </td>
                </tr>
              </tfoot>
              </table>
            </div>
          </div>
          
          <!-- Info Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 sm:p-5 border border-blue-200">
              <div class="flex items-center mb-3">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h4 class="text-sm font-semibold text-blue-800">Keterangan Status</h4>
              </div>
              <div class="text-xs text-blue-700 space-y-2">
                <p>• <strong>Stok Siap Pack</strong>: Status kedatangan</p>
                <p>• <strong>Siap Distribusi</strong>: Selesai packing</p>
                <p>• <strong>Terdistribusi</strong>: Sudah diterima penerima</p>
              </div>
            </div>
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 sm:p-5 border border-yellow-200">
              <div class="flex items-center mb-3">
                <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <h4 class="text-sm font-semibold text-yellow-800">Rekomendasi Pembelian</h4>
              </div>
              <p class="text-xs text-yellow-700">
                Dihitung jika stok kurang dari 100 unit
              </p>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 sm:p-5 border border-green-200">
              <div class="flex items-center mb-3">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h4 class="text-sm font-semibold text-green-800">Total Terhimpun</h4>
              </div>
              <p class="text-xs text-green-700">
                Total Quran/Iqra yang sudah terkumpul dari donasi wakaf (dari semua donatur)
              </p>
            </div>
          </div>
          
          <div class="flex justify-between items-center pt-4 border-t border-gray-200">
            <div class="text-sm text-gray-500">
              🕰️ Terakhir diperbarui: <span class="font-medium">{stockInfo.last_updated}</span>
            </div>
            <button 
              on:click={() => showStockModal = false}
              class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium"
            >
              Tutup
            </button>
          </div>
        {:else}
          <div class="text-center py-16 text-gray-500">
            <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="text-lg font-medium text-gray-600 mb-2">Tidak ada data stok tersedia</p>
            <p class="text-sm text-gray-500 mb-8">Data stok belum tersedia atau sedang dimuat</p>
            <button 
              on:click={() => showStockModal = false}
              class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium"
            >
              Tutup
            </button>
          </div>
        {/if}
      </div>
    </div>
  {/if}

</AdminLayout>
