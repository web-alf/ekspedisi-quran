<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for comprehensive file storage optimization
    |
    */

    'enabled' => env('STORAGE_OPTIMIZATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | File Organization Settings
    |--------------------------------------------------------------------------
    */
    'organization' => [
        'auto_organize' => env('STORAGE_AUTO_ORGANIZE', true),
        'organize_by' => env('STORAGE_ORGANIZE_BY', 'date'), // date, type, purpose
        'date_format' => env('STORAGE_DATE_FORMAT', 'Y/m'), // Year/Month
        'create_subdirectories' => env('STORAGE_CREATE_SUBDIRS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Cleanup Settings
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'enabled' => env('STORAGE_CLEANUP_ENABLED', true),
        'orphan_check_days' => env('STORAGE_ORPHAN_CHECK_DAYS', 30),
        'temp_file_hours' => env('STORAGE_TEMP_FILE_HOURS', 24),
        'thumbnail_cleanup' => env('STORAGE_THUMBNAIL_CLEANUP', true),
        'backup_before_delete' => env('STORAGE_BACKUP_BEFORE_DELETE', false),
        'safe_mode' => env('STORAGE_SAFE_MODE', true), // Require confirmation for bulk deletions
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_total_size' => env('STORAGE_MAX_TOTAL_SIZE', 5368709120), // 5GB
        'max_file_size' => env('STORAGE_MAX_FILE_SIZE', 52428800), // 50MB
        'max_files_per_directory' => env('STORAGE_MAX_FILES_PER_DIR', 1000),
        'warning_threshold' => env('STORAGE_WARNING_THRESHOLD', 80), // Percentage
        'critical_threshold' => env('STORAGE_CRITICAL_THRESHOLD', 95), // Percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | File Type Categories
    |--------------------------------------------------------------------------
    */
    'file_types' => [
        'images' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            'max_size' => 10485760, // 10MB
            'auto_optimize' => true,
        ],
        'documents' => [
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
            'mime_types' => ['application/pdf', 'application/msword', 'application/vnd.ms-excel'],
            'max_size' => 52428800, // 50MB
            'auto_optimize' => false,
        ],
        'videos' => [
            'extensions' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'],
            'mime_types' => ['video/mp4', 'video/avi', 'video/quicktime'],
            'max_size' => 104857600, // 100MB
            'auto_optimize' => false,
        ],
        'audio' => [
            'extensions' => ['mp3', 'wav', 'flac', 'aac', 'ogg'],
            'mime_types' => ['audio/mpeg', 'audio/wav', 'audio/flac'],
            'max_size' => 20971520, // 20MB
            'auto_optimize' => false,
        ],
        'archives' => [
            'extensions' => ['zip', 'rar', '7z', 'tar', 'gz'],
            'mime_types' => ['application/zip', 'application/x-rar'],
            'max_size' => 104857600, // 100MB
            'auto_optimize' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'scan_uploads' => env('STORAGE_SCAN_UPLOADS', true),
        'quarantine_suspicious' => env('STORAGE_QUARANTINE_SUSPICIOUS', true),
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf', 'application/msword', 'application/vnd.ms-excel',
            'text/plain', 'text/csv',
        ],
        'blocked_extensions' => [
            'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
            'php', 'asp', 'aspx', 'jsp', 'pl', 'py', 'rb', 'sh',
        ],
        'max_filename_length' => env('STORAGE_MAX_FILENAME_LENGTH', 255),
        'sanitize_filenames' => env('STORAGE_SANITIZE_FILENAMES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'lazy_loading' => env('STORAGE_LAZY_LOADING', true),
        'progressive_loading' => env('STORAGE_PROGRESSIVE_LOADING', true),
        'cache_file_info' => env('STORAGE_CACHE_FILE_INFO', true),
        'cache_duration' => env('STORAGE_CACHE_DURATION', 3600), // 1 hour
        'chunk_size' => env('STORAGE_CHUNK_SIZE', 8192), // 8KB chunks
        'concurrent_uploads' => env('STORAGE_CONCURRENT_UPLOADS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Integration
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'enabled' => env('STORAGE_CDN_ENABLED', false),
        'provider' => env('STORAGE_CDN_PROVIDER', 's3'), // s3, cloudinary, cloudflare
        'auto_upload' => env('STORAGE_CDN_AUTO_UPLOAD', false),
        'fallback_local' => env('STORAGE_CDN_FALLBACK_LOCAL', true),
        'purge_cache' => env('STORAGE_CDN_PURGE_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Settings
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('STORAGE_MONITORING_ENABLED', true),
        'check_interval' => env('STORAGE_MONITORING_INTERVAL', 3600), // 1 hour
        'alert_email' => env('STORAGE_ALERT_EMAIL'),
        'log_channel' => env('STORAGE_LOG_CHANNEL', 'daily'),
        'metrics_retention' => env('STORAGE_METRICS_RETENTION', 2592000), // 30 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'enabled' => env('STORAGE_BACKUP_ENABLED', false),
        'disk' => env('STORAGE_BACKUP_DISK', 's3'),
        'schedule' => env('STORAGE_BACKUP_SCHEDULE', 'daily'),
        'retention_days' => env('STORAGE_BACKUP_RETENTION', 30),
        'compress' => env('STORAGE_BACKUP_COMPRESS', true),
        'encrypt' => env('STORAGE_BACKUP_ENCRYPT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory Structure
    |--------------------------------------------------------------------------
    */
    'directories' => [
        'uploads' => 'uploads',
        'images' => 'images',
        'documents' => 'documents',
        'videos' => 'videos',
        'audio' => 'audio',
        'temp' => 'temp',
        'thumbnails' => 'thumbnails',
        'cache' => 'cache',
        'backups' => 'backups',
        'quarantine' => 'quarantine',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Versioning
    |--------------------------------------------------------------------------
    */
    'versioning' => [
        'enabled' => env('STORAGE_VERSIONING_ENABLED', false),
        'max_versions' => env('STORAGE_MAX_VERSIONS', 5),
        'cleanup_old_versions' => env('STORAGE_CLEANUP_OLD_VERSIONS', true),
        'version_retention_days' => env('STORAGE_VERSION_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression Settings
    |--------------------------------------------------------------------------
    */
    'compression' => [
        'enabled' => env('STORAGE_COMPRESSION_ENABLED', true),
        'algorithms' => ['gzip', 'brotli'],
        'min_file_size' => env('STORAGE_COMPRESSION_MIN_SIZE', 1024), // 1KB
        'exclude_types' => ['image/jpeg', 'image/png', 'video/*', 'audio/*'], // Already compressed
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'track_downloads' => env('STORAGE_TRACK_DOWNLOADS', true),
        'track_views' => env('STORAGE_TRACK_VIEWS', true),
        'popular_files_limit' => env('STORAGE_POPULAR_FILES_LIMIT', 100),
        'analytics_retention' => env('STORAGE_ANALYTICS_RETENTION', 365), // days
    ],
];
