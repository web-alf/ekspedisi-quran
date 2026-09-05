<script>
  import LoadingSpinner from '../UI/LoadingSpinner.svelte';
  import HeroIcon from '../UI/HeroIcon.svelte';
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
    <HeroIcon name="share" class="w-4 h-4" />
  {:else}
    <div class="flex items-center">
      <HeroIcon name="share" class="w-4 h-4 mr-2" />
      {buttonText}
    </div>
  {/if}
</button>