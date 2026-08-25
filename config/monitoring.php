<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the application monitoring system.
    |
    */

    'enabled' => env('MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'collection_interval' => env('MONITORING_COLLECTION_INTERVAL', 60), // seconds
        'retention_period' => env('MONITORING_RETENTION_PERIOD', 604800), // 7 days in seconds
        'storage_driver' => env('MONITORING_STORAGE_DRIVER', 'cache'), // cache, database, file

        'alert_thresholds' => [
            'response_time' => env('MONITORING_RESPONSE_TIME_THRESHOLD', 2000), // ms
            'memory_usage' => env('MONITORING_MEMORY_THRESHOLD', 512), // MB
            'cpu_usage' => env('MONITORING_CPU_THRESHOLD', 80), // percentage
            'error_rate' => env('MONITORING_ERROR_RATE_THRESHOLD', 5), // percentage
            'queue_size' => env('MONITORING_QUEUE_SIZE_THRESHOLD', 1000),
            'disk_usage' => env('MONITORING_DISK_USAGE_THRESHOLD', 85), // percentage
            'storage_usage' => env('MONITORING_STORAGE_USAGE_THRESHOLD', 90), // percentage
        ],

        'enabled_collectors' => [
            'system' => true,
            'application' => true,
            'database' => true,
            'cache' => true,
            'queue' => true,
            'performance' => true,
            'business' => true,
            'storage' => true,
            'errors' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'slow_query_threshold' => env('MONITORING_SLOW_QUERY_THRESHOLD', 1000), // ms
        'memory_threshold' => env('MONITORING_MEMORY_THRESHOLD', 128), // MB
        'request_threshold' => env('MONITORING_REQUEST_THRESHOLD', 2000), // ms

        'track_user_performance' => true,
        'track_endpoint_performance' => true,
        'track_query_performance' => true,

        'sampling_rate' => env('MONITORING_SAMPLING_RATE', 100), // percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    */
    'alerting' => [
        'enabled' => env('MONITORING_ALERTING_ENABLED', true),
        'cooldown_period' => env('MONITORING_ALERT_COOLDOWN', 300), // 5 minutes
        'escalation_levels' => ['info', 'warning', 'critical', 'emergency'],

        'channels' => [
            'log' => true,
            'email' => env('MONITORING_EMAIL_ALERTS', false),
            'webhook' => env('MONITORING_WEBHOOK_ALERTS', false),
            'slack' => env('MONITORING_SLACK_ALERTS', false),
        ],

        'email_settings' => [
            'to' => env('MONITORING_ALERT_EMAIL'),
            'from' => env('MAIL_FROM_ADDRESS'),
            'subject_prefix' => '[MONITORING ALERT]',
        ],

        'webhook_settings' => [
            'url' => env('MONITORING_WEBHOOK_URL'),
            'timeout' => 10,
            'retry_attempts' => 3,
        ],

        'slack_settings' => [
            'webhook_url' => env('MONITORING_SLACK_WEBHOOK_URL'),
            'channel' => env('MONITORING_SLACK_CHANNEL', '#alerts'),
            'username' => env('MONITORING_SLACK_USERNAME', 'MonitoringBot'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Monitoring
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'enabled' => true,
        'check_interval' => 3600, // 1 hour
        'warning_threshold' => 80, // percentage
        'critical_threshold' => 95, // percentage
        'cleanup_enabled' => true,
        'optimization_enabled' => true,

        'monitored_paths' => [
            'storage' => storage_path(),
            'public' => public_path(),
            'logs' => storage_path('logs'),
            'cache' => storage_path('framework/cache'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Benchmarks
    |--------------------------------------------------------------------------
    */
    'benchmarks' => [
        'enabled' => true,
        'auto_run_interval' => env('MONITORING_BENCHMARK_INTERVAL', 3600), // 1 hour
        'store_history' => true,
        'history_retention_days' => 30,

        'categories' => [
            'database' => true,
            'cache' => true,
            'file_io' => true,
            'memory' => true,
            'cpu' => true,
            'network' => false, // Disabled by default
        ],

        'baseline_auto_update' => false,
        'baseline_deviation_threshold' => 25, // percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'refresh_interval' => 30, // seconds
        'chart_data_points' => 50,
        'real_time_updates' => true,

        'widgets' => [
            'system_overview' => true,
            'performance_metrics' => true,
            'alert_summary' => true,
            'queue_status' => true,
            'storage_usage' => true,
            'database_health' => true,
            'recent_errors' => true,
            'business_metrics' => true,
        ],

        'charts' => [
            'response_time_trend' => true,
            'memory_usage_trend' => true,
            'error_rate_trend' => true,
            'queue_size_trend' => true,
            'throughput_trend' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    */
    'health_checks' => [
        'enabled' => true,
        'interval' => 300, // 5 minutes

        'checks' => [
            'database' => [
                'enabled' => true,
                'timeout' => 5,
                'critical_threshold' => 2000, // ms
            ],
            'cache' => [
                'enabled' => true,
                'timeout' => 3,
                'critical_threshold' => 1000, // ms
            ],
            'storage' => [
                'enabled' => true,
                'critical_threshold' => 95, // percentage
            ],
            'queue' => [
                'enabled' => true,
                'failed_jobs_threshold' => 50,
                'backlog_threshold' => 1000,
            ],
            'external_services' => [
                'enabled' => false,
                'services' => [
                    // 'api_service' => 'https://api.example.com/health',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('MONITORING_LOG_CHANNEL', 'daily'),
        'level' => env('MONITORING_LOG_LEVEL', 'info'),
        'include_context' => true,
        'include_stack_trace' => false,

        'performance_log' => [
            'enabled' => true,
            'channel' => 'performance',
            'slow_requests_only' => false,
        ],

        'error_tracking' => [
            'enabled' => true,
            'exclude_4xx' => true,
            'group_similar_errors' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => true,
        'rate_limit' => '60,1', // 60 requests per minute
        'authentication' => env('MONITORING_API_AUTH', false),
        'api_key' => env('MONITORING_API_KEY'),

        'endpoints' => [
            'metrics' => '/admin/monitoring/api/metrics',
            'health' => '/admin/monitoring/api/health',
            'alerts' => '/admin/monitoring/api/alerts',
            'benchmarks' => '/admin/monitoring/api/benchmarks',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encrypt_sensitive_data' => true,
        'mask_sensitive_fields' => ['password', 'token', 'key', 'secret'],
        'ip_whitelist' => env('MONITORING_IP_WHITELIST', ''), // comma-separated IPs

        'permissions' => [
            'view_dashboard' => 'view monitoring dashboard',
            'manage_alerts' => 'manage monitoring alerts',
            'run_benchmarks' => 'run performance benchmarks',
            'export_reports' => 'export monitoring reports',
            'configure_monitoring' => 'configure monitoring settings',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'prometheus' => [
            'enabled' => false,
            'endpoint' => '/metrics',
            'basic_auth' => false,
        ],

        'grafana' => [
            'enabled' => false,
            'api_url' => env('GRAFANA_API_URL'),
            'api_key' => env('GRAFANA_API_KEY'),
        ],

        'newrelic' => [
            'enabled' => false,
            'api_key' => env('NEWRELIC_API_KEY'),
            'app_id' => env('NEWRELIC_APP_ID'),
        ],

        'sentry' => [
            'enabled' => env('SENTRY_LARAVEL_DSN', false),
            'performance_monitoring' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup and Maintenance
    |--------------------------------------------------------------------------
    */
    'maintenance' => [
        'auto_cleanup' => true,
        'cleanup_interval' => 86400, // 24 hours

        'cleanup_rules' => [
            'old_metrics' => [
                'enabled' => true,
                'retention_days' => 7,
            ],
            'old_alerts' => [
                'enabled' => true,
                'retention_days' => 30,
            ],
            'old_benchmarks' => [
                'enabled' => true,
                'retention_days' => 30,
            ],
            'old_logs' => [
                'enabled' => true,
                'retention_days' => 14,
            ],
        ],

        'optimization' => [
            'compress_old_data' => true,
            'archive_old_reports' => true,
            'vacuum_cache' => true,
        ],
    ],
];
