<?php

namespace App\Enums;

enum PermissionEnum: string
{
    // Dashboard
    case DASHBOARD_VIEW = 'dashboard.view';
    case DASHBOARD_ANALYTICS = 'dashboard.analytics';

    // Users
    case USERS_CREATE = 'users.create';
    case USERS_READ = 'users.read';
    case USERS_UPDATE = 'users.update';
    case USERS_DELETE = 'users.delete';
    case USERS_TOGGLE = 'users.toggle';

    // Roles
    case ROLES_CREATE = 'roles.create';
    case ROLES_READ = 'roles.read';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';

    // Permissions
    case PERMISSIONS_CREATE = 'permissions.create';
    case PERMISSIONS_READ = 'permissions.read';
    case PERMISSIONS_UPDATE = 'permissions.update';
    case PERMISSIONS_DELETE = 'permissions.delete';

    // Donatur
    case DONATUR_CREATE = 'donatur.create';
    case DONATUR_READ = 'donatur.read';
    case DONATUR_UPDATE = 'donatur.update';
    case DONATUR_DELETE = 'donatur.delete';
    case DONATUR_IMPORT = 'donatur.import';
    case DONATUR_EXPORT = 'donatur.export';

    // Shipments
    case SHIPMENTS_CREATE = 'shipments.create';
    case SHIPMENTS_READ = 'shipments.read';
    case SHIPMENTS_UPDATE = 'shipments.update';
    case SHIPMENTS_DELETE = 'shipments.delete';
    case SHIPMENTS_EXPORT = 'shipments.export';
    case SHIPMENTS_TRACK = 'shipments.track';
    case SHIPMENTS_BULK_UPDATE = 'shipments.bulk-update';
    case SHIPMENTS_UPDATE_STATUS = 'shipments.update-status';

    // Mushaf requests
    case MUSHAF_REQUESTS_CREATE = 'mushaf-requests.create';
    case MUSHAF_REQUESTS_READ = 'mushaf-requests.read';
    case MUSHAF_REQUESTS_UPDATE = 'mushaf-requests.update';
    case MUSHAF_REQUESTS_DELETE = 'mushaf-requests.delete';
    case MUSHAF_REQUESTS_APPROVE = 'mushaf-requests.approve';
    case MUSHAF_REQUESTS_REJECT = 'mushaf-requests.reject';
    case MUSHAF_REQUESTS_PROCESS = 'mushaf-requests.process';

    // Certificates
    case CERTIFICATES_CREATE = 'certificates.create';
    case CERTIFICATES_READ = 'certificates.read';
    case CERTIFICATES_UPDATE = 'certificates.update';
    case CERTIFICATES_DELETE = 'certificates.delete';
    case CERTIFICATES_GENERATE = 'certificates.generate';
    case CERTIFICATES_DOWNLOAD = 'certificates.download';

    // Templates
    case TEMPLATES_CREATE = 'templates.create';
    case TEMPLATES_READ = 'templates.read';
    case TEMPLATES_UPDATE = 'templates.update';
    case TEMPLATES_DELETE = 'templates.delete';
    case TEMPLATES_SET_DEFAULT = 'templates.set-default';
    case TEMPLATES_TOGGLE = 'templates.toggle';

    // Warehouse
    case WAREHOUSE_DASHBOARD = 'warehouse.dashboard';
    case WAREHOUSE_PACKING_VIEW = 'warehouse.packing.view';
    case WAREHOUSE_PACKING_SCAN = 'warehouse.packing.scan';
    case WAREHOUSE_PACKING_SEAL = 'warehouse.packing.seal';
    case WAREHOUSE_QR_GENERATE = 'warehouse.qr.generate';
    case WAREHOUSE_QR_SCAN = 'warehouse.qr.scan';
    case WAREHOUSE_QR_VERIFY = 'warehouse.qr.verify';
    case WAREHOUSE_QR_BULK_GENERATE = 'warehouse.qr.bulk_generate';
    case WAREHOUSE_PERFORMANCE_VIEW = 'warehouse.performance.view';
    case WAREHOUSE_TASKS_VIEW = 'warehouse.tasks.view';
    case WAREHOUSE_TASKS_UPDATE = 'warehouse.tasks.update';
    case WAREHOUSE_BOXES_VIEW = 'warehouse.boxes.view';
    case WAREHOUSE_BOXES_SEAL = 'warehouse.boxes.seal';
    case WAREHOUSE_BOX_UPDATE_SEALED = 'warehouse.box.update_sealed';
    case WAREHOUSE_BOX_UPDATE_ANY = 'warehouse.box.update_any';

    // Supervisor
    case SUPERVISOR_DASHBOARD = 'supervisor.dashboard';
    case SUPERVISOR_WAREHOUSE_MONITOR = 'supervisor.warehouse.monitor';
    case SUPERVISOR_WAREHOUSE_REDISTRIBUTE = 'supervisor.warehouse.redistribute';
    case SUPERVISOR_WAREHOUSE_ASSIGN = 'supervisor.warehouse.assign';
    case SUPERVISOR_PERFORMANCE_VIEW = 'supervisor.performance.view';
    case SUPERVISOR_PERFORMANCE_REPORTS = 'supervisor.performance.reports';

    // QR
    case QR_GENERATE = 'qr.generate';
    case QR_SCAN = 'qr.scan';
    case QR_VERIFY = 'qr.verify';

    // Status
    case STATUS_UPDATE = 'status.update';
    case STATUS_TRACK = 'status.track';

    // Wakaf batch
    case WAKAF_BATCH_CREATE = 'wakaf-batch.create';
    case WAKAF_BATCH_READ = 'wakaf-batch.read';
    case WAKAF_BATCH_UPDATE = 'wakaf-batch.update';
    case WAKAF_BATCH_DELETE = 'wakaf-batch.delete';

    // Settings
    case SETTINGS_READ = 'settings.read';
    case SETTINGS_WRITE = 'settings.write';
    case SETTINGS_DELETE = 'settings.delete';

    // System
    case SYSTEM_MONITOR = 'system.monitor';
}
