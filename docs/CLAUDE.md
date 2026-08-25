# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Ekspedisi Quran is a Laravel-based management system for Quran distribution and tracking for wakaf (Islamic endowment) programs. The application manages shipments, tracks delivery status, generates QR codes for verification, and provides admin dashboard and public API for tracking.

## Technology Stack

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Frontend**: Svelte + Inertia.js
- **Styling**: TailwindCSS
- **Database**: MySQL
- **Key Libraries**: 
  - QR Code generation (`simplesoftwareio/simple-qrcode`)
  - PDF generation (`barryvdh/laravel-dompdf`)
  - Excel handling (`maatwebsite/excel`)
  - Image processing (`intervention/image`)
  - QR scanning (`html5-qrcode`)
- **External APIs**:
  - **Indonesia Regional Data API**: `https://open-api.my.id/api/wilayah/`
    - Used for cascading address form (Province -> Regency -> District -> Village)
    - Free REST API for Indonesian administrative regions
    - Endpoints: provinces, regencies/{provinceId}, districts/{regencyId}, villages/{districtId}
    - Best Practice: Cache responses in backend and frontend to minimize requests

## Development Commands

### Frontend Development
```bash
npm run dev          # Start Vite development server
npm run build        # Build assets for production
```

### Backend Development
```bash
php artisan serve    # Start Laravel development server
composer dev         # Start all services (Laravel + Queue + Logs + Vite)
```

### Database
```bash
php artisan migrate --seed    # Run migrations with seeders
php artisan migrate:fresh --seed    # Fresh migration with seeders
```

### Testing
```bash
php artisan test     # Run PHPUnit/Pest tests
composer test        # Run tests with config clear
```

### Code Quality
```bash
vendor/bin/pint      # Laravel Pint code formatting
```

### Cache & Optimization
```bash
php artisan optimize         # Optimize for production
php artisan config:cache     # Cache configuration
php artisan route:cache      # Cache routes
php artisan storage:link     # Create storage symlink
```

## Architecture & Key Components

### Core Models
- **Pengiriman**: Main shipment entity with status tracking
- **Wakif**: Donors/wakaf contributors
- **MushafRequest**: Public requests for Quran distribution with quantity tracking
  - **NEW**: Separate tracking for requested vs approved quantities
  - **NEW**: Model accessors: `total_mushaf_approved`, `has_quantity_change`, `quantity_change_percentage`
  - **NEW**: Excel import with smart parsing for phone numbers, quantities, and GPS coordinates
  - **NEW**: Validation before processing to shipment when quantities are edited
- **Sertifikat**: Certificates generated for wakif
- **StatusPengiriman**: Shipment status definitions
- **WakafBatch**: Batch system for managing multiple shipments

### Controllers Structure
- **Admin Controllers** (`app/Http/Controllers/Admin/`): Admin dashboard functionality
- **Public Controllers** (`app/Http/Controllers/Public/`): Public-facing features
- **Auth Controllers** (`app/Http/Controllers/Auth/`): Authentication

### Frontend Structure
- **Pages** (`resources/js/Pages/`): Svelte page components
  - `Admin/`: Admin dashboard pages
  - `Public/`: Public-facing pages
  - `Auth/`: Authentication pages
- **Components** (`resources/js/Components/`): Reusable Svelte components
  - `AddressFormIndonesia.svelte`: Indonesia regional address form with API integration
- **Layouts** (`resources/js/Layouts/`): Page layout components

### Services
- **CertificateService** (`app/Services/CertificateService.php`): PDF certificate generation
- **PostDeliveryService** (`app/Services/PostDeliveryService.php`): Post-delivery tracking

### Key Features
- QR Code generation and scanning for shipment verification
- Certificate generation for wakif using customizable templates
- Batch processing for multiple shipments
- Status tracking with history
- Image upload for documentation
- Excel import/export for wakif data
- Public tracking API
- **Mushaf Request Management** (October 2025):
  - **Excel Import**: Bulk upload mushaf requests with smart parsing
    - Phone normalization (08xxx → 628xxx)
    - Quantity parsing (supports "100", "50 A5, 30 A6", "100 mushaf, 50 iqra")
    - GPS coordinate extraction from Google Maps URLs
    - Template download with sample data
  - **Quantity Approval System**: Separate tracking for requested vs approved quantities
    - Edit modal for adjusting quantities after approval
    - Visual comparison in public tracking (side-by-side view)
    - Percentage change calculation
    - Admin notes for quantity adjustments
  - **Enhanced Delete**: Delete allowed for all statuses except "completed"
  - **Validation**: Cannot process to shipment without approved quantities when edited
  - **Dashboard Integration**: All stats use approved quantities with fallback to requested
- **WhatsApp Notification System**: Integrated WhatsApp API for automated messaging
  - Text message sending with template support
  - Media message support (images, documents, audio, video)
  - Background connection testing and performance optimization
  - Bulk messaging capabilities
  - Message status tracking and retry functionality
  - **Development Note**: Media messages require publicly accessible URLs
    - Use ngrok for localhost development: `ngrok http 8000`
    - Auto-upload to temporary services for testing
    - Run `php artisan whatsapp:setup-ngrok` for easy setup
- **Indonesia Regional Address Form**: Cascading dropdown form using emsifa API
  - Province -> Regency/City -> District -> Village hierarchy
  - Automatic address composition from components
  - GPS coordinate picker for mapping visualization
  - Caching system to optimize API calls
  - Validation and error handling

### Database Design
- Uses standard Laravel migrations in `database/migrations/`
- Comprehensive seeder system for initial data
- Status flow: Pending → Processing → Shipped → Delivered → Completed
- Tracking history for audit trails

### File Storage Structure
- **certificates/**: Generated PDF certificates
- **qr-codes/**: Generated QR code images  
- **dokumentasi/**: Documentation photos
- **mushaf-requests/**: Files from public requests
- **certificate-templates/**: Template images for certificates

## Important Notes

- The project uses Inertia.js for SPA-like experience with server-side routing
- QR codes contain tracking URLs for public verification
- Certificate generation uses DOMPDF with custom templates
- Batch system allows processing multiple shipments simultaneously
- Comprehensive documentation available in `docs/` directory
- Development scripts available in `scripts/` directory for maintenance tasks

## Warehouse Packing System

### Overview
Daily task-based packing system for warehouse staff with automatic assignment, progress tracking, and performance monitoring.

### Key Components
- **DailyPackingTask**: 80 mushaf per day per user with carry-over system
- **PackingBox**: 20 mushaf per box (kerdus) with unique codes (KB-YYYYMMDD-XXX)
- **PackingItem**: Individual mushaf tracking within boxes
- **UserPerformance**: Monthly performance tracking and analytics
- **PackingNotification**: Real-time notifications and progress alerts

### Workflows
1. **Manual Target Assignment**: Supervisor assigns daily target (e.g., 80 mushaf + carry-over) to warehouse users
2. **Free-Pick Packing Process**: Staff scan any available QR code → System assigns to their task → Add to current box → Auto-seal when full → Move to next box
3. **Progress Monitoring**: Hourly checks with notifications (25% @ 10AM, 40% @ 12PM, etc.)
4. **Daily Expiration** (23:59): Expire incomplete tasks, calculate carry-over for next day

### Assignment System (NEW)
- **Target-Only Assignment**: Supervisor only sets daily target numbers (no specific item assignment)
- **Free-Pick System**: Warehouse staff can scan any available QR code, system auto-assigns to their daily task
- **Auto-Assignment DISABLED**: Removed automatic daily assignment, supervisor controls all assignments
- **Carry-Over System**: Unfinished work from previous day automatically added to next day's target

### Routes
- `/admin/warehouse/` - Warehouse dashboard (role: gudang, warehouse, super_admin)
- `/admin/warehouse/packing` - QR scanning and packing interface
- `/admin/supervisor/warehouse-monitor` - Supervisor monitoring (role: super_admin, cs, supervisor)

### Commands
- `php artisan packing:daily-assignment` - DISABLED: Use supervisor target assignment instead
- `php artisan packing:expire-tasks` - Expire old tasks
- `php artisan packing:check-progress` - Send progress notifications

### Scheduled Tasks
- Daily expiration at 23:59 (auto-assignment DISABLED)
- Hourly progress checks (08:00-17:00)

### API Endpoints (NEW SYSTEM)
- `POST /admin/supervisor/assign-target` - Assign daily target to warehouse user
  ```json
  {
    "user_id": 123,
    "target": 80
  }
  ```

## Configuration Files

- **Vite Config**: Frontend build configuration with Svelte support
- **Laravel Config**: Standard Laravel configuration in `config/`
- **Certificate Config** (`config/certificate.php`): Certificate generation settings

## Development Best Practices

- **Version Control**:
  - Commit setiap perubahan major dan beri message yang sesuai

When working with this codebase, always check existing patterns in the relevant directories and follow the established conventions for models, controllers, and Svelte components.

## WhatsApp API Integration

### StarSender WhatsApp API Details
- Webhook integration for real-time message reception
- Multiple webhook types supported:
  - Basic Webhook: Receive simple message data
  - Webhook with Device Data: Get device-specific information
  - Webhook Media Add-On: Receive media files (audio, image, document, video)
- PHP code for webhook processing:
  ```php
  $json = file_get_contents('php://input');
  $data = json_decode($json);
  ```
- Key API Endpoints:
  - Device Details
  - Contact Management
  - Message Sending
  - Number Verification
- Features:
  - Real-time message tracking
  - Media message support
  - Flexible message scheduling
  - Bulk messaging capabilities

### StarSender API Integration Details
- **HTTP REQUEST**
  - **Endpoint**: `POST https://api.starsender.online/api/send`
  - **Header Parameters**:
    - `Authorization`: Device API key (obtained from device menu)
  - **Body Parameters**:
    - `messageType`: Message type definition (text / media)
    - `to`: Destination phone number (can start with 0 or country code)
    - `body`: Message to be sent
    - `file`: URL of file to be sent
    - `delay`: Message delay in seconds
    - `schedule`: Message sending schedule in unix milliseconds
  - **PHP Implementation Example**:
    ```php
    $curl = curl_init();

    $pesan = [
      "messageType" => "text",
      "to" => "08123456789",
      "body" => "Your Message",
      "file" => "https://file-url.com",
      "delay" => 10,
      "schedule" => 1665408510000
    ];

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.starsender.online/api/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode($pesan),
      CURLOPT_HTTPHEADER => array(
        'Content-Type:application/json',
        'Authorization: YOUR API KEY'
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    echo $response;
    ```
  - **API Response**:
    ```json
    {
        "success": true,
        "data": {},
        "message": "Success sent message"
    }
    ```

## Webhook Integration

### Webhook Overview
- **Webhook dengan Device Data**:
  - Dengan webhook anda bisa menerima data pesan masuk ke website anda secara realtime
  - Untuk mengolah data webhook anda dapat menggunakan kode berikut:

```php
<?php
$json = file_get_contents('php://input');
$data = json_decode($json);
echo $data->device; // nama device - nomor hp device
echo $data->message; // isi pesan
echo $data->from; // dari
echo $data->timestamp; // timestamp
```

When working with WhatsApp API, ensure:
- Proper error handling
- Secure API key management
- Optimized webhook processing
- Compliance with WhatsApp Business API guidelines

## API Message Details

### Detail Pesan
API untuk mengambil detail pesan.

#### HTTP REQUEST
- **Endpoint**: `GET https://api.starsender.online/api/messages/{id}`

#### Header Parameters
| Parameter | Default | Description |
|-----------|---------|-------------|
| Authorization | true | Account API key, anda bisa dapatkan di menu profile |

#### Query Parameters
| Parameter | Default | Description |
|-----------|---------|-------------|
| ID | true | ID Pesan |

#### Implementasi dengan PHP
```php
<?php

$curl = curl_init();

$idPesan = 10

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.starsender.online/api/messages/'.$idPesan,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'Content-Type:application/json',
    'Authorization: YOUR API KEY'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
```

#### Respon API
```json
{
    "success": true,
    "data": {},
    "message": "Success to get message detail"
}
```

## WhatsApp Contact Management

### Membuat Kontak
API untuk membuat kontak.

#### HTTP REQUEST
- **Endpoint**: `POST https://api.starsender.online/api/contacts`

#### Header Parameters
| Parameter | Default | Description |
|-----------|---------|-------------|
| Authorization | true | Account API key, anda bisa dapatkan di menu profile |

#### Body Parameters
| Parameter | Default | Description |
|-----------|---------|-------------|
| Name | true | Nama kontak |
| Number | true | Nomor kontak whatsapp |
| Variabel[] | true | Variabel kontak, variabel bersifat opsional. pastikan variabel berbentuk array |
| GroupId | false | ID Group, anda bisa dapatkan di menu Group Kontak -> Edit |

#### Implementasi dengan PHP
```php
<?php

$curl = curl_init();

$data = [
  "name" => "Budi",
  "number" => "08123456789",
  "variabel" => [
    "Variabel 1",
    "Variabel 2",
  ], //OPSIONAL
  "group_id" => 1 //OPSIONAL
];

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.starsender.online/api/contacts',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode($pesan),
  CURLOPT_HTTPHEADER => array(
    'Content-Type:application/json',
    'Authorization: YOUR API KEY'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
```

#### Respon API
```json
{
    "success": true,
    "data": {},
    "message": "Contact created"
}
```

## Account API Key

- **API Key**: Configuration stored in environment variables
  - Set `WHATSAPP_API_KEY` in your `.env` file
  - Note: Never commit API keys to version control
  - Used for authenticating StarSender WhatsApp API requests