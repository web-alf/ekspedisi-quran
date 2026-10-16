<script>
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
  
  // Props
  export let userPerformance = [];
  export let weekRange = {};
  
  function getAchievementColor(rate) {
    if (rate >= 90) return 'text-green-600';
    if (rate >= 75) return 'text-yellow-600';
    if (rate >= 60) return 'text-orange-600';
    return 'text-red-600';
  }
  
  function getAchievementBadge(rate) {
    if (rate >= 90) return { text: 'Excellent', class: 'bg-green-100 text-green-800' };
    if (rate >= 75) return { text: 'Good', class: 'bg-yellow-100 text-yellow-800' };
    if (rate >= 60) return { text: 'Fair', class: 'bg-orange-100 text-orange-800' };
    return { text: 'Needs Improvement', class: 'bg-red-100 text-red-800' };
  }
  
  // Calculate summary stats
  $: summaryStats = {
    totalUsers: userPerformance.length,
    avgAchievementRate: userPerformance.length > 0 
      ? userPerformance.reduce((sum, user) => sum + user.achievement_rate, 0) / userPerformance.length 
      : 0,
    totalTarget: userPerformance.reduce((sum, user) => sum + user.total_target, 0),
    totalAchieved: userPerformance.reduce((sum, user) => sum + user.total_achieved, 0),
    excellentPerformers: userPerformance.filter(user => user.achievement_rate >= 90).length,
  };
  
  // Calculate performance distribution counts
  $: performanceDistribution = {
    excellent: userPerformance.filter(u => u.achievement_rate >= 90).length,
    good: userPerformance.filter(u => u.achievement_rate >= 75 && u.achievement_rate < 90).length,
    fair: userPerformance.filter(u => u.achievement_rate >= 60 && u.achievement_rate < 75).length,
    needsImprovement: userPerformance.filter(u => u.achievement_rate < 60).length,
  };
  
  // Calculate percentages
  $: distributionPercentages = {
    excellent: userPerformance.length > 0 ? (performanceDistribution.excellent / userPerformance.length * 100) : 0,
    good: userPerformance.length > 0 ? (performanceDistribution.good / userPerformance.length * 100) : 0,
    fair: userPerformance.length > 0 ? (performanceDistribution.fair / userPerformance.length * 100) : 0,
    needsImprovement: userPerformance.length > 0 ? (performanceDistribution.needsImprovement / userPerformance.length * 100) : 0,
  };
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Laporan Performa Mingguan</h1>
          <p class="text-gray-600">
            Periode: {new Date(weekRange.start).toLocaleDateString('id-ID')} - 
            {new Date(weekRange.end).toLocaleDateString('id-ID')}
          </p>
        </div>
        <div class="flex items-center space-x-4">
          <a
            href="/admin/supervisor/warehouse-monitor"
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Kembali ke Monitor
          </a>
        </div>
      </div>
    </div>
    
    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Total Staff</p>
        <p class="text-2xl font-bold text-gray-900">{summaryStats.totalUsers}</p>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Rata-rata Pencapaian</p>
        <p class="text-2xl font-bold {getAchievementColor(summaryStats.avgAchievementRate)}">
          {summaryStats.avgAchievementRate.toFixed(1)}%
        </p>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Total Target</p>
        <p class="text-2xl font-bold text-gray-900">{summaryStats.totalTarget.toLocaleString()}</p>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Total Tercapai</p>
        <p class="text-2xl font-bold text-green-600">{summaryStats.totalAchieved.toLocaleString()}</p>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Excellent Performers</p>
        <p class="text-2xl font-bold text-green-600">{summaryStats.excellentPerformers}</p>
      </div>
    </div>
    
    <!-- Overall Progress Bar -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-lg font-semibold text-gray-900">Progress Keseluruhan Minggu Ini</h2>
        <span class="text-2xl font-bold {getAchievementColor(summaryStats.avgAchievementRate)}">
          {summaryStats.avgAchievementRate.toFixed(1)}%
        </span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-6">
        <div 
          class="h-6 rounded-full transition-all duration-300 {summaryStats.avgAchievementRate >= 90 ? 'bg-green-500' : summaryStats.avgAchievementRate >= 75 ? 'bg-yellow-500' : summaryStats.avgAchievementRate >= 60 ? 'bg-orange-500' : 'bg-red-500'}"
          style="width: {Math.min(summaryStats.avgAchievementRate, 100)}%"
        ></div>
      </div>
      <div class="flex justify-between text-sm text-gray-600 mt-2">
        <span>{summaryStats.totalAchieved.toLocaleString()} mushaf tercapai</span>
        <span>{(summaryStats.totalTarget - summaryStats.totalAchieved).toLocaleString()} mushaf tersisa</span>
      </div>
    </div>
    
    <!-- Performance Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Detail Performa Individual</h2>
      
      {#if userPerformance.length > 0}
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Task</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Selesai</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tercapai</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tingkat Pencapaian</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rata-rata Selesai</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each userPerformance as performance}
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div>
                      <p class="text-sm font-medium text-gray-900">{performance.user.name}</p>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                    {performance.tasks_count}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">
                    <div class="flex items-center">
                      <span class="text-gray-900">{performance.completed_count}</span>
                      <span class="text-xs text-gray-500 ml-1">
                        ({Math.round(performance.completed_count / performance.tasks_count * 100)}%)
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                    {performance.total_target.toLocaleString()}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-green-600">
                    {performance.total_achieved.toLocaleString()}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-1 mr-2 w-16">
                        <div class="bg-gray-200 rounded-full h-2">
                          <div 
                            class="h-2 rounded-full {performance.achievement_rate >= 90 ? 'bg-green-500' : performance.achievement_rate >= 75 ? 'bg-yellow-500' : performance.achievement_rate >= 60 ? 'bg-orange-500' : 'bg-red-500'}"
                            style="width: {Math.min(performance.achievement_rate, 100)}%"
                          ></div>
                        </div>
                      </div>
                      <span class="text-sm font-medium {getAchievementColor(performance.achievement_rate)}">
                        {performance.achievement_rate.toFixed(1)}%
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="px-2 py-1 rounded-full text-xs font-medium {getAchievementBadge(performance.achievement_rate).class}">
                      {getAchievementBadge(performance.achievement_rate).text}
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                    {#if performance.avg_completion_time}
                      {Math.round(performance.avg_completion_time)}:00
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
          <div class="flex flex-col items-center">
            <HeroIcon name="chart-bar" class="w-12 h-12 text-gray-400 mb-4" />
            <p class="text-lg font-medium">Belum ada data performa</p>
            <p class="text-sm">Data performa akan muncul setelah ada aktivitas packing minggu ini</p>
          </div>
        </div>
      {/if}
    </div>
    
    <!-- Performance Analysis -->
    {#if userPerformance.length > 0}
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Top Performers -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performers</h3>
          <div class="space-y-3">
            {#each userPerformance.sort((a, b) => b.achievement_rate - a.achievement_rate).slice(0, 3) as performer, index}
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-r from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-bold text-sm mr-3">
                    {index + 1}
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">{performer.user.name}</p>
                    <p class="text-xs text-gray-500">{performer.total_achieved.toLocaleString()} mushaf</p>
                  </div>
                </div>
                <span class="text-sm font-bold {getAchievementColor(performer.achievement_rate)}">
                  {performer.achievement_rate.toFixed(1)}%
                </span>
              </div>
            {/each}
          </div>
        </div>
        
        <!-- Task Completion Rate -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Task Completion Rate</h3>
          <div class="space-y-4">
            {#each userPerformance as performance}
              <div>
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-gray-700">{performance.user.name}</span>
                  <span class="text-gray-500">
                    {performance.completed_count}/{performance.tasks_count}
                  </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    class="h-2 rounded-full bg-blue-500"
                    style="width: {(performance.completed_count / performance.tasks_count * 100)}%"
                  ></div>
                </div>
              </div>
            {/each}
          </div>
        </div>
        
        <!-- Weekly Trends -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Distribution</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Excellent (&ge;90%)</span>
              <div class="flex items-center">
                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                  <div 
                    class="h-2 rounded-full bg-green-500"
                    style="width: {distributionPercentages.excellent}%"
                  ></div>
                </div>
                <span class="text-sm font-medium text-green-600">
                  {performanceDistribution.excellent}
                </span>
              </div>
            </div>
            
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Good (75-89%)</span>
              <div class="flex items-center">
                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                  <div 
                    class="h-2 rounded-full bg-yellow-500"
                    style="width: {distributionPercentages.good}%"
                  ></div>
                </div>
                <span class="text-sm font-medium text-yellow-600">
                  {performanceDistribution.good}
                </span>
              </div>
            </div>
            
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Fair (60-74%)</span>
              <div class="flex items-center">
                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                  <div 
                    class="h-2 rounded-full bg-orange-500"
                    style="width: {distributionPercentages.fair}%"
                  ></div>
                </div>
                <span class="text-sm font-medium text-orange-600">
                  {performanceDistribution.fair}
                </span>
              </div>
            </div>
            
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Needs Improvement (&lt;60%)</span>
              <div class="flex items-center">
                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                  <div 
                    class="h-2 rounded-full bg-red-500"
                    style="width: {distributionPercentages.needsImprovement}%"
                  ></div>
                </div>
                <span class="text-sm font-medium text-red-600">
                  {performanceDistribution.needsImprovement}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    {/if}
  </div>
</AdminLayout>