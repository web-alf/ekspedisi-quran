<script>
  import LoadingSpinner from '../UI/LoadingSpinner.svelte';
  import { showSuccess, showError, showInfo } from '../../stores/toast.js';

  export let templateId = null;
  export let wakafBatchId = null;
  export let isRegenerate = false;
  export let buttonText = 'Generate Sertifikat';
  export let buttonClass = 'px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed';
  export let onSuccess = null;
  export let onError = null;

  let isLoading = false;

  async function generateCertificate() {
    if (isLoading) return;

    isLoading = true;

    try {
      // Show loading notification
      showInfo(
        isRegenerate ? 'Regenerating Certificate' : 'Generating Certificate',
        'Please wait while we process your request...'
      );

      // Use the correct route based on context
      const url = isRegenerate
        ? `/admin/certificates/regenerate/batch/${wakafBatchId}`
        : `/admin/certificates/generate/batch/${wakafBatchId}`;

      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        },
        body: JSON.stringify({
          template_id: templateId
        })
      });

      if (response.ok) {
        // Check if response is PDF (direct download)
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/pdf')) {
          // Handle direct PDF download
          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.style.display = 'none';
          a.href = url;
          a.download = `Sertifikat-${wakafBatchId}.pdf`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);

          // Success notification
          showSuccess('Certificate Generated Successfully!', 'Your certificate has been downloaded automatically.', { duration: 8000 });

          if (onSuccess) onSuccess({ direct_download: true });
        } else {
          // Handle JSON response (fallback)
          const result = await response.json();
          if (result.success) {
            showSuccess('Certificate Generated Successfully!', result.message || 'Certificate generated successfully.', { duration: 8000 });

            if (onSuccess) onSuccess(result);
          } else {
            throw new Error(result.message || 'Failed to generate certificate');
          }
        }
      } else {
        // Try to parse error as JSON
        try {
          const errorResult = await response.json();
          throw new Error(errorResult.message || 'Failed to generate certificate');
        } catch (e) {
          throw new Error('Failed to generate certificate. Please try again.');
        }
      }

    } catch (error) {
      console.error('Certificate generation error:', error);

      // Error notification
      showError('Generation Failed', error.message || 'Failed to generate certificate. Please try again.');

      if (onError) onError(error);
    } finally {
      isLoading = false;
    }
  }
</script>

<button 
  on:click={generateCertificate}
  disabled={isLoading}
  class="{buttonClass}"
>
  {#if isLoading}
    <div class="flex items-center justify-center">
      <LoadingSpinner size="sm" color="white" />
      <span class="ml-2">{isRegenerate ? 'Regenerating...' : 'Generating...'}</span>
    </div>
  {:else}
    {buttonText}
  {/if}
</button>
