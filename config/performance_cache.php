<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls the hierarchical caching system
    | designed to dramatically improve application performance.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto Cache Warming
    |--------------------------------------------------------------------------
    |
    | Whether to automatically warm the cache when the application boots.
    | Only applies in production environment.
    |
    */

    'auto_warm' => env('CACHE_AUTO_WARM', false),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings
    |--------------------------------------------------------------------------
    |
    | Default TTL (Time To Live) settings for different cache layers.
    | Values are in seconds.
    |
    */

    'ttl' => [
        'dashboard' => env('CACHE_TTL_DASHBOARD', 300), // 5 minutes
        'reference' => env('CACHE_TTL_REFERENCE', 3600), // 1 hour
        'geographic' => env('CACHE_TTL_GEOGRAPHIC', 86400), // 24 hours
        'user' => env('CACHE_TTL_USER', 1800), // 30 minutes
        'query' => env('CACHE_TTL_QUERY', 900), // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tags Configuration
    |--------------------------------------------------------------------------
    |
    | Enable or disable cache tagging. Requires Redis cache driver.
    | Cache tagging allows for more efficient cache invalidation.
    |
    */

    'tagging' => [
        'enabled' => env('CACHE_TAGGING_ENABLED', true),
        'fallback_without_tagging' => env('CACHE_TAGGING_FALLBACK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Prefixes
    |--------------------------------------------------------------------------
    |
    | Prefixes for different cache layers to avoid key collisions
    | and enable easier cache management.
    |
    */

    'prefixes' => [
        'dashboard' => 'dash',
        'reference' => 'ref',
        'geographic' => 'geo',
        'user' => 'usr',
        'query' => 'qry',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for cache performance monitoring and debugging.
    |
    */

    'monitoring' => [
        'enabled' => env('CACHE_MONITORING_ENABLED', true),
        'log_hits' => env('CACHE_LOG_HITS', false),
        'log_misses' => env('CACHE_LOG_MISSES', true),
        'log_invalidations' => env('CACHE_LOG_INVALIDATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cache failover behavior when cache operations fail.
    |
    */

    'failover' => [
        'fallback_to_database' => env('CACHE_FALLBACK_DB', true),
        'log_failures' => env('CACHE_LOG_FAILURES', true),
        'retry_attempts' => env('CACHE_RETRY_ATTEMPTS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warm-up Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cache warming operations.
    |
    */

    'warm_up' => [
        'timeout' => env('CACHE_WARMUP_TIMEOUT', 300), // 5 minutes
        'services' => [
            'reference' => true,   // Always warm reference data
            'dashboard' => true,   // Warm dashboard for performance
            'geographic' => true,  // Warm geographic data
            'user' => true,        // Warm user permissions
            'query' => false,      // Don't warm queries (too many variations)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation Rules
    |--------------------------------------------------------------------------
    |
    | Define which cache layers should be invalidated when certain
    | models are created, updated, or deleted.
    |
    */

    'invalidation_rules' => [
        'pengiriman' => ['dashboard', 'query'],
        'donatur' => ['dashboard', 'query'],
        'mushaf_request' => ['dashboard', 'query'],
        'status_pengiriman' => ['reference', 'dashboard'],
        'jenis_quran' => ['reference'],
        'certificate_template' => ['reference'],
        'user' => ['user', 'dashboard'],
        'permission' => ['user'],
        'role' => ['user'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Specific Settings
    |--------------------------------------------------------------------------
    |
    | Settings specific to Redis cache driver.
    |
    */

    'redis' => [
        'compression' => env('REDIS_CACHE_COMPRESSION', false),
        'serialization' => env('REDIS_CACHE_SERIALIZATION', 'php'),
        'key_pattern_cleanup' => env('REDIS_KEY_PATTERN_CLEANUP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Size Limits
    |--------------------------------------------------------------------------
    |
    | Limits for cache sizes to prevent memory issues.
    |
    */

    'limits' => [
        'max_key_size' => env('CACHE_MAX_KEY_SIZE', 250), // characters
        'max_value_size' => env('CACHE_MAX_VALUE_SIZE', 10485760), // 10MB in bytes
        'warn_value_size' => env('CACHE_WARN_VALUE_SIZE', 1048576), // 1MB in bytes
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Settings
    |--------------------------------------------------------------------------
    |
    | Settings for debugging cache operations.
    |
    */

    'debug' => [
        'enabled' => env('CACHE_DEBUG', env('APP_DEBUG', false)),
        'slow_query_threshold' => env('CACHE_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'log_slow_queries' => env('CACHE_LOG_SLOW_QUERIES', true),
    ],
];
