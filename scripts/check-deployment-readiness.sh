#!/bin/bash

# =============================================================================
# 🚀 DEPLOYMENT READINESS CHECKER
# =============================================================================
# Script untuk memeriksa kesiapan deployment
# Jalankan sebelum deploy pertama kali atau untuk troubleshooting
# =============================================================================

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  🚀 DEPLOYMENT READINESS CHECK                                  ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

# Warna
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SERVER="root@45.77.42.220"
APP_DIR="/var/www/app"
FAILED=0

# -----------------------------------------------------------------------------
# FUNGSI HELPER
# -----------------------------------------------------------------------------

check_pass() {
    echo -e "${GREEN}✅${NC} $1"
}

check_fail() {
    echo -e "${RED}❌${NC} $1"
    FAILED=$((FAILED + 1))
}

check_warn() {
    echo -e "${YELLOW}⚠️ ${NC} $1"
}

check_info() {
    echo -e "${BLUE}ℹ️ ${NC} $1"
}

# -----------------------------------------------------------------------------
# CHECK 1: Local Environment
# -----------------------------------------------------------------------------
echo "📦 CHECK 1: Local Environment"
echo ""

# Check PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+')
    if [[ "$PHP_VERSION" == "8.4"* ]] || [[ "$PHP_VERSION" == "8.3"* ]]; then
        check_pass "PHP $PHP_VERSION"
    else
        check_warn "PHP $PHP_VERSION (recommended: 8.3+)"
    fi
else
    check_fail "PHP not found"
fi

# Check Composer
if command -v composer &> /dev/null; then
    check_pass "Composer installed"
else
    check_fail "Composer not found"
fi

# Check Node.js
if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v | grep -oP 'v\K[0-9]+')
    if [ "$NODE_VERSION" -ge 18 ]; then
        check_pass "Node.js $(node -v)"
    else
        check_warn "Node.js $(node -v) (recommended: 18+)"
    fi
else
    check_fail "Node.js not found"
fi

# Check NPM
if command -v npm &> /dev/null; then
    check_pass "NPM installed"
else
    check_fail "NPM not found"
fi

# Check Git
if command -v git &> /dev/null; then
    check_pass "Git installed"
else
    check_fail "Git not found"
fi

# Check Envoy
if command -v envoy &> /dev/null || [ -f "$HOME/.composer/vendor/bin/envoy" ]; then
    check_pass "Laravel Envoy installed"
else
    check_warn "Laravel Envoy not found (will be installed during deploy)"
fi

echo ""

# -----------------------------------------------------------------------------
# CHECK 2: Git Status
# -----------------------------------------------------------------------------
echo "📦 CHECK 2: Git Status"
echo ""

# Check if in git repo
if [ -d ".git" ]; then
    check_pass "Git repository detected"

    # Check current branch
    BRANCH=$(git branch --show-current)
    if [ "$BRANCH" = "main" ]; then
        check_pass "On main branch"
    else
        check_warn "On '$BRANCH' branch (deployment only works from main)"
    fi

    # Check for uncommitted changes
    if [ -z "$(git status --porcelain)" ]; then
        check_pass "Working directory clean"
    else
        check_warn "Uncommitted changes detected"
        git status --short
    fi

    # Check remote
    if git remote -v &> /dev/null; then
        check_pass "Git remote configured"
        check_info "Remote: $(git remote get-url origin 2>/dev/null || echo 'Not set')"
    else
        check_fail "No git remote configured"
    fi
else
    check_fail "Not a git repository"
fi

echo ""

# -----------------------------------------------------------------------------
# CHECK 3: Build Status
# -----------------------------------------------------------------------------
echo "📦 CHECK 3: Build Status"
echo ""

if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
    BUILD_TIME=$(stat -c %Y "public/build/manifest.json" 2>/dev/null || stat -f %m "public/build/manifest.json")
    CURRENT_TIME=$(date +%s)
    AGE_MINUTES=$(( (CURRENT_TIME - BUILD_TIME) / 60 ))

    if [ $AGE_MINUTES -lt 60 ]; then
        check_pass "Build assets present (built $AGE_MINUTES minutes ago)"
    else
        check_warn "Build assets present but old ($AGE_MINUTES minutes ago)"
        check_info "Consider rebuilding: npm ci && npm run build"
    fi
else
    check_fail "Build assets not found!"
    check_info "Run: npm ci && npm run build"
fi

echo ""

# -----------------------------------------------------------------------------
# CHECK 4: Dependencies
# -----------------------------------------------------------------------------
echo "📦 CHECK 4: Dependencies"
echo ""

# Check composer dependencies
if [ -d "vendor" ]; then
    check_pass "Composer dependencies installed"
else
    check_fail "Composer dependencies missing"
    check_info "Run: composer install"
fi

# Check node_modules
if [ -d "node_modules" ]; then
    check_pass "Node modules installed"
else
    check_fail "Node modules missing"
    check_info "Run: npm ci"
fi

echo ""

# -----------------------------------------------------------------------------
# CHECK 5: Server Connectivity
# -----------------------------------------------------------------------------
echo "📦 CHECK 5: Server Connectivity"
echo ""

# Check SSH connectivity
if ssh -o ConnectTimeout=5 -o BatchMode=yes $SERVER echo "OK" &> /dev/null; then
    check_pass "SSH connection to server ($SERVER)"
else
    check_fail "Cannot connect to server ($SERVER)"
    check_info "Ensure SSH key is added to server"
fi

# Check if server has required directories
if ssh -o ConnectTimeout=5 $SERVER "test -d $APP_DIR" &> /dev/null; then
    check_pass "Application directory exists on server"
else
    check_warn "Application directory not found on server (will be created)"
fi

echo ""

# -----------------------------------------------------------------------------
# CHECK 6: Environment Configuration
# -----------------------------------------------------------------------------
echo "📦 CHECK 6: Environment Configuration"
echo ""

# Check .env.example exists
if [ -f ".env.example" ]; then
    check_pass ".env.example exists"
else
    check_warn ".env.example not found"
fi

# Check .env exists (local)
if [ -f ".env" ]; then
    check_pass ".env exists locally"

    # Check critical variables
    if grep -q "APP_KEY=" .env && ! grep -q "APP_KEY=$" .env; then
        check_pass "APP_KEY set"
    else
        check_fail "APP_KEY not set"
    fi
else
    check_warn ".env not found locally"
    check_info "Copy from: cp .env.example .env"
fi

echo ""

# -----------------------------------------------------------------------------
# CHECK 7: Tests Status
# -----------------------------------------------------------------------------
echo "📦 CHECK 7: Tests Status"
echo ""

if [ -f ".tests-passed" ]; then
    check_pass "Tests marker found (.tests-passed)"

    TEST_TIME=$(stat -c %Y ".tests-passed" 2>/dev/null || stat -f %m ".tests-passed")
    CURRENT_TIME=$(date +%s)
    AGE_HOURS=$(( (CURRENT_TIME - TEST_TIME) / 3600 ))

    if [ $AGE_HOURS -gt 24 ]; then
        check_warn "Tests are $AGE_HOURS hours old"
    fi
else
    check_warn "Tests not run yet"
    check_info "Run: php artisan test && touch .tests-passed"
fi

echo ""

# -----------------------------------------------------------------------------
# SUMMARY
# -----------------------------------------------------------------------------
echo "════════════════════════════════════════════════════════════════════"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL CHECKS PASSED!${NC}"
    echo ""
    echo "Your project is ready for deployment."
    echo ""
    echo "Next steps:"
    echo "  1. git add ."
    echo "  2. git commit -m \"your message\""
    echo "  3. git push origin main"
    echo ""
    exit 0
else
    echo -e "${RED}⚠️  $FAILED CHECK(S) FAILED${NC}"
    echo ""
    echo "Please fix the issues above before deploying."
    echo ""

    if [ $FAILED -ge 3 ]; then
        echo "💡 Run full setup:"
        echo "  composer install"
        echo "  npm ci"
        echo "  npm run build"
        echo "  php artisan test && touch .tests-passed"
    fi
    echo ""
    exit 1
fi
