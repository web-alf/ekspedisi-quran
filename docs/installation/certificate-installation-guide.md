# 📄 Certificate Generation Feature - Installation Guide

## 🚀 Quick Installation

### 1. Install Dependencies
```bash
# Install DomPDF
composer require barryvdh/laravel-dompdf

# Publish config (optional)
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### 2. Run Database Migration
```bash
# The sertifikat table should already exist from previous migrations
php artisan migrate

# If you need to create the migration manually:
# php artisan make:migration create_sertifikat_table
```

### 3. Create Storage Directories
```bash
# Create certificate storage directory
mkdir -p storage/app/certificates

# Ensure proper permissions
chmod -R 775 storage/app/certificates
```

### 4. Environment Configuration
Add to your `.env` file:
```env
# Certificate Settings
CERTIFICATE_AUTO_GENERATE=true
CERTIFICATE_TEMPLATE=default
CERTIFICATE_STORAGE_DISK=local
CERTIFICATE_ORG_NAME="Ekspedisi Quran"
CERTIFICATE_ORG_ADDRESS=""
CERTIFICATE_ORG_PHONE=""
```

### 5. Test Certificate Generation
```bash
# Test with first available pengiriman
php artisan certificate:test

# Test with specific pengiriman ID
php artisan certificate:test 1
```

## 🎯 How to Use

### Admin Interface

1. **Generate Single Certificate**
   - Go to Pengiriman list
   - Click purple certificate icon
   - Certificate will be generated and downloadable

2. **Bulk Generate Certificates**
   - Select multiple pengiriman
   - Use bulk actions menu
   - Generate certificates for selected items

3. **Download Certificate**
   - Click the certificate icon (purple) if already generated
   - PDF will download automatically

### API Usage

```php
// Generate certificate
POST /admin/certificates/generate/{pengiriman_id}
{
    "template": "default",
    "regenerate": false
}

// Download certificate
GET /admin/certificates/download/{pengiriman_id}

// View certificate inline
GET /admin/certificates/view/{pengiriman_id}
```

## 🏗️ Files Created/Modified

### New Files:
- `app/Models/Sertifikat.php` - Certificate model
- `app/Services/CertificateService.php` - Main service class
- `app/Http/Controllers/Admin/CertificateController.php` - Admin controller
- `app/Console/Commands/TestCertificate.php` - Test command
- `resources/views/certificates/template.blade.php` - PDF template
- `config/certificate.php` - Configuration file

### Modified Files:
- `app/Models/Pengiriman.php` - Added sertifikat relationship
- `routes/web.php` - Added certificate routes
- `resources/js/Pages/Admin/Pengiriman/Index.svelte` - Added certificate UI

## 🎨 Template Customization

The certificate template is located at:
`resources/views/certificates/template.blade.php`

### Available Variables:
- `$certificate_number` - Auto-generated certificate number
- `$wakif_name` - Name of the donor
- `$resi_number` - Shipment tracking number
- `$mushaf_count` - Number of Quran copies
- `$mushaf_type` - Type of Quran (A5, A6, etc.)
- `$donation_date` - Date of donation
- `$recipient_name` - Recipient name
- `$recipient_address` - Recipient address
- `$generated_date` - Certificate generation date
- `$organization_name` - Organization name

### CSS Styling:
The template uses inline CSS for PDF compatibility. You can customize:
- Colors and fonts
- Layout and spacing
- Logo and branding
- Paper size and orientation

## 📊 Database Schema

```sql
CREATE TABLE sertifikat (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    pengiriman_id BIGINT NOT NULL,
    nomor_sertifikat VARCHAR(50) UNIQUE NOT NULL,
    template_used VARCHAR(50) DEFAULT 'default',
    file_path VARCHAR(255) NOT NULL,
    generated_by BIGINT NOT NULL,
    generated_at TIMESTAMP NOT NULL,
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pengiriman_id) REFERENCES pengiriman(id) ON DELETE RESTRICT,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

## 🔧 Configuration Options

### Certificate Config (`config/certificate.php`):

```php
return [
    'auto_generate' => true,                    // Auto-generate on status change
    'trigger_status' => ['Selesai', 'Diterima'], // Status that trigger generation
    'template' => 'default',                    // Default template
    'pdf' => [
        'paper_size' => 'a4',                  // Paper size
        'orientation' => 'landscape',           // Orientation
        'dpi' => 150,                          // DPI for images
    ],
    'organization' => [
        'name' => 'Ekspedisi Quran',          // Organization name
        'address' => '',                       // Address
        'phone' => '',                         // Phone
    ],
];
```

## 🚨 Troubleshooting

### Common Issues:

1. **"Class 'Barryvdh\DomPDF\Facade\Pdf' not found"**
   ```bash
   composer require barryvdh/laravel-dompdf
   php artisan config:clear
   ```

2. **Permission denied on storage**
   ```bash
   chmod -R 775 storage/app/certificates
   chown -R www-data:www-data storage/app/certificates
   ```

3. **Template not found**
   - Check `resources/views/certificates/template.blade.php` exists
   - Verify template name in config

4. **PDF generation timeout**
   - Increase PHP max_execution_time
   - Optimize template (reduce images/complexity)

5. **Font issues**
   - Use web-safe fonts (Arial, Times New Roman)
   - For Arabic fonts, install additional font packages

### Debug Commands:

```bash
# Test certificate generation
php artisan certificate:test

# Check storage permissions
ls -la storage/app/

# Clear caches
php artisan config:clear
php artisan view:clear
```

## 🎯 Next Steps

### Phase 1 - Basic Implementation (Current)
- ✅ Certificate generation service
- ✅ PDF template with Islamic design
- ✅ Admin interface integration
- ✅ Download functionality

### Phase 2 - Auto Generation (Coming Soon)
- Event listener for status changes
- Queue jobs for bulk processing
- Auto-generate on "Selesai" status

### Phase 3 - Distribution (Future)
- WhatsApp integration
- Email delivery (optional)
- Public download portal

### Phase 4 - Advanced Features (Future)
- Multiple template options
- Custom template editor
- Certificate verification system
- Analytics and reporting

## 📞 Support

If you encounter any issues:

1. Check the troubleshooting section above
2. Run the test command: `php artisan certificate:test`
3. Check Laravel logs: `tail -f storage/logs/laravel.log`
4. Verify file permissions on storage directory

## 🎉 Congratulations!

Your certificate generation feature is now ready to use! 

- Navigate to `/admin/pengiriman` to start generating certificates
- Look for the purple certificate icon in the action buttons
- Generated certificates will be stored in `storage/app/certificates/`

**Happy certificate generating! 📄✨**
