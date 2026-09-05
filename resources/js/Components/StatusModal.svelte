<script>
  import { createEventDispatcher } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  const dispatch = createEventDispatcher();
  
  export let show = false;
  export let type = 'info'; // 'success', 'error', 'warning', 'info', 'confirm'
  export let title = '';
  export let message = '';
  export let confirmText = 'OK';
  export let cancelText = 'Batal';
  export let showCancel = false;
  export let loading = false;
  
  function handleConfirm() {
    dispatch('confirm');
  }
  
  function handleCancel() {
    dispatch('cancel');
    show = false;
  }
  
  function handleClose() {
    if (!loading) {
      dispatch('close');
      show = false;
    }
  }
  
  // Close modal when clicking outside (if not loading)
  function handleBackdropClick(event) {
    if (event.target === event.currentTarget && !loading) {
      handleClose();
    }
  }
  
  // Get icon based on type
  function getIcon(type) {
    switch (type) {
      case 'success':
        return 'check-circle';
      case 'error':
        return 'x-circle';
      case 'warning':
        return 'exclamation-triangle';
      case 'confirm':
        return 'question-mark-circle';
      case 'info':
      default:
        return 'information-circle';
    }
  }
  
  // Get color classes based on type
  function getColorClasses(type) {
    switch (type) {
      case 'success':
        return {
          icon: 'bg-green-100 text-green-600',
          button: 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
        };
      case 'error':
        return {
          icon: 'bg-red-100 text-red-600',
          button: 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
        };
      case 'warning':
        return {
          icon: 'bg-yellow-100 text-yellow-600',
          button: 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'
        };
      case 'confirm':
        return {
          icon: 'bg-blue-100 text-blue-600',
          button: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
        };
      case 'info':
      default:
        return {
          icon: 'bg-blue-100 text-blue-600',
          button: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
        };
    }
  }
  
  $: colorClasses = getColorClasses(type);
  $: icon = getIcon(type);
</script>

{#if show}
  <!-- Modal backdrop -->
  <div 
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    on:click|self={handleBackdropClick}
    on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleClose(); } }}
    role="button"
    aria-label="Tutup dialog"
    tabindex="0"
    transition:fade={{ duration: 200 }}
  >
    <!-- Modal container -->
    <div 
      class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-200"
      role="dialog"
      aria-modal="true"
      tabindex="-1"
      transition:scale={{ duration: 200, start: 0.9 }}
    >
      <!-- Modal content -->
      <div class="p-6">
        <!-- Icon and Title -->
        <div class="flex items-center mb-4">
          <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 {colorClasses.icon}">
            <HeroIcon name={icon} class="w-7 h-7" />
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">{title}</h3>
          </div>
        </div>
        
        <!-- Message -->
        <div class="mb-6">
          <p class="text-gray-700 leading-relaxed">{message}</p>
        </div>
        
        <!-- Action buttons -->
        <div class="flex gap-3 justify-end">
          {#if showCancel}
            <button
              on:click={handleCancel}
              disabled={loading}
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {cancelText}
            </button>
          {/if}
          
          <button
            on:click={handleConfirm}
            disabled={loading}
            class="px-4 py-2 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed {colorClasses.button}"
          >
            {#if loading}
              <div class="flex items-center">
                <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                <span>Memproses...</span>
              </div>
            {:else}
              {confirmText}
            {/if}
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<script context="module">
  import { fade, scale } from 'svelte/transition';
</script>

<style>
  /* Smooth transitions */
  .transform {
    transform-origin: center;
  }
</style>
