#!/bin/bash

echo "🎨 Installing Certificate Template Feature..."

# 1. Install additional PHP extensions for image processing
echo "📦 Checking PHP Extensions..."

# Check if GD extension is installed
if ! php -m | grep -q "gd"; then
    echo "❌ PHP GD extension not found. Please install php-gd:"
    echo "   Ubuntu/Debian: sudo apt-get install php-gd"
    echo "   CentOS/RHEL: sudo yum install php-gd"
    echo "   macOS: Already included in PHP"
    exit 1
else
    echo "✅ PHP GD extension found"
fi

# Check if Imagick extension is available (optional but recommended)
if php -m | grep -q "imagick"; then
    echo "✅ PHP Imagick extension found"
else
    echo "⚠️  PHP Imagick extension not found (optional, but recommended for better image quality)"
fi

# 2. Create storage directories
echo "📁 Creating storage directories..."
mkdir -p storage/app/certificate-templates
mkdir -p storage/app/certificates
mkdir -p storage/app/temp
mkdir -p storage/fonts

# Set permissions
chmod -R 775 storage/app/certificate-templates
chmod -R 775 storage/app/certificates
chmod -R 775 storage/app/temp
chmod -R 775 storage/fonts

echo "✅ Storage directories created"

# 3. Run migrations
echo "🗃️ Running database migrations..."
php artisan migrate

echo "✅ Migrations completed"

# 4. Download default fonts (optional)
echo "🔤 Setting up fonts..."

# Download Arial font if not exists
if [ ! -f "storage/fonts/arial.ttf" ]; then
    echo "📥 Downloading Arial font..."
    # You can add font download logic here or provide instructions
    echo "⚠️  Please manually add arial.ttf to storage/fonts/ for better text rendering"
    echo "   You can download it from: https://fonts.google.com/download/arial"
fi

# 5. Create sample template (optional)
echo "🖼️ Creating sample template..."

# Check if we can create a sample template
if command -v convert >/dev/null 2>&1; then
    echo "✅ ImageMagick found, creating sample template..."
    
    # Create a sample certificate template using ImageMagick
    convert -size 1200x800 xc:white \
        -fill "#2d5016" -pointsize 60 -gravity north -annotate +0+50 "SERTIFIKAT WAKAF" \
        -fill "#d4af37" -pointsize 40 -gravity north -annotate +0+150 "AL-QURAN AL-KARIM" \
        -fill black -pointsize 20 -gravity center -annotate +0-100 "Template akan diisi dengan data dinamis" \
        -fill "#2d5016" -strokewidth 5 -stroke "#d4af37" -draw "rectangle 20,20 1180,780" \
        storage/app/certificate-templates/sample-template.png
    
    echo "✅ Sample template created at storage/app/certificate-templates/sample-template.png"
else
    echo "⚠️  ImageMagick not found. Please manually create template PNG files"
    echo "   Template should be 1200x800px or similar certificate dimensions"
fi

# 6. Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan view:clear
php artisan route:clear

echo "✅ Caches cleared"

# 7. Build frontend assets
echo "🏗️ Building frontend assets..."
if command -v npm >/dev/null 2>&1; then
    npm run build
    echo "✅ Frontend assets built"
else
    echo "⚠️  npm not found. Please run 'npm run build' manually"
fi

echo ""
echo "🎉 Certificate Template Feature Installation Complete!"
echo ""
echo "📋 Next Steps:"
echo "1. Visit /admin/certificate-templates to manage templates"
echo "2. Upload your PNG template files"
echo "3. Configure field positions using X,Y coordinates"
echo "4. Test certificate generation from /admin/pengiriman"
echo ""
echo "📖 Documentation:"
echo "- Template management: /admin/certificate-templates"
echo "- Create template: /admin/certificate-templates/create"
echo "- Generate certificate: Click purple icon in pengiriman list"
echo ""
echo "🔧 Troubleshooting:"
echo "- Check PHP GD extension: php -m | grep gd"
echo "- Check storage permissions: ls -la storage/app/"
echo "- Check logs: tail -f storage/logs/laravel.log"
echo ""
echo "✨ Happy certificate generating with custom templates!"
