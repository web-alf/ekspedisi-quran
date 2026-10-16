#!/bin/bash

# Production Validation Suite
# Comprehensive validation of all optimizations and deployments

set -e

echo "🧪 Production Validation Suite"
echo "==============================="
echo "Starting comprehensive validation of production deployment..."
echo ""

# Configuration
APP_DIR="${APP_DIR:-/var/www/ekspedisi-quran}"
VALIDATION_REPORT="/tmp/production-validation-$(date +%Y%m%d_%H%M%S).json"
VALIDATION_LOG="/tmp/validation.log"
EXIT_CODE=0

# Validation thresholds
RESPONSE_TIME_THRESHOLD=2000    # milliseconds
DB_QUERY_THRESHOLD=100         # milliseconds
MEMORY_USAGE_THRESHOLD=80      # percentage
DISK_USAGE_THRESHOLD=85        # percentage
CACHE_HIT_THRESHOLD=85         # percentage
QUEUE_SIZE_THRESHOLD=1000      # jobs

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log_result() {
    local test_name="$1"
    local status="$2"
    local message="$3"
    local value="$4"
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $test_name: $status - $message" >> $VALIDATION_LOG
    
    case $status in
        "PASS")
            echo -e "${GREEN}✓ $test_name${NC}: $message"
            ;;
        "FAIL")
            echo -e "${RED}✗ $test_name${NC}: $message"
            EXIT_CODE=1
            ;;
        "WARN")
            echo -e "${YELLOW}⚠ $test_name${NC}: $message"
            ;;
        "INFO")
            echo -e "${BLUE}ℹ $test_name${NC}: $message"
            ;;
    esac
}

# Start validation report
echo "{" > $VALIDATION_REPORT
echo "  \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"," >> $VALIDATION_REPORT
echo "  \"environment\": \"production\"," >> $VALIDATION_REPORT
echo "  \"validations\": {" >> $VALIDATION_REPORT

echo "🔍 1. Infrastructure Validation"
echo "==============================="

# 1.1 System Resources
MEMORY_TOTAL=$(free -m | awk 'NR==2{print $2}')
MEMORY_USED=$(free -m | awk 'NR==2{print $3}')
MEMORY_USAGE=$((MEMORY_USED * 100 / MEMORY_TOTAL))

if [ $MEMORY_USAGE -lt $MEMORY_USAGE_THRESHOLD ]; then
    log_result "Memory Usage" "PASS" "${MEMORY_USAGE}% (${MEMORY_USED}MB/${MEMORY_TOTAL}MB)"
else
    log_result "Memory Usage" "FAIL" "${MEMORY_USAGE}% exceeds threshold of ${MEMORY_USAGE_THRESHOLD}%"
fi

# 1.2 Disk Space
DISK_USAGE=$(df / | awk 'NR==2{print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt $DISK_USAGE_THRESHOLD ]; then
    log_result "Disk Usage" "PASS" "${DISK_USAGE}%"
else
    log_result "Disk Usage" "FAIL" "${DISK_USAGE}% exceeds threshold of ${DISK_USAGE_THRESHOLD}%"
fi

# 1.3 System Load
LOAD_1MIN=$(uptime | awk '{print $10}' | sed 's/,//')
CPU_CORES=$(nproc)
LOAD_RATIO=$(echo "scale=2; $LOAD_1MIN / $CPU_CORES" | bc)

if (( $(echo "$LOAD_RATIO < 1.0" | bc -l) )); then
    log_result "System Load" "PASS" "$LOAD_1MIN (${LOAD_RATIO} per core)"
elif (( $(echo "$LOAD_RATIO < 2.0" | bc -l) )); then
    log_result "System Load" "WARN" "$LOAD_1MIN (${LOAD_RATIO} per core) - moderate load"
else
    log_result "System Load" "FAIL" "$LOAD_1MIN (${LOAD_RATIO} per core) - high load"
fi

echo ""
echo "🔧 2. Service Validation"
echo "========================"

# 2.1 Critical Services
SERVICES=("nginx" "php8.1-fpm" "mysql" "redis-server" "supervisor")
for service in "${SERVICES[@]}"; do
    if systemctl is-active --quiet $service; then
        log_result "Service $service" "PASS" "Running"
    else
        log_result "Service $service" "FAIL" "Not running"
    fi
done

# 2.2 Port Accessibility
PORTS=("80:HTTP" "443:HTTPS" "22:SSH")
for port_info in "${PORTS[@]}"; do
    port=$(echo $port_info | cut -d: -f1)
    name=$(echo $port_info | cut -d: -f2)
    
    if netstat -ln | grep -q ":$port "; then
        log_result "Port $name" "PASS" "Port $port is listening"
    else
        log_result "Port $name" "FAIL" "Port $port is not accessible"
    fi
done

echo ""
echo "🗄️ 3. Database Validation"
echo "========================="

# 3.1 Database Connectivity
if mysql -u ekspedisi -p$DB_PASSWORD ekspedisi_quran -e "SELECT 1;" &>/dev/null; then
    log_result "Database Connection" "PASS" "Connected successfully"
    
    # 3.2 Database Performance
    DB_START=$(php -r "echo microtime(true);")
    mysql -u ekspedisi -p$DB_PASSWORD ekspedisi_quran -e "SELECT COUNT(*) FROM pengiriman;" &>/dev/null
    DB_END=$(php -r "echo microtime(true);")
    DB_TIME_MS=$(echo "($DB_END - $DB_START) * 1000" | bc | cut -d. -f1)
    
    if [ $DB_TIME_MS -lt $DB_QUERY_THRESHOLD ]; then
        log_result "Database Performance" "PASS" "${DB_TIME_MS}ms query time"
    else
        log_result "Database Performance" "FAIL" "${DB_TIME_MS}ms exceeds ${DB_QUERY_THRESHOLD}ms threshold"
    fi
    
    # 3.3 Database Indexes
    INDEX_COUNT=$(mysql -u ekspedisi -p$DB_PASSWORD ekspedisi_quran -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='ekspedisi_quran';" | tail -1)
    if [ $INDEX_COUNT -gt 20 ]; then
        log_result "Database Indexes" "PASS" "$INDEX_COUNT indexes found"
    else
        log_result "Database Indexes" "WARN" "Only $INDEX_COUNT indexes found - may need optimization"
    fi
else
    log_result "Database Connection" "FAIL" "Cannot connect to database"
fi

echo ""
echo "⚡ 4. Cache System Validation"
echo "============================"

# 4.1 Redis Connectivity
if redis-cli -a $REDIS_PASSWORD ping | grep -q PONG; then
    log_result "Redis Connection" "PASS" "Connected successfully"
    
    # 4.2 Cache Performance
    CACHE_START=$(php -r "echo microtime(true);")
    redis-cli -a $REDIS_PASSWORD set test_key test_value &>/dev/null
    redis-cli -a $REDIS_PASSWORD get test_key &>/dev/null
    redis-cli -a $REDIS_PASSWORD del test_key &>/dev/null
    CACHE_END=$(php -r "echo microtime(true);")
    CACHE_TIME_MS=$(echo "($CACHE_END - $CACHE_START) * 1000" | bc | cut -d. -f1)
    
    if [ $CACHE_TIME_MS -lt 50 ]; then
        log_result "Cache Performance" "PASS" "${CACHE_TIME_MS}ms operation time"
    else
        log_result "Cache Performance" "WARN" "${CACHE_TIME_MS}ms - slower than expected"
    fi
    
    # 4.3 Cache Hit Rate
    REDIS_INFO=$(redis-cli -a $REDIS_PASSWORD info stats)
    HITS=$(echo "$REDIS_INFO" | grep keyspace_hits | cut -d: -f2 | tr -d '\r')
    MISSES=$(echo "$REDIS_INFO" | grep keyspace_misses | cut -d: -f2 | tr -d '\r')
    
    if [ $HITS -gt 0 ] && [ $MISSES -gt 0 ]; then
        HIT_RATE=$(echo "scale=2; $HITS / ($HITS + $MISSES) * 100" | bc)
        HIT_RATE_INT=$(echo $HIT_RATE | cut -d. -f1)
        
        if [ $HIT_RATE_INT -gt $CACHE_HIT_THRESHOLD ]; then
            log_result "Cache Hit Rate" "PASS" "${HIT_RATE}%"
        else
            log_result "Cache Hit Rate" "WARN" "${HIT_RATE}% below ${CACHE_HIT_THRESHOLD}% threshold"
        fi
    else
        log_result "Cache Hit Rate" "INFO" "No cache statistics available yet"
    fi
else
    log_result "Redis Connection" "FAIL" "Cannot connect to Redis"
fi

echo ""
echo "📦 5. Queue System Validation"
echo "============================="

# 5.1 Supervisor Status
QUEUE_WORKERS=$(supervisorctl status ekspedisi-queue-* | grep RUNNING | wc -l)
EXPECTED_WORKERS=6

if [ $QUEUE_WORKERS -eq $EXPECTED_WORKERS ]; then
    log_result "Queue Workers" "PASS" "$QUEUE_WORKERS/$EXPECTED_WORKERS workers running"
elif [ $QUEUE_WORKERS -gt 0 ]; then
    log_result "Queue Workers" "WARN" "$QUEUE_WORKERS/$EXPECTED_WORKERS workers running"
else
    log_result "Queue Workers" "FAIL" "No queue workers running"
fi

# 5.2 Queue Sizes
if [ -f $APP_DIR/artisan ]; then
    HIGH_QUEUE=$(php $APP_DIR/artisan queue:size redis high 2>/dev/null || echo 0)
    DEFAULT_QUEUE=$(php $APP_DIR/artisan queue:size redis default 2>/dev/null || echo 0)
    LOW_QUEUE=$(php $APP_DIR/artisan queue:size redis low 2>/dev/null || echo 0)
    FAILED_JOBS=$(php $APP_DIR/artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo 0)
    
    TOTAL_QUEUE=$((HIGH_QUEUE + DEFAULT_QUEUE + LOW_QUEUE))
    
    if [ $TOTAL_QUEUE -lt $QUEUE_SIZE_THRESHOLD ]; then
        log_result "Queue Size" "PASS" "$TOTAL_QUEUE jobs (High: $HIGH_QUEUE, Default: $DEFAULT_QUEUE, Low: $LOW_QUEUE)"
    else
        log_result "Queue Size" "WARN" "$TOTAL_QUEUE jobs exceeds threshold"
    fi
    
    if [ $FAILED_JOBS -eq 0 ]; then
        log_result "Failed Jobs" "PASS" "No failed jobs"
    elif [ $FAILED_JOBS -lt 10 ]; then
        log_result "Failed Jobs" "WARN" "$FAILED_JOBS failed jobs"
    else
        log_result "Failed Jobs" "FAIL" "$FAILED_JOBS failed jobs"
    fi
fi

echo ""
echo "🌐 6. Web Server Validation"
echo "==========================="

# 6.1 HTTP Response
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    log_result "HTTP Response" "PASS" "HTTP $HTTP_CODE"
else
    log_result "HTTP Response" "FAIL" "HTTP $HTTP_CODE"
fi

# 6.2 HTTPS Response
HTTPS_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://localhost/ || echo "000")
if [ "$HTTPS_CODE" = "200" ]; then
    log_result "HTTPS Response" "PASS" "HTTPS $HTTPS_CODE"
else
    log_result "HTTPS Response" "FAIL" "HTTPS $HTTPS_CODE"
fi

# 6.3 Response Time
RESPONSE_TIME=$(curl -w "%{time_total}" -o /dev/null -s http://localhost/ || echo "999")
RESPONSE_TIME_MS=$(echo "$RESPONSE_TIME * 1000" | bc | cut -d. -f1)

if [ $RESPONSE_TIME_MS -lt $RESPONSE_TIME_THRESHOLD ]; then
    log_result "Response Time" "PASS" "${RESPONSE_TIME_MS}ms"
else
    log_result "Response Time" "FAIL" "${RESPONSE_TIME_MS}ms exceeds ${RESPONSE_TIME_THRESHOLD}ms threshold"
fi

echo ""
echo "🔒 7. Security Validation"
echo "========================="

# 7.1 SSL Certificate
if openssl s_client -connect localhost:443 -servername localhost < /dev/null 2>/dev/null | grep -q "Verify return code: 0"; then
    log_result "SSL Certificate" "PASS" "Valid SSL certificate"
else
    log_result "SSL Certificate" "WARN" "SSL certificate validation failed"
fi

# 7.2 File Permissions
SENSITIVE_FILES=("$APP_DIR/.env" "$APP_DIR/storage")
for file in "${SENSITIVE_FILES[@]}"; do
    if [ -e "$file" ]; then
        PERMS=$(stat -c "%a" "$file")
        if [[ "$PERMS" =~ ^[0-7][0-7][0-5]$ ]]; then
            log_result "File Permissions" "PASS" "$file has secure permissions ($PERMS)"
        else
            log_result "File Permissions" "WARN" "$file has permissions $PERMS"
        fi
    fi
done

# 7.3 Firewall Status
if ufw status | grep -q "Status: active"; then
    log_result "Firewall" "PASS" "UFW is active"
else
    log_result "Firewall" "WARN" "UFW is not active"
fi

echo ""
echo "🚀 8. Application Validation"
echo "============================"

if [ -f $APP_DIR/artisan ]; then
    # 8.1 Application Health Check
    if php $APP_DIR/artisan monitoring:health-check --format=json > /tmp/app-health.json 2>/dev/null; then
        APP_STATUS=$(jq -r '.overall_status' /tmp/app-health.json 2>/dev/null || echo "unknown")
        case $APP_STATUS in
            "healthy")
                log_result "Application Health" "PASS" "All systems healthy"
                ;;
            "warning")
                log_result "Application Health" "WARN" "Some warnings detected"
                ;;
            "critical")
                log_result "Application Health" "FAIL" "Critical issues detected"
                ;;
            *)
                log_result "Application Health" "WARN" "Status: $APP_STATUS"
                ;;
        esac
        rm -f /tmp/app-health.json
    else
        log_result "Application Health" "FAIL" "Health check command failed"
    fi
    
    # 8.2 Cache Functionality
    if php $APP_DIR/artisan tinker --execute="Cache::put('validation_test', 'success', 60); echo Cache::get('validation_test'); Cache::forget('validation_test'); exit;" 2>/dev/null | grep -q "success"; then
        log_result "Application Cache" "PASS" "Cache operations working"
    else
        log_result "Application Cache" "FAIL" "Cache operations failed"
    fi
    
    # 8.3 Database Connectivity from App
    if php $APP_DIR/artisan tinker --execute="DB::select('SELECT 1 as test'); echo 'DB_OK'; exit;" 2>/dev/null | grep -q "DB_OK"; then
        log_result "Application Database" "PASS" "Database connectivity working"
    else
        log_result "Application Database" "FAIL" "Database connectivity failed"
    fi
else
    log_result "Application" "FAIL" "Application not found at $APP_DIR"
fi

echo ""
echo "📊 9. Performance Benchmarks"
echo "============================"

if [ -f $APP_DIR/artisan ]; then
    # Run performance benchmarks
    if php $APP_DIR/artisan monitoring:benchmarks --quick --export=/tmp/benchmark-results.json &>/dev/null; then
        log_result "Performance Benchmarks" "PASS" "Benchmarks completed successfully"
        
        # Extract key metrics
        if command -v jq &> /dev/null; then
            DB_TIME=$(jq -r '.database.simple_query_time // "N/A"' /tmp/benchmark-results.json)
            CACHE_TIME=$(jq -r '.cache.cache_hit_time // "N/A"' /tmp/benchmark-results.json)
            MEMORY_USAGE=$(jq -r '.memory.large_dataset_increase // "N/A"' /tmp/benchmark-results.json)
            
            log_result "Benchmark Results" "INFO" "DB: ${DB_TIME}ms, Cache: ${CACHE_TIME}ms, Memory: ${MEMORY_USAGE}MB"
        fi
        
        rm -f /tmp/benchmark-results.json
    else
        log_result "Performance Benchmarks" "WARN" "Benchmark command failed"
    fi
fi

echo ""
echo "🎯 10. Load Testing"
echo "==================="

# Simple load test with curl
if command -v ab &> /dev/null; then
    log_result "Load Testing Tool" "PASS" "Apache Bench available"
    
    # Run a simple load test
    AB_RESULT=$(ab -n 100 -c 5 -q http://localhost/ 2>/dev/null | grep "Requests per second" | awk '{print $4}')
    if [ ! -z "$AB_RESULT" ]; then
        log_result "Load Test" "PASS" "$AB_RESULT requests/second"
    else
        log_result "Load Test" "WARN" "Load test failed"
    fi
else
    log_result "Load Testing Tool" "WARN" "Apache Bench not available"
fi

# Finish validation report
echo "  }," >> $VALIDATION_REPORT
echo "  \"summary\": {" >> $VALIDATION_REPORT
echo "    \"exit_code\": $EXIT_CODE," >> $VALIDATION_REPORT
echo "    \"status\": \"$([ $EXIT_CODE -eq 0 ] && echo "PASS" || echo "FAIL")\"" >> $VALIDATION_REPORT
echo "  }" >> $VALIDATION_REPORT
echo "}" >> $VALIDATION_REPORT

echo ""
echo "📋 Validation Summary"
echo "===================="

TOTAL_TESTS=$(grep -c "PASS\|FAIL\|WARN" $VALIDATION_LOG)
PASSED_TESTS=$(grep -c "PASS" $VALIDATION_LOG)
FAILED_TESTS=$(grep -c "FAIL" $VALIDATION_LOG)
WARNING_TESTS=$(grep -c "WARN" $VALIDATION_LOG)

echo -e "Total Tests: $TOTAL_TESTS"
echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
echo -e "${YELLOW}Warnings: $WARNING_TESTS${NC}"
echo -e "${RED}Failed: $FAILED_TESTS${NC}"

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ VALIDATION PASSED${NC}"
    echo "Production deployment validation successful!"
else
    echo -e "${RED}❌ VALIDATION FAILED${NC}"
    echo "Production deployment has issues that need attention!"
fi

echo ""
echo "📁 Reports saved to:"
echo "  Detailed log: $VALIDATION_LOG"
echo "  JSON report: $VALIDATION_REPORT"

# Cleanup
rm -f /tmp/app-health.json /tmp/benchmark-results.json

exit $EXIT_CODE
