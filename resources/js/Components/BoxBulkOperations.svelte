<script>
  import { createEventDispatcher, onMount } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  const dispatch = createEventDispatcher();
  
  // Props
  export let isOpen = false;
  export let boxData = null;
  export let availableStatuses = [];
  
  // State
  let operationType = 'status'; // 'status', 'address', 'both'
  let selectedStatusId = '';
  let newAddress = '';
  let notes = '';
  let isLoading = false;
  let previewData = null;
  let showPreview = false;
  
  $: if (isOpen && boxData) {
    loadPreview();
  }
  
  onMount(() => {
    loadAvailableStatuses();
  });
  
  async function loadAvailableStatuses() {
    try {
      const response = await fetch('/admin/box-bulk/statuses');
      const result = await response.json();
      if (result.success) {
        availableStatuses = result.data;
      }
    } catch (error) {
      console.error('Failed to load statuses:', error);
    }
  }
  
  async function loadPreview() {
    if (!boxData?.qrData) return;
    
    try {
      isLoading = true;
      const response = await fetch('/admin/box-bulk/preview', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          qr_data: boxData.qrData
        })
      });
      
      const result = await response.json();
      if (result.success) {
        previewData = result.data;
        showPreview = true;
      } else {
        throw new Error(result.message);
      }
    } catch (error) {
      console.error('Preview failed:', error);
      dispatch('error', { message: 'Failed to load preview: ' + error.message });
    } finally {
      isLoading = false;
    }
  }
  
  async function performBulkUpdate() {
    if (!validateForm()) return;
    
    try {
      isLoading = true;
      
      let endpoint = '/admin/box-bulk/update-both';
      let requestData = {
        qr_data: boxData.qrData,
        notes: notes
      };
      
      if (operationType === 'status' || operationType === 'both') {
        if (selectedStatusId) {
          requestData.new_status_id = selectedStatusId;
        }
      }
      
      if (operationType === 'address' || operationType === 'both') {
        if (newAddress.trim()) {
          requestData.new_address = newAddress.trim();
        }
      }
      
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(requestData)
      });
      
      const result = await response.json();
      
      if (result.success) {
        dispatch('success', {
          message: result.message,
          data: result.data
        });
        closeModal();
      } else {
        throw new Error(result.message);
      }
      
    } catch (error) {
      console.error('Bulk update failed:', error);
      dispatch('error', { 
        message: 'Bulk update failed: ' + error.message 
      });
    } finally {
      isLoading = false;
    }
  }
  
  function validateForm() {
    if (operationType === 'status' && !selectedStatusId) {
      dispatch('error', { message: 'Please select a status' });
      return false;
    }
    
    if (operationType === 'address' && !newAddress.trim()) {
      dispatch('error', { message: 'Please enter a new address' });
      return false;
    }
    
    if (operationType === 'both' && !selectedStatusId && !newAddress.trim()) {
      dispatch('error', { message: 'Please select at least one update option' });
      return false;
    }
    
    return true;
  }
  
  function closeModal() {
    isOpen = false;
    resetForm();
    dispatch('close');
  }
  
  function resetForm() {
    operationType = 'status';
    selectedStatusId = '';
    newAddress = '';
    notes = '';
    showPreview = false;
    previewData = null;
  }
  
  function getStatusName(statusId) {
    const status = availableStatuses.find(s => s.id == statusId);
    return status ? status.nama_status : 'Unknown';
  }
</script>

{#if isOpen}
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
      
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">Box Bulk Operations</h2>
          <button
            on:click={closeModal}
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <HeroIcon name="x-mark" class="w-6 h-6" />
          </button>
        </div>
        
        {#if previewData}
          <div class="mt-2 text-sm text-gray-600">
            Box: <span class="font-mono font-medium">{previewData.box.kode_kerdus}</span> | 
            {previewData.box.item_count} items | 
            {previewData.box.jenis_quran}
          </div>
        {/if}
      </div>
      
      <!-- Loading State -->
      {#if isLoading}
        <div class="px-6 py-8 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p class="mt-2 text-gray-600">Loading...</p>
        </div>
      
      <!-- Main Content -->
      {:else if showPreview && previewData}
        <div class="px-6 py-4">
          
          <!-- Box Info -->
          <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h3 class="font-semibold text-gray-900 mb-2">Box Information</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Code:</span>
                <span class="font-mono font-medium ml-2">{previewData.box.kode_kerdus}</span>
              </div>
              <div>
                <span class="text-gray-600">Status:</span>
                <span class="ml-2 capitalize">{previewData.box.status}</span>
              </div>
              <div>
                <span class="text-gray-600">Items:</span>
                <span class="ml-2 font-medium">{previewData.box.item_count}</span>
              </div>
              <div>
                <span class="text-gray-600">Staff:</span>
                <span class="ml-2">{previewData.box.user_name}</span>
              </div>
            </div>
            
            {#if previewData.box.seal_code}
              <div class="mt-2 text-sm">
                <span class="text-gray-600">Seal:</span>
                <span class="font-mono ml-2">{previewData.box.seal_code}</span>
                <span class="text-gray-500 ml-2">({previewData.box.sealed_at})</span>
              </div>
            {/if}
          </div>
          
          <!-- Operation Type Selection -->
          <div class="mb-6">
            <h3 class="font-semibold text-gray-900 mb-3">Select Operation</h3>
            <div class="grid grid-cols-3 gap-3">
              <label class="relative cursor-pointer">
                <input 
                  type="radio" 
                  bind:group={operationType} 
                  value="status"
                  class="sr-only"
                />
                <div class="p-3 border-2 rounded-lg transition-colors {operationType === 'status' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}">
                  <div class="text-sm font-medium">Update Status</div>
                  <div class="text-xs text-gray-600 mt-1">Change status for all items</div>
                </div>
              </label>
              
              <label class="relative cursor-pointer">
                <input 
                  type="radio" 
                  bind:group={operationType} 
                  value="address"
                  class="sr-only"
                />
                <div class="p-3 border-2 rounded-lg transition-colors {operationType === 'address' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}">
                  <div class="text-sm font-medium">Update Address</div>
                  <div class="text-xs text-gray-600 mt-1">Change address for all items</div>
                </div>
              </label>
              
              <label class="relative cursor-pointer">
                <input 
                  type="radio" 
                  bind:group={operationType} 
                  value="both"
                  class="sr-only"
                />
                <div class="p-3 border-2 rounded-lg transition-colors {operationType === 'both' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}">
                  <div class="text-sm font-medium">Update Both</div>
                  <div class="text-xs text-gray-600 mt-1">Change status and address</div>
                </div>
              </label>
            </div>
          </div>
          
          <!-- Form Fields -->
          <div class="space-y-4 mb-6">
            
            <!-- Status Selection -->
            {#if operationType === 'status' || operationType === 'both'}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  New Status
                </label>
                <select
                  bind:value={selectedStatusId}
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Select Status...</option>
                  {#each availableStatuses as status}
                    <option value={status.id}>{status.nama_status}</option>
                  {/each}
                </select>
              </div>
            {/if}
            
            <!-- Address Input -->
            {#if operationType === 'address' || operationType === 'both'}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  New Address
                </label>
                <textarea
                  bind:value={newAddress}
                  rows="3"
                  placeholder="Enter new address for all items in this box..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                ></textarea>
              </div>
            {/if}
            
            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Notes (Optional)
              </label>
              <input
                type="text"
                bind:value={notes}
                placeholder="Add notes about this bulk operation..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
          
          <!-- Items Preview -->
          <div class="mb-6">
            <h3 class="font-semibold text-gray-900 mb-3">
              Items That Will Be Updated ({previewData.items.length})
            </h3>
            <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resi</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Donatur/Wakif</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Current Status</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Address</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  {#each previewData.items as item}
                    <tr class="hover:bg-gray-50">
                      <td class="px-3 py-2 text-sm font-mono">{item.no_resi}</td>
                      <td class="px-3 py-2 text-sm">
                        <div>{item.donatur}</div>
                        {#if item.wakif !== item.donatur}
                          <div class="text-xs text-gray-500">Wakif: {item.wakif}</div>
                        {/if}
                      </td>
                      <td class="px-3 py-2 text-sm">{item.current_status}</td>
                      <td class="px-3 py-2 text-sm text-gray-600">
                        {item.alamat_tujuan || 'No address'}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Confirmation Summary -->
          {#if (operationType === 'status' && selectedStatusId) || (operationType === 'address' && newAddress.trim()) || operationType === 'both'}
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <h4 class="font-medium text-yellow-800 mb-2">Confirmation Summary</h4>
              <div class="text-sm text-yellow-700">
                <p class="mb-2">You are about to update <strong>{previewData.items.length} items</strong> in box <strong>{previewData.box.kode_kerdus}</strong>:</p>
                <ul class="list-disc list-inside space-y-1">
                  {#if (operationType === 'status' || operationType === 'both') && selectedStatusId}
                    <li>Status will be changed to: <strong>{getStatusName(selectedStatusId)}</strong></li>
                  {/if}
                  {#if (operationType === 'address' || operationType === 'both') && newAddress.trim()}
                    <li>Address will be updated to: <strong>{newAddress.substring(0, 50)}...</strong></li>
                  {/if}
                </ul>
              </div>
            </div>
          {/if}
        </div>
        
        <!-- Footer Actions -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
          <button
            on:click={closeModal}
            class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition-colors"
            disabled={isLoading}
          >
            Cancel
          </button>
          <button
            on:click={performBulkUpdate}
            disabled={isLoading || !validateForm()}
            class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors"
          >
            {isLoading ? 'Updating...' : 'Apply Bulk Update'}
          </button>
        </div>
      
      {:else}
        <div class="px-6 py-8 text-center text-gray-500">
          No box data available
        </div>
      {/if}
    </div>
  </div>
{/if}