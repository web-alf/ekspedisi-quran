<script>
  export let errors = {};
  export let field = '';
  export let className = 'mt-1 text-sm text-red-600';
</script>

{#if errors[field]}
  <p class={className}>
    {#if Array.isArray(errors[field])}
      {errors[field][0]}
    {:else}
      {errors[field]}
    {/if}
  </p>
{/if}
