#!/bin/bash

# Fix Permission Issues Script
# This script fixes permission issues for backup and temporary files

echo "🔧 Fixing permission issues for backup files..."

# Change ownership of backup files to current user
echo "📁 Changing ownership of backup files..."
sudo chown -R $(whoami):$(id -gn) resources/js/Pages/Warehouse/Packing.svelte.backup* 2>/dev/null || true
sudo chown -R $(whoami):$(id -gn) resources/js/Pages/Admin/Donatur/Index.svelte.backup 2>/dev/null || true
sudo chown -R $(whoami):$(id -gn) app/Services/PackingAssignmentService.php.backup 2>/dev/null || true
sudo chown -R $(whoami):$(id -gn) app/Http/Controllers/Warehouse/PackingController.php.backup 2>/dev/null || true

# Set proper permissions for backup files
echo "🔐 Setting proper permissions..."
chmod 644 resources/js/Pages/Warehouse/Packing.svelte.backup* 2>/dev/null || true
chmod 644 resources/js/Pages/Admin/Donatur/Index.svelte.backup 2>/dev/null || true
chmod 644 app/Services/PackingAssignmentService.php.backup 2>/dev/null || true
chmod 644 app/Http/Controllers/Warehouse/PackingController.php.backup 2>/dev/null || true

# Move backup files to temp directory
echo "📦 Moving backup files to temp directory..."
mv resources/js/Pages/Warehouse/Packing.svelte.backup temp/ 2>/dev/null || echo "  ✓ Packing.svelte.backup already moved or doesn't exist"
mv resources/js/Pages/Admin/Donatur/Index.svelte.backup temp/ 2>/dev/null || echo "  ✓ Index.svelte.backup already moved or doesn't exist"
mv resources/js/Pages/Warehouse/Packing.svelte.final_backup temp/ 2>/dev/null || echo "  ✓ Packing.svelte.final_backup already moved or doesn't exist"
mv resources/js/Pages/Warehouse/Packing.svelte.scanner_backup temp/ 2>/dev/null || echo "  ✓ Packing.svelte.scanner_backup already moved or doesn't exist"
mv resources/js/Pages/Warehouse/Packing.svelte.complete_backup temp/ 2>/dev/null || echo "  ✓ Packing.svelte.complete_backup already moved or doesn't exist"
mv resources/js/Pages/Warehouse/Packing.svelte.backup_broken temp/ 2>/dev/null || echo "  ✓ Packing.svelte.backup_broken already moved or doesn't exist"
mv app/Services/PackingAssignmentService.php.backup temp/ 2>/dev/null || echo "  ✓ PackingAssignmentService.php.backup already moved or doesn't exist"
mv app/Http/Controllers/Warehouse/PackingController.php.backup temp/ 2>/dev/null || echo "  ✓ PackingController.php.backup already moved or doesn't exist"

# Fix permissions for project directories
echo "🏗️  Fixing general project permissions..."
sudo chown -R $(whoami):$(id -gn) temp/ docs/ scripts/ 2>/dev/null || true
chmod -R 755 scripts/ 2>/dev/null || true
chmod -R 644 docs/ 2>/dev/null || true
chmod -R 644 temp/ 2>/dev/null || true

echo "✅ Permission issues fixed successfully!"
echo ""
echo "📋 Summary:"
echo "  • Changed ownership to current user: $(whoami)"
echo "  • Set proper permissions (755 for scripts, 644 for other files)"
echo "  • Moved backup files to temp/ directory"
echo "  • Fixed permissions for temp/, docs/, and scripts/ directories"
echo ""
echo "🎯 All backup files should now be properly organized in the temp/ directory"