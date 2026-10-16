<script>
  import { fade, scale } from 'svelte/transition';
  import { cubicOut } from 'svelte/easing';
  import WarehouseButton from './WarehouseButton.svelte';
  import HeroIcon from '../UI/HeroIcon.svelte';

  export let show = false;
  export let title = '';
  export let subtitle = '';
  export let size = 'default'; // 'sm', 'default', 'lg', 'xl', 'full'
  export let closable = true;
  export let closeOnClickOutside = true;
  export let loading = false;
  export let icon = '';

  // Actions
  export let primaryAction = null; // { text, onClick, loading, disabled, variant }
  export let secondaryAction = null; // { text, onClick, loading, disabled }

  // Get size classes
  function getSizeClasses(size) {
    const sizes = {
      sm: 'max-w-md',
      default: 'max-w-2xl',
      lg: 'max-w-4xl',
      xl: 'max-w-6xl',
      full: 'max-w-full mx-4'
    };
    return sizes[size] || sizes.default;
  }

  // Close modal
  function handleClose() {
    if (closable && !loading) {
      show = false;
    }
  }

  // Handle backdrop click
  function handleBackdropClick(event) {
    if (closeOnClickOutside && event.target === event.currentTarget) {
      handleClose();
    }
  }

  // Handle escape key
  function handleKeydown(event) {
    if (event.key === 'Escape' && closable && !loading) {
      handleClose();
    }
  }
</script>

{#if show}
  <!-- Backdrop -->
  <div 
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    transition:fade={{ duration: 200 }}
    on:click={handleBackdropClick}
    on:keydown={handleKeydown}
    role="dialog" 
    aria-modal="true"
    tabindex="-1"
  >
    <!-- Modal -->
    <div 
      class="
        bg-white rounded-xl shadow-xl w-full 
        {getSizeClasses(size)}
        max-h-[90vh] overflow-y-auto
        {loading ? 'animate-pulse' : ''}
      "
      transition:scale={{ duration: 200, easing: cubicOut }}
      on:click|stopPropagation
    >
      <!-- Header -->
      {#if title || subtitle || closable}
        <div class="flex items-start justify-between p-6 pb-0">
          <div class="flex items-center space-x-3">
            {#if icon}
              <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100">
                <span class="text-2xl">{icon}</span>
              </div>
            {/if}
            <div>
              {#if title}
                <h3 class="text-xl font-semibold text-gray-900">{title}</h3>
              {/if}
              {#if subtitle}
                <p class="text-sm text-gray-500 mt-1">{subtitle}</p>
              {/if}
            </div>
          </div>

          {#if closable}
            <button 
              class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100"
              on:click={handleClose}
              disabled={loading}
            >
              <HeroIcon name="x-mark" class="w-6 h-6" />
            </button>
          {/if}
        </div>
      {/if}

      <!-- Content -->
      <div class="p-6">
        <slot />
      </div>

      <!-- Actions -->
      {#if primaryAction || secondaryAction}
        <div class="flex flex-col sm:flex-row gap-3 p-6 pt-0">
          {#if secondaryAction}
            <WarehouseButton
              variant="ghost"
              size="lg"
              fullWidth={true}
              loading={secondaryAction.loading}
              disabled={secondaryAction.disabled || loading}
              on:click={secondaryAction.onClick}
            >
              {secondaryAction.text}
            </WarehouseButton>
          {/if}

          {#if primaryAction}
            <WarehouseButton
              variant={primaryAction.variant || 'primary'}
              size="lg"
              fullWidth={true}
              loading={primaryAction.loading}
              disabled={primaryAction.disabled || loading}
              on:click={primaryAction.onClick}
            >
              {primaryAction.text}
            </WarehouseButton>
          {/if}
        </div>
      {/if}
    </div>
  </div>
{/if}

<style>
  /* Prevent background scroll when modal is open */
  :global(body.modal-open) {
    overflow: hidden;
  }
</style>