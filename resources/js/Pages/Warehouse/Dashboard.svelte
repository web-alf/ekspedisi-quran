<script>
  import { onMount } from 'svelte';
  import { page } from '@inertiajs/svelte';
  import { router } from '@inertiajs/svelte';
  import { showError } from '../../stores/toast.js';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import SharedBoxCollaboration from '../../Components/SharedBoxCollaboration.svelte';
  import SealReadyBoxes from '../../Components/SealReadyBoxes.svelte';
  
  // Props - FIXED: Added missing props
  export let todayTask = null;
  export let activeBox = null;
  export let notifications = [];
  export let performanceData = {};
  // Unused incoming props removed to silence build warnings
  export let currentTime = '';
  export let currentDate = '';
  
  export let sharedBoxes = [];  // ADDED for collaboration
  
  // State
  let showNotifications = false;
  let isStartingTask = false;
  let showSharedBoxCollaboration = false;
  
  // Progress colors
  function getProgressColor(percentage) {
    if (percentage >= 80) return 'bg-green-600';
    if (percentage >= 60) return 'bg-yellow-500';
    if (percentage >= 40) return 'bg-orange-500';
    return 'bg-red-500';
  }
  
  // Get status badge
  function getStatusBadge(status) {
    const badges = {
      'assigned': { text: 'Belum Mulai', class: 'bg-gray-100 text-gray-800' },
      'in_progress': { text: 'Sedang Berjalan', class: 'bg-blue-100 text-blue-800' },
      'completed': { text: 'Selesai', class: 'bg-green-100 text-green-800' },
      'expired': { text: 'Kadaluarsa', class: 'bg-red-100 text-red-800' }
    };
    return badges[status] || badges.assigned;
  }
  
  // Start scanning
  async function startScanning() {
    if (isStartingTask) return;
    
    isStartingTask = true;
    try {
      const response = await fetch('/admin/warehouse/start-scanning', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Redirect to packing page
        router.visit('/admin/warehouse/packing');
      } else {
        showError('Gagal Memulai Scanning', data.message || 'Gagal memulai scanning');
      }
    } catch (error) {
      console.error('Error starting task:', error);
      showError('Kesalahan', 'Terjadi kesalahan saat memulai tugas');
    } finally {
      isStartingTask = false;
    }
  }
  
  // Mark notification as read
  async function markNotificationRead(notificationId) {
    try {
      await fetch(`/admin/warehouse/notification/${notificationId}/read`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      // Remove from list
      notifications = notifications.filter(n => n.id !== notificationId);
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  }
  
  // Real-time updates with smart refresh
  let isRefreshing = false;
  let refreshInterval;
  let lastUpdate = null;
  
  // Auto refresh with real-time API endpoint
  onMount(() => {
    refreshInterval = setInterval(async () => {
      await updateDashboardStatus();
    }, 15000); // 15 seconds for better real-time feeling
    
    return () => {
      if (refreshInterval) {
        clearInterval(refreshInterval);
      }
    };
  });
  
  async function updateDashboardStatus() {
    if (isRefreshing) return;
    
    isRefreshing = true;
    try {
      const response = await fetch('/admin/warehouse/dashboard-status', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          const data = result.data;
          
          // Update task data if changed
          if (data.todayTask) {
            // Check if task progress changed
            if (!todayTask || todayTask.progress_percentage !== data.todayTask.progress_percentage) {
              todayTask = data.todayTask;
            }
          }
          
          // Update active box if changed
          if (data.activeBox) {
            if (!activeBox || activeBox.progress_percentage !== data.activeBox.progress_percentage) {
              activeBox = data.activeBox;
            }
          }
          
          // Update shared boxes data
          if (data.sharedBoxes && data.sharedBoxes.length > 0) {
            sharedBoxes = data.sharedBoxes;
          }
          
          // Update notifications count
          if (data.notificationsCount !== undefined) {
            // Update notification badge (we could add a reactive variable for this)
            const notificationBadge = document.querySelector('.notification-badge');
            if (notificationBadge) {
              notificationBadge.textContent = data.notificationsCount;
              notificationBadge.style.display = data.notificationsCount > 0 ? 'flex' : 'none';
            }
          }
          
          lastUpdate = new Date(data.timestamp);
        }
      } else {
        console.warn('Failed to refresh dashboard status:', response.status);
      }
    } catch (error) {
      console.error('Error refreshing dashboard status:', error);
    } finally {
      isRefreshing = false;
    }
  }
  
  // Manual refresh function for button
  async function manualRefresh() {
    await updateDashboardStatus();
    // Show brief feedback
    const refreshBtn = document.querySelector('.refresh-btn');
    if (refreshBtn) {
      refreshBtn.classList.add('animate-spin');
      setTimeout(() => {
        refreshBtn.classList.remove('animate-spin');
      }, 1000);
    }
  }
</script>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header - Responsive -->
      <div class="mb-6 sm:mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="text-center sm:text-left">
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">Dashboard Gudang</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1">{currentDate} • {currentTime}</p>
          </div>
          <div class="flex items-center justify-center sm:justify-end space-x-2 sm:space-x-3">
            <!-- System Online Status - Responsive -->
            <div class="inline-flex items-center px-2 sm:px-3 py-2 bg-green-100 text-green-800 rounded-lg text-xs sm:text-sm font-medium">
              <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2"></div>
              <span class="hidden sm:inline">System Online</span>
              <span class="sm:hidden">Online</span>
            </div>
            
            <!-- Notifications button - Responsive -->
            <div class="relative">
              <button
                on:click={() => showNotifications = !showNotifications}
                class="relative p-2 bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 border border-gray-100 hover:border-gray-200"
              >
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                {#if notifications.length > 0}
                  <span class="notification-badge absolute -top-2 -right-2 h-4 w-4 sm:h-5 sm:w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                    {notifications.length}
                  </span>
                {/if}
              </button>
            
              {#if showNotifications}
                <div class="notification-dropdown absolute right-0 mt-2 w-72 sm:w-80 bg-white rounded-lg shadow-lg z-50">
                  <div class="p-3 sm:p-4 border-b">
                    <h3 class="font-semibold text-sm sm:text-base">Notifikasi</h3>
                  </div>
                  <div class="max-h-64 sm:max-h-96 overflow-y-auto">
                    {#if notifications.length > 0}
                      {#each notifications as notification}
                        <button type="button" class="p-3 sm:p-4 w-full text-left border-b hover:bg-gray-50" on:click={() => markNotificationRead(notification.id)}>
                          <div class="flex items-start">
                            <div class="flex-1">
                              <p class="font-medium text-xs sm:text-sm">{notification.title}</p>
                              <p class="text-xs sm:text-sm text-gray-600 mt-1">{notification.message}</p>
                              <p class="text-xs text-gray-400 mt-1">{new Date(notification.created_at).toLocaleString('id-ID')}</p>
                            </div>
                          </div>
                        </button>
                      {/each}
                    {:else}
                      <div class="p-4 text-center text-gray-500">
                        <p class="text-sm">Tidak ada notifikasi</p>
                      </div>
                    {/if}
                  </div>
                </div>
              {/if}
            </div>
          </div>
        </div>
    </div>
    
    <!-- Today's Task Section - Responsive -->
    <div class="bg-white rounded-lg sm:rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
      <div class="flex items-center justify-center sm:justify-start mb-4 sm:mb-6">
        <div class="flex items-center space-x-2 sm:space-x-3">
          <div class="text-xl sm:text-2xl">📝</div>
          <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Tugas Hari Ini</h2>
        </div>
      </div>
          {#if todayTask}
            <div class="text-center">
              <!-- Status Badge -->
              <div class="mb-6">
                <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium {getStatusBadge(todayTask.status).class}">
                  {getStatusBadge(todayTask.status).text}
                </span>
              </div>
              
              <!-- Progress Display - Responsive -->
              <div class="mb-4 sm:mb-6">
                <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">
                  {todayTask.total_selesai}<span class="text-lg sm:text-xl text-gray-500">/{todayTask.total_target}</span>
                </div>
                <div class="text-base sm:text-lg text-gray-600 mb-3 sm:mb-4">
                  <span class="font-semibold">{todayTask.progress_percentage}%</span> Progress Completed
                </div>
                
                <!-- Progress Bar - Responsive -->
                <div class="max-w-xs sm:max-w-md mx-auto">
                  <div class="w-full bg-gray-200 rounded-full h-2 sm:h-3 mb-2">
                    <div 
                      class="h-2 sm:h-3 rounded-full transition-all duration-500 {getProgressColor(todayTask.progress_percentage)}"
                      style="width: {todayTask.progress_percentage}%"
                    ></div>
                  </div>
                  <div class="flex justify-between text-xs sm:text-sm text-gray-500">
                    <span>0 mushaf</span>
                    <span>{todayTask.total_target} mushaf</span>
                  </div>
                </div>
              </div>
              
              <!-- Action Button - Responsive -->
              {#if todayTask.is_completed}
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 sm:p-6">
                  <div class="text-3xl sm:text-4xl mb-2">🎉</div>
                  <p class="text-green-800 font-semibold text-base sm:text-lg mb-1">Selamat! Tugas hari ini telah selesai!</p>
                  <p class="text-sm sm:text-base text-green-600">Total: <span class="font-semibold">{todayTask.total_selesai} mushaf</span> berhasil dipacking</p>
                </div>
              {:else}
                <button
                  on:click={startScanning}
                  disabled={isStartingTask}
                  class="inline-flex items-center px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base w-full sm:w-auto justify-center"
                >
                  {#if isStartingTask}
                    <svg class="animate-spin -ml-1 mr-2 sm:mr-3 h-4 w-4 sm:h-6 sm:w-6 text-white" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memulai...
                  {:else}
                    <svg class="w-4 h-4 sm:w-6 sm:h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {todayTask.status === 'assigned' ? 'Mulai Packing' : 'Lanjutkan Packing'}
                  {/if}
                </button>
              {/if}
            </div>
          {:else}
            <div class="text-center py-8 sm:py-12">
              <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full mb-4 sm:mb-6">
                <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
              </div>
              <div class="text-gray-900 text-lg sm:text-xl font-bold mb-2 sm:mb-3">Tidak ada tugas untuk hari ini</div>
              <div class="text-gray-600 text-sm sm:text-lg px-4">
                {#if new Date().getDay() === 0 || new Date().getDay() === 6}
                  🌴 Hari libur - tidak ada penugasan
                {:else}
                  📞 Hubungi supervisor jika ada kesalahan
                {/if}
              </div>
            </div>
          {/if}
    </div>
    
    <!-- Performance Stats Grid - Responsive -->
    <div class="performance-stats grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
      <!-- This Week Performance -->
      <div class="bg-white rounded-lg sm:rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 hover:shadow-md transition-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6">
          <div class="flex items-center space-x-2 sm:space-x-3 mb-2 sm:mb-0">
            <div class="text-xl sm:text-2xl">📊</div>
            <div>
              <h3 class="text-base sm:text-lg font-semibold text-gray-900">Performa Minggu Ini</h3>
              <p class="text-xs sm:text-sm text-gray-500">7 hari terakhir</p>
            </div>
          </div>
        </div>
        
        <div class="space-y-3 sm:space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Target</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.total_target || (todayTask ? todayTask.total_target : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Selesai</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.total_achieved || (todayTask ? todayTask.total_selesai : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Completion Rate</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.completion_rate || (todayTask ? todayTask.progress_percentage : 0)}%</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Hari Kerja</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.days_worked || 1} hari</span>
          </div>
        </div>
      </div>
      
      <!-- This Month Performance -->
      <div class="bg-white rounded-lg sm:rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 hover:shadow-md transition-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6">
          <div class="flex items-center space-x-2 sm:space-x-3 mb-2 sm:mb-0">
            <div class="text-xl sm:text-2xl">📅</div>
            <div>
              <h3 class="text-base sm:text-lg font-semibold text-gray-900">Performa Bulan Ini</h3>
              <p class="text-xs sm:text-sm text-gray-500">30 hari terakhir</p>
            </div>
          </div>
        </div>
        
        <div class="space-y-3 sm:space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Target</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.total_target || (todayTask ? todayTask.total_target : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Selesai</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.total_achieved || (todayTask ? todayTask.total_selesai : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Completion Rate</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.completion_rate || (todayTask ? todayTask.progress_percentage : 0)}%</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Perfect Days</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.perfect_days || 0} hari</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>

<style>
  /* Ensure notification dropdown doesn't get cut off */
  .relative {
    position: relative;
  }
  
  /* Enhanced responsive adjustments */
  @media (max-width: 640px) {
    /* Mobile optimizations */
    .notification-dropdown {
      width: calc(100vw - 2rem);
      right: -1rem;
      max-width: 320px;
    }
    
    /* Touch-friendly buttons */
    button {
      min-height: 44px;
      touch-action: manipulation;
    }
    
    /* Better spacing on mobile */
    .grid-cols-1 {
      gap: 1rem;
    }
    
    /* Prevent horizontal scroll */
    .max-w-7xl {
      max-width: 100%;
    }
    
    /* Optimize text sizes */
    .text-3xl {
      font-size: 1.75rem;
    }
    
    .text-xl {
      font-size: 1.125rem;
    }
  }
  
  @media (min-width: 640px) and (max-width: 1024px) {
    /* Tablet optimizations */
    .performance-stats {
      grid-template-columns: 1fr;
      gap: 1.5rem;
    }
  }
  
  @media (min-width: 1024px) {
    /* Desktop optimizations */
    .performance-stats {
      grid-template-columns: repeat(2, 1fr);
      gap: 2rem;
    }
  }
  
  /* Progress bar animations */
  .transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
  }
  
  /* Enhanced visual hierarchy */
  .performance-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(249,250,251,0.9) 100%);
    backdrop-filter: blur(10px);
  }
</style>
