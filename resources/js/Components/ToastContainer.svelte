<script>
  import Toast from './Toast.svelte';
  import { toasts, removeToast } from '../stores/toast.js';
</script>

<!-- Toast Container - Positioned below navbar with sufficient top spacing -->
<!-- z-[9999999] ensures toasts appear above all UI elements including navbar and dropdowns -->
<!-- mt-20 (5rem/80px) ensures toast appears below navbar which is h-16 (64px) -->
<div class="fixed top-20 right-4 left-4 sm:left-auto z-[9999999] pointer-events-none">
  <div class="flex flex-col items-end space-y-3">
    {#each $toasts as toast (toast.id)}
      <div class="pointer-events-auto">
        <Toast
          type={toast.type}
          title={toast.title}
          message={toast.message}
          duration={toast.duration}
          closable={toast.closable}
          on:close={() => removeToast(toast.id)}
        />
      </div>
    {/each}
  </div>
</div>