<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk optimasi gambar otomatis
    |
    */

    'enabled' => env('IMAGE_OPTIMIZATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Quality Settings
    |--------------------------------------------------------------------------
    */
    'quality' => [
        'webp' => env('IMAGE_WEBP_QUALITY', 85),
        'jpeg' => env('IMAGE_JPEG_QUALITY', 85),
        'png' => env('IMAGE_PNG_QUALITY', 85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Dimensions
    |--------------------------------------------------------------------------
    */
    'dimensions' => [
        'max_width' => env('IMAGE_MAX_WIDTH', 1920),
        'max_height' => env('IMAGE_MAX_HEIGHT', 1080),
        'preserve_aspect_ratio' => env('IMAGE_PRESERVE_ASPECT_RATIO', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Settings
    |--------------------------------------------------------------------------
    */
    'thumbnail' => [
        'enabled' => env('IMAGE_CREATE_THUMBNAILS', true),
        'width' => env('IMAGE_THUMBNAIL_WIDTH', 300),
        'height' => env('IMAGE_THUMBNAIL_HEIGHT', 200),
        'quality' => env('IMAGE_THUMBNAIL_QUALITY', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    */
    'allowed_types' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size (in kilobytes)
    |--------------------------------------------------------------------------
    */
    'max_file_size' => env('IMAGE_MAX_FILE_SIZE', 10240), // 10MB

    /*
    |--------------------------------------------------------------------------
    | Directory Configuration
    |--------------------------------------------------------------------------
    */
    'directories' => [
        'galleries' => [
            'webp_quality' => 90,
            'max_width' => 1920,
            'max_height' => 1080,
            'create_thumbnail' => true,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
        ],
        'testimonials' => [
            'webp_quality' => 85,
            'max_width' => 800,
            'max_height' => 600,
            'create_thumbnail' => true,
            'thumbnail_width' => 150,
            'thumbnail_height' => 150,
        ],
        'avatars' => [
            'webp_quality' => 85,
            'max_width' => 400,
            'max_height' => 400,
            'create_thumbnail' => true,
            'thumbnail_width' => 100,
            'thumbnail_height' => 100,
        ],
        'logos' => [
            'webp_quality' => 95,
            'max_width' => 800,
            'max_height' => 400,
            'create_thumbnail' => false,
        ],
        'certificates' => [
            'webp_quality' => 95,
            'max_width' => 2048,
            'max_height' => 1536,
            'create_thumbnail' => true,
            'thumbnail_width' => 200,
            'thumbnail_height' => 150,
        ],
        'default' => [
            'webp_quality' => 85,
            'max_width' => 1920,
            'max_height' => 1080,
            'create_thumbnail' => true,
            'thumbnail_width' => 300,
            'thumbnail_height' => 200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Optimization Routes
    |--------------------------------------------------------------------------
    |
    | Routes yang akan otomatis menggunakan optimasi gambar
    |
    */
    'auto_optimize_routes' => [
        'admin.galleries.store',
        'admin.galleries.update',
        'admin.testimonials.store',
        'admin.testimonials.update',
        'admin.settings.landing-content.general.update',
        'admin.settings.landing-content.landing.update',
        'admin.settings.landing-content.contact.update',
        'admin.settings.landing-content.social.update',
        'admin.settings.landing-content.seo.update',
        'admin.settings.landing-content.legal.update',
        'admin.users.store',
        'admin.users.update',
        'admin.certificate-templates.store',
        'admin.certificate-templates.update',
        'public.mushaf-requests.store',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'enabled' => env('IMAGE_BACKUP_ENABLED', false),
        'directory' => 'backups/images',
        'keep_days' => env('IMAGE_BACKUP_KEEP_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'memory_limit' => env('IMAGE_MEMORY_LIMIT', '256M'),
        'timeout' => env('IMAGE_TIMEOUT', 60), // seconds
        'chunk_size' => env('IMAGE_CHUNK_SIZE', 5), // files per batch
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Integration
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'enabled' => env('IMAGE_CDN_ENABLED', false),
        'disk' => env('IMAGE_CDN_DISK', 's3'),
        'path_prefix' => env('IMAGE_CDN_PATH_PREFIX', 'images'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('IMAGE_LOGGING_ENABLED', true),
        'channel' => env('IMAGE_LOG_CHANNEL', 'daily'),
        'level' => env('IMAGE_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Settings
    |--------------------------------------------------------------------------
    */
    'fallback' => [
        'on_error' => env('IMAGE_FALLBACK_ON_ERROR', true),
        'original_upload' => env('IMAGE_FALLBACK_ORIGINAL_UPLOAD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Progressive Loading
    |--------------------------------------------------------------------------
    */
    'progressive' => [
        'enabled' => env('IMAGE_PROGRESSIVE_ENABLED', true),
        'create_multiple_sizes' => env('IMAGE_CREATE_MULTIPLE_SIZES', true),
        'sizes' => [
            'small' => ['width' => 400, 'height' => 300, 'quality' => 80],
            'medium' => ['width' => 800, 'height' => 600, 'quality' => 85],
            'large' => ['width' => 1200, 'height' => 900, 'quality' => 90],
            'xlarge' => ['width' => 1920, 'height' => 1080, 'quality' => 90],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WebP Conversion
    |--------------------------------------------------------------------------
    */
    'webp' => [
        'enabled' => env('IMAGE_WEBP_ENABLED', true),
        'convert_existing' => env('IMAGE_WEBP_CONVERT_EXISTING', false),
        'quality' => env('IMAGE_WEBP_QUALITY', 85),
        'fallback_formats' => ['jpg', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_dimensions' => [
            'width' => env('IMAGE_MAX_DIMENSION_WIDTH', 4000),
            'height' => env('IMAGE_MAX_DIMENSION_HEIGHT', 4000),
        ],
        'min_dimensions' => [
            'width' => env('IMAGE_MIN_DIMENSION_WIDTH', 50),
            'height' => env('IMAGE_MIN_DIMENSION_HEIGHT', 50),
        ],
        'aspect_ratio_tolerance' => env('IMAGE_ASPECT_RATIO_TOLERANCE', 0.1),
        'security_scan' => env('IMAGE_SECURITY_SCAN_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'auto_cleanup' => env('IMAGE_AUTO_CLEANUP_ENABLED', false),
        'cleanup_schedule' => env('IMAGE_CLEANUP_SCHEDULE', 'weekly'),
        'orphan_age_days' => env('IMAGE_ORPHAN_AGE_DAYS', 30),
        'temp_file_age_hours' => env('IMAGE_TEMP_FILE_AGE_HOURS', 24),
    ],
];
