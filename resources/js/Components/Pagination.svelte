<script>
  import { router } from '@inertiajs/svelte';
  
  export let data = {};
  export let additionalParams = {};
  
  // Extract pagination info from Laravel pagination object
  $: pagination = {
    current_page: data.current_page || 1,
    last_page: data.last_page || 1,
    per_page: data.per_page || 20,
    total: data.total || 0,
    from: data.from || 0,
    to: data.to || 0,
    links: data.links || []
  };
  
  function goToPage(url) {
    if (url) {
      // If additionalParams provided, merge them with the URL
      if (Object.keys(additionalParams).length > 0) {
        const urlObj = new URL(url);
        const params = Object.fromEntries(urlObj.searchParams);
        const mergedParams = { ...params, ...additionalParams };
        
        router.get(urlObj.pathname, mergedParams, {
          preserveState: true,
          preserveScroll: true
        });
      } else {
        router.get(url, {}, {
          preserveState: true,
          preserveScroll: true
        });
      }
    }
  }
</script>

{#if pagination.last_page > 1}
  <div class="flex items-center justify-between">
    <!-- Results info -->
    <div class="text-sm text-gray-700">
      Showing <span class="font-medium">{pagination.from}</span> to <span class="font-medium">{pagination.to}</span> of <span class="font-medium">{pagination.total}</span> results
    </div>

    <!-- Pagination links -->
    <div class="flex items-center space-x-2">
      {#each pagination.links as link}
        {#if link.label.includes('Previous')}
          <button
            on:click={() => goToPage(link.url)}
            disabled={!link.url}
            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
        {:else if link.label.includes('Next')}
          <button
            on:click={() => goToPage(link.url)}
            disabled={!link.url}
            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        {:else}
          <button
            on:click={() => goToPage(link.url)}
            disabled={!link.url}
            class={`px-3 py-2 text-sm font-medium border rounded-md ${
              link.active 
                ? 'text-white bg-blue-600 border-blue-600' 
                : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'
            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            {link.label}
          </button>
        {/if}
      {/each}
    </div>
  </div>
{/if}