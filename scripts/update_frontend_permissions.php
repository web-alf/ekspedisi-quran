<?php

/**
 * 🚀 SPATIE FRONTEND PERMISSION UPDATER
 * 
 * Updates frontend Svelte components and JS utils to use standardized permissions
 * Run: php scripts/update_frontend_permissions.php
 */

$frontendFiles = [
    __DIR__ . '/../resources/js/utils/permissions.js',
    __DIR__ . '/../resources/js/utils/auth.js',
];

// Permission mappings for frontend
$permissionMappings = [
    // Mushaf requests
    "'mushaf-requests.create'" => "'mushaf.requests.create'",
    "'mushaf-requests.read'" => "'mushaf.requests.read'",
    "'mushaf-requests.update'" => "'mushaf.requests.update'",
    "'mushaf-requests.delete'" => "'mushaf.requests.delete'",
    "'mushaf-requests.approve'" => "'mushaf.requests.approve'",
    "'mushaf-requests.reject'" => "'mushaf.requests.reject'",
    "'mushaf-requests.process'" => "'mushaf.requests.process'",
    
    // Wakaf batch
    "'wakaf-batch.create'" => "'wakaf.batch.create'",
    "'wakaf-batch.read'" => "'wakaf.batch.read'",
    "'wakaf-batch.update'" => "'wakaf.batch.update'",
    "'wakaf-batch.delete'" => "'wakaf.batch.delete'",
    
    // QR permissions - standardize
    "'qr.bulk_operations'" => "'qr.bulk.operations'",
    "'warehouse.qr.generate'" => "'qr.generate'",
    "'warehouse.qr.scan'" => "'qr.scan'",
    "'warehouse.qr.verify'" => "'qr.verify'",
    
    // Warehouse permissions
    "'warehouse.box.update_sealed'" => "'warehouse.boxes.update.sealed'",
    "'warehouse.box.update_any'" => "'warehouse.boxes.update'",
    
    // Settings permissions
    "'settings.read'" => "'settings.general.read'",
    "'settings.write'" => "'settings.general.write'",
    "'settings.delete'" => "'settings.general.write'",
    
    // Shipments
    "'shipments.bulk-update'" => "'shipments.bulk.update'",
    "'shipments.update-status'" => "'shipments.status.update'",
];

$totalUpdates = 0;
$updatedFiles = [];

foreach ($frontendFiles as $filePath) {
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $fileUpdates = 0;
    
    // Apply permission mappings
    foreach ($permissionMappings as $old => $new) {
        $count = 0;
        $content = str_replace($old, $new, $content, $count);
        if ($count > 0) {
            $fileName = basename($filePath);
            echo "✅ {$fileName}: {$old} → {$new} ({$count}x)\n";
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

// Find and update Svelte components
$svelteDirectories = [
    __DIR__ . '/../resources/js/Pages',
    __DIR__ . '/../resources/js/Components',
];

foreach ($svelteDirectories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'svelte') {
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
                echo "🎨 {$file->getFilename()}: {$old} → {$new} ({$count}x)\n";
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

echo "\n🚀 Frontend permission standardization completed!\n";
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
echo "   4. Build frontend: npm run build\n";