#!/bin/bash

echo "🚀 Deploying Ekspedisi Quran to Production..."
echo "Domain: ekspedisi-quran.aguss.id"
echo "================================="

# 1. Copy production environment
echo "📋 Setting up production environment..."
cp .env.production .env

# 2. Generate new application key if needed
echo "🔑 Generating application key..."
php artisan key:generate --force

# 3. Clear and optimize caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 4. Run migrations (if needed)
echo "🗄️ Running migrations..."
php artisan migrate --force

# 5. Create storage symlink
echo "🔗 Creating storage symlink..."
php artisan storage:link

# 6. Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Build frontend assets
echo "🎨 Building frontend assets..."
npm run build

# 8. Set proper permissions
echo "🔒 Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod 644 .env

# 9. Test WhatsApp configuration
echo "📱 Testing WhatsApp configuration..."
php artisan tinker --execute="
try {
    \$service = new App\Services\WhatsAppService();
    echo 'WhatsApp Available: ' . (\$service->isAvailable() ? 'YES' : 'NO') . PHP_EOL;
    echo 'Base URL: ' . config('app.url') . PHP_EOL;
    echo 'Media Base URL: ' . config('whatsapp.media.base_url', 'NOT SET') . PHP_EOL;
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "✅ Deployment completed!"
echo ""
echo "🌐 URLs untuk StarSender Webhook:"
echo "   - Webhook: https://ekspedisi-quran.aguss.id/webhook/whatsapp"
echo "   - Webhook + Device: https://ekspedisi-quran.aguss.id/webhook/whatsapp/device"
echo "   - Test: https://ekspedisi-quran.aguss.id/webhook/whatsapp/test"
echo ""
echo "📱 Test Commands:"
echo "   php artisan whatsapp:test-text +6281234567890 'Test production'"
echo "   php artisan whatsapp:test-media +6281234567890 --type=image"
echo "   php artisan whatsapp:test-external +6281234567890"
echo ""