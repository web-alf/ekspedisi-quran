// Unified notification utilities
import { addToast, showSuccess as toastSuccess, showError as toastError, showWarning as toastWarning, showInfo as toastInfo } from '../stores/toast.js';
import { showConfirm, showAlert, confirmDelete, confirmAction, showSuccess as dialogSuccess, showError as dialogError, showInfo as dialogInfo } from '../stores/dialog.js';

// Toast notifications (non-blocking)
export const toast = {
  success: (message, title = 'Berhasil', options = {}) => {
    return addToast({
      type: 'success',
      title,
      message,
      duration: 4000,
      ...options
    });
  },

  error: (message, title = 'Error', options = {}) => {
    return addToast({
      type: 'error',
      title,
      message,
      duration: 6000,
      ...options
    });
  },

  warning: (message, title = 'Peringatan', options = {}) => {
    return addToast({
      type: 'warning',
      title,
      message,
      duration: 5000,
      ...options
    });
  },

  info: (message, title = 'Informasi', options = {}) => {
    return addToast({
      type: 'info',
      title,
      message,
      duration: 4000,
      ...options
    });
  }
};

// Dialog notifications (blocking)
export const dialog = {
  // Confirmation dialogs
  confirm: showConfirm,
  
  // Alert dialogs
  alert: showAlert,
  
  // Convenience methods
  confirmDelete,
  confirmAction,
  
  success: dialogSuccess,
  error: dialogError,
  info: dialogInfo,
  
  // Custom confirmations
  confirmSave: (itemName = 'data ini') => {
    return showConfirm({
      title: 'Simpan Perubahan',
      message: `Apakah Anda yakin ingin menyimpan ${itemName}?`,
      type: 'success',
      confirmText: 'Simpan',
      cancelText: 'Batal'
    });
  },

  confirmSend: (target = 'pesan') => {
    return showConfirm({
      title: 'Kirim Data',
      message: `Apakah Anda yakin ingin mengirim ${target}?`,
      type: 'info',
      confirmText: 'Kirim',
      cancelText: 'Batal'
    });
  },

  confirmCancel: (action = 'operasi ini') => {
    return showConfirm({
      title: 'Batalkan Operasi',
      message: `Apakah Anda yakin ingin membatalkan ${action}?`,
      type: 'warning',
      confirmText: 'Ya, Batalkan',
      cancelText: 'Tidak'
    });
  }
};

// Replace browser alert and confirm
export function replaceNativeDialogs() {
  // Replace alert
  window.alert = (message) => {
    return dialog.alert({
      title: 'Peringatan',
      message: message,
      type: 'info'
    });
  };

  // Replace confirm
  window.confirm = (message) => {
    return dialog.confirm({
      title: 'Konfirmasi',
      message: message,
      type: 'warning',
      confirmText: 'OK',
      cancelText: 'Cancel'
    });
  };
}

// Export everything for backward compatibility
export { 
  addToast, 
  showConfirm, 
  showAlert, 
  confirmDelete, 
  confirmAction 
};