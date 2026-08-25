<script>
  import { router, page } from '@inertiajs/svelte';
  import { hasPermission, can } from '../utils/permissions.js';
  
  export let currentMode = 'list';
  export let baseUrl = '/admin/pengiriman';
  
  const tabs = [
    { id: 'list', label: 'Daftar Pengiriman', check: () => can.shipments.read() },
    { id: 'generate-qr', label: 'Generate QR', check: () => can.qr.generate() },
    { id: 'scan-status', label: 'Scan QR', check: () => can.qr.scan() }
  ];
  
  // Filter tabs based on permissions
  $: visibleTabs = tabs.filter(tab => {
    if (!tab.check) {
      return true; // Show if no permission check required
    }
    // Show if user has the required permission
    return tab.check();
  });
  
  function setMode(mode) {
    currentMode = mode;
    router.visit(`${baseUrl}?mode=${mode}`, {
      preserveState: true,
      preserveScroll: true,
      only: ['pengiriman', 'statusList', 'jenisQuranList', 'wakifList', 'stats']
    });
  }
</script>

<div class="border-b border-gray-200 mb-6">
  <nav class="-mb-px flex space-x-8">
    {#each visibleTabs as tab}
      <button
        on:click={() => setMode(tab.id)}
        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {currentMode === tab.id 
          ? 'border-red-500 text-red-600' 
          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
      >
        {tab.label}
      </button>
    {/each}
  </nav>
</div> 