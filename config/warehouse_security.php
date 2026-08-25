<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Warehouse Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security configuration options for warehouse features.
    | These settings help protect against various security vulnerabilities.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Configuration for secure file uploads in warehouse features.
    |
    */
    'file_upload' => [
        'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
        'max_size_mb' => 5,
        'max_size_bytes' => 5242880, // 5MB in bytes
        'storage_disk' => 'public',
        'storage_path' => 'status_updates',
        'scan_for_viruses' => env('WAREHOUSE_SCAN_FILES', false),
        'quarantine_suspicious' => env('WAREHOUSE_QUARANTINE_FILES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns for validating various input types in warehouse operations.
    |
    */
    'validation_patterns' => [
        'qr_code' => '/^[A-Za-z0-9\-\{\}\":\s,_]+$/',
        'box_code' => '/^KB-\d{8}-\d{3}-[A-Z0-9]+-\d{2}$/',
        'resi_number' => '/^EQ-\d{4}-\d{5}$/',
        'address' => '/^[a-zA-Z0-9\s\.,\-\/\(\)]+$/',
        'notes' => '/^[a-zA-Z0-9\s\.,\-\!\?]+$/',
        'location' => '/^[a-zA-Z0-9\s\.,\-]+$/',
        'safe_filename' => '/^[A-Za-z0-9\-_]+$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for warehouse operations to prevent abuse.
    |
    */
    'rate_limits' => [
        'scan_attempts_per_minute' => env('WAREHOUSE_SCAN_RATE_LIMIT', 30),
        'file_uploads_per_hour' => env('WAREHOUSE_UPLOAD_RATE_LIMIT', 10),
        'status_updates_per_minute' => env('WAREHOUSE_STATUS_RATE_LIMIT', 20),
        'failed_scans_before_lockout' => env('WAREHOUSE_FAILED_SCAN_LIMIT', 5),
        'lockout_duration_minutes' => env('WAREHOUSE_LOCKOUT_DURATION', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for security-related logging in warehouse operations.
    |
    */
    'logging' => [
        'enabled' => env('WAREHOUSE_SECURITY_LOGGING', true),
        'log_channel' => env('WAREHOUSE_LOG_CHANNEL', 'daily'),
        'log_level' => env('WAREHOUSE_LOG_LEVEL', 'info'),
        'log_failed_attempts' => env('WAREHOUSE_LOG_FAILURES', true),
        'log_successful_operations' => env('WAREHOUSE_LOG_SUCCESS', true),
        'include_ip_address' => env('WAREHOUSE_LOG_IP', true),
        'include_user_agent' => env('WAREHOUSE_LOG_USER_AGENT', false),
        'retention_days' => env('WAREHOUSE_LOG_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    |
    | Configuration for warehouse access control and authorization.
    |
    */
    'access_control' => [
        'require_active_task' => env('WAREHOUSE_REQUIRE_ACTIVE_TASK', true),
        'allow_cross_user_box_access' => env('WAREHOUSE_CROSS_USER_ACCESS', false),
        'supervisor_override' => env('WAREHOUSE_SUPERVISOR_OVERRIDE', true),
        'admin_full_access' => env('WAREHOUSE_ADMIN_FULL_ACCESS', true),
        'session_timeout_minutes' => env('WAREHOUSE_SESSION_TIMEOUT', 480), // 8 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security
    |--------------------------------------------------------------------------
    |
    | Configuration for content validation and sanitization.
    |
    */
    'content_security' => [
        'max_qr_data_length' => 1000,
        'max_json_depth' => 5,
        'strip_html_tags' => true,
        'validate_json_structure' => true,
        'sanitize_file_names' => true,
        'prevent_path_traversal' => true,
        'validate_mime_types' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    |
    | Configuration for maintaining audit trails of warehouse operations.
    |
    */
    'audit_trail' => [
        'enabled' => env('WAREHOUSE_AUDIT_ENABLED', true),
        'track_box_access' => env('WAREHOUSE_AUDIT_BOX_ACCESS', true),
        'track_status_changes' => env('WAREHOUSE_AUDIT_STATUS_CHANGES', true),
        'track_file_uploads' => env('WAREHOUSE_AUDIT_FILE_UPLOADS', true),
        'track_scan_operations' => env('WAREHOUSE_AUDIT_SCAN_OPS', true),
        'track_authorization_failures' => env('WAREHOUSE_AUDIT_AUTH_FAILURES', true),
        'retention_period_days' => env('WAREHOUSE_AUDIT_RETENTION', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Controls
    |--------------------------------------------------------------------------
    |
    | Emergency controls that can be activated during security incidents.
    |
    */
    'emergency' => [
        'lockdown_mode' => env('WAREHOUSE_LOCKDOWN_MODE', false),
        'disable_file_uploads' => env('WAREHOUSE_DISABLE_UPLOADS', false),
        'read_only_mode' => env('WAREHOUSE_READ_ONLY', false),
        'maintenance_message' => env('WAREHOUSE_MAINTENANCE_MSG', 'Warehouse system is temporarily unavailable for maintenance.'),
    ],
];