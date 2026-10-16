<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for file uploads in the application.
    | It defines size limits and other upload-related settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Size Limits
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file sizes for different types of uploads.
    | Note: These are separate from the PHP settings in php.ini.
    |
    */
    'limits' => [
        'foto' => [
            'max' => 2048, // in KB (2MB)
            'label' => '2MB',
            'mime_types' => ['image/jpeg', 'image/png', 'image/jpg'],
            'extensions' => ['jpg', 'jpeg', 'png'],
        ],
        'dokumen' => [
            'max' => 5120, // in KB (5MB)
            'label' => '5MB',
            'mime_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        ],
        'total' => [
            'max' => 10240, // in KB (10MB)
            'label' => '10MB'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    |
    | Custom error messages for file upload errors.
    |
    */
    'error_messages' => [
        'size_exceeded' => '⚠️ Ukuran file melebihi batas maksimum! Batas ukuran: :limit. Ukuran file Anda: :size. Silakan kompres atau pilih file yang lebih kecil.',
        'total_size_exceeded' => '⚠️ Total ukuran file melebihi batas maksimum! Batas total ukuran: :limit. Total ukuran file Anda: :size. Silakan kompres atau pilih file yang lebih kecil.',
        'mime_type' => 'Format file tidak diizinkan. Format yang diizinkan: :allowed_types.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Directories
    |--------------------------------------------------------------------------
    |
    | Define where various types of files should be stored.
    | All paths are relative to the public storage directory.
    |
    */
    'directories' => [
        'foto_santri' => 'mushaf-requests/foto-santri',
        'foto_lembaga' => 'mushaf-requests/foto-lembaga',
        'file_nama_santri' => 'mushaf-requests/file-nama-santri',
        'dokumentasi' => 'mushaf-requests/dokumentasi',
    ],
];
