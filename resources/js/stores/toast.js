import { writable } from 'svelte/store';

// Toast store
export const toasts = writable([]);

// Function to add a new toast
export function addToast(toast) {
  const newToast = {
    id: Date.now() + Math.random(), // More unique ID
    type: 'success',
    title: '',
    message: '',
    duration: 4000,
    closable: true,
    ...toast
  };

  // Check for duplicate messages to prevent spam
  toasts.update(existing => {
    // Remove existing toast with same message and type (if any)
    const filtered = existing.filter(existingToast => 
      !(existingToast.message === newToast.message && existingToast.type === newToast.type)
    );
    
    return [...filtered, newToast];
  });

  // Auto remove after duration
  if (newToast.duration > 0) {
    setTimeout(() => {
      removeToast(newToast.id);
    }, newToast.duration + 300); // Add extra time for exit animation
  }

  return newToast.id;
}

// Function to remove a toast
export function removeToast(id) {
  toasts.update(existing => existing.filter(toast => toast.id !== id));
}

// Convenience functions
export function showSuccess(title, message, options = {}) {
  return addToast({
    type: 'success',
    title,
    message,
    ...options
  });
}

export function showError(title, message, options = {}) {
  return addToast({
    type: 'error',
    title,
    message,
    duration: 6000, // Error messages stay longer
    ...options
  });
}

export function showWarning(title, message, options = {}) {
  return addToast({
    type: 'warning',
    title,
    message,
    ...options
  });
}

export function showInfo(title, message, options = {}) {
  return addToast({
    type: 'info',
    title,
    message,
    ...options
  });
}