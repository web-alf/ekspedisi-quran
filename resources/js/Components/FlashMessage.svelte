<script>
  import { page } from '@inertiajs/svelte';
  import { addToast } from '../stores/toast.js';
  
  // Track processed flash messages to prevent duplicates
  let processedFlash = {};
  
  // Watch for flash messages from Laravel and convert to toast
  $: if ($page.props.flash) {
    const currentFlash = JSON.stringify($page.props.flash);
    
    // Only process if this is a new flash message
    if (processedFlash.key !== currentFlash) {
      processedFlash = { key: currentFlash };
      
      if ($page.props.flash.success) {
        addToast({
          type: 'success',
          title: 'Berhasil',
          message: $page.props.flash.success,
          duration: 5000
        });
      } else if ($page.props.flash.error) {
        addToast({
          type: 'error',
          title: 'Error',
          message: $page.props.flash.error,
          duration: 6000
        });
      } else if ($page.props.flash.warning) {
        addToast({
          type: 'warning',
          title: 'Peringatan',
          message: $page.props.flash.warning,
          duration: 5000
        });
      } else if ($page.props.flash.info) {
        addToast({
          type: 'info',
          title: 'Informasi',
          message: $page.props.flash.info,
          duration: 5000
        });
      }
    }
  }
</script>

<!-- FlashMessage now uses Toast system, no UI needed here -->
