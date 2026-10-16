import { writable } from 'svelte/store';

// Dialog store
export const dialog = writable({
  show: false,
  title: '',
  message: '',
  type: 'warning',
  confirmText: 'OK',
  cancelText: 'Cancel',
  confirmOnly: false,
  requireText: null,
  onConfirm: null,
  onCancel: null
});

// Function to show confirmation dialog
export function showConfirm(options = {}) {
  return new Promise((resolve) => {
    dialog.set({
      show: true,
      title: options.title || 'Konfirmasi',
      message: options.message || 'Apakah Anda yakin?',
      type: options.type || 'warning',
      confirmText: options.confirmText || 'OK',
      cancelText: options.cancelText || 'Cancel',
      confirmOnly: options.confirmOnly || false,
      requireText: options.requireText || null,
      onConfirm: () => {
        hideDialog();
        resolve(true);
      },
      onCancel: () => {
        hideDialog();
        resolve(false);
      }
    });
  });
}

// Function to show alert dialog (only confirm button)
export function showAlert(options = {}) {
  return new Promise((resolve) => {
    dialog.set({
      show: true,
      title: options.title || 'Informasi',
      message: options.message || '',
      type: options.type || 'info',
      confirmText: options.confirmText || 'OK',
      cancelText: 'Cancel',
      confirmOnly: true,
      onConfirm: () => {
        hideDialog();
        resolve(true);
      },
      onCancel: () => {
        hideDialog();
        resolve(true);
      }
    });
  });
}

// Function to hide dialog
export function hideDialog() {
  dialog.update(d => ({ ...d, show: false }));
}

// Convenience functions
export function confirmDelete(itemName = 'item ini') {
  return showConfirm({
    title: 'Hapus Data',
    message: `Apakah Anda yakin ingin menghapus ${itemName}? Tindakan ini tidak dapat dibatalkan.`,
    type: 'danger',
    confirmText: 'Hapus',
    cancelText: 'Batal'
  });
}

/**
 * Konfirmasi hapus untuk data yang tidak punya arsip sama sekali.
 *
 * `rincian` menyebutkan APA yang akan ikut terhapus, dan `ketikUntuk` mewajibkan
 * pengguna mengetik kode donaturnya. Tanpa keduanya, menghapus donatur terasa
 * sama ringannya dengan menutup notifikasi — padahal isinya lenyap permanen.
 */
export function confirmDeletePermanen({ nama, rincian = [], ketikUntuk = null }) {
  const daftar = rincian.length > 0
    ? ' Ikut terhapus: ' + rincian.join(', ') + '.'
    : '';

  return showConfirm({
    title: 'Hapus Permanen',
    message: `Data ${nama} akan dihapus PERMANEN dan tidak bisa dikembalikan.${daftar}`,
    type: 'danger',
    confirmText: 'Hapus Permanen',
    cancelText: 'Batal',
    requireText: ketikUntuk
  });
}

export function confirmAction(action = 'melakukan tindakan ini') {
  return showConfirm({
    title: 'Konfirmasi Tindakan',
    message: `Apakah Anda yakin ingin ${action}?`,
    type: 'warning',
    confirmText: 'Ya',
    cancelText: 'Tidak'
  });
}

export function showSuccess(message) {
  return showAlert({
    title: 'Berhasil',
    message,
    type: 'success'
  });
}

export function showError(message) {
  return showAlert({
    title: 'Error',
    message,
    type: 'danger'
  });
}

export function showInfo(message) {
  return showAlert({
    title: 'Informasi',
    message,
    type: 'info'
  });
}