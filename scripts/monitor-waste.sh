#!/bin/bash

# 🔍 FILE WASTE MONITOR SCRIPT
# File: scripts/monitor-waste.sh
# Description: Monitor and detect potential "sampah" files in the project

echo "🔍 Ekspedisi Quran - File Waste Monitor"
echo "======================================"

PROJECT_ROOT=$(pwd)
DATE=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Scanning project for potential waste files...${NC}"
echo ""

# 1. CHECK ROOT DIRECTORY FOR TEST FILES
echo -e "${YELLOW}📁 CHECKING ROOT DIRECTORY${NC}"
echo "============================================"

TEST_FILES_ROOT=$(find . -maxdepth 1 -name "test_*.php" -o -name "simple_*.php" -o -name "*_test.php" -o -name "debug_*.php" 2>/dev/null)
if [ -n "$TEST_FILES_ROOT" ]; then
    echo -e "${RED}❌ Test files found in root:${NC}"
    echo "$TEST_FILES_ROOT" | sed 's/^/   /'
    TEST_COUNT=$(echo "$TEST_FILES_ROOT" | wc -l)
    echo -e "${RED}   → Action needed: Move $TEST_COUNT files to archive${NC}"
else
    echo -e "${GREEN}✅ Root directory clean - no test files${NC}"
fi

BACKUP_FILES_ROOT=$(find . -maxdepth 1 -name "*_BACKUP.*" -o -name "*_OLD.*" -o -name "*.backup" -o -name "*.bak" 2>/dev/null)
if [ -n "$BACKUP_FILES_ROOT" ]; then
    echo -e "${RED}❌ Backup files found in root:${NC}"
    echo "$BACKUP_FILES_ROOT" | sed 's/^/   /'
    BACKUP_COUNT=$(echo "$BACKUP_FILES_ROOT" | wc -l)
    echo -e "${RED}   → Action needed: Move $BACKUP_COUNT files to archive${NC}"
else
    echo -e "${GREEN}✅ Root directory clean - no backup files${NC}"
fi

echo ""

# 2. CHECK LOG FILES SIZE
echo -e "${YELLOW}📜 CHECKING LOG FILES${NC}"
echo "============================================"

LOG_DIR="storage/logs"
if [ -d "$LOG_DIR" ]; then
    LOG_FILES=$(find "$LOG_DIR" -name "*.log" -type f 2>/dev/null)
    if [ -n "$LOG_FILES" ]; then
        echo "$LOG_FILES" | while read log_file; do
            if [ -f "$log_file" ]; then
                LOG_SIZE=$(stat -f%z "$log_file" 2>/dev/null || stat -c%s "$log_file" 2>/dev/null)
                LOG_SIZE_KB=$((LOG_SIZE / 1024))
                LOG_SIZE_MB=$((LOG_SIZE / 1048576))
                
                if [ "$LOG_SIZE" -gt 10485760 ]; then  # 10MB
                    echo -e "${RED}❌ Large log file: $(basename "$log_file") (${LOG_SIZE_MB}MB)${NC}"
                    echo -e "${RED}   → Action needed: Archive this log file${NC}"
                elif [ "$LOG_SIZE" -gt 1048576 ]; then  # 1MB
                    echo -e "${YELLOW}⚠️  Medium log file: $(basename "$log_file") (${LOG_SIZE_MB}MB)${NC}"
                    echo -e "${YELLOW}   → Consider archiving soon${NC}"
                else
                    echo -e "${GREEN}✅ Log file OK: $(basename "$log_file") (${LOG_SIZE_KB}KB)${NC}"
                fi
            fi
        done
    else
        echo -e "${GREEN}✅ No log files found${NC}"
    fi
else
    echo -e "${GREEN}✅ Log directory not found${NC}"
fi

echo ""

# 3. CHECK QR CODE DUPLICATES
echo -e "${YELLOW}📱 CHECKING QR CODE DUPLICATES${NC}"
echo "============================================"

QR_DIR="storage/app/public/qr-codes"
if [ -d "$QR_DIR" ]; then
    # Check for QR files with timestamp patterns (likely duplicates)
    QR_DUPLICATES=$(find "$QR_DIR" -name "QR-*-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9].png" 2>/dev/null)
    if [ -n "$QR_DUPLICATES" ]; then
        echo -e "${RED}❌ QR code duplicates found:${NC}"
        echo "$QR_DUPLICATES" | sed 's/^/   /' | while read qr_file; do
            QR_SIZE=$(stat -f%z "$qr_file" 2>/dev/null || stat -c%s "$qr_file" 2>/dev/null)
            QR_SIZE_KB=$((QR_SIZE / 1024))
            echo "   $(basename "$qr_file") (${QR_SIZE_KB}KB)"
        done
        QR_DUP_COUNT=$(echo "$QR_DUPLICATES" | wc -l)
        echo -e "${RED}   → Action needed: Archive $QR_DUP_COUNT duplicate QR files${NC}"
    else
        echo -e "${GREEN}✅ No QR duplicates found${NC}"
    fi
    
    # Show current QR summary
    QR_TOTAL=$(find "$QR_DIR" -name "*.png" | wc -l)
    QR_TOTAL_SIZE=$(du -sh "$QR_DIR" 2>/dev/null | cut -f1)
    echo -e "${BLUE}ℹ️  QR Summary: $QR_TOTAL files, total size: $QR_TOTAL_SIZE${NC}"
else
    echo -e "${GREEN}✅ QR directory not found${NC}"
fi

echo ""

# 4. CHECK STORAGE UPLOADS FOR LARGE FILES
echo -e "${YELLOW}📦 CHECKING STORAGE UPLOADS${NC}"
echo "============================================"

UPLOAD_DIRS=("storage/app/public/mushaf-requests" "storage/app/public/sertifikat" "storage/app/public")
for upload_dir in "${UPLOAD_DIRS[@]}"; do
    if [ -d "$upload_dir" ]; then
        # Find large files (>5MB)
        LARGE_FILES=$(find "$upload_dir" -type f -size +5M 2>/dev/null)
        if [ -n "$LARGE_FILES" ]; then
            echo -e "${YELLOW}⚠️  Large files in $upload_dir:${NC}"
            echo "$LARGE_FILES" | while read large_file; do
                FILE_SIZE=$(stat -f%z "$large_file" 2>/dev/null || stat -c%s "$large_file" 2>/dev/null)
                FILE_SIZE_MB=$((FILE_SIZE / 1048576))
                echo "   $(basename "$large_file") (${FILE_SIZE_MB}MB)"
            done
        else
            echo -e "${GREEN}✅ No large files in $upload_dir${NC}"
        fi
        
        # Show directory summary
        if [ -d "$upload_dir" ]; then
            DIR_SIZE=$(du -sh "$upload_dir" 2>/dev/null | cut -f1)
            FILE_COUNT=$(find "$upload_dir" -type f | wc -l)
            echo -e "${BLUE}ℹ️  $upload_dir: $FILE_COUNT files, $DIR_SIZE${NC}"
        fi
    fi
done

echo ""

# 5. CHECK NODE_MODULES SIZE (if too large)
echo -e "${YELLOW}📦 CHECKING NODE_MODULES${NC}"
echo "============================================"

if [ -d "node_modules" ]; then
    NODE_SIZE=$(du -sh node_modules 2>/dev/null | cut -f1)
    echo -e "${BLUE}ℹ️  node_modules size: $NODE_SIZE${NC}"
    
    # Check if larger than 500MB
    NODE_SIZE_MB=$(du -sm node_modules 2>/dev/null | cut -f1)
    if [ "$NODE_SIZE_MB" -gt 500 ]; then
        echo -e "${YELLOW}⚠️  node_modules is large (${NODE_SIZE_MB}MB)${NC}"
        echo -e "${YELLOW}   → Consider running 'npm ci' instead of 'npm install'${NC}"
    else
        echo -e "${GREEN}✅ node_modules size is reasonable${NC}"
    fi
else
    echo -e "${GREEN}✅ node_modules not found${NC}"
fi

echo ""

# 6. CHECK VENDOR SIZE
echo -e "${YELLOW}📦 CHECKING VENDOR${NC}"
echo "============================================"

if [ -d "vendor" ]; then
    VENDOR_SIZE=$(du -sh vendor 2>/dev/null | cut -f1)
    echo -e "${BLUE}ℹ️  vendor size: $VENDOR_SIZE${NC}"
    
    # Check if larger than 200MB
    VENDOR_SIZE_MB=$(du -sm vendor 2>/dev/null | cut -f1)
    if [ "$VENDOR_SIZE_MB" -gt 200 ]; then
        echo -e "${YELLOW}⚠️  vendor is large (${VENDOR_SIZE_MB}MB)${NC}"
        echo -e "${YELLOW}   → Consider running 'composer install --no-dev' for production${NC}"
    else
        echo -e "${GREEN}✅ vendor size is reasonable${NC}"
    fi
else
    echo -e "${GREEN}✅ vendor not found${NC}"
fi

echo ""

# 7. SUMMARY AND RECOMMENDATIONS
echo -e "${BLUE}📊 WASTE MONITORING SUMMARY${NC}"
echo "============================================"

# Count total issues
TOTAL_ISSUES=0

if [ -n "$TEST_FILES_ROOT" ]; then
    TOTAL_ISSUES=$((TOTAL_ISSUES + 1))
fi

if [ -n "$BACKUP_FILES_ROOT" ]; then
    TOTAL_ISSUES=$((TOTAL_ISSUES + 1))
fi

# Check for large logs
LARGE_LOGS=$(find "storage/logs" -name "*.log" -size +10M 2>/dev/null)
if [ -n "$LARGE_LOGS" ]; then
    TOTAL_ISSUES=$((TOTAL_ISSUES + 1))
fi

if [ -n "$QR_DUPLICATES" ]; then
    TOTAL_ISSUES=$((TOTAL_ISSUES + 1))
fi

if [ "$TOTAL_ISSUES" -eq 0 ]; then
    echo -e "${GREEN}🎉 PROJECT IS CLEAN!${NC}"
    echo -e "${GREEN}   No waste files detected${NC}"
    echo -e "${GREEN}   Project is ready for development/production${NC}"
else
    echo -e "${RED}⚠️  $TOTAL_ISSUES ISSUES DETECTED${NC}"
    echo -e "${YELLOW}   Run './scripts/cleanup-maintenance.sh' to fix automatically${NC}"
fi

echo ""
echo -e "${BLUE}📅 Last check: $(date)${NC}"
echo -e "${BLUE}🔄 Run this monitor weekly to keep project clean${NC}"

# 8. QUICK FIX SUGGESTIONS
if [ "$TOTAL_ISSUES" -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}💡 QUICK FIX COMMANDS:${NC}"
    echo "============================================"
    
    if [ -n "$TEST_FILES_ROOT" ]; then
        echo -e "${YELLOW}# Move test files to archive:${NC}"
        echo "mkdir -p docs/development/archived_files/temp_files"
        echo "find . -maxdepth 1 -name 'test_*.php' -exec mv {} docs/development/archived_files/temp_files/ \;"
        echo ""
    fi
    
    if [ -n "$LARGE_LOGS" ]; then
        echo -e "${YELLOW}# Archive large log files:${NC}"
        echo "mkdir -p docs/development/archived_files/old_logs"
        echo "mv storage/logs/laravel.log docs/development/archived_files/old_logs/laravel_$(date +%Y%m%d_%H%M%S).log"
        echo ""
    fi
    
    if [ -n "$QR_DUPLICATES" ]; then
        echo -e "${YELLOW}# Clean QR duplicates:${NC}"
        echo "mkdir -p docs/development/archived_files/old_qr_codes"
        echo "find storage/app/public/qr-codes -name 'QR-*-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9].png' -exec mv {} docs/development/archived_files/old_qr_codes/ \;"
        echo ""
    fi
    
    echo -e "${GREEN}# Or run automatic cleanup:${NC}"
    echo "./scripts/cleanup-maintenance.sh"
fi

echo ""
echo "🔍 File waste monitoring completed!"
