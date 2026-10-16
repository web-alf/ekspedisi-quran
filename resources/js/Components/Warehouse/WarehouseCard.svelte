<script>
  export let title = '';
  export let subtitle = '';
  export let icon = '';
  export let variant = 'default'; // 'default', 'success', 'warning', 'error', 'info'
  export let size = 'default'; // 'sm', 'default', 'lg'
  export let padding = 'default'; // 'sm', 'default', 'lg'
  export let hover = false;
  export let loading = false;

  // Get variant classes
  function getVariantClasses(variant) {
    const variants = {
      default: 'bg-white border-gray-100',
      success: 'bg-green-50 border-green-200',
      warning: 'bg-yellow-50 border-yellow-200', 
      error: 'bg-red-50 border-red-200',
      info: 'bg-blue-50 border-blue-200'
    };
    return variants[variant] || variants.default;
  }

  // Get size classes
  function getSizeClasses(size) {
    const sizes = {
      sm: 'text-sm',
      default: 'text-base',
      lg: 'text-lg'
    };
    return sizes[size] || sizes.default;
  }

  // Get padding classes
  function getPaddingClasses(padding) {
    const paddings = {
      sm: 'p-4',
      default: 'p-6',
      lg: 'p-8'
    };
    return paddings[padding] || paddings.default;
  }
</script>

<div 
  class="
    rounded-xl shadow-sm border 
    {getVariantClasses(variant)} 
    {getPaddingClasses(padding)}
    {getSizeClasses(size)}
    {hover ? 'hover:shadow-lg transition-all duration-200 cursor-pointer' : ''}
    {loading ? 'animate-pulse' : ''}
  "
  on:click
>
  <!-- Header -->
  {#if title || subtitle || icon}
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center space-x-3">
        {#if icon}
          <div class="text-xl md:text-2xl">{icon}</div>
        {/if}
        <div>
          {#if title}
            <h3 class="font-semibold text-gray-900">{title}</h3>
          {/if}
          {#if subtitle}
            <p class="text-sm text-gray-600 mt-1">{subtitle}</p>
          {/if}
        </div>
      </div>
      
      <!-- Loading indicator -->
      {#if loading}
        <div class="w-5 h-5 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin"></div>
      {/if}
    </div>
  {/if}

  <!-- Content slot -->
  <slot />
</div>