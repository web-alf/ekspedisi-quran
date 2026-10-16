# 🎨 Certificate Template Feature - Complete Guide

## 🚀 Enhanced Certificate System

Sistem sertifikat telah diupgrade dengan fitur **Template Upload** menggunakan file PNG dan **positioning coordinates (X,Y)** untuk data dinamis.

### ✨ **New Features:**
- 📁 **Upload Template PNG** - Upload template sertifikat Anda sendiri
- 📍 **X,Y Positioning** - Atur posisi nama wakif, jumlah mushaf, dll dengan koordinat
- 🎨 **Visual Template Editor** - Interface untuk mengatur posisi field
- 📱 **Template Management** - CRUD lengkap untuk template
- 🔄 **Multiple Templates** - Support multiple template dengan set default
- 🖼️ **High Quality PDF** - Generate PDF dari PNG template

---

## 🛠️ **Installation Guide**

### **Method 1: Auto Install (Recommended)**
```bash
# Run automated installation script
./install-certificate-templates.sh
```

### **Method 2: Manual Installation**

#### **1. Install Dependencies**
```bash
# Install DomPDF (if not installed)
composer require barryvdh/laravel-dompdf

# Install PHP GD Extension (required for image processing)
# Ubuntu/Debian:
sudo apt-get install php-gd

# CentOS/RHEL:
sudo yum install php-gd

# macOS: Already included
```

#### **2. Run Migrations**
```bash
php artisan migrate
```

#### **3. Create Storage Directories**
```bash
mkdir -p storage/app/certificate-templates
mkdir -p storage/app/certificates
mkdir -p storage/app/temp
mkdir -p storage/fonts

chmod -R 775 storage/app/certificate-templates
chmod -R 775 storage/app/certificates
chmod -R 775 storage/app/temp
chmod -R 775 storage/fonts
```

#### **4. Build Frontend**
```bash
npm run build
```

---

## 🎯 **How to Use**

### **Step 1: Create Template**
1. **Prepare PNG Template**
   - Recommended size: 1200x800px (A4 landscape) 
   - High resolution untuk print quality
   - Background design sesuai kebutuhan

2. **Upload Template**
   ```
   URL: /admin/certificate-templates/create
   ```
   - Upload file PNG
   - Set nama template
   - Atur posisi field menggunakan koordinat X,Y

3. **Configure Field Positions**
   - **wakif_name**: Nama donatur
   - **mushaf_count**: Jumlah mushaf (otomatis + "eksemplar")
   - **mushaf_type**: Jenis mushaf (A5, A6, dll)
   - **donation_date**: Tanggal wakaf
   - **recipient_name**: Nama penerima
   - **recipient_address**: Alamat tujuan
   - **certificate_number**: Nomor sertifikat (auto-generated)
   - **generated_date**: Tanggal generate
   - **organization_name**: Nama organisasi

### **Step 2: Generate Certificate**
1. Go to `/admin/pengiriman`
2. Click **purple certificate icon** 📄
3. PDF akan otomatis ter-generate dan download

### **Step 3: Template Management**
```
URL: /admin/certificate-templates
```
- View all templates
- Set default template
- Edit field positions
- Toggle active/inactive
- Delete unused templates

---

## 🔧 **Field Positioning Guide**

### **Coordinate System:**
- **X**: Horizontal position (0 = left edge)
- **Y**: Vertical position (0 = top edge)
- **Font Size**: 8-72px
- **Color**: Hex color code (#000000)

### **Example Positions:**
```json
{
    "wakif_name": {
        "x": 400,
        "y": 300,
        "font_size": 24,
        "color": "#2d5016"
    },
    "mushaf_count": {
        "x": 300,
        "y": 400,
        "font_size": 18,
        "color": "#000000"
    },
    "certificate_number": {
        "x": 100,
        "y": 100,
        "font_size": 12,
        "color": "#666666"
    }
}
```

### **Tips untuk Positioning:**
1. **Test dengan data sample** untuk memastikan posisi tepat
2. **Gunakan font size yang sesuai** dengan space di template
3. **Pilih warna kontras** dengan background template
4. **Preview sebelum finalize** positioning

---

## 📊 **Database Schema**

### **New Tables:**

#### **certificate_templates**
```sql
- id (primary key)
- name (template name)
- slug (url-friendly name)
- description (optional)
- template_file_path (PNG file path)
- field_positions (JSON coordinates)
- width, height (image dimensions)
- is_active, is_default (status flags)
- created_by (user who created)
- timestamps
```

#### **Updated sertifikat table**
```sql
+ template_id (foreign key to certificate_templates)
```

---

## 🎨 **Template Design Guidelines**

### **Recommended Specifications:**
- **Size**: 1200x800px (A4 landscape) atau 800x1200px (portrait)
- **Resolution**: 300 DPI untuk print quality
- **Format**: PNG dengan background transparan atau solid
- **File Size**: Maksimal 10MB

### **Design Tips:**
1. **Leave space** untuk text fields yang akan diisi dinamis
2. **Use contrasting colors** untuk background dan text
3. **Consider print margins** (minimal 20px dari edge)
4. **Test readability** pada berbagai ukuran font

### **Field Space Planning:**
```
Template Layout (1200x800):
┌─────────────────────────────────┐
│ Logo/Header        Cert# (1100,50) │
│                                 │
│        SERTIFIKAT WAKAF         │
│      AL-QURAN AL-KARIM          │
│                                 │
│    Nama Wakif (400,300)         │
│  Jumlah: X eksemplar (300,400)  │
│   Tanggal: DD/MM/YYYY (200,500) │
│                                 │
│     Penerima: Name (300,550)    │
│    Generated: Date (600,650)    │
└─────────────────────────────────┘
```

---

## 🔄 **API Endpoints**

### **Template Management:**
```php
GET    /admin/certificate-templates              // List templates
GET    /admin/certificate-templates/create       // Create form
POST   /admin/certificate-templates              // Store template
GET    /admin/certificate-templates/{id}         // Show template
GET    /admin/certificate-templates/{id}/edit    // Edit form
PUT    /admin/certificate-templates/{id}         // Update template
DELETE /admin/certificate-templates/{id}         // Delete template
GET    /admin/certificate-templates/{id}/preview // Preview PNG
PATCH  /admin/certificate-templates/{id}/set-default // Set default
```

### **Certificate Generation:**
```php
POST /admin/certificates/generate/{pengiriman}   // Generate with template
{
    "template_id": 1,          // Optional: specific template ID
    "regenerate": false        // Optional: force regenerate
}
```

---

## 🚨 **Troubleshooting**

### **Common Issues:**

#### **1. "GD extension not found"**
```bash
# Install PHP GD extension
sudo apt-get install php-gd    # Ubuntu/Debian
sudo yum install php-gd        # CentOS/RHEL
brew install php-gd            # macOS

# Restart web server
sudo service apache2 restart   # Apache
sudo service nginx restart     # Nginx
```

#### **2. "Permission denied on template upload"**
```bash
# Fix storage permissions
chmod -R 775 storage/app/certificate-templates
chown -R www-data:www-data storage/app/certificate-templates
```

#### **3. "Template file not found"**
- Check file path di database
- Verify file exists di storage/app/certificate-templates/
- Check file permissions

#### **4. "Text not appearing on certificate"**
- Verify field positions tidak di luar bounds image
- Check font size tidak terlalu kecil
- Verify color contrast dengan background

#### **5. "PDF generation timeout"**
```bash
# Increase PHP limits
echo "max_execution_time = 120" >> php.ini
echo "memory_limit = 256M" >> php.ini
```

### **Debug Commands:**
```bash
# Check PHP extensions
php -m | grep gd

# Test template generation
php artisan certificate:test

# Check storage
ls -la storage/app/certificate-templates/

# View logs
tail -f storage/logs/laravel.log
```

---

## 📋 **Migration from Old System**

Jika sudah ada sertifikat dengan template HTML lama:

### **Option 1: Keep Both Systems**
- Template HTML tetap bisa digunakan untuk sertifikat lama
- Template PNG untuk sertifikat baru

### **Option 2: Migrate to PNG Templates**
```bash
# Backup existing certificates
cp -r storage/app/certificates storage/app/certificates-backup

# Create default PNG template
# Upload template melalui /admin/certificate-templates/create

# Regenerate certificates with new template
# Gunakan bulk regenerate function
```

---

## 🎉 **Feature Complete!**

### **✅ Implemented Features:**
- [x] PNG template upload
- [x] X,Y coordinate positioning  
- [x] Visual template editor
- [x] Multiple template support
- [x] Template management CRUD
- [x] High-quality PDF generation
- [x] Font size and color customization
- [x] Default template system
- [x] Template preview
- [x] Database relationships
- [x] Error handling
- [x] Installation scripts

### **🚀 Ready to Use:**
1. **Install dependencies**: `./install-certificate-templates.sh`
2. **Create template**: `/admin/certificate-templates/create`
3. **Generate certificate**: Click purple icon di pengiriman list
4. **Manage templates**: `/admin/certificate-templates`

### **🎯 Next Phase (Optional):**
- Visual drag-and-drop field positioning
- Template preview dengan sample data
- Batch template conversion
- Template sharing/import system

---

**Template sertifikat dengan PNG + X,Y positioning sekarang sudah PRODUCTION READY!** ✨

Upload template PNG Anda, atur posisi field, dan generate sertifikat berkualitas tinggi! 🎨📄
