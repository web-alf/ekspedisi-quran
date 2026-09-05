<script>
  import { createEventDispatcher } from 'svelte';
  import { fade, fly } from 'svelte/transition';
  import HeroIcon from './UI/HeroIcon.svelte';

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
      iconName: 'check-circle'
    },
    error: {
      bgColor: 'bg-white',
      borderColor: 'border-l-red-500',
      iconBg: 'bg-red-50',
      iconColor: 'text-red-500',
      titleColor: 'text-gray-900',
      iconName: 'x-circle'
    },
    warning: {
      bgColor: 'bg-white',
      borderColor: 'border-l-amber-500',
      iconBg: 'bg-amber-50',
      iconColor: 'text-amber-500',
      titleColor: 'text-gray-900',
      iconName: 'exclamation-triangle'
    },
    info: {
      bgColor: 'bg-white',
      borderColor: 'border-l-blue-500',
      iconBg: 'bg-blue-50',
      iconColor: 'text-blue-500',
      titleColor: 'text-gray-900',
      iconName: 'information-circle'
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
              <HeroIcon name={config.iconName} class="w-5 h-5" />
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
                <HeroIcon name="x-mark" class="w-4 h-4" />
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