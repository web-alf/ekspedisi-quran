<script>
  import { page } from '@inertiajs/svelte';
  import { hasPermission, hasAnyPermission, hasRole, hasAnyRole } from '../utils/permissions.js';
  
  // Props for permission checking
  export let permission = '';
  export let permissions = [];
  export let role = '';
  export let roles = [];
  export let requireAll = false; // For permissions array - require all vs any
  
  // Check if user meets the requirements
  $: canAccess = checkAccess();
  
  function checkAccess() {
    // Permission-first check
    if (permission) {
      return hasPermission(permission);
    }
    
    if (permissions.length > 0) {
      return requireAll 
        ? permissions.every(p => hasPermission(p))
        : hasAnyPermission(permissions);
    }

    // Fallback to role-based check if no permission provided
    if (role) {
      return hasRole(role);
    }
    
    if (roles.length > 0) {
      return hasAnyRole(roles);
    }
    
    // If no requirements specified, allow access
    return true;
  }
</script>

{#if canAccess}
  <slot />
{/if}
