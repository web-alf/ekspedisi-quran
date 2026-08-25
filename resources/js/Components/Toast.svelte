<script>
  import { createEventDispatcher } from 'svelte';
  import { fade, fly } from 'svelte/transition';

  export let type = 'success'; // 'success', 'error', 'warning', 'info'
  export let title = '';
  export let message = '';
  export let duration = 4000; // Auto close after 4 seconds
  export let closable = true;

  const dispatch = createEventDispatcher();

  let visible = true;

  // Auto close timer
  let timer;
  if (duration > 0) {
    timer = setTimeout(() => {
      close();
    }, duration);
  }

  function close() {
    visible = false;
    if (timer) clearTimeout(timer);
    setTimeout(() => dispatch('close'), 300); // Wait for exit animation
  }

  // Toast type configurations
  const configs = {
    success: {
      bgColor: 'bg-white',
      borderColor: 'border-l-green-500',
      iconBg: 'bg-green-50',
      iconColor: 'text-green-500',
      titleColor: 'text-gray-900',
      icon: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
               <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
             </svg>`
    },
    error: {
      bgColor: 'bg-white',
      borderColor: 'border-l-red-500',
      iconBg: 'bg-red-50',
      iconColor: 'text-red-500',
      titleColor: 'text-gray-900',
      icon: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
               <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
             </svg>`
    },
    warning: {
      bgColor: 'bg-white',
      borderColor: 'border-l-amber-500',
      iconBg: 'bg-amber-50',
      iconColor: 'text-amber-500',
      titleColor: 'text-gray-900',
      icon: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
               <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
             </svg>`
    },
    info: {
      bgColor: 'bg-white',
      borderColor: 'border-l-blue-500',
      iconBg: 'bg-blue-50',
      iconColor: 'text-blue-500',
      titleColor: 'text-gray-900',
      icon: `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
               <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
             </svg>`
    }
  };

  $: config = configs[type] || configs.success;
</script>

{#if visible}
  <div
    class="max-w-sm w-full sm:w-96"
    in:fly="{{ y: 100, duration: 300 }}"
    out:fly="{{ y: 100, duration: 300 }}"
  >
    <div class="relative rounded-lg shadow-xl border-l-4 {config.bgColor} {config.borderColor} overflow-hidden">
      <!-- Progress bar for auto-close -->
      {#if duration > 0}
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-100">
          <div 
            class="h-full {config.borderColor.replace('border-l-', 'bg-')} origin-left"
            style="animation: progressbar {duration}ms linear;"
          ></div>
        </div>
      {/if}
      
      <div class="p-4">
        <div class="flex items-start">
          <!-- Icon -->
          <div class="flex-shrink-0">
            <div class="w-8 h-8 rounded-full {config.iconBg} flex items-center justify-center {config.iconColor}">
              {@html config.icon}
            </div>
          </div>
          
          <!-- Content -->
          <div class="ml-3 flex-1">
            {#if title}
              <h4 class="text-sm font-semibold {config.titleColor}">{title}</h4>
            {/if}
            <p class="text-sm text-gray-600 {title ? 'mt-1' : ''}">{message}</p>
          </div>
          
          <!-- Close button -->
          {#if closable}
            <div class="ml-4 flex-shrink-0">
              <button
                on:click={close}
                class="inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition ease-in-out duration-150"
                aria-label="Close notification"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          {/if}
        </div>
      </div>
    </div>
  </div>
{/if}

<style>
  @keyframes progressbar {
    from { 
      transform: scaleX(1);
    }
    to { 
      transform: scaleX(0);
    }
  }
</style>