<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Certificate Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for certificate generation
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto Generation
    |--------------------------------------------------------------------------
    |
    | Automatically generate certificates when shipment status changes
    |
    */
    'auto_generate' => env('CERTIFICATE_AUTO_GENERATE', true),
    
    /*
    |--------------------------------------------------------------------------
    | Trigger Status
    |--------------------------------------------------------------------------
    |
    | Status names that trigger certificate generation
    |
    */
    'trigger_status' => ['Selesai', 'Diterima'],
    
    /*
    |--------------------------------------------------------------------------
    | Default Template
    |--------------------------------------------------------------------------
    |
    | Default template to use for certificate generation
    |
    */
    'template' => env('CERTIFICATE_TEMPLATE', 'default'),
    
    /*
    |--------------------------------------------------------------------------
    | PDF Settings
    |--------------------------------------------------------------------------
    |
    | PDF generation settings
    |
    */
    'pdf' => [
        'paper_size' => 'a4',
        'orientation' => 'landscape',
        'dpi' => 150,
        'font' => 'Arial',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Certificate file storage settings
    |
    */
    'storage' => [
        'disk' => env('CERTIFICATE_STORAGE_DISK', 'local'),
        'path' => 'certificates',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Numbering Format
    |--------------------------------------------------------------------------
    |
    | Certificate numbering format
    |
    */
    'numbering_format' => 'CERT-EQ-{year}-{sequence}',
    
    /*
    |--------------------------------------------------------------------------
    | Organization Information
    |--------------------------------------------------------------------------
    |
    | Organization details to be included in certificates
    |
    */
    'organization' => [
        'name' => env('CERTIFICATE_ORG_NAME', 'Ekspedisi Quran'),
        'address' => env('CERTIFICATE_ORG_ADDRESS', ''),
        'phone' => env('CERTIFICATE_ORG_PHONE', ''),
        'email' => env('CERTIFICATE_ORG_EMAIL', ''),
        'website' => env('CERTIFICATE_ORG_WEBSITE', ''),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Available certificate templates
    |
    */
    'templates' => [
        'default' => [
            'name' => 'Default Template',
            'view' => 'certificates.template',
            'description' => 'Standard certificate template',
        ],
        // Add more templates here
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable/disable specific features
    |
    */
    'features' => [
        'qr_code' => true,
        'hijri_date' => false,
        'watermark' => true,
        'digital_signature' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security related settings
    |
    */
    'security' => [
        'max_downloads' => 10,
        'download_expires_days' => 30,
        'require_token' => true,
    ],
];
