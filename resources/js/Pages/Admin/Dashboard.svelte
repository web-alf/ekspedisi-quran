<script>
  import { onMount, onDestroy, afterUpdate, tick } from 'svelte';
  import { fade, scale } from 'svelte/transition';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import { logout } from '../../utils/auth.js';
  import { hasRole, can } from '../../utils/permissions.js';
  import { ROLES } from '../../constants/roles.js';

  // Lazy chart component
  import LazyChart from '../../Components/LazyChart.svelte';
  let chartsVisible = false;

  // Props from controller
  export let auth = {};
  export let stats = {};
  export let activities = [];
  export let chartsData = {};

  // Derived stats for display
  $: dynamicStats = getDynamicStats();
  $: quickActions = getQuickActionsForRole();
  $: statusSummary = getStatusSummary();
  $: userPermissions = auth.user?.permissions || [];
  $: canViewCharts = userPermissions.includes('dashboard.analytics') || hasRole(ROLES.SUPER_ADMIN);

  // Auto-refresh state management
  let showLogoutModal = false;
  let refreshInterval;
  let isRefreshing = false;
  let isUserInteracting = false;
  let lastRefreshTime = new Date();
  let refreshCountdown = 30;
  let countdownInterval;
  let autoRefreshEnabled = true;

  // Auto-refresh settings
  const REFRESH_INTERVAL = 30000; // 30 seconds
  const INTERACTION_PAUSE_DURATION = 60000; // 1 minute pause after interaction

  // Initialize auto-refresh on mount
  onMount(async () => {
    
    // Wait for DOM to be ready
    await tick();
    
    // Show charts if user has permission and data exists
    const userPermissions = auth.user?.permissions || [];
    const hasAnalyticsPermission = userPermissions.includes('dashboard.analytics');
    const isSuperAdmin = hasRole(ROLES.SUPER_ADMIN);

    if (chartsData && Object.keys(chartsData).length > 0 &&
        (hasAnalyticsPermission || isSuperAdmin)) {
      chartsVisible = true;
    }
    
    setupAutoRefresh();
    setupUserInteractionDetection();
    startCountdown();
  });

  // Update charts when data changes - but prevent unnecessary re-renders
  let lastChartsDataString = '';
  let initializationTimeout = null;
  
  // Update charts visibility when data changes
  $: {
    const userPermissions = auth.user?.permissions || [];
    const hasAnalyticsPermission = userPermissions.includes('dashboard.analytics');
    const isSuperAdmin = auth.user?.role === 'super-admin';
    
    if (chartsData && Object.keys(chartsData).length > 0 && 
         (hasAnalyticsPermission || isSuperAdmin)) {
      chartsVisible = true;
    } else {
      chartsVisible = false;
    }
  }

  // Reactive chart data for LazyChart components
  $: monthlyShipmentsChartData = prepareMonthlyShipmentsData();
  $: monthlyRequestsChartData = prepareMonthlyRequestsData();
  $: statusDistributionChartData = prepareStatusDistributionData();
  $: dailyActivitiesChartData = prepareDailyActivitiesData();

  // Clean up intervals on destroy
  onDestroy(() => {
    if (refreshInterval) clearInterval(refreshInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    if (initializationTimeout) clearTimeout(initializationTimeout);
    
    // Charts are handled by LazyChart components
    chartsVisible = false;
  });


  // Data preparation functions for LazyChart components
  function prepareMonthlyShipmentsData() {
    if (!chartsData?.monthlyShipments?.length) return { labels: [], datasets: [] };
    
    const validData = chartsData.monthlyShipments.filter(d => d && typeof d.count === 'number' && !isNaN(d.count));
    return {
      labels: validData.map(d => d.month || 'Unknown'),
      datasets: [{
        label: 'Pengiriman per Bulan',
        data: validData.map(d => d.count || 0),
        borderColor: '#eb3434',
        backgroundColor: 'rgba(235, 52, 52, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#eb3434',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 6,
        pointHoverRadius: 8
      }]
    };
  }

  function prepareMonthlyRequestsData() {
    if (!chartsData?.monthlyMushafRequests?.length) return { labels: [], datasets: [] };
    
    const validData = chartsData.monthlyMushafRequests.filter(d => d && typeof d.count === 'number' && !isNaN(d.count));
    return {
      labels: validData.map(d => d.month || 'Unknown'),
      datasets: [{
        label: 'Permintaan Mushaf',
        data: validData.map(d => d.count || 0),
        backgroundColor: [
          'rgba(59, 130, 246, 0.8)',
          'rgba(16, 185, 129, 0.8)',
          'rgba(245, 158, 11, 0.8)',
          'rgba(139, 92, 246, 0.8)',
          'rgba(239, 68, 68, 0.8)',
          'rgba(107, 114, 128, 0.8)'
        ],
        borderColor: [
          '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#6b7280'
        ],
        borderWidth: 2,
        borderRadius: 8
      }]
    };
  }

  function prepareStatusDistributionData() {
    if (!chartsData?.statusDistribution?.length) return { labels: [], datasets: [] };
    
    const validData = chartsData.statusDistribution.filter(d => d && typeof d.count === 'number' && !isNaN(d.count) && d.count > 0);
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#6b7280'];
    
    return {
      labels: validData.map(d => d.name || 'Unknown'),
      datasets: [{
        data: validData.map(d => d.count || 0),
        backgroundColor: colors.slice(0, validData.length),
        borderColor: '#ffffff',
        borderWidth: 3,
        hoverOffset: 10
      }]
    };
  }

  function prepareDailyActivitiesData() {
    if (!chartsData?.dailyActivities?.length) return { labels: [], datasets: [] };
    
    const validData = chartsData.dailyActivities.filter(d => d && 
      (typeof d.shipments === 'number' || d.shipments === null || d.shipments === undefined) &&
      (typeof d.requests === 'number' || d.requests === null || d.requests === undefined) &&
      (typeof d.completed_shipments === 'number' || d.completed_shipments === null || d.completed_shipments === undefined)
    );
    
    return {
      labels: ['6 hari lalu', '5 hari lalu', '4 hari lalu', '3 hari lalu', '2 hari lalu', 'Kemarin', 'Hari ini'],
      datasets: [
        {
          label: 'Sedang Dikirim',
          data: validData.map(d => d.shipments || 0),
          backgroundColor: 'rgba(235, 52, 52, 0.8)',
          borderColor: '#eb3434',
          borderWidth: 1,
          borderRadius: 6
        },
        {
          label: 'Pengiriman Selesai',
          data: validData.map(d => d.completed_shipments || 0),
          backgroundColor: 'rgba(16, 185, 129, 0.8)',
          borderColor: '#10b981',
          borderWidth: 1,
          borderRadius: 6
        },
        {
          label: 'Permintaan',
          data: validData.map(d => d.requests || 0),
          backgroundColor: 'rgba(59, 130, 246, 0.8)',
          borderColor: '#3b82f6',
          borderWidth: 1,
          borderRadius: 6
        }
      ]
    };
  }


  function setupAutoRefresh() {
    refreshInterval = setInterval(() => {
      if (autoRefreshEnabled && !isUserInteracting && !isRefreshing) {
        refreshData();
      }
    }, REFRESH_INTERVAL);
  }

  function setupUserInteractionDetection() {
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    
    const handleUserInteraction = () => {
      isUserInteracting = true;
      // Reset interaction state after pause duration
      setTimeout(() => {
        isUserInteracting = false;
      }, INTERACTION_PAUSE_DURATION);
    };

    events.forEach(event => {
      window.addEventListener(event, handleUserInteraction);
    });

    // Clean up event listeners
    return () => {
      events.forEach(event => {
        window.removeEventListener(event, handleUserInteraction);
      });
    };
  }

  function startCountdown() {
    countdownInterval = setInterval(() => {
      if (autoRefreshEnabled && !isUserInteracting && !isRefreshing) {
        refreshCountdown--;
        if (refreshCountdown <= 0) {
          refreshCountdown = 30;
        }
      } else {
        refreshCountdown = 30; // Reset countdown when paused
      }
    }, 1000);
  }

  function refreshData() {
    if (isRefreshing) return;
    
    isRefreshing = true;
    lastRefreshTime = new Date();
    refreshCountdown = 30;

    router.reload({
      only: ['stats', 'activities', 'chartsData'],
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        isRefreshing = false;
        // Charts update automatically via reactive LazyChart components
      },
      onError: () => {
        isRefreshing = false;
        console.error('Failed to refresh dashboard data');
      }
    });
  }

  function manualRefresh() {
    refreshData();
  }

  function toggleAutoRefresh() {
    autoRefreshEnabled = !autoRefreshEnabled;
    if (!autoRefreshEnabled) {
      refreshCountdown = 30;
    }
  }

  // Format stats for display based on user role
  function getDynamicStats() {
    const role = auth.user?.role || 'super-admin';
    
    switch (role) {
      case 'super-admin':
        return [
          {
            title: 'Total Penghimpunan Quran',
            value: formatNumber(stats.totalPengiriman || 0),
            change: `+${stats.monthlyPengiriman || 0} bulan ini`,
            trend: 'up',
            icon: '📦'
          },
          {
            title: 'Mushaf Tersalurkan',
            value: formatNumber(stats.totalMushafDistributed || 0),
            change: stats.totalMushafDistributed === 0 ? 'Belum ada yang diterima' : 'Sudah diterima penerima',
            trend: stats.totalMushafDistributed === 0 ? 'neutral' : 'up',
            icon: '📖'
          },
          {
            title: 'Total Donatur',
            value: formatNumber(stats.totalDonatur || 0),
            change: 'Donatur terdaftar',
            trend: 'up',
            icon: '👥'
          },
          {
            title: 'Permintaan Mushaf',
            value: formatNumber(stats.totalMushafRequests || 0),
            change: `+${stats.monthlyMushafRequests || 0} bulan ini`,
            trend: 'up',
            icon: '📋'
          }
        ];
      
      case 'customer-service':
        return [
          {
            title: 'Permintaan Pending',
            value: formatNumber(stats.pendingMushafRequests || 0),
            change: 'Menunggu review',
            trend: 'neutral',
            icon: '⏳'
          },
          {
            title: 'Permintaan Disetujui',
            value: formatNumber(stats.approvedMushafRequests || 0),
            change: 'Sudah disetujui',
            trend: 'up',
            icon: '✅'
          },
          {
            title: 'Total Donatur',
            value: formatNumber(stats.totalDonatur || 0),
            change: 'Donatur terdaftar',
            trend: 'up',
            icon: '👥'
          },
          {
            title: 'Bulan Ini',
            value: formatNumber(stats.monthlyMushafRequests || 0),
            change: 'Permintaan baru',
            trend: 'up',
            icon: '📅'
          }
        ];
      
      case 'warehouse':
        return [
          {
            title: 'Pending Shipments',
            value: formatNumber(stats.pendingShipments || 0),
            change: 'Siap dikemas',
            trend: 'neutral',
            icon: '📦'
          },
          {
            title: 'Dalam Perjalanan',
            value: formatNumber(stats.inTransitShipments || 0),
            change: 'Sedang dikirim',
            trend: 'up',
            icon: '🚚'
          },
          {
            title: 'Mushaf Tersalurkan',
            value: formatNumber(stats.totalMushafDistributed || 0),
            change: 'Total terdistribusi',
            trend: 'up',
            icon: '📖'
          },
          {
            title: 'Bulan Ini',
            value: formatNumber(stats.monthlyPengiriman || 0),
            change: 'Pengiriman baru',
            trend: 'up',
            icon: '📅'
          }
        ];
      
      case 'courier':
        return [
          {
            title: 'Pending Delivery',
            value: formatNumber(stats.pendingShipments || 0),
            change: 'Siap diantar',
            trend: 'neutral',
            icon: '📦'
          },
          {
            title: 'Dalam Perjalanan',
            value: formatNumber(stats.inTransitShipments || 0),
            change: 'Sedang diantar',
            trend: 'up',
            icon: '🚚'
          },
          {
            title: 'Selesai Diantar',
            value: formatNumber(stats.completedShipments || 0),
            change: 'Total terkirim',
            trend: 'up',
            icon: '✅'
          },
          {
            title: 'Mushaf Diantar',
            value: formatNumber(stats.totalMushafDistributed || 0),
            change: 'Total mushaf',
            trend: 'up',
            icon: '📖'
          }
        ];
      
      default:
        return [];
    }
  }

  function getQuickActionsForRole() {
    const baseActions = [];

    if (can.shipments.read()) {
      baseActions.push({
        title: 'Kelola Pengiriman',
        icon: '📦',
        color: 'bg-blue-500',
        href: '/admin/pengiriman'
      });
    }

    if (can.donatur.read()) {
      baseActions.push({
        title: 'Kelola Wakif',
        icon: '👥',
        color: 'bg-green-500',
        href: '/admin/donatur'
      });
    }

    if (can.mushafRequests.read()) {
      baseActions.push({
        title: 'Permintaan Mushaf',
        icon: '📋',
        color: 'bg-purple-500',
        href: '/admin/mushaf-requests'
      });
    }

    if (can.qr.scan()) {
      baseActions.push({
        title: 'QR Scanner',
        icon: '📱',
        color: 'bg-orange-500',
        href: '/admin/qr-scanner'
      });
    }

    return baseActions;
  }

  function getStatusSummary() {
    if (!chartsData?.statusDistribution || !Array.isArray(chartsData.statusDistribution)) return [];
    
    return chartsData.statusDistribution
      .filter(status => status && typeof status.count === 'number' && !isNaN(status.count) && status.count > 0)
      .map(status => ({
        name: status.name || 'Unknown',
        count: status.count || 0,
        slug: status.slug || null,
        category: status.category || 'unknown',
        color: getStatusColorClass(status.name, status.slug)
      }));
  }

  function formatNumber(num) {
    // Handle null, undefined, or non-numeric values
    if (num === null || num === undefined || isNaN(num)) {
      return '0';
    }
    
    const number = Number(num);
    if (number >= 1000000) {
      return (number / 1000000).toFixed(1) + 'M';
    } else if (number >= 1000) {
      return (number / 1000).toFixed(1) + 'K';
    }
    return number.toString();
  }

  function getStatusColor(status) {
    switch(status?.toLowerCase()) {
      case 'completed':
      case 'selesai':
      case 'approved': return 'bg-green-100 text-green-800';
      case 'in-transit':
      case 'dikirim':
      case 'dalam perjalanan': return 'bg-blue-100 text-blue-800';
      case 'delivered':
      case 'terkirim': return 'bg-purple-100 text-purple-800';
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'rejected':
      case 'ditolak': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  }

  function getStatusColorClass(statusName, statusSlug = null) {
    // Use slug if available for more accurate mapping
    if (statusSlug) {
      switch(statusSlug.toLowerCase()) {
        case 'diterima': return 'bg-green-500';
        case 'pengiriman':
        case 'selesai-packing': return 'bg-blue-500';
        case 'pembelian':
        case 'pemesanan': return 'bg-yellow-500';
        case 'produksi':
        case 'kedatangan':
        case 'packing': return 'bg-purple-500';
        case 'batal': return 'bg-red-500';
        default: return 'bg-gray-500';
      }
    }
    
    // Fallback to name-based mapping
    switch(statusName?.toLowerCase()) {
      case 'selesai':
      case 'diterima':
      case 'diterima penerima': return 'bg-green-500';
      case 'dikirim':
      case 'dalam perjalanan':
      case 'proses pengiriman':
      case 'selesai packing': return 'bg-blue-500';
      case 'pending':
      case 'diproses':
      case 'proses pembelian quran':
      case 'proses pemesanan': return 'bg-yellow-500';
      case 'dikemas':
      case 'proses produksi':
      case 'proses kedatangan/penurunan':
      case 'proses packing': return 'bg-purple-500';
      case 'batal': return 'bg-red-500';
      default: return 'bg-gray-500';
    }
  }

  function handleQuickAction(action) {
    if (action.href) {
      router.visit(action.href);
    }
  }

  function confirmLogout() {
    // Use utility function for consistent CSRF handling across all roles
    logout();
    showLogoutModal = false;
  }

  function cancelLogout() {
    showLogoutModal = false;
  }

  // Calculate today's stats with null safety
  $: todayStats = {
    shipments: (chartsData?.dailyActivities?.[6]?.shipments) || 0,
    requests: (chartsData?.dailyActivities?.[6]?.requests) || 0,
    mushafSent: (chartsData?.dailyActivities?.[6]?.mushaf_sent) || 0, // Use actual mushaf count from backend
    completed: (chartsData?.dailyActivities?.[6]?.completed_shipments) || 0 // Use actual completed count from backend
  };
</script>

<svelte:head>
  <title>Dashboard - Admin Ekspedisi Qur'an</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<AdminLayout>
  <!-- Welcome Section with Refresh Controls -->
  <div class="mb-8">
    <div class="bg-gradient-to-r from-[#eb3434] via-red-500 to-red-600 rounded-xl shadow-lg text-white p-4 md:p-6 relative overflow-hidden">
      <!-- Decorative pattern -->
      <div class="absolute inset-0 opacity-10">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <pattern id="pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
              <circle cx="10" cy="10" r="2" fill="white"/>
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#pattern)"/>
        </svg>
      </div>
      
      <div class="relative z-10">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
          <div class="flex-1 min-w-0">
            <h1 class="text-xl md:text-2xl font-bold mb-2 break-words">{auth.user?.name || 'Admin'} السلام عليكم،</h1>
            <p class="text-red-100 text-sm md:text-base">
              {#if hasRole(ROLES.SUPER_ADMIN)}
                Kelola seluruh sistem penyaluran mushaf Al-Qur'an
              {:else if hasRole(ROLES.CUSTOMER_SERVICE)}
                Kelola permintaan mushaf dan wakif
              {:else if hasRole(ROLES.WAREHOUSE)}
                Kelola inventori dan pengiriman
              {:else if hasRole(ROLES.COURIER)}
                Kelola pengantaran dan status pengiriman
              {:else}
                Dashboard sistem penyaluran mushaf Al-Qur'an
              {/if}
            </p>
          </div>
          
          <!-- Real-time Status & Controls -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 flex-shrink-0">
            <!-- Live Status Indicator -->
            <div class="flex items-center space-x-2 bg-white/20 rounded-lg px-3 py-2">
              <div class="flex items-center space-x-2">
                {#if isRefreshing}
                  <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div>
                  <span class="text-xs text-white">Refreshing...</span>
                {:else if autoRefreshEnabled && !isUserInteracting}
                  <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                  <span class="text-xs text-white">Live • {refreshCountdown}s</span>
                {:else if isUserInteracting}
                  <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                  <span class="text-xs text-white">Paused</span>
                {:else}
                  <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                  <span class="text-xs text-white">Stopped</span>
                {/if}
              </div>
            </div>

            <!-- Refresh Controls -->
            <div class="flex items-center space-x-2">
              <!-- Manual Refresh Button -->
              <button 
                on:click={manualRefresh}
                disabled={isRefreshing}
                class="bg-white/20 hover:bg-white/30 disabled:opacity-50 rounded-lg p-2 transition-colors"
                title="Refresh Manual"
              >
                <svg class="w-4 h-4 text-white {isRefreshing ? 'animate-spin' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
              </button>

              <!-- Auto-refresh Toggle -->
              <button 
                on:click={toggleAutoRefresh}
                class="bg-white/20 hover:bg-white/30 rounded-lg p-2 transition-colors"
                title="{autoRefreshEnabled ? 'Disable' : 'Enable'} Auto-refresh"
              >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  {#if autoRefreshEnabled}
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  {:else}
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-9-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  {/if}
                </svg>
              </button>
            </div>

            <!-- Last Updated Time -->
            <div class="text-xs text-red-100 hidden sm:block">
              <div>Update terakhir:</div>
              <div class="font-medium">{lastRefreshTime.toLocaleTimeString('id-ID')}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Grid -->
  {#if dynamicStats && dynamicStats.length > 0}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
      {#each dynamicStats as stat}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-4">
            <div class="text-xl md:text-2xl">{stat.icon}</div>
            <span class="text-xs md:text-sm font-medium {stat.trend === 'up' ? 'text-green-600' : stat.trend === 'down' ? 'text-red-600' : 'text-gray-600'}">
              {stat.change}
            </span>
          </div>
          <div>
            <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-1">{stat.value}</h3>
            <p class="text-xs md:text-sm text-gray-600">{stat.title}</p>
          </div>
        </div>
      {/each}
    </div>
  {/if}

  <!-- Enhanced Quick Stats Summary with Live Updates -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 space-y-4 sm:space-y-0">
      <h2 class="text-lg md:text-xl font-semibold text-gray-900">📊 Ringkasan Hari Ini</h2>
      <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
        <!-- Refresh Status -->
        <div class="flex items-center space-x-2">
          {#if isRefreshing}
            <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
            <span class="text-sm text-blue-600 font-medium">Memperbarui...</span>
          {:else}
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-sm text-green-600 font-medium">Live</span>
          {/if}
        </div>
        
        <!-- Last Update Time -->
        <div class="text-sm text-gray-500">
          Update: {lastRefreshTime.toLocaleString('id-ID', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
          })}
        </div>
      </div>
    </div>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
      <div class="text-center p-4 rounded-lg bg-blue-50 border-l-4 border-blue-500 hover:bg-blue-100 transition-colors">
        <div class="text-3xl font-bold text-blue-600 mb-1" class:animate-pulse={isRefreshing}>
          {todayStats.shipments}
        </div>
        <p class="text-sm text-gray-600">Pengiriman Hari Ini</p>
        <div class="mt-2 text-xs text-blue-500">
          {#if todayStats.shipments > 0}
            📈 Aktif
          {:else}
            💤 Belum ada
          {/if}
        </div>
      </div>
      
      <div class="text-center p-4 rounded-lg bg-green-50 border-l-4 border-green-500 hover:bg-green-100 transition-colors">
        <div class="text-3xl font-bold text-green-600 mb-1" class:animate-pulse={isRefreshing}>
          {todayStats.mushafSent}
        </div>
        <p class="text-sm text-gray-600">Mushaf Dikirim Hari Ini</p>
        <div class="mt-2 text-xs text-green-500">
          {#if todayStats.mushafSent > 0}
            🚚 Sudah dikirim
          {:else}
            📦 Belum ada pengiriman
          {/if}
        </div>
      </div>
      
      <div class="text-center p-4 rounded-lg bg-purple-50 border-l-4 border-purple-500 hover:bg-purple-100 transition-colors">
        <div class="text-3xl font-bold text-purple-600 mb-1" class:animate-pulse={isRefreshing}>
          {todayStats.requests}
        </div>
        <p class="text-sm text-gray-600">Permintaan Baru</p>
        <div class="mt-2 text-xs text-purple-500">
          {#if todayStats.requests > 5}
            📈 Tinggi
          {:else if todayStats.requests > 2}
            📊 Normal
          {:else}
            📉 Rendah
          {/if}
        </div>
      </div>
      
      <div class="text-center p-4 rounded-lg bg-orange-50 border-l-4 border-orange-500 hover:bg-orange-100 transition-colors">
        <div class="text-3xl font-bold text-orange-600 mb-1" class:animate-pulse={isRefreshing}>
          {todayStats.completed}
        </div>
        <p class="text-sm text-gray-600">Pengiriman Selesai</p>
        <div class="mt-2 text-xs text-orange-500">
          {#if todayStats.completed > 0}
            ✅ Berhasil
          {:else}
            ⏳ Menunggu
          {/if}
        </div>
      </div>
    </div>


  </div>

  <!-- Main Content Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Recent Activities -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Aktivitas Terbaru</h3>
          <p class="text-sm text-gray-500">Update terkini dari sistem</p>
        </div>
        <div class="p-6">
          {#if activities && activities.length > 0}
            <div class="space-y-4">
              {#each activities as activity}
                <div class="flex items-start space-x-4 p-4 rounded-lg hover:bg-gray-50 transition-colors">
                  <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-lg">
                      {activity.icon}
                    </div>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-medium text-gray-900">{activity.title}</h4>
                      <span class="text-xs text-gray-500">{activity.timestamp}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">{activity.description}</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2 {getStatusColor(activity.status)}">
                      {#if activity.status === 'completed' || activity.status === 'approved'}
                        Selesai
                      {:else if activity.status === 'in-transit' || activity.status === 'dikirim'}
                        Dalam Perjalanan
                      {:else if activity.status === 'delivered' || activity.status === 'terkirim'}
                        Terkirim
                      {:else if activity.status === 'pending'}
                        Pending
                      {:else if activity.status === 'rejected'}
                        Ditolak
                      {:else}
                        {activity.status}
                      {/if}
                    </span>
                  </div>
                </div>
              {/each}
            </div>
          {:else}
            <div class="text-center py-8">
              <div class="text-gray-400 text-4xl mb-4">📭</div>
              <p class="text-gray-500">Belum ada aktivitas terbaru</p>
            </div>
          {/if}
          
          {#if auth.user?.permissions?.includes('view_all')}
            <div class="mt-6 text-center">
              <button 
                on:click={() => router.visit('/admin/pengiriman')}
                class="text-[#eb3434] text-sm font-medium hover:text-red-600 transition-colors"
              >
                Lihat Semua Aktivitas →
              </button>
            </div>
          {/if}
        </div>
      </div>
    </div>

    <!-- Quick Actions & Summary -->
    <div class="space-y-6">
      

      <!-- Performance Metrics -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">📊 Performa Operasional</h3>
            <div class="flex items-center space-x-1">
              {#if isRefreshing}
                <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                <span class="text-xs text-blue-600">Live</span>
              {:else}
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs text-green-600">Sync</span>
              {/if}
            </div>
          </div>
        </div>
        <div class="p-6">
          <div class="space-y-4">
            <!-- Wakif Metric with better context -->
            <div class="flex items-center justify-between p-3 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
              <div class="flex items-center">
                <div class="w-3 h-3 bg-blue-500 rounded-full mr-3 animate-pulse"></div>
                <div>
                  <span class="text-sm font-medium text-gray-700">Wakif Baru Bulan Ini</span>
                  <div class="text-xs text-gray-500">
                    {#if stats.totalWakif === 0}
                      Belum ada wakif baru bulan ini
                    {:else}
                      Dari total {stats.totalDonatur || 0} wakif terdaftar
                    {/if}
                  </div>
                </div>
              </div>
              <div class="text-right">
                <span class="text-lg font-bold text-blue-600">{stats.totalWakif || 0}</span>
                <div class="text-xs text-blue-500">
                  {stats.totalWakif > 0 ? '📈 Aktif' : '📊 Normal'}
                </div>
              </div>
            </div>
            
            <!-- Success Rate with better calculation and display -->
            <div class="flex items-center justify-between p-3 rounded-lg bg-green-50 hover:bg-green-100 transition-colors">
              <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                <div>
                  <span class="text-sm font-medium text-gray-700">Tingkat Keberhasilan</span>
                  <div class="text-xs text-gray-500">
                    {stats.completedShipments || 0} dari {stats.totalPengiriman || 0} pengiriman selesai
                  </div>
                </div>
              </div>
              <div class="text-right">
                <span class="text-lg font-bold text-green-600">
                  {stats.totalPengiriman > 0 ? ((stats.completedShipments / stats.totalPengiriman) * 100).toFixed(1) : '0.0'}%
                </span>
                <div class="text-xs text-green-500">
                  {#if stats.completedShipments === 0 && stats.totalPengiriman > 0}
                    🔄 Dalam Proses
                  {:else if (stats.completedShipments / Math.max(stats.totalPengiriman, 1)) > 0.8}
                    🎯 Excellent
                  {:else if (stats.completedShipments / Math.max(stats.totalPengiriman, 1)) > 0.6}
                    👍 Good
                  {:else if stats.totalPengiriman > 0}
                    📈 Improving
                  {:else}
                    📊 Ready
                  {/if}
                </div>
              </div>
            </div>
            
            <!-- Average Delivery with better context -->
            <div class="flex items-center justify-between p-3 rounded-lg bg-purple-50 hover:bg-purple-100 transition-colors">
              <div class="flex items-center">
                <div class="w-3 h-3 bg-purple-500 rounded-full mr-3 animate-pulse"></div>
                <div>
                  <span class="text-sm font-medium text-gray-700">Rata-rata Pengiriman</span>
                  <div class="text-xs text-gray-500">
                    {#if stats.avgDeliveryDays === 0 && stats.completedShipments === 0}
                      Belum ada pengiriman selesai
                    {:else if stats.completedShipments > 0}
                      Berdasarkan {stats.completedShipments} pengiriman
                    {:else}
                      Menunggu data pengiriman
                    {/if}
                  </div>
                </div>
              </div>
              <div class="text-right">
                <span class="text-lg font-bold text-purple-600">{stats.avgDeliveryDays || 0}</span>
                <span class="text-sm text-purple-600">hari</span>
                <div class="text-xs text-purple-500">
                  {#if stats.avgDeliveryDays === 0}
                    ⏳ Menunggu
                  {:else if stats.avgDeliveryDays <= 7}
                    🚀 Cepat
                  {:else if stats.avgDeliveryDays <= 14}
                    📦 Normal
                  {:else}
                    🔄 Review
                  {/if}
                </div>
              </div>
            </div>
          </div>
          
          <!-- Additional Operational Insights -->
          <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="grid grid-cols-2 gap-4">
              <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-sm font-medium text-gray-700">Dalam Proses</div>
                <div class="text-xl font-bold text-orange-600">{stats.pendingShipments + stats.inTransitShipments || 0}</div>
                <div class="text-xs text-gray-500">Pengiriman aktif</div>
              </div>
              <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-sm font-medium text-gray-700">Permintaan</div>
                <div class="text-xl font-bold text-blue-600">{stats.pendingMushafRequests || 0}</div>
                <div class="text-xs text-gray-500">Menunggu review</div>
              </div>
            </div>
          </div>
        </div>
      </div>


    </div>
  </div>

  <!-- Enhanced Interactive Charts Section with Real Chart.js -->
  {#if canViewCharts && chartsData?.monthlyShipments && Array.isArray(chartsData.monthlyShipments) && chartsData.monthlyShipments.length > 0}
    <div class="mt-8">
      <div class="bg-gradient-to-br from-white via-gray-50 to-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-[#eb3434] to-red-500 px-4 md:px-6 py-4 text-white">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <div class="flex-1">
              <h3 class="text-lg md:text-xl font-bold">📊 Analisis Statistik 6 Bulan Terakhir</h3>
              <p class="text-red-100 text-sm">Visualisasi data pengiriman dan permintaan dengan Chart.js</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
          </div>
        </div>
        
        <div class="p-4 md:p-8">
          <!-- Main Charts Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
            
            <!-- Monthly Shipments Line Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-md transition-shadow">
              <div class="flex items-center justify-between mb-4 md:mb-6">
                <h4 class="text-base md:text-lg font-semibold text-gray-800">📈 Penghimpunan Quran Bulanan</h4>
                <div class="flex items-center space-x-2">
                  <div class="w-3 h-3 bg-[#eb3434] rounded-full"></div>
                  <span class="text-xs text-gray-500">Line Chart</span>
                </div>
              </div>
              
              <!-- Chart Canvas -->
              <div class="h-64 md:h-80 relative">
                {#if chartsVisible}
                  <LazyChart 
                    type="line" 
                    data={monthlyShipmentsChartData} 
                    height={320}
                    loadingText="Loading shipments chart..."
                  />
                {/if}
              </div>
              
              <!-- Chart Summary Stats -->
              <div class="mt-6 pt-4 border-t border-gray-100">
                <div class="grid grid-cols-3 gap-4 text-center">
                  <div class="bg-blue-50 rounded-lg p-3">
                    <div class="text-lg font-bold text-blue-600">{chartsData?.monthlyShipments?.length > 0 ? Math.max(...chartsData.monthlyShipments.map(d => d.count || 0)) : 0}</div>
                    <div class="text-xs text-gray-600">Peak Bulan</div>
                  </div>
                  <div class="bg-green-50 rounded-lg p-3">
                    <div class="text-lg font-bold text-green-600">{chartsData?.monthlyShipments?.length > 0 ? Math.round(chartsData.monthlyShipments.reduce((sum, d) => sum + (d.count || 0), 0) / chartsData.monthlyShipments.length) : 0}</div>
                    <div class="text-xs text-gray-600">Rata-rata</div>
                  </div>
                  <div class="bg-purple-50 rounded-lg p-3">
                    <div class="text-lg font-bold text-purple-600">{chartsData?.monthlyShipments?.length > 0 ? chartsData.monthlyShipments.reduce((sum, d) => sum + (d.count || 0), 0) : 0}</div>
                    <div class="text-xs text-gray-600">Total 6 Bulan</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Monthly Requests Bar Chart -->
            {#if chartsData?.monthlyMushafRequests && Array.isArray(chartsData.monthlyMushafRequests) && chartsData.monthlyMushafRequests.length > 0}
              <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4 md:mb-6">
                  <h4 class="text-base md:text-lg font-semibold text-gray-800">📊 Permintaan Mushaf Bulanan</h4>
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <span class="text-xs text-gray-500">Bar Chart</span>
                  </div>
                </div>
                
                <!-- Chart Canvas -->
                <div class="h-64 md:h-80 relative">
                  {#if chartsVisible}
                    <LazyChart 
                      type="bar" 
                      data={monthlyRequestsChartData} 
                      height={320}
                      loadingText="Loading requests chart..."
                    />
                  {/if}
                </div>
                
                <!-- Chart Summary Stats -->
                <div class="mt-6 pt-4 border-t border-gray-100">
                  <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-orange-50 rounded-lg p-3">
                      <div class="text-lg font-bold text-orange-600">{chartsData?.monthlyMushafRequests?.length > 0 ? Math.max(...chartsData.monthlyMushafRequests.map(d => d.count || 0)) : 0}</div>
                      <div class="text-xs text-gray-600">Peak Bulan</div>
                    </div>
                    <div class="bg-teal-50 rounded-lg p-3">
                      <div class="text-lg font-bold text-teal-600">{chartsData?.monthlyMushafRequests?.length > 0 ? Math.round(chartsData.monthlyMushafRequests.reduce((sum, d) => sum + (d.count || 0), 0) / chartsData.monthlyMushafRequests.length) : 0}</div>
                      <div class="text-xs text-gray-600">Rata-rata</div>
                    </div>
                    <div class="bg-indigo-50 rounded-lg p-3">
                      <div class="text-lg font-bold text-indigo-600">{chartsData?.monthlyMushafRequests?.length > 0 ? chartsData.monthlyMushafRequests.reduce((sum, d) => sum + (d.count || 0), 0) : 0}</div>
                      <div class="text-xs text-gray-600">Total 6 Bulan</div>
                    </div>
                  </div>
                </div>
              </div>
            {/if}

          </div>

          <!-- Secondary Charts Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
            
            <!-- Status Distribution Doughnut Chart -->
            {#if chartsData?.statusDistribution && Array.isArray(chartsData.statusDistribution) && chartsData.statusDistribution.length > 0}
              <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4 md:mb-6">
                  <h4 class="text-base md:text-lg font-semibold text-gray-800">🎯 Distribusi Status</h4>
                  <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-xs text-gray-500">Doughnut Chart</span>
                  </div>
                </div>
                
                <!-- Chart Canvas -->
                <div class="h-64 md:h-80 relative">
                  {#if chartsVisible}
                    <LazyChart 
                      type="doughnut" 
                      data={statusDistributionChartData} 
                      height={320}
                      loadingText="Loading status chart..."
                    />
                  {/if}
                </div>
              </div>
            {/if}

            <!-- Daily Activities Chart -->
            {#if chartsData?.dailyActivities && Array.isArray(chartsData.dailyActivities) && chartsData.dailyActivities.length > 0}
              <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 hover:shadow-md transition-shadow">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 md:mb-6 space-y-2 lg:space-y-0">
                  <h4 class="text-base md:text-lg font-semibold text-gray-800">📊 Aktivitas 7 Hari Terakhir</h4>
                  <div class="flex flex-wrap items-center gap-2 lg:gap-4">
                    <div class="flex items-center space-x-2">
                      <div class="w-3 h-3 bg-[#eb3434] rounded-full"></div>
                      <span class="text-xs text-gray-500">Sedang Dikirim</span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                      <span class="text-xs text-gray-500">Pengiriman Selesai</span>
                    </div>
                    <div class="flex items-center space-x-2">
                      <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                      <span class="text-xs text-gray-500">Permintaan</span>
                    </div>
                  </div>
                </div>
                
                <!-- Chart Canvas -->
                <div class="h-64 md:h-80 relative">
                  {#if chartsVisible}
                    <LazyChart 
                      type="bar" 
                      data={dailyActivitiesChartData} 
                      options={{
                        plugins: {
                          legend: {
                            display: false  // Disable Chart.js legend since we have manual legend above
                          }
                        },
                        scales: {
                          y: {
                            beginAtZero: true
                          }
                        }
                      }}
                      height={320}
                      loadingText="Loading activities chart..."
                    />
                  {/if}
                </div>
              </div>
            {/if}

          </div>
          
          <!-- Enhanced Insights Section -->
          <div class="mt-6 md:mt-8 bg-gradient-to-r from-gray-50 to-white rounded-xl p-4 md:p-6 border border-gray-200">
            <h5 class="text-base md:text-lg font-semibold text-gray-800 mb-4">💡 Insights & Rekomendasi Real-time</h5>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
              
              <!-- Growth Analysis -->
              <div class="bg-white rounded-lg p-4 border border-green-200 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-3">
                  <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-white text-sm">📈</span>
                  </div>
                  <h6 class="text-sm font-medium text-gray-800">Growth Analysis</h6>
                </div>
                <p class="text-xs text-gray-600">
                  Pengiriman menunjukkan tren {(chartsData?.monthlyShipments?.length >= 2 && chartsData.monthlyShipments[chartsData.monthlyShipments.length-1]?.count > chartsData.monthlyShipments[chartsData.monthlyShipments.length-2]?.count) ? 'positif 📈' : 'perlu perhatian ⚠️'} 
                  dibanding bulan sebelumnya.
                </p>
                <div class="mt-2 text-xs font-medium {(chartsData?.monthlyShipments?.length >= 2 && chartsData.monthlyShipments[chartsData.monthlyShipments.length-1]?.count > chartsData.monthlyShipments[chartsData.monthlyShipments.length-2]?.count) ? 'text-green-600' : 'text-yellow-600'}">
                  {(chartsData?.monthlyShipments?.length >= 2 && chartsData.monthlyShipments[chartsData.monthlyShipments.length-1]?.count > chartsData.monthlyShipments[chartsData.monthlyShipments.length-2]?.count) ? '✅ Trending Up' : '⚠️ Needs Attention'}
                </div>
              </div>

              <!-- Performance Rating -->
              <div class="bg-white rounded-lg p-4 border border-blue-200 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-3">
                  <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-white text-sm">⭐</span>
                  </div>
                  <h6 class="text-sm font-medium text-gray-800">Performance Rating</h6>
                </div>
                <p class="text-xs text-gray-600">
                  Target bulanan tercapai {chartsData?.monthlyShipments?.length > 0 ? chartsData.monthlyShipments.filter(d => (d.count || 0) >= 50).length : 0} dari {chartsData?.monthlyShipments?.length || 0} bulan.
                </p>
                <div class="mt-2 flex items-center justify-between">
                  <span class="text-xs font-medium text-blue-600">
                    {chartsData?.monthlyShipments?.length > 0 ? Math.round((chartsData.monthlyShipments.filter(d => (d.count || 0) >= 50).length / chartsData.monthlyShipments.length) * 100) : 0}% Success Rate
                  </span>
                  <div class="w-16 bg-blue-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full" style="width: {chartsData?.monthlyShipments?.length > 0 ? Math.round((chartsData.monthlyShipments.filter(d => (d.count || 0) >= 50).length / chartsData.monthlyShipments.length) * 100) : 0}%"></div>
                  </div>
                </div>
              </div>

              <!-- Smart Recommendation -->
              <div class="bg-white rounded-lg p-4 border border-purple-200 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-3">
                  <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-white text-sm">🤖</span>
                  </div>
                  <h6 class="text-sm font-medium text-gray-800">AI Recommendation</h6>
                </div>
                <p class="text-xs text-gray-600">
                  {#if (chartsData?.monthlyShipments?.[chartsData.monthlyShipments.length-1]?.count || 0) < 25}
                    🚨 Critical: Implementasi strategi peningkatan pengiriman segera diperlukan.
                  {:else if (chartsData?.monthlyShipments?.[chartsData.monthlyShipments.length-1]?.count || 0) < 50}
                    ⚡ Good: Sedikit peningkatan lagi untuk mencapai target optimal.
                  {:else}
                    🎉 Excellent! Pertahankan momentum dan eksplorasi target lebih tinggi.
                  {/if}
                </p>
                <div class="mt-2 text-xs font-medium {(chartsData?.monthlyShipments?.[chartsData.monthlyShipments.length-1]?.count || 0) < 25 ? 'text-red-600' : (chartsData?.monthlyShipments?.[chartsData.monthlyShipments.length-1]?.count || 0) < 50 ? 'text-yellow-600' : 'text-green-600'}">
                  {(chartsData?.monthlyShipments?.[chartsData.monthlyShipments.length-1]?.count || 0) < 25 ? '🔴 Action Required' : (chartsData?.monthlyShipments?.[chartsData.monthlyShipments.length-1]?.count || 0) < 50 ? '🟡 On Track' : '🟢 Exceeding Goals'}
                </div>
              </div>

            </div>


          </div>
          
        </div>
      </div>
    </div>
  {/if}

  <!-- Logout Confirmation Modal -->
  {#if showLogoutModal}
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <!-- Background overlay -->
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div 
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
          on:click={cancelLogout}
          on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cancelLogout(); } }}
          role="button"
          aria-label="Batal logout"
          tabindex="0"
          transition:fade={{ duration: 200 }}
        ></div>

        <!-- This element is to trick the browser into centering the modal contents. -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div 
          class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
          transition:scale={{ duration: 200, start: 0.95 }}
        >
          <div class="sm:flex sm:items-start">
            <!-- Warning Icon -->
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
              <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 15.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            
            <!-- Content -->
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Konfirmasi Logout
              </h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500">
                  Apakah Anda yakin ingin keluar dari sistem? Anda perlu login ulang untuk mengakses dashboard.
                </p>
              </div>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
            <!-- Logout Button -->
            <button
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200"
              on:click={confirmLogout}
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
              </svg>
              Ya, Logout
            </button>
            
            <!-- Cancel Button -->
            <button
              type="button"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:mt-0 sm:w-auto sm:text-sm transition-colors duration-200"
              on:click={cancelLogout}
            >
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>
  {/if}

</AdminLayout>
