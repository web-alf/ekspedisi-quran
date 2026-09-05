<script>
  import { createEventDispatcher } from 'svelte';
  import { fade, scale } from 'svelte/transition';
  import { quintOut } from 'svelte/easing';
  import HeroIcon from './UI/HeroIcon.svelte';

  export let show = false;
  export let title = 'Konfirmasi';
  export let message = 'Apakah Anda yakin?';
  export let type = 'warning'; // 'warning', 'danger', 'info', 'success'
  export let confirmText = 'OK';
  export let cancelText = 'Cancel';
  export let confirmOnly = false; // Show only confirm button, no cancel

  const dispatch = createEventDispatcher();

  // Type configurations
  const configs = {
    warning: {
      iconBg: 'bg-amber-100',
      iconColor: 'text-amber-600',
      confirmBtn: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
      iconName: 'exclamation-triangle'
    },
    danger: {
      iconBg: 'bg-red-100',
      iconColor: 'text-red-600',
      confirmBtn: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
      iconName: 'x-circle'
    },
    info: {
      iconBg: 'bg-blue-100',
      iconColor: 'text-blue-600',
      confirmBtn: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
      iconName: 'information-circle'
    },
    success: {
      iconBg: 'bg-green-100',
      iconColor: 'text-green-600',
      confirmBtn: 'bg-green-600 hover:bg-green-700 focus:ring-green-500',
      iconName: 'check-circle'
    }
  };

  $: config = configs[type] || configs.warning;

  function handleConfirm() {
    dispatch('confirm');
    show = false;
  }

  function handleCancel() {
    dispatch('cancel');
    show = false;
  }

  function handleBackdropClick(e) {
    if (e.target === e.currentTarget) {
      handleCancel();
    }
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') {
      handleCancel();
    } else if (e.key === 'Enter') {
      handleConfirm();
    }
  }
</script>

<svelte:window on:keydown={handleKeydown} />

{#if show}
  <!-- Backdrop -->
  <div 
    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-[10000]"
    transition:fade={{ duration: 200 }}
    role="button"
    tabindex="0"
    aria-label="Tutup dialog"
    on:click={handleBackdropClick}
    on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleCancel(); } }}
  >
    <!-- Dialog -->
    <div class="fixed inset-0 z-[10001] overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div 
          class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
          transition:scale={{ duration: 200, easing: quintOut, start: 0.95 }}
        >
          <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <!-- Icon -->
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full {config.iconBg} sm:mx-0 sm:h-10 sm:w-10">
                <HeroIcon name={config.iconName} class="w-6 h-6 {config.iconColor}" />
              </div>
              
              <!-- Content -->
              <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">
                  {title}
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    {message}
                  </p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <!-- Confirm Button -->
            <button
              type="button"
              class="inline-flex w-full justify-center rounded-md px-3 py-2 text-sm font-semibold text-white shadow-sm {config.confirmBtn} focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto"
              on:click={handleConfirm}
            >
              {confirmText}
            </button>
            
            <!-- Cancel Button -->
            {#if !confirmOnly}
              <button
                type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                on:click={handleCancel}
              >
                {cancelText}
              </button>
            {/if}
          </div>
        </div>
      </div>
    </div>
  </div>
{/if}
