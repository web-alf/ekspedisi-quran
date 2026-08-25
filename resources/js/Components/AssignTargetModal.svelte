<script>
  import { createEventDispatcher } from 'svelte';
  
  export let show = false;
  export let warehouseUsers = [];
  export let isLoading = false;
  
  const dispatch = createEventDispatcher();
  
  let form = {
    user_id: '',
    target: 80
  };
  
  let errors = {};
  
  function closeModal() {
    show = false;
    form = { user_id: '', target: 80 };
    errors = {};
    dispatch('close');
  }
  
  function handleSubmit() {
    errors = {};
    
    // Basic validation
    if (!form.user_id) {
      errors.user_id = 'Pilih user warehouse';
      return;
    }
    
    if (!form.target || form.target < 1 || form.target > 200) {
      errors.target = 'Target harus antara 1-200 mushaf';
      return;
    }
    
    dispatch('submit', form);
  }
  
  function handleKeydown(event) {
    if (event.key === 'Escape') {
      closeModal();
    }
  }
</script>

<svelte:window on:keydown={handleKeydown} />

{#if show}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
        on:click={closeModal}
      ></div>

      <!-- This element is to trick the browser into centering the modal contents. -->
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div class="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div>
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
            </svg>
          </div>
          
          <div class="mt-3 text-center sm:mt-5">
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
              Assign Target Harian
            </h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Berikan target packing harian untuk warehouse staff. Sistem akan otomatis menambahkan carry-over dari hari sebelumnya.
              </p>
            </div>
          </div>
        </div>

        <form on:submit|preventDefault={handleSubmit} class="mt-6 space-y-4">
          <!-- User Selection -->
          <div>
            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
              Pilih Warehouse Staff
            </label>
            <select
              id="user_id"
              bind:value={form.user_id}
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {errors.user_id ? 'border-red-300' : ''}"
              disabled={isLoading}
            >
              <option value="">-- Pilih Staff --</option>
              {#each warehouseUsers as user}
                <option value={user.id}>{user.name} ({user.email})</option>
              {/each}
            </select>
            {#if errors.user_id}
              <p class="mt-1 text-sm text-red-600">{errors.user_id}</p>
            {/if}
          </div>

          <!-- Target Input -->
          <div>
            <label for="target" class="block text-sm font-medium text-gray-700 mb-2">
              Target Mushaf
            </label>
            <div class="relative">
              <input
                id="target"
                type="number"
                min="1"
                max="200"
                bind:value={form.target}
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {errors.target ? 'border-red-300' : ''}"
                placeholder="80"
                disabled={isLoading}
              />
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <span class="text-gray-500 text-sm">mushaf</span>
              </div>
            </div>
            {#if errors.target}
              <p class="mt-1 text-sm text-red-600">{errors.target}</p>
            {/if}
            <p class="mt-1 text-xs text-gray-500">
              Sistem akan otomatis menambahkan carry-over dari hari sebelumnya jika ada.
            </p>
          </div>

          <!-- Action Buttons -->
          <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-3">
            <button
              type="button"
              on:click={closeModal}
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm"
              disabled={isLoading}
            >
              Batal
            </button>
            <button
              type="submit"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={isLoading}
            >
              {#if isLoading}
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Assign Target...
              {:else}
                Assign Target
              {/if}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
{/if}

<style>
  /* Custom focus styles */
  select:focus,
  input:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
</style>