<script>
  import LoadingSpinner from '../UI/LoadingSpinner.svelte';
  import { showSuccess, showError } from '../../stores/toast.js';

  export let certificateId;
  export let buttonText = 'Generate Link';
  export let buttonClass = 'px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed';
  export let iconOnly = false;
  export let onTokenGenerated = null;

  let isLoading = false;

  async function generateToken() {
    if (!certificateId || isLoading) return;

    isLoading = true;

    try {
      const response = await fetch(`/admin/certificates/generate-token/${certificateId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        },
        credentials: 'same-origin'
      });

      const result = await response.json();

      if (response.ok && result.success) {
        // Copy URL to clipboard
        await navigator.clipboard.writeText(result.data.public_url);

        // Success notification
        showSuccess('Public Link Generated!', 'Download link copied to clipboard. Valid for 24 hours.', { duration: 6000 });

        if (onTokenGenerated) onTokenGenerated(result.data);

      } else {
        throw new Error(result.message || 'Failed to generate download link');
      }

    } catch (error) {
      console.error('Token generation error:', error);

      // Error notification
      showError('Failed to Generate Link', error.message || 'Failed to generate download link. Please try again.');

    } finally {
      isLoading = false;
    }
  }
</script>

<button 
  on:click={generateToken}
  disabled={isLoading}
  class="{buttonClass}"
  title="Generate public download link"
>
  {#if isLoading}
    <div class="flex items-center justify-center">
      <LoadingSpinner size="sm" color="white" />
      {#if !iconOnly}<span class="ml-2">Generating...</span>{/if}
    </div>
  {:else if iconOnly}
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
    </svg>
  {:else}
    <div class="flex items-center">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
      </svg>
      {buttonText}
    </div>
  {/if}
</button>