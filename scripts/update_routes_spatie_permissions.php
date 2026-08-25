<?php

/**
 * 🚀 SPATIE ROUTES PERMISSION UPDATER
 * 
 * Updates all routes to use standardized Spatie permission names
 * Run: php scripts/update_routes_spatie_permissions.php
 */

$routesFile = __DIR__ . '/../routes/web.php';

// Permission name mappings for routes update
$permissionMappings = [
    // Fix API permission
    'permission:api.wilayah.clear_cache' => 'permission:api.wilayah.clear.cache',
    
    // QR permissions (remove duplicates, use unified naming)
    'permission:qr.bulk_operations' => 'permission:qr.bulk.operations',
    
    // Mushaf requests
    'permission:mushaf-requests.create' => 'permission:mushaf.requests.create',
    'permission:mushaf-requests.read' => 'permission:mushaf.requests.read',
    'permission:mushaf-requests.update' => 'permission:mushaf.requests.update',
    'permission:mushaf-requests.delete' => 'permission:mushaf.requests.delete',
    'permission:mushaf-requests.approve' => 'permission:mushaf.requests.approve',
    'permission:mushaf-requests.reject' => 'permission:mushaf.requests.reject',
    'permission:mushaf-requests.process' => 'permission:mushaf.requests.process',
    
    // Wakaf batch
    'permission:wakaf-batch.create' => 'permission:wakaf.batch.create',
    'permission:wakaf-batch.read' => 'permission:wakaf.batch.read',
    'permission:wakaf-batch.update' => 'permission:wakaf.batch.update',
    'permission:wakaf-batch.delete' => 'permission:wakaf.batch.delete',
    
    // Shipments bulk operations
    'permission:shipments.bulk-update' => 'permission:shipments.bulk.update',
    'permission:shipments.update-status' => 'permission:shipments.status.update',
    
    // Settings granular permissions
    'permission:settings.read' => 'permission:settings.general.read',
    'permission:settings.write' => 'permission:settings.general.write',
    'permission:settings.delete' => 'permission:settings.general.write', // Write includes delete for settings
    
    // Add specific settings permissions where needed
    'permission:settings.landing.read' => 'permission:settings.landing.read',
    'permission:settings.landing.write' => 'permission:settings.landing.write',
    'permission:settings.contact.read' => 'permission:settings.contact.read',
    'permission:settings.contact.write' => 'permission:settings.contact.write',
    'permission:settings.social.read' => 'permission:settings.social.read',
    'permission:settings.social.write' => 'permission:settings.social.write',
    'permission:settings.seo.read' => 'permission:settings.seo.read',
    'permission:settings.seo.write' => 'permission:settings.seo.write',
    'permission:settings.legal.read' => 'permission:settings.legal.read',
    'permission:settings.legal.write' => 'permission:settings.legal.write',
    
    // System monitoring
    'permission:system.monitor' => 'permission:system.monitor', // Already correct
];

if (!file_exists($routesFile)) {
    echo "❌ Routes file not found: {$routesFile}\n";
    exit(1);
}

$content = file_get_contents($routesFile);
$originalContent = $content;

echo "🔄 Starting routes permission standardization...\n";

// Apply permission mappings
foreach ($permissionMappings as $old => $new) {
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        echo "✅ Updated: {$old} → {$new}\n";
    }
}

// Additional specific updates for route patterns

// Update warehouse routes to use standardized permissions
$warehouseUpdates = [
    // Remove warehouse.qr.* duplicates, use qr.* instead
    "'permission:warehouse.qr.generate'" => "'permission:qr.generate'",
    "'permission:warehouse.qr.scan'" => "'permission:qr.scan'",
    "'permission:warehouse.qr.verify'" => "'permission:qr.verify'",
    "'permission:warehouse.qr.bulk_generate'" => "'permission:qr.bulk.generate'",
    
    // Supervisor permissions
    'permission:supervisor.warehouse.monitor' => 'permission:supervisor.warehouse.monitor', // Already correct
];

foreach ($warehouseUpdates as $old => $new) {
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        echo "🔄 Warehouse: {$old} → {$new}\n";
    }
}

// Write updated content
if ($content !== $originalContent) {
    file_put_contents($routesFile, $content);
    echo "✅ Routes file updated successfully!\n";
    echo "📁 Updated: {$routesFile}\n";
} else {
    echo "ℹ️ No changes needed in routes file.\n";
}

echo "\n🚀 Routes permission standardization completed!\n";
echo "🔍 Next steps:\n";
echo "   1. Run migration: php artisan migrate\n";
echo "   2. Run seeder: php artisan db:seed --class=StandardizedRolePermissionSeeder\n";
echo "   3. Clear cache: php artisan permission:cache-reset\n";