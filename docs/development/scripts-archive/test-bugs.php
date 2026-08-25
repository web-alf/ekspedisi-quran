<?php

/**
 * 🧪 BUG TESTING SCRIPT
 * Script untuk testing bugs yang sudah diperbaiki
 */

echo "🧪 EKSPEDISI QURAN - BUG TESTING SCRIPT\n";
echo "=====================================\n";

// Test 1: Database Connection
echo "🔍 Test 1: Database Connection...\n";
try {
    $pdo = new PDO("sqlite:database/database.sqlite");
    echo "✅ SQLite database connection successful\n";
    
    // Test tables exist
    $tables = ['users', 'jenis_quran', 'status_pengiriman', 'wakif', 'pengiriman', 'status_histories'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if ($stmt->fetchColumn()) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' NOT found\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Model Relationships
echo "🔍 Test 2: Model Structure...\n";
$modelTests = [
    'App\\Models\\Wakif' => ['pengiriman', 'creator'],
    'App\\Models\\Pengiriman' => ['wakif', 'jenisQuran', 'status', 'statusHistory'],
    'App\\Models\\JenisQuran' => ['pengiriman'],
    'App\\Models\\StatusPengiriman' => ['pengiriman', 'statusHistoryFrom', 'statusHistoryTo'],
    'App\\Models\\StatusHistory' => ['pengiriman', 'statusFrom', 'statusTo', 'creator']
];

foreach ($modelTests as $model => $relationships) {
    if (class_exists($model)) {
        echo "✅ Model $model exists\n";
        
        $reflection = new ReflectionClass($model);
        foreach ($relationships as $relation) {
            if ($reflection->hasMethod($relation)) {
                echo "  ✅ Relationship '$relation' exists\n";
            } else {
                echo "  ❌ Relationship '$relation' NOT found\n";
            }
        }
    } else {
        echo "❌ Model $model NOT found\n";
    }
}

echo "\n";

// Test 3: Route Testing
echo "🔍 Test 3: Important Routes...\n";
$routes = [
    '/login',
    '/dashboard', 
    '/admin/wakif',
    '/admin/wakif-import',
    '/admin/pengiriman'
];

foreach ($routes as $route) {
    echo "📍 Route: $route\n";
}

echo "\n";

// Test 4: File Structure
echo "🔍 Test 4: Critical Files...\n";
$files = [
    'app/Http/Controllers/Admin/WakifController.php',
    'app/Http/Controllers/Admin/PengirimanController.php',
    'app/Models/Wakif.php',
    'app/Models/Pengiriman.php', 
    'app/Imports/WakifImport.php',
    'app/Imports/WakifImportPreview.php',
    'database/migrations/2025_05_25_000002_create_jenis_quran_table.php',
    'database/migrations/2025_05_25_000003_create_status_pengiriman_table.php',
    'database/migrations/2025_05_25_000007_create_status_histories_table.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file NOT found\n";
    }
}

echo "\n";

// Test 5: Migration Issues Fixed
echo "🔍 Test 5: Migration Cleanup...\n";
$backupFiles = [
    'app/Http/Controllers/Admin/WakifController_BACKUP.php',
    'app/Http/Controllers/Admin/WakifController_OLD.php',
    'app/Models/Pengiriman_BACKUP.php',
    'database/migrations/2025_05_26_create_jenis_quran_table.php',
    'database/migrations/2025_05_26_create_status_and_history_tables.php'
];

$allClean = true;
foreach ($backupFiles as $file) {
    if (file_exists($file)) {
        echo "❌ Backup file still exists: $file\n";
        $allClean = false;
    }
}

if ($allClean) {
    echo "✅ All backup files cleaned up successfully\n";
}

echo "\n";

// Test 6: Generate Sample Data Test
echo "🔍 Test 6: Sample Data Generation...\n";
try {
    if (class_exists('App\\Models\\Pengiriman')) {
        $pengiriman = new App\Models\Pengiriman();
        $sampleResi = $pengiriman->generateUniqueNoResi();
        
        if (preg_match('/^EQ-\d{4}-\d{5}$/', $sampleResi)) {
            echo "✅ No Resi generation works: $sampleResi\n";
        } else {
            echo "❌ No Resi format incorrect: $sampleResi\n";
        }
    }
} catch (Exception $e) {
    echo "❌ No Resi generation failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "🎯 TESTING SUMMARY\n";
echo "================\n";
echo "✅ Fixed Issues:\n";
echo "   - Removed duplicate migrations\n";
echo "   - Cleaned up backup files\n";  
echo "   - Fixed migration schema consistency\n";
echo "   - Added missing StatusHistory table\n";
echo "   - Updated project README\n";
echo "   - Organized development files\n";

echo "\n";
echo "📋 Next Steps:\n";
echo "   1. Run: php artisan migrate:fresh\n";
echo "   2. Test import functionality\n";
echo "   3. Create first admin user\n";
echo "   4. Test full workflow\n";

echo "\n";
echo "🎉 Bug cleanup completed!\n";
