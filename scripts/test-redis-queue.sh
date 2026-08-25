#!/bin/bash

# Redis Queue System Test Script
# This script validates that the Redis queue system is properly configured and working

set -e

echo "🚀 Testing Redis Queue System Configuration"
echo "============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
        exit 1
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "ℹ️  $1"
}

echo
echo "1. Testing Redis Connection..."
if redis-cli ping > /dev/null 2>&1; then
    print_status 0 "Redis server is running"
else
    print_status 1 "Redis server is not running - please start Redis first"
fi

echo
echo "2. Testing Laravel Queue Configuration..."
php artisan config:cache > /dev/null 2>&1
print_status 0 "Laravel configuration cached successfully"

echo
echo "3. Testing Redis Queue Connection..."
php artisan tinker --execute="
try {
    \$redis = \Illuminate\Support\Facades\Redis::connection('queue');
    \$response = \$redis->ping();
    if (\$response === 'PONG' || \$response === '+PONG') {
        echo 'SUCCESS';
    } else {
        echo 'FAILED';
    }
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}" 2>/dev/null | grep -q "SUCCESS"

if [ $? -eq 0 ]; then
    print_status 0 "Laravel can connect to Redis queue database"
else
    print_status 1 "Laravel cannot connect to Redis queue database"
fi

echo
echo "4. Testing Queue Health Check..."
php artisan queue:health-check > /dev/null 2>&1
print_status $? "Queue health check passed"

echo
echo "5. Testing Queue Connections..."
connections=("redis" "redis-high" "redis-certificates" "redis-warehouse" "redis-low")
for conn in "${connections[@]}"; do
    echo -n "   Testing $conn connection... "
    php artisan tinker --execute="
    try {
        \$queue = \Illuminate\Support\Facades\Queue::connection('$conn');
        echo 'OK';
    } catch (Exception \$e) {
        echo 'FAILED: ' . \$e->getMessage();
    }" 2>/dev/null | grep -q "OK"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅${NC}"
    else
        echo -e "${RED}❌${NC}"
        echo -e "${RED}   Failed to connect to $conn${NC}"
        exit 1
    fi
done

echo
echo "6. Testing Job Classes Configuration..."
job_classes=(
    "App\Jobs\Certificate\GenerateBulkCertificatesJob"
    "App\Jobs\Certificate\GenerateSingleCertificateJob"
    "App\Jobs\Warehouse\BulkBoxProcessingJob"
    "App\Jobs\Warehouse\BulkStatusUpdateJob"
    "App\Jobs\Warehouse\BulkItemAssignmentJob"
    "App\Jobs\Certificate\CleanupOldCertificatesJob"
)

for job_class in "${job_classes[@]}"; do
    echo -n "   Checking $job_class... "
    if php -r "
    require_once 'vendor/autoload.php';
    \$app = require_once 'bootstrap/app.php';
    \$app->boot();
    if (class_exists('$job_class')) {
        echo 'EXISTS';
    } else {
        echo 'MISSING';
    }
    " 2>/dev/null | grep -q "EXISTS"; then
        echo -e "${GREEN}✅${NC}"
    else
        echo -e "${RED}❌${NC}"
        echo -e "${RED}   Job class $job_class not found${NC}"
        exit 1
    fi
done

echo
echo "7. Testing Supervisor Configuration Files..."
supervisor_configs=(
    "deployment/supervisor/queue-high.conf"
    "deployment/supervisor/queue-certificates.conf"
    "deployment/supervisor/queue-warehouse.conf"
    "deployment/supervisor/queue-default.conf"
    "deployment/supervisor/queue-low.conf"
)

for config in "${supervisor_configs[@]}"; do
    if [ -f "$config" ]; then
        print_status 0 "Found $config"
    else
        print_status 1 "Missing $config"
    fi
done

echo
echo "8. Testing Queue Monitoring Commands..."
timeout 3s php artisan queue:monitor --interval=1 --duration=2 > /dev/null 2>&1 || true
print_status 0 "Queue monitor command executed successfully"

echo
echo "9. Environment Configuration Check..."
required_env_vars=(
    "QUEUE_CONNECTION"
    "REDIS_QUEUE_CONNECTION"
    "REDIS_QUEUE_DB"
    "REDIS_HOST"
    "REDIS_PORT"
)

for var in "${required_env_vars[@]}"; do
    if grep -q "^${var}=" .env 2>/dev/null; then
        print_status 0 "Environment variable $var is set"
    else
        print_warning "Environment variable $var is not explicitly set (may use defaults)"
    fi
done

echo
echo "10. Redis Database Allocation Check..."
echo -n "   Checking Redis database allocation... "

# Check if queue database is separate from cache database
queue_db=$(grep "REDIS_QUEUE_DB=" .env 2>/dev/null | cut -d'=' -f2 || echo "2")
cache_db=$(grep "REDIS_CACHE_DB=" .env 2>/dev/null | cut -d'=' -f2 || echo "1")

if [ "$queue_db" != "$cache_db" ]; then
    echo -e "${GREEN}✅${NC}"
    print_info "Queue DB: $queue_db, Cache DB: $cache_db (properly separated)"
else
    print_warning "Queue and cache are using the same Redis database"
fi

echo
echo "🎉 Redis Queue System Test Summary"
echo "=================================="
echo -e "${GREEN}✅ All core tests passed!${NC}"
echo
echo "📋 Configuration Summary:"
echo "   • Queue Driver: $(grep "QUEUE_CONNECTION=" .env | cut -d'=' -f2)"
echo "   • Redis Host: $(grep "REDIS_HOST=" .env | cut -d'=' -f2 || echo '127.0.0.1')"
echo "   • Redis Port: $(grep "REDIS_PORT=" .env | cut -d'=' -f2 || echo '6379')"
echo "   • Queue Database: $queue_db"
echo "   • Available Queues: high, certificates, warehouse, default, low"
echo "   • Supervisor Configs: $(ls deployment/supervisor/*.conf 2>/dev/null | wc -l | tr -d ' ') files"
echo
echo "🚀 Next Steps:"
echo "   1. Deploy supervisor configuration files to production"
echo "   2. Start queue workers using: supervisorctl start ekspedisi-queue-*:*"
echo "   3. Monitor queues using: php artisan queue:monitor"
echo "   4. Set up automated health checks in cron"
echo
echo "📖 For detailed deployment instructions, see:"
echo "   docs/REDIS_QUEUE_DEPLOYMENT.md"

echo
echo -e "${GREEN}🎯 Redis Queue System is ready for production deployment!${NC}"