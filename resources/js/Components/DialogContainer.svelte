<script>
  import ConfirmDialog from './ConfirmDialog.svelte';
  import { dialog } from '../stores/dialog.js';
  
  function handleConfirm() {
    if ($dialog.onConfirm) {
      $dialog.onConfirm();
    }
  }
  
  function handleCancel() {
    if ($dialog.onCancel) {
      $dialog.onCancel();
    }
  }
</script>

<ConfirmDialog
  bind:show={$dialog.show}
  title={$dialog.title}
  message={$dialog.message}
  type={$dialog.type}
  confirmText={$dialog.confirmText}
  cancelText={$dialog.cancelText}
  confirmOnly={$dialog.confirmOnly}
  on:confirm={handleConfirm}
  on:cancel={handleCancel}
/>