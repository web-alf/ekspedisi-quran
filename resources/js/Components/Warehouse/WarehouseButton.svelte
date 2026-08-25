<script>
  export let variant = 'primary'; // 'primary', 'secondary', 'success', 'warning', 'danger', 'ghost'
  export let size = 'default'; // 'sm', 'default', 'lg', 'xl'
  export let disabled = false;
  export let loading = false;
  export let fullWidth = false;
  export let icon = '';
  export let iconPosition = 'left'; // 'left', 'right'
  export let type = 'button';
  export let href = '';
  export let target = '';

  // Get variant classes
  function getVariantClasses(variant) {
    const variants = {
      primary: 'bg-[#eb3434] text-white hover:bg-red-600 focus:ring-red-500 border-transparent',
      secondary: 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500 border-transparent',
      success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 border-transparent',
      warning: 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500 border-transparent',
      danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 border-transparent',
      ghost: 'bg-white text-gray-700 hover:bg-gray-50 focus:ring-gray-500 border-gray-300'
    };
    return variants[variant] || variants.primary;
  }

  // Get size classes
  function getSizeClasses(size) {
    const sizes = {
      sm: 'px-3 py-1.5 text-sm min-h-[36px]',
      default: 'px-4 py-2 text-base min-h-[44px]', // Touch-friendly minimum
      lg: 'px-6 py-3 text-lg min-h-[48px]',
      xl: 'px-8 py-4 text-xl min-h-[56px]'
    };
    return sizes[size] || sizes.default;
  }

  // Base classes
  const baseClasses = `
    inline-flex items-center justify-center
    font-medium rounded-lg border
    transition-all duration-200
    focus:outline-none focus:ring-2 focus:ring-offset-2
    disabled:opacity-50 disabled:cursor-not-allowed
    touch-manipulation
  `;
</script>

{#if href}
  <a
    {href}
    {target}
    class="
      {baseClasses}
      {getVariantClasses(variant)}
      {getSizeClasses(size)}
      {fullWidth ? 'w-full' : ''}
      {disabled ? 'pointer-events-none' : ''}
    "
    on:click
  >
    {#if loading}
      <svg class="animate-spin -ml-1 mr-3 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    {:else if icon && iconPosition === 'left'}
      <span class="mr-2 text-lg">{icon}</span>
    {/if}
    
    <slot />
    
    {#if icon && iconPosition === 'right' && !loading}
      <span class="ml-2 text-lg">{icon}</span>
    {/if}
  </a>
{:else}
  <button
    {type}
    {disabled}
    class="
      {baseClasses}
      {getVariantClasses(variant)}
      {getSizeClasses(size)}
      {fullWidth ? 'w-full' : ''}
    "
    on:click
  >
    {#if loading}
      <svg class="animate-spin -ml-1 mr-3 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    {:else if icon && iconPosition === 'left'}
      <span class="mr-2 text-lg">{icon}</span>
    {/if}
    
    <slot />
    
    {#if icon && iconPosition === 'right' && !loading}
      <span class="ml-2 text-lg">{icon}</span>
    {/if}
  </button>
{/if}