#!/bin/bash

# =============================================================================
# 🚀 COMMIT REMINDER - Ekspedisi Quran
# =============================================================================
# Script ini menampilkan checklist sebelum commit
# Jalankan: ./scripts/commit-reminder.sh
# =============================================================================

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  📋 PRE-COMMIT CHECKLIST                                         ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

# Warna
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Check functions
check_build() {
    if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
        echo -e "${GREEN}✅${NC} Build assets ready"
        return 0
    else
        echo -e "${RED}❌${NC} Build assets NOT FOUND"
        return 1
    fi
}

check_tests() {
    if [ -f ".tests-passed" ]; then
        echo -e "${GREEN}✅${NC} Tests marker found"
        return 0
    else
        echo -e "${YELLOW}⚠️ ${NC} Tests not verified"
        return 1
    fi
}

check_uncommitted() {
    local count=$(git status --short | wc -l)
    if [ $count -gt 0 ]; then
        echo -e "${YELLOW}⚠️ ${NC} $count uncommitted files"
        return 1
    else
        echo -e "${GREEN}✅${NC} No uncommitted changes"
        return 0
    fi
}

# Display checklist
echo -e "${BLUE}Required Steps:${NC}"
echo ""

echo "1. Build Assets"
check_build
echo "   Command: npm ci && npm run build"
echo ""

echo "2. Run Tests"
check_tests
echo "   Command: php artisan test"
echo "   Then: touch .tests-passed"
echo ""

echo "3. Check Uncommitted Changes"
check_uncommitted
echo ""

echo -e "${BLUE}Git Commands:${NC}"
echo ""
echo "   git add ."
echo "   git commit -m \"feat: your message\""
echo "   git push origin main"
echo ""

echo -e "${BLUE}Quick Commands:${NC}"
echo ""
echo "   # Build + Test + Mark"
echo "   npm ci && npm run build && php artisan test && touch .tests-passed"
echo ""
echo "   # Commit dan Push"
echo "   git add . && git commit -m \"feat: update\" && git push origin main"
echo ""

# Summary
echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo -e "${YELLOW}📚 Documentation:${NC}"
echo "   docs/03-deployment/LOCAL_BUILD_WORKFLOW.md"
echo ""
