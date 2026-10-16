<script>
  import HeroIcon from '../UI/HeroIcon.svelte';

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
    <HeroIcon name="eye" class="w-4 h-4" />
  {:else}
    <div class="flex items-center">
      <HeroIcon name="eye" class="w-4 h-4 mr-2" />
      {buttonText}
    </div>
  {/if}
</button>