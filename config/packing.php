<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Warehouse Packing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration untuk sistem packing warehouse Ekspedisi Quran
    |
    */

    /**
     * Auto seal box when full
     *
     * Jika true, kerdus otomatis sealed ketika sudah penuh
     * Jika false, warehouse staff harus manual seal
     */
    'auto_seal_on_full' => filter_var(env('PACKING_AUTO_SEAL', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Default daily target per user
     *
     * Target default untuk warehouse staff per hari
     * Supervisor bisa override per user
     */
    'default_daily_target' => env('PACKING_DAILY_TARGET', 80),

    /**
     * Allow parallel packing (multiple jenis at once)
     *
     * Jika true, staff bisa pack multiple jenis sekaligus
     * Jika false, harus selesaikan satu jenis dulu
     */
    'allow_parallel_packing' => filter_var(env('PACKING_ALLOW_PARALLEL', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Warning threshold untuk jenis switching
     *
     * Show warning jika ada N boxes terbuka dari jenis lain
     */
    'jenis_switch_warning_threshold' => 1,

    /**
     * Box capacity buffer (percentage)
     *
     * Buffer capacity untuk safety margin (0-100%)
     * Example: 10 means allow up to 110% of capacity
     */
    'capacity_buffer_percent' => 0,

    /**
     * Enable shared box system
     *
     * Multiple staff bisa collaborate dalam satu box
     */
    'enable_shared_boxes' => filter_var(env('PACKING_ENABLE_SHARED_BOXES', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Progress tracking milestones
     *
     * Expected progress percentage per hour
     * Format: hour => expected_percentage
     */
    'progress_milestones' => [
        10 => 25,  // Jam 10: minimal 25%
        12 => 40,  // Jam 12: minimal 40%
        14 => 60,  // Jam 14: minimal 60%
        16 => 80,  // Jam 16: minimal 80%
    ],

    /**
     * Performance tracking settings
     */
    'performance' => [
        /**
         * Minimum achievement rate untuk bonus eligibility (%)
         */
        'bonus_threshold' => 90,

        /**
         * Critical achievement threshold untuk alert (%)
         */
        'critical_threshold' => 50,

        /**
         * Warning achievement threshold (%)
         */
        'warning_threshold' => 75,
    ],

    /**
     * Task expiration settings
     */
    'task_expiration' => [
        /**
         * Auto expire tasks after N days
         */
        'expire_after_days' => 1,

        /**
         * Carry over incomplete items to next day
         */
        'allow_carryover' => true,

        /**
         * Maximum carryover percentage (0-100%)
         * Example: 20 means max 20% of target can be carried over
         */
        'max_carryover_percent' => 20,
    ],

    /**
     * QR Code settings
     */
    'qr' => [
        /**
         * QR code size (pixels)
         */
        'size' => 300,

        /**
         * QR code format (png, svg)
         */
        'format' => 'png',

        /**
         * Error correction level (L, M, Q, H)
         * L = 7%, M = 15%, Q = 25%, H = 30%
         */
        'error_correction' => 'M',
    ],

    /**
     * Notification settings
     */
    'notifications' => [
        /**
         * Send notification when task assigned
         */
        'notify_on_assignment' => true,

        /**
         * Send notification when behind schedule
         */
        'notify_on_behind_schedule' => true,

        /**
         * Send notification when task completed
         */
        'notify_on_completion' => true,

        /**
         * Send notification when box full
         */
        'notify_on_box_full' => false,
    ],

    /**
     * Holidays (no packing assignments)
     *
     * Format: MM-DD
     * Note: Ini adalah default fixed holidays
     * Untuk dynamic holidays, gunakan database table
     */
    'fixed_holidays' => [
        '01-01', // Tahun Baru
        '08-17', // HUT RI
        '12-25', // Natal
    ],

    /**
     * Work schedule
     */
    'work_schedule' => [
        /**
         * Start hour (24h format)
         */
        'start_hour' => 8,

        /**
         * End hour (24h format)
         */
        'end_hour' => 17,

        /**
         * Break duration (minutes)
         */
        'break_duration' => 60,

        /**
         * Working days (0 = Sunday, 6 = Saturday)
         */
        'working_days' => [1, 2, 3, 4, 5], // Monday - Friday
    ],

];
