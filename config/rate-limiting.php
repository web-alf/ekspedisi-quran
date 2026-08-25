<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the application's rate limiting
    | system. You can define whitelisted IPs, default limits, and other
    | rate limiting behaviors here.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Rate Limit
    |--------------------------------------------------------------------------
    |
    | The default number of requests allowed per minute for fallback scenarios
    |
    */
    'default_limit' => env('RATE_LIMIT_DEFAULT', 120),

    /*
    |--------------------------------------------------------------------------
    | Whitelisted IPs
    |--------------------------------------------------------------------------
    |
    | IP addresses that should bypass rate limiting. Supports both individual
    | IPs and CIDR notation for subnets (e.g., '192.168.1.0/24')
    |
    */
    'whitelist_ips' => [
        '127.0.0.1',           // Localhost
        '::1',                 // IPv6 localhost
        // Add your admin/monitoring IPs here
        // Example: '192.168.1.100',
        // Example: '10.0.0.0/8',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Strategies by Endpoint Type
    |--------------------------------------------------------------------------
    |
    | Define rate limiting strategies for different types of endpoints.
    | This helps with monitoring and analytics.
    |
    */
    'strategies' => [
        'authentication' => [
            'name' => 'Authentication Endpoints',
            'description' => 'Login, logout, and password reset endpoints',
            'base_limit' => 20,
            'burst_limit' => 10,
            'window' => 'minute',
            'endpoints' => [
                '/login',
                '/logout',
                '/password/reset',
                '/register',
            ],
        ],

        'public_api' => [
            'name' => 'Public API Endpoints',
            'description' => 'Publicly accessible API endpoints',
            'base_limit' => 120,
            'burst_limit' => 60,
            'window' => 'minute',
            'endpoints' => [
                '/api/wilayah/*',
                '/api/tracking/*',
            ],
        ],

        'public_forms' => [
            'name' => 'Public Form Submissions',
            'description' => 'Public forms like mushaf requests',
            'base_limit' => 60,
            'burst_limit' => 30,
            'window' => 'hour',
            'endpoints' => [
                '/mushaf-request',
                '/tracking/search',
            ],
        ],

        'admin_operations' => [
            'name' => 'Admin Operations',
            'description' => 'Administrative operations and management',
            'base_limit' => 300,
            'burst_limit' => 200,
            'window' => 'minute',
            'endpoints' => [
                '/admin/*',
            ],
        ],

        'warehouse_operations' => [
            'name' => 'Warehouse Operations',
            'description' => 'Warehouse scanning and packing operations',
            'base_limit' => 600,
            'burst_limit' => 400,
            'window' => 'minute',
            'endpoints' => [
                '/admin/warehouse/*',
            ],
        ],

        'bulk_operations' => [
            'name' => 'Bulk Operations',
            'description' => 'Resource-intensive bulk operations',
            'base_limit' => 30,
            'burst_limit' => 15,
            'window' => 'minute',
            'endpoints' => [
                '/admin/*/bulk-*',
                '/admin/box-bulk/*',
            ],
        ],

        'exports' => [
            'name' => 'Export Operations',
            'description' => 'Data export and report generation',
            'base_limit' => 15,
            'burst_limit' => 10,
            'window' => 'minute',
            'endpoints' => [
                '/admin/*/export',
                '/admin/*-export',
            ],
        ],

        'certificates' => [
            'name' => 'Certificate Operations',
            'description' => 'Certificate download and preview',
            'base_limit' => 20,
            'burst_limit' => 10,
            'window' => 'minute',
            'endpoints' => [
                '/certificate/*',
                '/admin/certificates/*',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Storage
    |--------------------------------------------------------------------------
    |
    | The cache store to use for rate limiting. Redis is recommended for
    | high-traffic applications. Set to null to use the default cache store.
    |
    */
    'cache_store' => env('RATE_LIMIT_CACHE_STORE', null),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Monitoring
    |--------------------------------------------------------------------------
    |
    | Enable or disable monitoring and alerting for rate limiting events
    |
    */
    'monitoring' => [
        'enabled' => env('RATE_LIMIT_MONITORING', true),
        'log_channel' => env('RATE_LIMIT_LOG_CHANNEL', 'stack'),
        'alert_threshold' => env('RATE_LIMIT_ALERT_THRESHOLD', 0.8), // Alert when 80% of limit reached
    ],

    /*
    |--------------------------------------------------------------------------
    | User Role Rate Limits
    |--------------------------------------------------------------------------
    |
    | Define different rate limits based on user roles
    |
    */
    'role_limits' => [
        'super-admin' => [
            'per_minute' => 1000,
            'per_hour' => 10000,
            'per_day' => 100000,
        ],
        'admin' => [
            'per_minute' => 600,
            'per_hour' => 6000,
            'per_day' => 60000,
        ],
        'warehouse' => [
            'per_minute' => 800, // High limits for scanning operations
            'per_hour' => 8000,
            'per_day' => 80000,
        ],
        'cs' => [
            'per_minute' => 300,
            'per_hour' => 3000,
            'per_day' => 30000,
        ],
        'courier' => [
            'per_minute' => 200,
            'per_hour' => 2000,
            'per_day' => 20000,
        ],
        'guest' => [
            'per_minute' => 60,
            'per_hour' => 600,
            'per_day' => 6000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related rate limiting configurations
    |
    */
    'security' => [
        'block_suspicious_patterns' => env('RATE_LIMIT_BLOCK_SUSPICIOUS', true),
        'max_failed_attempts' => env('RATE_LIMIT_MAX_FAILED_ATTEMPTS', 20),
        'lockout_duration' => env('RATE_LIMIT_LOCKOUT_DURATION', 300), // 5 minutes
        'track_user_agents' => env('RATE_LIMIT_TRACK_USER_AGENTS', true),
    ],
];
