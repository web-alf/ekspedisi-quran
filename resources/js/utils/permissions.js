// Permission utility functions
import { page } from '@inertiajs/svelte';
import { get } from 'svelte/store';

/**
 * Check if user has a specific permission
 * @param {string} permission - Permission to check
 * @returns {boolean}
 */
export function hasPermission(permission) {
  const pageData = get(page);
  const userPermissions = pageData?.props?.auth?.user?.permissions || [];
  return userPermissions.includes(permission);
}

/**
 * Check if user has any of the provided permissions
 * @param {string[]} permissions - Array of permissions to check
 * @returns {boolean}
 */
export function hasAnyPermission(permissions) {
  return permissions.some(permission => hasPermission(permission));
}

/**
 * Check if user has all of the provided permissions
 * @param {string[]} permissions - Array of permissions to check
 * @returns {boolean}
 */
export function hasAllPermissions(permissions) {
  return permissions.every(permission => hasPermission(permission));
}

/**
 * Get user's role
 * @returns {string}
 */
export function getUserRole() {
  const pageData = get(page);
  return pageData?.props?.auth?.user?.role || '';
}

/**
 * Check if user has a specific role
 * @param {string} role - Role to check
 * @returns {boolean}
 */
export function hasRole(role) {
  const pageData = get(page);
  const userRoles = pageData?.props?.auth?.user?.roles || [];
  return userRoles.includes(role);
}

/**
 * Check if user has any of the provided roles
 * @param {string[]} roles - Array of roles to check
 * @returns {boolean}
 */
export function hasAnyRole(roles) {
  const pageData = get(page);
  const userRoles = pageData?.props?.auth?.user?.roles || [];
  return roles.some(role => userRoles.includes(role));
}

/**
 * CRUD permission helpers
 */
export const can = {
  // Donatur permissions
  donatur: {
    create: () => hasPermission('donatur.create'),
    read: () => hasPermission('donatur.read'),
    update: () => hasPermission('donatur.update'),
    delete: () => hasPermission('donatur.delete'),
    import: () => hasPermission('donatur.import'),
    export: () => hasPermission('donatur.export'),
  },
  
  // Shipments permissions
  shipments: {
    create: () => hasPermission('shipments.create'),
    read: () => hasPermission('shipments.read'),
    update: () => hasPermission('shipments.update'),
    delete: () => hasPermission('shipments.delete'),
    track: () => hasPermission('shipments.track'),
    export: () => hasPermission('shipments.export'),
    bulkUpdate: () => hasPermission('shipments.bulk-update'),
    updateStatus: () => hasPermission('shipments.update-status'),
  },
  
  // Mushaf requests permissions
  mushafRequests: {
    create: () => hasPermission('mushaf-requests.create'),
    read: () => hasPermission('mushaf-requests.read'),
    update: () => hasPermission('mushaf-requests.update'),
    delete: () => hasPermission('mushaf-requests.delete'),
    approve: () => hasPermission('mushaf-requests.approve'),
    reject: () => hasPermission('mushaf-requests.reject'),
    process: () => hasPermission('mushaf-requests.process'),
  },
  
  // Users permissions
  users: {
    create: () => hasPermission('users.create'),
    read: () => hasPermission('users.read'),
    update: () => hasPermission('users.update'),
    delete: () => hasPermission('users.delete'),
    toggle: () => hasPermission('users.toggle'),
  },
  
  // Roles permissions
  roles: {
    create: () => hasPermission('roles.create'),
    read: () => hasPermission('roles.read'),
    update: () => hasPermission('roles.update'),
    delete: () => hasPermission('roles.delete'),
  },
  
  // Templates permissions
  templates: {
    create: () => hasPermission('templates.create'),
    read: () => hasPermission('templates.read'),
    update: () => hasPermission('templates.update'),
    delete: () => hasPermission('templates.delete'),
    setDefault: () => hasPermission('templates.set-default'),
    toggle: () => hasPermission('templates.toggle'),
  },
  
  // QR permissions
  qr: {
    generate: () => hasAnyPermission(['qr.generate', 'warehouse.qr.generate']),
    scan: () => hasAnyPermission(['qr.scan', 'warehouse.qr.scan']),
    verify: () => hasAnyPermission(['qr.verify', 'warehouse.qr.verify']),
    bulkOperations: () => hasPermission('warehouse.qr.bulk_generate'),
  },
  
  // Certificates permissions
  certificates: {
    create: () => hasPermission('certificates.create'),
    read: () => hasPermission('certificates.read'),
    update: () => hasPermission('certificates.update'),
    delete: () => hasPermission('certificates.delete'),
    generate: () => hasPermission('certificates.generate'),
    download: () => hasPermission('certificates.download'),
  },
  
  // Warehouse permissions
  warehouse: {
    dashboard: () => hasPermission('warehouse.dashboard'),
    packing: () => hasPermission('warehouse.packing.view'),
    performance: () => hasPermission('warehouse.performance.view'),
    boxes: {
      view: () => hasPermission('warehouse.boxes.view'),
      seal: () => hasPermission('warehouse.boxes.seal'),
    },
  },
  
  // Supervisor permissions
  supervisor: {
    monitor: () => hasPermission('supervisor.warehouse.monitor'),
    reports: () => hasPermission('supervisor.performance.reports'),
    assign: () => hasPermission('supervisor.warehouse.assign'),
  },
  
  // Dashboard permissions
  dashboard: {
    view: () => hasPermission('dashboard.view'),
    analytics: () => hasPermission('dashboard.analytics'),
  }
};
