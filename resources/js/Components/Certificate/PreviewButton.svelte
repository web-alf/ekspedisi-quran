<script>
  export let certificateId;
  export let buttonText = 'Preview';
  export let buttonClass = 'px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed';
  export let iconOnly = false;
  
  function previewCertificate() {
    if (!certificateId) {
      console.error('Certificate ID is missing.');
      return;
    }
    // Use window.open with proper URL - browser should preserve session cookies
    const url = `/admin/certificates/preview/${certificateId}`;
    window.open(url, '_blank');
  }
</script>

<button 
  on:click={previewCertificate}
  class="{buttonClass}"
  title="Preview Certificate"
>
  {#if iconOnly}
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>
  {:else}
    <div class="flex items-center">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
      </svg>
      {buttonText}
    </div>
  {/if}
</button>