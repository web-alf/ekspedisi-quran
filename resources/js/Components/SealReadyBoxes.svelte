<script>
  import { onMount, onDestroy } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  // Props
  export const currentUser = null;
  
  // State
  let refreshInterval = null;
  let isLoading = false;
  let sealReadyBoxes = [];
  
  onMount(() => {
    // Initial load
    loadSealReadyBoxes();
    
    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(() => {
      loadSealReadyBoxes();
    }, 30000);
  });
  
  onDestroy(() => {
    if (refreshInterval) {
      clearInterval(refreshInterval);
    }
  });
  
  async function loadSealReadyBoxes() {
    if (isLoading) return;
    
    try {
      isLoading = true;
      const response = await fetch('/admin/warehouse/boxes-ready-for-seal', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          sealReadyBoxes = result.boxes || [];
        }
      } else {
        console.warn('Failed to load seal ready boxes:', response.status);
      }
    } catch (error) {
      console.error('Error loading seal ready boxes:', error);
    } finally {
      isLoading = false;
    }
  }
  
  async function proceedToSeal(box) {
    if (!confirm(`Apakah Anda yakin ingin menyegel box ${box.kode_kerdus}?`)) {
      return;
    }
    
    try {
      const response = await fetch(`/admin/warehouse/packing/box/${box.id}/seal`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const result = await response.json();
      
      if (result.success) {
        showNotification(`✅ Box ${box.kode_kerdus} berhasil di-seal! Kode: ${result.seal_code}`, 'success');
        // Refresh the list
        await loadSealReadyBoxes();
      } else {
        showNotification(`❌ Gagal seal box: ${result.message}`, 'error');
      }
    } catch (error) {
      console.error('Error sealing box:', error);
      showNotification('❌ Error sealing box', 'error');
    }
  }
  
  function showNotification(message, type = 'info') {
    const className = type === 'success' ? 'bg-green-100 text-green-800 border-green-200' :
                     type === 'error' ? 'bg-red-100 text-red-800 border-red-200' :
                     'bg-blue-100 text-blue-800 border-blue-200';
    
    // Create inline notification instead of floating
    const notification = document.createElement('div');
    notification.className = `mt-4 px-4 py-2 rounded-lg border ${className} max-w-md`;
    notification.textContent = message;
    
    // Insert at the beginning of the seal ready component instead of floating
    const container = document.querySelector('[data-seal-ready-boxes]');
    if (container) {
      container.insertBefore(notification, container.firstChild);
    } else {
      document.body.appendChild(notification);
    }
    
    setTimeout(() => {
      notification.remove();
    }, 5000);
  }
  
  function getStatusBadge(status) {
    switch (status) {
      case 'ready_to_seal':
        return { text: 'Ready to Seal', class: 'bg-green-100 text-green-800 border-green-200' };
      case 'seal_requested':
        return { text: 'Seal Requested', class: 'bg-yellow-100 text-yellow-800 border-yellow-200' };
      default:
        return { text: status, class: 'bg-gray-100 text-gray-800 border-gray-200' };
    }
  }
  
  function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
      day: '2-digit',
      month: '2-digit', 
      hour: '2-digit',
      minute: '2-digit'
    });
  }
</script>

<div class="space-y-4" data-seal-ready-boxes>
  <!-- Refresh Controls -->
  <div class="flex items-center justify-between">
    <p class="text-sm text-gray-600">Shared boxes yang telah siap untuk di-seal</p>
    <div class="flex items-center space-x-2">
      {#if isLoading}
        <div class="animate-spin rounded-full h-4 w-4 border-2 border-orange-500 border-t-transparent"></div>
      {/if}
      <button
        on:click={loadSealReadyBoxes}
        class="text-sm bg-orange-100 text-orange-800 px-3 py-1 rounded-full hover:bg-orange-200 transition-colors"
      >
        Refresh
      </button>
    </div>
  </div>

  {#if sealReadyBoxes.length === 0}
    <div class="text-center py-8 text-gray-500">
      <HeroIcon name="cube" class="w-12 h-12 mx-auto mb-4 text-gray-400" />
      <p class="font-medium">Tidak ada boxes yang siap untuk sealing</p>
      <p class="text-sm mt-1">Boxes akan muncul di sini ketika semua contributor telah selesai</p>
    </div>
  {:else}
    <!-- Seal Ready Boxes List -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      {#each sealReadyBoxes as box}
        <div class="border border-green-300 bg-green-50 rounded-lg p-4 shadow-sm">
          <!-- Box Header -->
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2">
              <span class="font-mono text-lg font-semibold text-gray-900">{box.kode_kerdus}</span>
              <span class="text-xs px-2 py-1 rounded-full {box.jenis_badge_class || 'bg-gray-100 text-gray-800'}">
                {box.jenis_name}
              </span>
            </div>
            
            <div class="flex items-center space-x-2">
              <span class="text-xs px-2 py-1 rounded-full border {getStatusBadge(box.status).class}">
                {getStatusBadge(box.status).text}
              </span>
              {#if box.assignment_type === 'shared'}
                <HeroIcon name="user-group" class="w-4 h-4 text-green-600" />
              {/if}
            </div>
          </div>
          
          <!-- Box Stats -->
          <div class="grid grid-cols-2 gap-4 mb-3">
            <div>
              <p class="text-xs text-gray-600">Items / Capacity</p>
              <p class="font-semibold">{box.total_items} / {box.capacity}</p>
            </div>
            <div>
              <p class="text-xs text-gray-600">Fill Percentage</p>
              <p class="font-semibold">{box.completion_status?.capacity_filled || 0}%</p>
            </div>
          </div>
          
          <!-- Contributors (for shared boxes) -->
          {#if box.contributors && box.contributors.length > 0}
            <div class="mb-3">
              <p class="text-xs text-gray-600 mb-2">Contributors:</p>
              <div class="flex flex-wrap gap-1">
                {#each box.contributors as contributor}
                  <span class="text-xs bg-white px-2 py-1 rounded border">
                    {contributor.user_name} ({contributor.completed_items}/{contributor.allocated_items})
                  </span>
                {/each}
              </div>
            </div>
          {/if}
          
          <!-- Completion Status Message -->
          {#if box.completion_status}
            <div class="mb-3">
              <div class="flex items-center space-x-2">
                <span class="text-lg">🎉</span>
                <span class="text-sm font-medium text-green-800">
                  {box.completion_status.message}
                </span>
              </div>
            </div>
          {/if}
          
          <!-- Ready Times -->
          <div class="text-xs text-gray-600 space-y-1 mb-4">
            {#if box.ready_to_seal_at}
              <div>Completed at: {formatDateTime(box.ready_to_seal_at)}</div>
            {/if}
            {#if box.seal_requested_at}
              <div>Seal requested at: {formatDateTime(box.seal_requested_at)}</div>
            {/if}
          </div>
          
          <!-- Action Button -->
          <button
            on:click={() => proceedToSeal(box)}
            class="w-full bg-green-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-green-700 transition-colors flex items-center justify-center space-x-2"
          >
            <HeroIcon name="lock-closed" class="w-4 h-4" />
            <span>Seal Box</span>
          </button>
        </div>
      {/each}
    </div>
  {/if}
</div>