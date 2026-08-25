#!/bin/bash

# 🧹 PROJECT CLEANUP & MAINTENANCE SCRIPT
# File: scripts/cleanup-maintenance.sh
# Description: Automated cleanup for development files and optimization

echo "🧹 Starting Ekspedisi Quran Project Cleanup..."
echo "================================================"

# Set variables
PROJECT_ROOT=$(pwd)
ARCHIVE_DIR="docs/development/archived_files"
QR_DIR="storage/app/public/qr-codes"
LOG_DIR="storage/logs"
DATE=$(date +%Y%m%d_%H%M%S)

# Create archive directories if not exist
mkdir -p "$ARCHIVE_DIR/old_logs"
mkdir -p "$ARCHIVE_DIR/old_qr_codes"
mkdir -p "$ARCHIVE_DIR/temp_files"

echo "📁 Archive directories ready..."

# 1. CLEANUP ROOT TEST FILES
echo "🗂️  Cleaning root test files..."
TEST_FILES=$(find . -maxdepth 1 -name "test_*.php" -o -name "simple_*.php" -o -name "*_test.php" 2>/dev/null)
if [ -n "$TEST_FILES" ]; then
    echo "   Found test files to archive:"
    echo "$TEST_FILES" | sed 's/^/   - /'
    echo "$TEST_FILES" | xargs -I {} mv {} "$ARCHIVE_DIR/temp_files/"
    echo "   ✅ Test files archived"
else
    echo "   ✅ No test files found in root"
fi

# 2. ROTATE LARGE LOG FILES  
echo "🗂️  Checking log files..."
LOG_FILE="$LOG_DIR/laravel.log"
if [ -f "$LOG_FILE" ]; then
    LOG_SIZE=$(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE" 2>/dev/null)
    if [ "$LOG_SIZE" -gt 1048576 ]; then  # 1MB
        echo "   Log file is large ($(($LOG_SIZE / 1024))KB), archiving..."
        mv "$LOG_FILE" "$ARCHIVE_DIR/old_logs/laravel_$DATE.log"
        echo "   ✅ Log file archived as laravel_$DATE.log"
    else
        echo "   ✅ Log file size OK ($(($LOG_SIZE / 1024))KB)"
    fi
else
    echo "   ✅ No log file found"
fi

# 3. CLEANUP QR DUPLICATES
echo "🗂️  Cleaning QR code duplicates..."
if [ -d "$QR_DIR" ]; then
    # Find QR codes with timestamp pattern (duplicates)
    QR_DUPLICATES=$(find "$QR_DIR" -name "QR-*-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9].png" 2>/dev/null)
    if [ -n "$QR_DUPLICATES" ]; then
        echo "   Found QR duplicates to archive:"
        echo "$QR_DUPLICATES" | sed 's/^/   - /'
        echo "$QR_DUPLICATES" | while read file; do
            if [ -f "$file" ]; then
                basename_file=$(basename "$file")
                mv "$file" "$ARCHIVE_DIR/old_qr_codes/${basename_file}"
            fi
        done
        echo "   ✅ QR duplicates archived"
    else
        echo "   ✅ No QR duplicates found"
    fi
else
    echo "   ✅ QR directory not found"
fi

# 4. CLEANUP BACKUP FILES IN WRONG PLACES
echo "🗂️  Cleaning misplaced backup files..."
BACKUP_FILES=$(find . -path "./vendor" -prune -o -path "./node_modules" -prune -o -path "./docs" -prune -o \( -name "*_BACKUP.*" -o -name "*_OLD.*" -o -name "*.backup" \) -print 2>/dev/null)
if [ -n "$BACKUP_FILES" ]; then
    echo "   Found misplaced backup files:"
    echo "$BACKUP_FILES" | sed 's/^/   - /'
    echo "$BACKUP_FILES" | while read file; do
        if [ -f "$file" ]; then
            basename_file=$(basename "$file")
            mv "$file" "$ARCHIVE_DIR/temp_files/${basename_file}"
        fi
    done
    echo "   ✅ Backup files archived"
else
    echo "   ✅ No misplaced backup files found"
fi

# 5. CLEANUP TEMPORARY DIRECTORIES
echo "🗂️  Cleaning temporary directories..."
TEMP_DIRS=$(find . -path "./vendor" -prune -o -path "./node_modules" -prune -o -type d \( -name "temp" -o -name "tmp" \) -print 2>/dev/null)
if [ -n "$TEMP_DIRS" ]; then
    echo "   Found temporary directories:"
    echo "$TEMP_DIRS" | sed 's/^/   - /'
    echo "$TEMP_DIRS" | while read dir; do
        if [ -d "$dir" ] && [ "$(ls -A $dir)" ]; then
            dir_name=$(basename "$dir")_$(dirname "$dir" | tr '/' '_')_$DATE
            mv "$dir" "$ARCHIVE_DIR/${dir_name}"
        elif [ -d "$dir" ]; then
            rmdir "$dir"  # Remove empty temp directories
        fi
    done
    echo "   ✅ Temporary directories cleaned"
else
    echo "   ✅ No temporary directories found"
fi

# 6. OPTIMIZE STORAGE STATISTICS
echo "📊 Calculating storage optimization..."
ARCHIVE_SIZE=$(du -sh "$ARCHIVE_DIR" 2>/dev/null | cut -f1)
QR_COUNT=$(ls "$QR_DIR" 2>/dev/null | wc -l)
LOG_COUNT=$(ls "$ARCHIVE_DIR/old_logs" 2>/dev/null | wc -l)

echo ""
echo "🎯 CLEANUP SUMMARY"
echo "=================="
echo "📁 Archive folder size: $ARCHIVE_SIZE"
echo "📱 Active QR codes: $QR_COUNT"
echo "📜 Archived logs: $LOG_COUNT"
echo "📅 Cleanup date: $(date)"
echo ""

# 7. VERIFICATION
echo "🔍 Running verification checks..."

# Check if main routes still work (basic syntax check)
if php artisan route:list > /dev/null 2>&1; then
    echo "   ✅ Routes are valid"
else
    echo "   ❌ Routes may have issues"
fi

# Check if migrations are clean
if php artisan migrate:status > /dev/null 2>&1; then
    echo "   ✅ Migrations are valid"
else
    echo "   ❌ Migration issues detected"
fi

echo ""
echo "🎉 CLEANUP COMPLETED SUCCESSFULLY!"
echo "Project is now clean and optimized for development."
echo ""
echo "📝 Next steps:"
echo "   1. Run 'git add .' to stage changes"
echo "   2. Commit with: git commit -m '🧹 Project cleanup and optimization'"
echo "   3. Optional: Run weekly with 'chmod +x scripts/cleanup-maintenance.sh && ./scripts/cleanup-maintenance.sh'"
