<script>
  import LoadingSpinner from '../UI/LoadingSpinner.svelte';
  import HeroIcon from '../UI/HeroIcon.svelte';
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
    <HeroIcon name="arrow-down-tray" class="w-4 h-4" />
  {:else}
    <div class="flex items-center gap-2">
      <HeroIcon name="arrow-down-tray" class="w-4 h-4" />
      {buttonText}
    </div>
  {/if}
</button>
