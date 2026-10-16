<script>
  import { onMount } from 'svelte';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
  
  // Props
// Omit unused props to reduce build warnings
  export let performance = null;
  export let weeklyComparison = {};
  export let recentTasks = [];
  
  // State
  let selectedPeriod = 'week'; // 'week' | 'month' | 'all'
  let showChart = false;
  
  // Calculate performance metrics
  $: filteredTasks = filterTasksByPeriod(recentTasks, selectedPeriod);
  $: performanceMetrics = calculateMetrics(filteredTasks);
  
  function filterTasksByPeriod(tasks, period) {
    const now = new Date();
    
    switch(period) {
      case 'week':
        const weekStart = new Date(now);
        weekStart.setDate(weekStart.getDate() - weekStart.getDay());
        weekStart.setHours(0, 0, 0, 0);
        return tasks.filter(task => new Date(task.tanggal_tugas) >= weekStart);
        
      case 'month':
        const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
        return tasks.filter(task => new Date(task.tanggal_tugas) >= monthStart);
        
      default:
        return tasks;
    }
  }
  
  function calculateMetrics(tasks) {
    if (tasks.length === 0) {
      return {
        totalTasks: 0,
        completedTasks: 0,
        totalTarget: 0,
        totalAchieved: 0,
        achievementRate: 0,
        averageDaily: 0,
        bestDay: null,
        worstDay: null
      };
    }
    
    const completed = tasks.filter(t => t.is_completed);
    const totalTarget = tasks.reduce((sum, t) => sum + t.total_target, 0);
    const totalAchieved = tasks.reduce((sum, t) => sum + t.total_selesai, 0);
    
    // Find best and worst days
    const sortedByPerformance = [...tasks].sort((a, b) => b.progress_percentage - a.progress_percentage);
    
    return {
      totalTasks: tasks.length,
      completedTasks: completed.length,
      totalTarget,
      totalAchieved,
      achievementRate: totalTarget > 0 ? Math.round((totalAchieved / totalTarget) * 100) : 0,
      averageDaily: Math.round(totalAchieved / tasks.length),
      bestDay: sortedByPerformance[0],
      worstDay: sortedByPerformance[sortedByPerformance.length - 1]
    };
  }
  
  function getPerformanceBadge(level) {
    const badges = {
      'excellent': { text: 'Excellent', class: 'bg-green-100 text-green-800', icon: '🌟' },
      'good': { text: 'Good', class: 'bg-blue-100 text-blue-800', icon: '👍' },
      'average': { text: 'Average', class: 'bg-yellow-100 text-yellow-800', icon: '📊' },
      'poor': { text: 'Needs Improvement', class: 'bg-red-100 text-red-800', icon: '⚠️' }
    };
    return badges[level] || badges.average;
  }
  
  function getStatusColor(status) {
    const colors = {
      'completed': 'text-green-600',
      'expired': 'text-red-600',
      'in_progress': 'text-blue-600',
      'assigned': 'text-gray-600'
    };
    return colors[status] || 'text-gray-600';
  }
  
  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', { 
      weekday: 'short', 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    });
  }
  
  onMount(() => {
    // Could add chart visualization here
    showChart = true;
  });
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Laporan Performa</h1>
          <p class="text-gray-600">Pantau kinerja packing Anda</p>
        </div>
        <a 
          href="/admin/warehouse" 
          class="text-gray-600 hover:text-gray-900"
        >
          <HeroIcon name="x-mark" class="w-6 h-6" />
        </a>
      </div>
    </div>
    
    <!-- Period Selector -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
      <div class="flex space-x-1">
        <button
          on:click={() => selectedPeriod = 'week'}
          class="flex-1 py-2 px-4 rounded-lg font-medium transition-colors {selectedPeriod === 'week' ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
        >
          Minggu Ini
        </button>
        <button
          on:click={() => selectedPeriod = 'month'}
          class="flex-1 py-2 px-4 rounded-lg font-medium transition-colors {selectedPeriod === 'month' ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
        >
          Bulan Ini
        </button>
        <button
          on:click={() => selectedPeriod = 'all'}
          class="flex-1 py-2 px-4 rounded-lg font-medium transition-colors {selectedPeriod === 'all' ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
        >
          Semua
        </button>
      </div>
    </div>
    
    <!-- Performance Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Target</p>
            <p class="text-2xl font-bold text-gray-900">{performanceMetrics.totalTarget}</p>
          </div>
          <div class="p-3 bg-blue-100 rounded-full">
            <HeroIcon name="clipboard-document-check" class="w-6 h-6 text-blue-600" />
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Selesai</p>
            <p class="text-2xl font-bold text-green-600">{performanceMetrics.totalAchieved}</p>
          </div>
          <div class="p-3 bg-green-100 rounded-full">
            <HeroIcon name="check-circle" class="w-6 h-6 text-green-600" />
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Achievement Rate</p>
            <p class="text-2xl font-bold {performanceMetrics.achievementRate >= 80 ? 'text-green-600' : performanceMetrics.achievementRate >= 60 ? 'text-yellow-600' : 'text-red-600'}">{performanceMetrics.achievementRate}%</p>
          </div>
          <div class="p-3 bg-purple-100 rounded-full">
            <HeroIcon name="arrow-trending-up" class="w-6 h-6 text-purple-600" />
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Rata-rata/Hari</p>
            <p class="text-2xl font-bold text-gray-900">{performanceMetrics.averageDaily}</p>
          </div>
          <div class="p-3 bg-orange-100 rounded-full">
            <HeroIcon name="chart-bar" class="w-6 h-6 text-orange-600" />
          </div>
        </div>
      </div>
    </div>
    
    <!-- Monthly Performance Badge -->
    {#if performance}
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Performa Bulan Ini</h2>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-600">Level Performa</p>
            <div class="flex items-center space-x-2 mt-2">
              <span class="text-2xl">{getPerformanceBadge(performance.performance_level).icon}</span>
              <span class="px-3 py-1 rounded-full text-sm font-medium {getPerformanceBadge(performance.performance_level).class}">
                {getPerformanceBadge(performance.performance_level).text}
              </span>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-600">Achievement Rate</p>
            <p class="text-3xl font-bold {performance.achievement_rate >= 80 ? 'text-green-600' : performance.achievement_rate >= 60 ? 'text-yellow-600' : 'text-red-600'}">
              {performance.achievement_rate}%
            </p>
          </div>
        </div>
      </div>
    {/if}
    
    <!-- Weekly Comparison -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Perbandingan Mingguan</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="border border-gray-200 rounded-lg p-4">
          <h3 class="font-medium text-gray-900 mb-3">Minggu Ini</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Hari Kerja</span>
              <span class="font-medium">{weeklyComparison.this_week.tasks}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tugas Selesai</span>
              <span class="font-medium">{weeklyComparison.this_week.completed}/{weeklyComparison.this_week.tasks}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Total Target</span>
              <span class="font-medium">{weeklyComparison.this_week.total_target}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Total Tercapai</span>
              <span class="font-medium text-green-600">{weeklyComparison.this_week.total_achieved}</span>
            </div>
          </div>
        </div>
        
        <div class="border border-gray-200 rounded-lg p-4">
          <h3 class="font-medium text-gray-900 mb-3">Minggu Lalu</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Hari Kerja</span>
              <span class="font-medium">{weeklyComparison.last_week.tasks}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tugas Selesai</span>
              <span class="font-medium">{weeklyComparison.last_week.completed}/{weeklyComparison.last_week.tasks}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Total Target</span>
              <span class="font-medium">{weeklyComparison.last_week.total_target}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Total Tercapai</span>
              <span class="font-medium text-green-600">{weeklyComparison.last_week.total_achieved}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Best & Worst Performance -->
    {#if performanceMetrics.bestDay && performanceMetrics.worstDay}
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
          <h3 class="font-medium text-green-900 mb-2">Performa Terbaik</h3>
          <p class="text-sm text-green-700">{formatDate(performanceMetrics.bestDay.tanggal_tugas)}</p>
          <div class="mt-3 space-y-1">
            <p class="text-2xl font-bold text-green-900">{performanceMetrics.bestDay.progress_percentage}%</p>
            <p class="text-sm text-green-700">{performanceMetrics.bestDay.total_selesai}/{performanceMetrics.bestDay.total_target} mushaf</p>
          </div>
        </div>
        
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
          <h3 class="font-medium text-red-900 mb-2">Perlu Peningkatan</h3>
          <p class="text-sm text-red-700">{formatDate(performanceMetrics.worstDay.tanggal_tugas)}</p>
          <div class="mt-3 space-y-1">
            <p class="text-2xl font-bold text-red-900">{performanceMetrics.worstDay.progress_percentage}%</p>
            <p class="text-sm text-red-700">{performanceMetrics.worstDay.total_selesai}/{performanceMetrics.worstDay.total_target} mushaf</p>
          </div>
        </div>
      </div>
    {/if}
    
    <!-- Recent Tasks History -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Riwayat Tugas</h2>
      
      {#if filteredTasks.length > 0}
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selesai</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each filteredTasks as task}
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{formatDate(task.tanggal_tugas)}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{task.total_target}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">{task.total_selesai}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-1 mr-2">
                        <div class="bg-gray-200 rounded-full h-2">
                          <div 
                            class="h-2 rounded-full {task.progress_percentage >= 80 ? 'bg-green-500' : task.progress_percentage >= 60 ? 'bg-yellow-500' : task.progress_percentage >= 40 ? 'bg-orange-500' : 'bg-red-500'}"
                            style="width: {task.progress_percentage}%"
                          ></div>
                        </div>
                      </div>
                      <span class="text-sm font-medium">{task.progress_percentage}%</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="text-sm font-medium {getStatusColor(task.status)}">
                      {task.status === 'completed' ? '✓ Selesai' : task.status === 'expired' ? '✗ Expired' : task.status === 'in_progress' ? '⟳ Progress' : '○ Assigned'}
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                    {#if task.started_at && task.completed_at}
                      {task.started_at} - {task.completed_at}
                    {:else if task.started_at}
                      {task.started_at} - ?
                    {:else}
                      -
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {:else}
        <div class="text-center py-8 text-gray-500">
          <p>Belum ada data untuk periode ini</p>
        </div>
      {/if}
    </div>
  </div>
</AdminLayout>
