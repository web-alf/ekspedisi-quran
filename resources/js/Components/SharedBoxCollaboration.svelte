<script>
  import { onMount, onDestroy } from 'svelte';
  import { page } from '@inertiajs/svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  // Props
  export let sharedBoxes = [];
  export let currentUser = null;
  
  // State
  let refreshInterval = null;
  let isLoading = false;
  let expandedBoxes = new Set();
  
  onMount(() => {
    // Auto-refresh every 10 seconds for real-time collaboration
    refreshInterval = setInterval(() => {
      refreshSharedBoxData();
    }, 10000);
  });
  
  onDestroy(() => {
    if (refreshInterval) {
      clearInterval(refreshInterval);
    }
  });
  
  async function refreshSharedBoxData() {
    if (isLoading) return;
    
    try {
      isLoading = true;
      const response = await fetch('/admin/warehouse/shared-boxes-status', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        if (data.shared_boxes) {
          // Check if data actually changed before updating
          const hasChanged = JSON.stringify(sharedBoxes) !== JSON.stringify(data.shared_boxes);
          if (hasChanged) {
            sharedBoxes = data.shared_boxes;
          }
        }
      } else {
        console.warn('Failed to refresh shared box data:', response.status);
      }
    } catch (error) {
      console.error('Error refreshing shared box data:', error);
    } finally {
      isLoading = false;
    }
  }
  
  function toggleBoxExpansion(boxId) {
    if (expandedBoxes.has(boxId)) {
      expandedBoxes.delete(boxId);
    } else {
      expandedBoxes.add(boxId);
    }
    expandedBoxes = new Set(expandedBoxes); // Trigger reactivity
  }
  
  function getContributorStatusColor(contributor) {
    if (contributor.is_completed) return 'text-green-600';
    if (contributor.is_started && !contributor.is_completed) return 'text-blue-600';
    return 'text-gray-500';
  }
  
  function getContributorStatusIcon(contributor) {
    if (contributor.is_completed) return '✅';
    if (contributor.is_started && !contributor.is_completed) return '🔄';
    return '⏳';
  }
  
  function getBoxProgressColor(percentage) {
    if (percentage >= 80) return 'bg-green-500';
    if (percentage >= 60) return 'bg-yellow-500';
    if (percentage >= 40) return 'bg-orange-500';
    return 'bg-red-500';
  }
  
  function getCompletionPhaseColor(phase) {
    switch (phase) {
      case 'ready_to_seal': return 'bg-green-100 text-green-800 border-green-200';
      case 'partial_completion': return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'completed_insufficient': return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'not_started': return 'bg-gray-100 text-gray-800 border-gray-200';
      default: return 'bg-blue-100 text-blue-800 border-blue-200';
    }
  }
  
  function getCompletionPhaseIcon(phase) {
    switch (phase) {
      case 'ready_to_seal': return '🎉';
      case 'partial_completion': return '⏳';
      case 'completed_insufficient': return '⚠️';
      case 'not_started': return '🔄';
      default: return '📦';
    }
  }
  
  // Completion workflow actions
  async function requestSealing(boxId) {
    try {
      const response = await fetch(`/admin/warehouse/box/${boxId}/seal-request`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Refresh data to show updated status
        await refreshSharedBoxData();
        // Show success feedback
        showNotification('Seal request submitted successfully!', 'success');
      } else {
        showNotification(result.error || 'Failed to request sealing', 'error');
      }
    } catch (error) {
      console.error('Error requesting sealing:', error);
      showNotification('Error requesting sealing', 'error');
    }
  }
  
  async function triggerCompletion(boxId) {
    try {
      const response = await fetch(`/admin/warehouse/box/${boxId}/trigger-completion`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const result = await response.json();
      
      if (result.success) {
        await refreshSharedBoxData();
        showNotification('Completion workflow triggered!', 'success');
      } else {
        showNotification(result.error || 'Failed to trigger completion', 'error');
      }
    } catch (error) {
      console.error('Error triggering completion:', error);
      showNotification('Error triggering completion', 'error');
    }
  }
  
  function showNotification(message, type = 'info') {
    // Simple notification - inline instead of floating
    const className = type === 'success' ? 'bg-green-100 text-green-800 border-green-200' :
                     type === 'error' ? 'bg-red-100 text-red-800 border-red-200' :
                     'bg-blue-100 text-blue-800 border-blue-200';
    
    // Create inline notification in the current component instead of floating
    const notification = document.createElement('div');
    notification.className = `mt-4 px-4 py-2 rounded-lg border ${className}`;
    notification.textContent = message;
    
    // Insert at the beginning of the component instead of floating
    const container = document.querySelector('[data-shared-box-collaboration]');
    if (container) {
      container.insertBefore(notification, container.firstChild);
    } else {
      document.body.appendChild(notification);
    }
    
    setTimeout(() => {
      notification.remove();
    }, 3000);
  }
  
  function formatTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleTimeString('id-ID', { 
      hour: '2-digit', 
      minute: '2-digit' 
    });
  }
  
  function getRelativeTime(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));
    
    if (diffInMinutes < 1) return 'Baru saja';
    if (diffInMinutes < 60) return `${diffInMinutes}m yang lalu`;
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h yang lalu`;
    
    return date.toLocaleDateString('id-ID');
  }
  
  function isCurrentUser(userId) {
    return currentUser && currentUser.id === userId;
  }
</script>

<div class="space-y-4" data-shared-box-collaboration>
  <!-- Header with refresh indicator -->
  <div class="flex items-center justify-between">
    <div>
      <h3 class="text-lg font-semibold text-gray-900">Shared Box Collaboration</h3>
      <p class="text-sm text-gray-600">Real-time status dari shared boxes dan tim yang bekerja</p>
    </div>
    <div class="flex items-center space-x-2">
      {#if isLoading}
        <div class="animate-spin rounded-full h-4 w-4 border-2 border-blue-500 border-t-transparent"></div>
      {/if}
      <span class="text-xs text-gray-500">Auto-refresh: 10s</span>
    </div>
  </div>

  {#if sharedBoxes.length === 0}
    <div class="text-center py-8 text-gray-500">
      <HeroIcon name="cube" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
      <p class="font-medium">Tidak ada shared boxes aktif</p>
      <p class="text-sm mt-1">Shared boxes akan muncul ketika ada assignment collaboration</p>
    </div>
  {:else}
    <!-- Shared Boxes List -->
    <div class="space-y-3">
      {#each sharedBoxes as box}
        <div class="bg-white border rounded-lg shadow-sm {
          box.completion_status?.phase === 'ready_to_seal' ? 'border-green-300 bg-green-50' :
          box.completion_status?.phase === 'completed_insufficient' ? 'border-orange-300 bg-orange-50' :
          box.completion_status?.phase === 'partial_completion' ? 'border-blue-300 bg-blue-50' :
          'border-gray-200'
        }">
          <!-- Box Header -->
          <div class="p-4 border-b border-gray-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2">
                  <span class="font-mono text-lg font-semibold text-gray-900">{box.kode_kerdus}</span>
                  <span class="text-xs px-2 py-1 rounded-full {box.jenis_badge_class || 'bg-gray-100 text-gray-800'}">
                    {box.jenis_name}
                  </span>
                </div>
                <div class="flex items-center space-x-2">
                  <HeroIcon name="user-group" class="w-5 h-5 text-yellow-600" />
                  <span class="text-sm text-gray-600">
                    {box.contributors_count} Contributors
                  </span>
                </div>
              </div>
              
              <div class="flex items-center space-x-3">
                <!-- Overall Progress -->
                <div class="text-right">
                  <div class="text-sm font-medium text-gray-900">
                    {box.total_completed}/{box.total_allocated} items
                  </div>
                  <div class="text-xs text-gray-500">
                    {box.overall_progress}% complete
                  </div>
                </div>
                
                <!-- Expand/Collapse Button -->
                <button
                  on:click={() => toggleBoxExpansion(box.id)}
                  class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  <HeroIcon 
                    name="chevron-down"
                    class="w-5 h-5 transform transition-transform {expandedBoxes.has(box.id) ? 'rotate-180' : ''}" 
                  />
                </button>
              </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-3">
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div 
                  class="h-2 rounded-full transition-all duration-300 {getBoxProgressColor(box.overall_progress)}"
                  style="width: {box.overall_progress}%"
                ></div>
              </div>
            </div>
            
            <!-- Completion Status -->
            {#if box.completion_status}
              <div class="mt-3">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <span class="text-lg">{getCompletionPhaseIcon(box.completion_status.phase)}</span>
                    <span class="text-xs px-2 py-1 rounded-full border {getCompletionPhaseColor(box.completion_status.phase)}">
                      {box.completion_status.message}
                    </span>
                  </div>
                  
                  <!-- Completion Actions -->
                  {#if box.completion_status.ready_to_seal && box.completion_status.can_proceed_to_seal}
                    <button
                      on:click={() => requestSealing(box.id)}
                      class="text-xs bg-green-600 text-white px-3 py-1 rounded-full hover:bg-green-700 transition-colors"
                      title="Request sealing for this shared box"
                    >
                      🔒 Request Seal
                    </button>
                  {:else if box.completion_status.phase === 'completed_insufficient'}
                    <button
                      on:click={() => triggerCompletion(box.id)}
                      class="text-xs bg-orange-600 text-white px-3 py-1 rounded-full hover:bg-orange-700 transition-colors"
                      title="Force completion workflow"
                    >
                      ⚡ Force Complete
                    </button>
                  {/if}
                </div>
                
                <!-- Capacity and Contributors Status -->
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-600">
                  <div>
                    <span class="font-medium">Capacity:</span> 
                    {box.completion_status.capacity_filled}% filled
                  </div>
                  <div>
                    <span class="font-medium">Contributors:</span>
                    {box.completion_status.completed_contributors}/{box.completion_status.total_contributors} done
                  </div>
                </div>
              </div>
            {/if}
          </div>

          <!-- Contributors Details (Expandable) -->
          {#if expandedBoxes.has(box.id)}
            <div class="p-4">
              <h4 class="font-medium text-gray-900 mb-3">Tim Contributors</h4>
              
              <div class="space-y-3">
                {#each box.contributors as contributor}
                  <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg {isCurrentUser(contributor.user_id) ? 'ring-2 ring-blue-200 bg-blue-50' : ''}">
                    <div class="flex items-center space-x-3">
                      <!-- Status Icon -->
                      <span class="text-lg" title="{contributor.is_completed ? 'Completed' : contributor.is_started ? 'Active' : 'Pending'}">
                        {getContributorStatusIcon(contributor)}
                      </span>
                      
                      <!-- User Info -->
                      <div>
                        <div class="flex items-center space-x-2">
                          <span class="font-medium {getContributorStatusColor(contributor)}">
                            {contributor.user_name}
                            {#if isCurrentUser(contributor.user_id)}
                              <span class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded-full ml-1">You</span>
                            {/if}
                          </span>
                        </div>
                        <div class="text-xs text-gray-500">
                          {#if contributor.is_completed}
                            Selesai: {formatTime(contributor.completed_at)}
                          {:else if contributor.is_started}
                            Mulai: {formatTime(contributor.started_at)} ({getRelativeTime(contributor.started_at)})
                          {:else}
                            Belum mulai
                          {/if}
                        </div>
                      </div>
                    </div>
                    
                    <!-- Progress Info -->
                    <div class="text-right">
                      <div class="text-sm font-medium {getContributorStatusColor(contributor)}">
                        {contributor.completed_items}/{contributor.allocated_items}
                      </div>
                      <div class="text-xs text-gray-500">
                        {contributor.progress_percentage}%
                        {#if contributor.is_started && !contributor.is_completed && contributor.contribution_rate > 0}
                          <span class="ml-1">• {contributor.contribution_rate}/min</span>
                        {/if}
                      </div>
                    </div>
                  </div>
                {/each}
              </div>
              
              <!-- Collaboration Stats -->
              <div class="mt-4 pt-3 border-t border-gray-200">
                <div class="grid grid-cols-3 gap-4 text-center">
                  <div>
                    <div class="text-sm font-medium text-gray-900">{box.active_contributors}</div>
                    <div class="text-xs text-gray-500">Active Now</div>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">{box.completed_contributors}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">{box.total_remaining}</div>
                    <div class="text-xs text-gray-500">Items Left</div>
                  </div>
                </div>
              </div>
            </div>
          {/if}
        </div>
      {/each}
    </div>
  {/if}
</div>