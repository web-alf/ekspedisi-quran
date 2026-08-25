<script>
  import LoadingSpinner from '../UI/LoadingSpinner.svelte';
  import { showSuccess, showError, showInfo } from '../../stores/toast.js';

  export let certificateId;
  export let buttonText = 'Download';
  export let buttonClass = 'px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed';
  export let iconOnly = false;

  let isLoading = false;

  // Simplified download function
  function downloadCertificate() {
    if (!certificateId) {
      console.error('Certificate ID is missing.');
      showError('Error', 'Certificate ID is missing.');
      return;
    }
    // Use window.open for download - browser should preserve session cookies
    const url = `/admin/certificates/download/${certificateId}`;
    window.open(url, '_blank');
    showInfo('Downloading...', 'Certificate download started.');
  }
</script>

<button 
  on:click={downloadCertificate}
  disabled={isLoading}
  class="{buttonClass}"
  title="Download Certificate"
>
  {#if isLoading}
    <LoadingSpinner size="sm" color="white" />
  {:else if iconOnly}
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
  {:else}
    <div class="flex items-center">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      {buttonText}
    </div>
  {/if}
</button>
