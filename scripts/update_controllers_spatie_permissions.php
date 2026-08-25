<?php

/**
 * 🚀 SPATIE CONTROLLERS PERMISSION UPDATER
 * 
 * Updates all controllers to use standardized Spatie permission names
 * Run: php scripts/update_controllers_spatie_permissions.php
 */

// Find all controller files
$controllerDirectories = [
    __DIR__ . '/../app/Http/Controllers/Admin',
    __DIR__ . '/../app/Http/Controllers/Warehouse',
    __DIR__ . '/../app/Http/Controllers/Supervisor',
];

// Permission name mappings for controllers
$permissionMappings = [
    // Mushaf requests
    "can('mushaf-requests.create')" => "can('mushaf.requests.create')",
    "can('mushaf-requests.read')" => "can('mushaf.requests.read')",
    "can('mushaf-requests.update')" => "can('mushaf.requests.update')",
    "can('mushaf-requests.delete')" => "can('mushaf.requests.delete')",
    "can('mushaf-requests.approve')" => "can('mushaf.requests.approve')",
    "can('mushaf-requests.reject')" => "can('mushaf.requests.reject')",
    "can('mushaf-requests.process')" => "can('mushaf.requests.process')",
    
    // Wakaf batch
    "can('wakaf-batch.create')" => "can('wakaf.batch.create')",
    "can('wakaf-batch.read')" => "can('wakaf.batch.read')",
    "can('wakaf-batch.update')" => "can('wakaf.batch.update')",
    "can('wakaf-batch.delete')" => "can('wakaf.batch.delete')",
    
    // QR permissions - remove warehouse.qr duplicates
    "can('warehouse.qr.generate')" => "can('qr.generate')",
    "can('warehouse.qr.scan')" => "can('qr.scan')",
    "can('warehouse.qr.verify')" => "can('qr.verify')",
    "can('warehouse.qr.bulk_generate')" => "can('qr.bulk.generate')",
    
    // Standardize bulk operations
    "can('qr.bulk_operations')" => "can('qr.bulk.operations')",
    "can('shipments.bulk-update')" => "can('shipments.bulk.update')",
    "can('shipments.update-status')" => "can('shipments.status.update')",
    
    // Warehouse boxes
    "can('warehouse.box.update_sealed')" => "can('warehouse.boxes.update.sealed')",
    "can('warehouse.box.update_any')" => "can('warehouse.boxes.update')",
    
    // Settings permissions
    "can('settings.read')" => "can('settings.general.read')",
    "can('settings.write')" => "can('settings.general.write')",
    "can('settings.delete')" => "can('settings.general.write')",
];

// Additional custom logic replacements
$customLogicReplacements = [
    // Remove hasRole usage in favor of permission checks
    "hasRole('warehouse')" => "can('warehouse.dashboard')",
    "hasRole('supervisor')" => "can('supervisor.warehouse.monitor')",
    "hasRole('super-admin')" => "can('system.monitor')",
    "hasRole('customer-service')" => "can('donatur.read')",
    "hasRole('courier')" => "can('shipments.read')",
    
    // Role-based conditionals to permission-based
    '$user->role === "warehouse"' => '$user->can("warehouse.dashboard")',
    '$user->role === "supervisor"' => '$user->can("supervisor.warehouse.monitor")',
    '$user->role === "super-admin"' => '$user->can("system.monitor")',
    '$user->role === "customer-service"' => '$user->can("donatur.read")',
    '$user->role === "courier"' => '$user->can("shipments.read")',
    
    // Array role checks
    "in_array(\$user->role, ['warehouse', 'supervisor'])" => "(\$user->can('warehouse.dashboard') || \$user->can('supervisor.warehouse.monitor'))",
    "in_array(\$user->role, ['super-admin', 'supervisor'])" => "(\$user->can('system.monitor') || \$user->can('supervisor.warehouse.monitor'))",
];

$totalUpdates = 0;
$updatedFiles = [];

foreach ($controllerDirectories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        
        $filePath = $file->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $fileUpdates = 0;
        
        // Apply permission mappings
        foreach ($permissionMappings as $old => $new) {
            $count = 0;
            $content = str_replace($old, $new, $content, $count);
            if ($count > 0) {
                echo "✅ {$file->getFilename()}: {$old} → {$new} ({$count}x)\n";
                $fileUpdates += $count;
            }
        }
        
        // Apply custom logic replacements
        foreach ($customLogicReplacements as $old => $new) {
            $count = 0;
            $content = str_replace($old, $new, $content, $count);
            if ($count > 0) {
                echo "🔄 {$file->getFilename()}: {$old} → {$new} ({$count}x)\n";
                $fileUpdates += $count;
            }
        }
        
        // Write back if changed
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $updatedFiles[] = $filePath;
            $totalUpdates += $fileUpdates;
        }
    }
}

// Also check specific policy files
$policyDir = __DIR__ . '/../app/Policies';
if (is_dir($policyDir)) {
    $files = glob($policyDir . '/*.php');
    
    foreach ($files as $filePath) {
        $content = file_get_contents($filePath);
        $originalContent = $content;
        $fileUpdates = 0;
        
        // Apply permission mappings to policies
        foreach ($permissionMappings as $old => $new) {
            $count = 0;
            $content = str_replace($old, $new, $content, $count);
            if ($count > 0) {
                $fileName = basename($filePath);
                echo "📋 {$fileName}: {$old} → {$new} ({$count}x)\n";
                $fileUpdates += $count;
            }
        }
        
        // Write back if changed
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $updatedFiles[] = $filePath;
            $totalUpdates += $fileUpdates;
        }
    }
}

echo "\n🚀 Controllers & Policies permission standardization completed!\n";
echo "📊 Summary:\n";
echo "   • Total updates: {$totalUpdates}\n";
echo "   • Files modified: " . count($updatedFiles) . "\n";

if (!empty($updatedFiles)) {
    echo "\n📁 Updated files:\n";
    foreach ($updatedFiles as $file) {
        $relativePath = str_replace(__DIR__ . '/../', '', $file);
        echo "   • {$relativePath}\n";
    }
}

echo "\n🔍 Next steps:\n";
echo "   1. Run migration: php artisan migrate\n";
echo "   2. Run seeder: php artisan db:seed --class=StandardizedRolePermissionSeeder\n";
echo "   3. Clear cache: php artisan permission:cache-reset\n";
echo "   4. Test key functionality to ensure permissions work correctly\n";