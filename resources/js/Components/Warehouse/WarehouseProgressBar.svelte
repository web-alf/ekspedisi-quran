<script>
  export let value = 0; // 0-100
  export let max = 100;
  export let showLabel = true;
  export let showPercentage = true;
  export let label = '';
  export let size = 'default'; // 'sm', 'default', 'lg'
  export let variant = 'default'; // 'default', 'success', 'warning', 'danger'
  export let animated = true;
  export let showValues = false; // Show current/max values
  export let currentValue = 0;
  export let maxValue = 100;

  // Calculate percentage
  $: percentage = Math.min(Math.max((value / max) * 100, 0), 100);

  // Get color classes based on percentage and variant
  function getColorClasses(percentage, variant) {
    if (variant !== 'default') {
      const variants = {
        success: 'bg-green-500',
        warning: 'bg-yellow-500',
        danger: 'bg-red-500'
      };
      return variants[variant] || variants.success;
    }

    // Auto-color based on percentage
    if (percentage >= 80) return 'bg-green-500';
    if (percentage >= 60) return 'bg-yellow-500';
    if (percentage >= 40) return 'bg-orange-500';
    return 'bg-red-500';
  }

  // Get size classes
  function getSizeClasses(size) {
    const sizes = {
      sm: 'h-2',
      default: 'h-3',
      lg: 'h-4'
    };
    return sizes[size] || sizes.default;
  }

  // Get text size classes
  function getTextSizeClasses(size) {
    const sizes = {
      sm: 'text-xs',
      default: 'text-sm',
      lg: 'text-base'
    };
    return sizes[size] || sizes.default;
  }
</script>

<div class="w-full">
  <!-- Label and values -->
  {#if showLabel || showValues || showPercentage}
    <div class="flex justify-between items-center mb-2">
      <div class="flex items-center space-x-2">
        {#if showLabel && label}
          <span class="font-medium text-gray-700 {getTextSizeClasses(size)}">{label}</span>
        {/if}
        {#if showValues}
          <span class="text-gray-600 {getTextSizeClasses(size)}">{currentValue}/{maxValue}</span>
        {/if}
      </div>
      
      {#if showPercentage}
        <span class="font-semibold text-gray-900 {getTextSizeClasses(size)}">{percentage.toFixed(0)}%</span>
      {/if}
    </div>
  {/if}

  <!-- Progress bar -->
  <div class="w-full bg-gray-200 rounded-full {getSizeClasses(size)}">
    <div 
      class="
        {getColorClasses(percentage, variant)}
        {getSizeClasses(size)} 
        rounded-full
        {animated ? 'transition-all duration-500 ease-out' : ''}
      "
      style="width: {percentage}%"
    ></div>
  </div>

  <!-- Additional content slot -->
  <slot />
</div>