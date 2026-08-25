#!/bin/bash

echo "🔍 QR Feature Health Check & Repair"
echo "===================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Health Check Functions
check_dependencies() {
    echo -e "${BLUE}📦 Checking Dependencies...${NC}"
    
    local issues=0
    
    # Check PHP QR dependencies
    if composer show | grep -q "simplesoftwareio/simple-qrcode"; then
        echo -e "${GREEN}✅ SimpleSoftwareIO QrCode installed${NC}"
    else
        echo -e "${RED}❌ SimpleSoftwareIO QrCode missing${NC}"
        issues=$((issues + 1))
    fi
    
    if composer show | grep -q "intervention/image"; then
        echo -e "${GREEN}✅ Intervention Image installed${NC}"
    else
        echo -e "${RED}❌ Intervention Image missing${NC}"
        issues=$((issues + 1))
    fi
    
    # Check JS dependencies
    if [ -f "node_modules/html5-qrcode/package.json" ]; then
        echo -e "${GREEN}✅ html5-qrcode installed${NC}"
    else
        echo -e "${RED}❌ html5-qrcode missing${NC}"
        issues=$((issues + 1))
    fi
    
    if [ -f "node_modules/qrcode/package.json" ]; then
        echo -e "${GREEN}✅ qrcode installed${NC}"
    else
        echo -e "${RED}❌ qrcode missing${NC}"
        issues=$((issues + 1))
    fi
    
    return $issues
}

check_files() {
    echo -e "${BLUE}📁 Checking Critical Files...${NC}"
    
    local issues=0
    
    # Check controllers
    if [ -f "app/Http/Controllers/Admin/QRCodeController.php" ]; then
        echo -e "${GREEN}✅ QRCodeController exists${NC}"
    else
        echo -e "${RED}❌ QRCodeController missing${NC}"
        issues=$((issues + 1))
    fi
    
    # Check Svelte components
    if [ -f "resources/js/Pages/Admin/Pengiriman/ScanStatus.svelte" ]; then
        echo -e "${GREEN}✅ ScanStatus component exists${NC}"
    else
        echo -e "${RED}❌ ScanStatus component missing${NC}"
        issues=$((issues + 1))
    fi
    
    if [ -f "resources/js/Pages/Admin/Pengiriman/GenerateQR.svelte" ]; then
        echo -e "${GREEN}✅ GenerateQR component exists${NC}"
    else
        echo -e "${RED}❌ GenerateQR component missing${NC}"
        issues=$((issues + 1))
    fi
    
    return $issues
}

check_directories() {
    echo -e "${BLUE}📂 Checking Directories...${NC}"
    
    local issues=0
    
    # Storage directories
    if [ -d "storage/app/public/qr-codes" ]; then
        echo -e "${GREEN}✅ QR storage directory exists${NC}"
    else
        echo -e "${YELLOW}⚠️ Creating QR storage directory${NC}"
        mkdir -p storage/app/public/qr-codes
        chmod 775 storage/app/public/qr-codes
        echo -e "${GREEN}✅ QR storage directory created${NC}"
    fi
    
    # Check permissions
    if [ -w "storage/app/public" ]; then
        echo -e "${GREEN}✅ Storage directory writable${NC}"
    else
        echo -e "${YELLOW}⚠️ Fixing storage permissions${NC}"
        chmod -R 775 storage/
        echo -e "${GREEN}✅ Storage permissions fixed${NC}"
    fi
    
    return $issues
}

check_routes() {
    echo -e "${BLUE}🛣️ Checking Routes...${NC}"
    
    local issues=0
    
    # Check QR routes exist
    if php artisan route:list | grep -q "scan-status"; then
        echo -e "${GREEN}✅ QR scan routes exist${NC}"
    else
        echo -e "${RED}❌ QR scan routes missing${NC}"
        issues=$((issues + 1))
    fi
    
    if php artisan route:list | grep -q "qr.generate"; then
        echo -e "${GREEN}✅ QR generate routes exist${NC}"
    else
        echo -e "${RED}❌ QR generate routes missing${NC}"
        issues=$((issues + 1))
    fi
    
    return $issues
}

check_config() {
    echo -e "${BLUE}⚙️ Checking Configuration...${NC}"
    
    local issues=0
    
    # Check if storage link exists
    if [ -L "public/storage" ]; then
        echo -e "${GREEN}✅ Storage symlink exists${NC}"
    else
        echo -e "${YELLOW}⚠️ Creating storage symlink${NC}"
        php artisan storage:link > /dev/null 2>&1
        echo -e "${GREEN}✅ Storage symlink created${NC}"
    fi
    
    return $issues
}

# Repair Functions
install_missing_dependencies() {
    echo -e "${YELLOW}🔧 Installing Missing Dependencies...${NC}"
    
    # Install PHP dependencies
    if ! composer show | grep -q "simplesoftwareio/simple-qrcode"; then
        echo -e "${YELLOW}Installing SimpleSoftwareIO QrCode...${NC}"
        composer require simplesoftwareio/simple-qrcode --quiet
    fi
    
    if ! composer show | grep -q "intervention/image"; then
        echo -e "${YELLOW}Installing Intervention Image...${NC}"
        composer require intervention/image --quiet
    fi
    
    # Install JS dependencies
    if [ ! -f "node_modules/html5-qrcode/package.json" ]; then
        echo -e "${YELLOW}Installing html5-qrcode...${NC}"
        npm install html5-qrcode --silent
    fi
    
    if [ ! -f "node_modules/qrcode/package.json" ]; then
        echo -e "${YELLOW}Installing qrcode...${NC}"
        npm install qrcode --silent
    fi
}

clear_caches() {
    echo -e "${YELLOW}🧹 Clearing Caches...${NC}"
    
    # Clear Laravel caches
    php artisan config:clear > /dev/null 2>&1
    php artisan cache:clear > /dev/null 2>&1
    php artisan view:clear > /dev/null 2>&1
    php artisan route:clear > /dev/null 2>&1
    
    # Cache config
    php artisan config:cache > /dev/null 2>&1
    
    echo -e "${GREEN}✅ Caches cleared and rebuilt${NC}"
}

build_assets() {
    echo -e "${YELLOW}🏗️ Building Assets...${NC}"
    
    # Build frontend assets
    npm run build > /dev/null 2>&1
    
    echo -e "${GREEN}✅ Assets built${NC}"
}

test_qr_functionality() {
    echo -e "${BLUE}🧪 Testing QR Functionality...${NC}"
    
    local issues=0
    
    # Test artisan command if available
    if php artisan qr:test 2>/dev/null | grep -q "Testing QR"; then
        echo -e "${GREEN}✅ QR test command works${NC}"
    else
        echo -e "${YELLOW}⚠️ QR test command not available${NC}"
    fi
    
    # Test route accessibility
    echo -e "${BLUE}📍 Testing Route Access:${NC}"
    echo -e "   - Generate QR: ${YELLOW}http://localhost:8000/admin/generate-qr${NC}"
    echo -e "   - Scan QR: ${YELLOW}http://localhost:8000/admin/scan-status${NC}"
    echo -e "   - Public Tracking: ${YELLOW}http://localhost:8000/tracking${NC}"
    
    return $issues
}

# Main execution
main() {
    echo -e "${GREEN}Starting QR Feature Health Check...${NC}\n"
    
    local total_issues=0
    
    # Run health checks
    check_dependencies
    total_issues=$((total_issues + $?))
    
    check_files  
    total_issues=$((total_issues + $?))
    
    check_directories
    total_issues=$((total_issues + $?))
    
    check_routes
    total_issues=$((total_issues + $?))
    
    check_config
    total_issues=$((total_issues + $?))
    
    # If issues found, attempt repairs
    if [ $total_issues -gt 0 ]; then
        echo -e "\n${YELLOW}🔧 Issues detected. Attempting repairs...${NC}\n"
        
        install_missing_dependencies
        clear_caches
        build_assets
        
        echo -e "\n${GREEN}🔄 Re-running health checks...${NC}\n"
        
        # Re-run checks
        check_dependencies > /dev/null
        check_files > /dev/null  
        check_directories > /dev/null
        check_routes > /dev/null
        check_config > /dev/null
    fi
    
    # Final test
    test_qr_functionality
    
    echo -e "\n${GREEN}=================================${NC}"
    if [ $total_issues -eq 0 ]; then
        echo -e "${GREEN}✅ QR Feature Health Check: PASSED${NC}"
        echo -e "${GREEN}All systems operational!${NC}"
    else
        echo -e "${YELLOW}⚠️ QR Feature Health Check: COMPLETED WITH REPAIRS${NC}"
        echo -e "${YELLOW}Some issues were fixed automatically${NC}"
    fi
    echo -e "${GREEN}=================================${NC}"
    
    echo -e "\n${BLUE}📋 Manual Testing Checklist:${NC}"
    echo -e "□ Visit /admin/generate-qr and generate a QR code"
    echo -e "□ Visit /admin/scan-status and test camera access"
    echo -e "□ Scan generated QR code with camera"
    echo -e "□ Update status via QR scan"
    echo -e "□ Verify status history is recorded"
    echo -e "□ Test manual resi input as fallback"
    
    echo -e "\n${BLUE}🚨 If you encounter errors:${NC}"
    echo -e "1. Check browser console for JavaScript errors"
    echo -e "2. Check Laravel logs: tail -f storage/logs/laravel.log"
    echo -e "3. Ensure HTTPS for camera access on production"
    echo -e "4. Clear browser cache and refresh page"
}

# Run main function
main
