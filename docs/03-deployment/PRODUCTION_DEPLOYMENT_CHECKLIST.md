# Production Deployment Checklist & Monitoring Alert System

**Project**: Ekspedisi Quran Application  
**Version**: 2.0.0  
**Date**: August 15, 2025  
**Target**: Production Environment

## Executive Summary

This document provides a comprehensive checklist and monitoring system for deploying the performance-optimized Ekspedisi Quran application to production. All optimizations including caching, queue processing, database optimization, and monitoring systems are validated for production deployment.

## 🎯 Quick Reference

### Critical Thresholds
- **Response Time**: < 200ms (P95)
- **Memory Usage**: < 80% of available
- **Disk Usage**: < 85% of capacity
- **Queue Backlog**: < 1,000 jobs
- **Cache Hit Rate**: > 85%
- **Database Response**: < 100ms
- **Uptime Target**: 99.9%

---

## 1. Pre-Deployment Checklist

### 1.1 Infrastructure Requirements ✅

#### Server Specifications
- [ ] **CPU**: 4+ cores (2.4GHz minimum, 8 cores recommended)
- [ ] **RAM**: 8GB minimum (16GB+ recommended)
- [ ] **Storage**: 100GB SSD (200GB+ NVMe recommended)
- [ ] **Network**: 1Gbps connection (10Gbps for high traffic)
- [ ] **OS**: Ubuntu 22.04 LTS or CentOS 8+

#### Software Dependencies
- [ ] **PHP**: 8.1+ with required extensions
  ```bash
  php -v
  php -m | grep -E "(redis|mysql|mbstring|xml|zip|curl|gd|intl|bcmath)"
  ```
- [ ] **MySQL**: 8.0+ configured and optimized
- [ ] **Redis**: Latest stable with persistence enabled
- [ ] **Nginx**: Latest with security headers
- [ ] **Supervisor**: For queue worker management
- [ ] **Node.js**: 16+ for asset compilation

#### Security Requirements
- [ ] **SSL Certificate**: Valid and auto-renewing
- [ ] **Firewall**: UFW configured (ports 22, 80, 443 only)
- [ ] **Fail2Ban**: Installed and configured
- [ ] **SSH**: Key-based authentication only
- [ ] **File Permissions**: Proper ownership and permissions

### 1.2 Environment Configuration ✅

#### Database Setup
- [ ] **Database Created**: `ekspedisi_quran` with UTF8MB4
- [ ] **User Created**: Dedicated DB user with proper privileges
- [ ] **Indexes Applied**: All performance indexes installed
- [ ] **Configuration**: Optimized MySQL settings applied
- [ ] **Monitoring User**: Created for database monitoring

#### Redis Configuration
- [ ] **Redis Running**: Service active and accessible
- [ ] **Password Protected**: Strong password configured
- [ ] **Memory Limit**: Set to appropriate value (2GB+)
- [ ] **Persistence**: RDB snapshots enabled
- [ ] **Log Rotation**: Configured for Redis logs

#### PHP-FPM Pool
- [ ] **Dedicated Pool**: Created for ekspedisi application
- [ ] **Memory Limit**: Set to 512MB+
- [ ] **Process Manager**: Dynamic with proper limits
- [ ] **Error Logging**: Configured and accessible
- [ ] **Security**: Dangerous functions disabled

### 1.3 Application Requirements ✅

#### Code Repository
- [ ] **Git Repository**: Accessible from production server
- [ ] **Branch Strategy**: Main/production branch ready
- [ ] **Dependencies**: composer.lock and package-lock.json committed
- [ ] **Environment**: .env.production template ready

#### Performance Optimizations
- [ ] **Database Indexes**: All critical indexes applied
- [ ] **Eager Loading**: N+1 query prevention implemented
- [ ] **Cache Strategy**: Multi-level caching configured
- [ ] **Queue System**: Background job processing ready
- [ ] **Image Optimization**: Compression and resizing enabled

---

## 2. Environment Configuration Validation

### 2.1 System Configuration ✅

#### System Limits
```bash
# Verify system limits
ulimit -n    # Should be 65535+
ulimit -u    # Should be 32768+
cat /proc/sys/fs/file-max    # Should be 2M+
```

#### Network Configuration
```bash
# Test network performance
ping -c 4 8.8.8.8
wget -O /dev/null http://speedtest.wdc01.softlayer.com/downloads/test10.zip
```

#### Disk Performance
```bash
# Test disk I/O
dd if=/dev/zero of=/tmp/test bs=1M count=1024 oflag=direct
rm /tmp/test
```

### 2.2 Service Configuration ✅

#### MySQL Validation
```bash
# Test MySQL configuration
mysql -u ekspedisi -p ekspedisi_quran -e "SELECT 1;"
mysql -u ekspedisi -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
mysql -u ekspedisi -p -e "SHOW VARIABLES LIKE 'query_cache_size';"
```

#### Redis Validation
```bash
# Test Redis connectivity
redis-cli -a YOUR_PASSWORD ping
redis-cli -a YOUR_PASSWORD info memory
redis-cli -a YOUR_PASSWORD config get maxmemory
```

#### Nginx Validation
```bash
# Test Nginx configuration
nginx -t
curl -I http://localhost
openssl s_client -connect localhost:443 -servername your-domain.com
```

### 2.3 Security Validation ✅

#### SSL/TLS Configuration
```bash
# Test SSL certificate
openssl x509 -in /etc/letsencrypt/live/your-domain.com/fullchain.pem -text -noout
curl -I https://your-domain.com
```

#### Firewall Status
```bash
# Verify firewall rules
ufw status verbose
iptables -L
```

#### File Permissions
```bash
# Check critical file permissions
ls -la /var/www/ekspedisi-quran/.env
ls -la /var/www/ekspedisi-quran/storage/
ls -la /var/www/ekspedisi-quran/bootstrap/cache/
```

---

## 3. Deployment Process

### 3.1 Backup Current State ✅

#### Database Backup
```bash
# Create database backup
mysqldump -u ekspedisi -p ekspedisi_quran > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### File System Backup
```bash
# Backup storage and configuration
tar -czf storage_backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/
cp .env env_backup_$(date +%Y%m%d_%H%M%S)
```

#### Service Status Snapshot
```bash
# Document current service status
systemctl status nginx php8.1-fpm mysql redis-server supervisor > services_status_$(date +%Y%m%d_%H%M%S).txt
```

### 3.2 Code Deployment ✅

#### Repository Update
```bash
# Update application code
git fetch origin
git reset --hard origin/main
git log --oneline -5  # Verify deployment
```

#### Dependencies Installation
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js dependencies
npm ci --only=production

# Verify installations
composer show | head -10
npm list --depth=0
```

### 3.3 Database Migration ✅

#### Migration Execution
```bash
# Run database migrations
php artisan migrate --force --no-interaction

# Verify migration status
php artisan migrate:status
```

#### Seed Critical Data
```bash
# Seed essential data if needed
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=StatusPengirimanSeeder --force
php artisan db:seed --class=JenisQuranSeeder --force
```

### 3.4 Configuration Optimization ✅

#### Laravel Optimization
```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify optimizations
ls -la bootstrap/cache/
```

#### Asset Compilation
```bash
# Build production assets
npm run build

# Verify assets
ls -la public/build/
du -sh public/build/
```

### 3.5 Service Configuration ✅

#### Queue Workers Setup
```bash
# Configure supervisor for queue workers
supervisorctl reread
supervisorctl update
supervisorctl status ekspedisi-queue-*

# Start all queue workers
supervisorctl start ekspedisi-queue-high:*
supervisorctl start ekspedisi-queue-default:*
supervisorctl start ekspedisi-queue-low:*
```

#### Service Restart
```bash
# Restart services in proper order
systemctl restart redis-server
systemctl restart mysql
systemctl restart php8.1-fpm
systemctl reload nginx
systemctl restart supervisor
```

---

## 4. Post-Deployment Validation

### 4.1 Application Health Check ✅

#### Basic Connectivity
```bash
# Test basic application response
curl -f http://localhost/
curl -f https://your-domain.com/

# Test API endpoints
curl -f https://your-domain.com/api/health
```

#### Database Connectivity
```bash
# Test database connection
php artisan tinker --execute="
DB::select('SELECT 1 as test');
echo 'Database: Connected';
exit;
"
```

#### Cache Functionality
```bash
# Test cache system
php artisan tinker --execute="
Cache::put('deploy_test', 'success', 60);
echo 'Cache result: ' . Cache::get('deploy_test');
Cache::forget('deploy_test');
exit;
"
```

#### Queue System
```bash
# Test queue processing
php artisan queue:work --once --timeout=10
supervisorctl status ekspedisi-queue-*
```

### 4.2 Performance Validation ✅

#### Response Time Testing
```bash
# Test page load times
curl -w "@curl-format.txt" -o /dev/null -s https://your-domain.com/

# Where curl-format.txt contains:
echo "time_total: %{time_total}\ntime_connect: %{time_connect}\ntime_appconnect: %{time_appconnect}\ntime_pretransfer: %{time_pretransfer}\ntime_starttransfer: %{time_starttransfer}" > curl-format.txt
```

#### Load Testing
```bash
# Basic load test
ab -n 100 -c 10 https://your-domain.com/

# Monitor during load test
htop
iotop
```

#### Database Performance
```bash
# Check slow query log
tail -f /var/log/mysql/slow.log

# Monitor database performance
mysqladmin -u monitor -p processlist
mysqladmin -u monitor -p status | grep -E "(Queries|Slow_queries)"
```

### 4.3 Security Validation ✅

#### SSL Configuration
```bash
# Test SSL configuration
openssl s_client -connect your-domain.com:443 -servername your-domain.com < /dev/null

# Check security headers
curl -I https://your-domain.com/ | grep -E "(Strict-Transport-Security|X-Frame-Options|X-Content-Type-Options)"
```

#### Access Control
```bash
# Test admin panel access
curl -f https://your-domain.com/admin/

# Verify file access restrictions
curl -f https://your-domain.com/.env  # Should return 403/404
curl -f https://your-domain.com/storage/logs/  # Should return 403/404
```

---

## 5. Monitoring Alert Configuration

### 5.1 System Health Monitoring ✅

#### Critical System Alerts
```bash
# Configure system monitoring
cat > /etc/monit/conf.d/ekspedisi-system.conf << 'EOL'
# System monitoring configuration
check system localhost
    if loadavg (5min) > 4 then alert
    if memory usage > 90% then alert
    if swap usage > 50% then alert
    if cpu usage (user) > 80% for 5 cycles then alert

check filesystem rootfs with path /
    if space usage > 85% then alert
    if space usage > 95% then exec "/usr/local/bin/cleanup-storage.sh"

check filesystem storage with path /var/www/ekspedisi-quran/storage
    if space usage > 90% then alert
EOL
```

#### Service Monitoring
```bash
# Configure service monitoring
cat > /etc/monit/conf.d/ekspedisi-services.conf << 'EOL'
# Service monitoring
check process nginx with pidfile /var/run/nginx.pid
    start program = "/bin/systemctl start nginx"
    stop program = "/bin/systemctl stop nginx"
    if failed host localhost port 80 protocol http then restart
    if failed host localhost port 443 type tcpssl protocol http then restart

check process mysql with pidfile /var/run/mysqld/mysqld.pid
    start program = "/bin/systemctl start mysql"
    stop program = "/bin/systemctl stop mysql"
    if failed host localhost port 3306 protocol mysql then restart

check process redis with pidfile /var/run/redis/redis-server.pid
    start program = "/bin/systemctl start redis-server"
    stop program = "/bin/systemctl stop redis-server"
    if failed host localhost port 6379 then restart

check process php-fpm with pidfile /var/run/php/php8.1-fpm.pid
    start program = "/bin/systemctl start php8.1-fpm"
    stop program = "/bin/systemctl stop php8.1-fpm"
EOL
```

### 5.2 Application Performance Monitoring ✅

#### Response Time Monitoring
```bash
# Create response time monitoring script
cat > /usr/local/bin/monitor-response-time.sh << 'EOL'
#!/bin/bash

THRESHOLD=2000  # 2 seconds
LOG_FILE="/var/log/ekspedisi/response-time.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"

# Test main page response time
RESPONSE_TIME=$(curl -w "%{time_total}" -o /dev/null -s https://your-domain.com/)
RESPONSE_TIME_MS=$(echo "$RESPONSE_TIME * 1000" | bc | cut -d. -f1)

echo "[$(date)] Response time: ${RESPONSE_TIME_MS}ms" >> $LOG_FILE

if [ $RESPONSE_TIME_MS -gt $THRESHOLD ]; then
    echo "[$(date)] ALERT: Slow response time ${RESPONSE_TIME_MS}ms" >> $ALERT_FILE
    # Send notification
    echo "Application response time is ${RESPONSE_TIME_MS}ms (threshold: ${THRESHOLD}ms)" | \
        mail -s "Performance Alert: Slow Response Time" admin@your-domain.com
fi
EOL

chmod +x /usr/local/bin/monitor-response-time.sh

# Add to crontab (every 5 minutes)
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/monitor-response-time.sh") | crontab -
```

#### Database Performance Monitoring
```bash
# Create database monitoring script
cat > /usr/local/bin/monitor-database.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/database-monitor.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"

# Check database response time
DB_RESPONSE=$(mysql -u monitor -p$MYSQL_MONITOR_PASSWORD -e "SELECT BENCHMARK(1000, SHA1('test'));" 2>&1 | grep -o "[0-9.]*" | tail -1)

# Check slow queries
SLOW_QUERIES=$(mysql -u monitor -p$MYSQL_MONITOR_PASSWORD -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';" | awk 'NR==2 {print $2}')

# Check connections
CONNECTIONS=$(mysql -u monitor -p$MYSQL_MONITOR_PASSWORD -e "SHOW GLOBAL STATUS LIKE 'Threads_connected';" | awk 'NR==2 {print $2}')

echo "[$(date)] DB Response: ${DB_RESPONSE}s, Slow Queries: ${SLOW_QUERIES}, Connections: ${CONNECTIONS}" >> $LOG_FILE

# Alert on high connection count
if [ $CONNECTIONS -gt 150 ]; then
    echo "[$(date)] ALERT: High database connections: ${CONNECTIONS}" >> $ALERT_FILE
fi

# Alert on slow queries increase
if [ $SLOW_QUERIES -gt 100 ]; then
    echo "[$(date)] ALERT: High slow query count: ${SLOW_QUERIES}" >> $ALERT_FILE
fi
EOL

chmod +x /usr/local/bin/monitor-database.sh

# Add to crontab (every 10 minutes)
(crontab -l 2>/dev/null; echo "*/10 * * * * /usr/local/bin/monitor-database.sh") | crontab -
```

### 5.3 Queue System Monitoring ✅

#### Queue Health Monitoring
```bash
# Create queue monitoring script
cat > /usr/local/bin/monitor-queues.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/queue-monitor.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"
APP_DIR="/var/www/ekspedisi-quran"

# Check queue sizes
HIGH_QUEUE=$(php $APP_DIR/artisan queue:size redis high 2>/dev/null || echo 0)
DEFAULT_QUEUE=$(php $APP_DIR/artisan queue:size redis default 2>/dev/null || echo 0)
LOW_QUEUE=$(php $APP_DIR/artisan queue:size redis low 2>/dev/null || echo 0)

# Check failed jobs
FAILED_JOBS=$(php $APP_DIR/artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo 0)

echo "[$(date)] Queues - High: $HIGH_QUEUE, Default: $DEFAULT_QUEUE, Low: $LOW_QUEUE, Failed: $FAILED_JOBS" >> $LOG_FILE

# Alert on high queue sizes
if [ $HIGH_QUEUE -gt 500 ]; then
    echo "[$(date)] ALERT: High queue backlog: $HIGH_QUEUE" >> $ALERT_FILE
fi

if [ $DEFAULT_QUEUE -gt 1000 ]; then
    echo "[$(date)] ALERT: Default queue backlog: $DEFAULT_QUEUE" >> $ALERT_FILE
fi

if [ $FAILED_JOBS -gt 50 ]; then
    echo "[$(date)] ALERT: High failed job count: $FAILED_JOBS" >> $ALERT_FILE
fi

# Check supervisor processes
QUEUE_PROCESSES=$(supervisorctl status ekspedisi-queue-* | grep RUNNING | wc -l)
EXPECTED_PROCESSES=6  # Adjust based on configuration

if [ $QUEUE_PROCESSES -lt $EXPECTED_PROCESSES ]; then
    echo "[$(date)] ALERT: Queue workers not running: $QUEUE_PROCESSES/$EXPECTED_PROCESSES" >> $ALERT_FILE
    supervisorctl restart ekspedisi-queue-*
fi
EOL

chmod +x /usr/local/bin/monitor-queues.sh

# Add to crontab (every 5 minutes)
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/monitor-queues.sh") | crontab -
```

### 5.4 Business Metrics Monitoring ✅

#### Application Metrics
```bash
# Create business metrics monitoring
cat > /usr/local/bin/monitor-business-metrics.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/business-metrics.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"
APP_DIR="/var/www/ekspedisi-quran"

# Run application health check
php $APP_DIR/artisan monitoring:health-check --format=json > /tmp/health-check.json

# Extract metrics
OVERALL_STATUS=$(jq -r '.overall_status' /tmp/health-check.json)
CRITICAL_COUNT=$(jq -r '.checks | to_entries | map(select(.value.status == "critical")) | length' /tmp/health-check.json)
WARNING_COUNT=$(jq -r '.checks | to_entries | map(select(.value.status == "warning")) | length' /tmp/health-check.json)

echo "[$(date)] Health Check - Status: $OVERALL_STATUS, Critical: $CRITICAL_COUNT, Warnings: $WARNING_COUNT" >> $LOG_FILE

# Alert on critical status
if [ "$OVERALL_STATUS" = "critical" ]; then
    echo "[$(date)] ALERT: Application health check critical" >> $ALERT_FILE
    # Send detailed health report
    php $APP_DIR/artisan monitoring:health-check --export=/tmp/health-report.json
    echo "Critical application health issues detected. See attached report." | \
        mail -s "CRITICAL: Application Health Alert" -a /tmp/health-report.json admin@your-domain.com
fi

# Clean up temporary files
rm -f /tmp/health-check.json /tmp/health-report.json
EOL

chmod +x /usr/local/bin/monitor-business-metrics.sh

# Add to crontab (every 15 minutes)
(crontab -l 2>/dev/null; echo "*/15 * * * * /usr/local/bin/monitor-business-metrics.sh") | crontab -
```

### 5.5 Storage and Capacity Monitoring ✅

#### Storage Monitoring
```bash
# Create storage monitoring script
cat > /usr/local/bin/monitor-storage.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/storage-monitor.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"
APP_DIR="/var/www/ekspedisi-quran"

# Check disk usage
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
STORAGE_USAGE=$(df /var/www/ekspedisi-quran/storage | awk 'NR==2 {print $5}' | sed 's/%//')

# Check storage directory sizes
LOGS_SIZE=$(du -sh $APP_DIR/storage/logs/ | cut -f1)
UPLOADS_SIZE=$(du -sh $APP_DIR/storage/app/public/ | cut -f1)
CACHE_SIZE=$(du -sh $APP_DIR/storage/framework/cache/ | cut -f1)

echo "[$(date)] Storage - Disk: ${DISK_USAGE}%, Storage: ${STORAGE_USAGE}%, Logs: $LOGS_SIZE, Uploads: $UPLOADS_SIZE, Cache: $CACHE_SIZE" >> $LOG_FILE

# Alert on high disk usage
if [ $DISK_USAGE -gt 85 ]; then
    echo "[$(date)] ALERT: High disk usage: ${DISK_USAGE}%" >> $ALERT_FILE
    # Trigger cleanup
    php $APP_DIR/artisan storage:cleanup --force
fi

if [ $STORAGE_USAGE -gt 90 ]; then
    echo "[$(date)] ALERT: High storage usage: ${STORAGE_USAGE}%" >> $ALERT_FILE
    # Run storage optimization
    php $APP_DIR/artisan storage:optimize --force
fi

# Run storage monitoring service
php $APP_DIR/artisan storage:monitor --alerts-only >> $LOG_FILE 2>&1
EOL

chmod +x /usr/local/bin/monitor-storage.sh

# Add to crontab (every hour)
(crontab -l 2>/dev/null; echo "0 * * * * /usr/local/bin/monitor-storage.sh") | crontab -
```

---

## 6. Emergency Response Procedures

### 6.1 Critical System Failures ⚠️

#### Database Connection Failure
```bash
# Emergency database recovery script
cat > /usr/local/bin/emergency-db-recovery.sh << 'EOL'
#!/bin/bash

echo "EMERGENCY: Database connection failure detected"

# Check MySQL service status
systemctl status mysql

# Attempt to restart MySQL
systemctl restart mysql

# Wait for service to start
sleep 10

# Test connection
mysql -u ekspedisi -p$DB_PASSWORD ekspedisi_quran -e "SELECT 1;" || {
    echo "CRITICAL: Database restart failed"
    # Restore from backup if needed
    # mysql -u root -p ekspedisi_quran < /var/backups/ekspedisi-quran/latest.sql
}

# Restart dependent services
systemctl restart php8.1-fpm
supervisorctl restart ekspedisi-queue-*

echo "Database recovery attempt completed"
EOL

chmod +x /usr/local/bin/emergency-db-recovery.sh
```

#### High Load Emergency Response
```bash
# Emergency load management script
cat > /usr/local/bin/emergency-load-response.sh << 'EOL'
#!/bin/bash

LOAD_THRESHOLD=8.0
CURRENT_LOAD=$(uptime | awk '{print $10}' | sed 's/,//')

if (( $(echo "$CURRENT_LOAD > $LOAD_THRESHOLD" | bc -l) )); then
    echo "EMERGENCY: High system load detected: $CURRENT_LOAD"
    
    # Enable maintenance mode
    php /var/www/ekspedisi-quran/artisan down --refresh=15
    
    # Reduce queue worker processes
    supervisorctl stop ekspedisi-queue-low:*
    supervisorctl stop ekspedisi-queue-default:1
    
    # Clear non-essential caches to free memory
    php /var/www/ekspedisi-quran/artisan cache:clear
    
    # Wait for load to decrease
    sleep 60
    
    # Check load again
    NEW_LOAD=$(uptime | awk '{print $10}' | sed 's/,//')
    if (( $(echo "$NEW_LOAD < $LOAD_THRESHOLD" | bc -l) )); then
        # Restore normal operations
        supervisorctl start ekspedisi-queue-*
        php /var/www/ekspedisi-quran/artisan up
        echo "System load normalized: $NEW_LOAD"
    else
        echo "CRITICAL: System load still high: $NEW_LOAD"
        # Send emergency notification
        echo "System remains under high load. Manual intervention required." | \
            mail -s "EMERGENCY: System Load Critical" admin@your-domain.com
    fi
fi
EOL

chmod +x /usr/local/bin/emergency-load-response.sh
```

### 6.2 Performance Degradation Response ⚠️

#### Cache System Failure
```bash
# Cache emergency recovery
cat > /usr/local/bin/emergency-cache-recovery.sh << 'EOL'
#!/bin/bash

echo "EMERGENCY: Cache system failure detected"

# Restart Redis
systemctl restart redis-server

# Wait for Redis to start
sleep 5

# Test Redis connectivity
redis-cli -a $REDIS_PASSWORD ping || {
    echo "CRITICAL: Redis restart failed"
    # Fall back to file cache temporarily
    php /var/www/ekspedisi-quran/artisan cache:clear
    sed -i 's/CACHE_STORE=redis/CACHE_STORE=file/' /var/www/ekspedisi-quran/.env
    php /var/www/ekspedisi-quran/artisan config:cache
}

# Restart PHP-FPM to clear opcache
systemctl restart php8.1-fpm

echo "Cache recovery attempt completed"
EOL

chmod +x /usr/local/bin/emergency-cache-recovery.sh
```

#### Queue System Recovery
```bash
# Queue emergency recovery
cat > /usr/local/bin/emergency-queue-recovery.sh << 'EOL'
#!/bin/bash

echo "EMERGENCY: Queue system failure detected"

# Check supervisor status
supervisorctl status ekspedisi-queue-*

# Restart all queue workers
supervisorctl restart ekspedisi-queue-*

# Wait for workers to start
sleep 10

# Check if workers are running
RUNNING_WORKERS=$(supervisorctl status ekspedisi-queue-* | grep RUNNING | wc -l)
EXPECTED_WORKERS=6

if [ $RUNNING_WORKERS -lt $EXPECTED_WORKERS ]; then
    echo "CRITICAL: Only $RUNNING_WORKERS/$EXPECTED_WORKERS queue workers running"
    
    # Clear failed jobs that might be blocking
    php /var/www/ekspedisi-quran/artisan queue:flush
    
    # Restart supervisor
    systemctl restart supervisor
fi

echo "Queue recovery attempt completed"
EOL

chmod +x /usr/local/bin/emergency-queue-recovery.sh
```

### 6.3 Rollback Procedures 🔄

#### Application Rollback
```bash
# Application rollback script
cat > /usr/local/bin/rollback-application.sh << 'EOL'
#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $0 <backup_date>"
    echo "Available backups:"
    ls -la /var/backups/ekspedisi-quran/
    exit 1
fi

BACKUP_DATE=$1
BACKUP_DIR="/var/backups/ekspedisi-quran"
APP_DIR="/var/www/ekspedisi-quran"

echo "ROLLBACK: Starting application rollback to $BACKUP_DATE"

# Put application in maintenance mode
php $APP_DIR/artisan down

# Restore database
mysql -u ekspedisi -p$DB_PASSWORD ekspedisi_quran < $BACKUP_DIR/database_$BACKUP_DATE.sql

# Restore storage
tar -xzf $BACKUP_DIR/storage_$BACKUP_DATE.tar.gz -C $APP_DIR/

# Restore environment
cp $BACKUP_DIR/env_$BACKUP_DATE $APP_DIR/.env

# Clear caches
php $APP_DIR/artisan config:clear
php $APP_DIR/artisan cache:clear

# Restart services
systemctl restart php8.1-fpm
supervisorctl restart ekspedisi-queue-*

# Bring application back online
php $APP_DIR/artisan up

echo "ROLLBACK: Application rollback completed"
EOL

chmod +x /usr/local/bin/rollback-application.sh
```

---

## 7. Operational Procedures

### 7.1 Daily Operations ✅

#### Morning Health Check
```bash
# Daily morning health check script
cat > /usr/local/bin/daily-health-check.sh << 'EOL'
#!/bin/bash

REPORT_FILE="/tmp/daily-health-report.txt"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "=== Daily Health Check Report - $DATE ===" > $REPORT_FILE

# System status
echo "System Status:" >> $REPORT_FILE
uptime >> $REPORT_FILE
free -h >> $REPORT_FILE
df -h >> $REPORT_FILE

# Service status
echo -e "\nService Status:" >> $REPORT_FILE
systemctl is-active nginx php8.1-fpm mysql redis-server supervisor >> $REPORT_FILE

# Application health
echo -e "\nApplication Health:" >> $REPORT_FILE
php /var/www/ekspedisi-quran/artisan monitoring:health-check >> $REPORT_FILE

# Queue status
echo -e "\nQueue Status:" >> $REPORT_FILE
supervisorctl status ekspedisi-queue-* >> $REPORT_FILE

# Performance metrics
echo -e "\nPerformance Metrics:" >> $REPORT_FILE
php /var/www/ekspedisi-quran/artisan monitoring:benchmarks --quick >> $REPORT_FILE

# Send report
mail -s "Daily Health Check Report" admin@your-domain.com < $REPORT_FILE

rm $REPORT_FILE
EOL

chmod +x /usr/local/bin/daily-health-check.sh

# Add to crontab (8 AM daily)
(crontab -l 2>/dev/null; echo "0 8 * * * /usr/local/bin/daily-health-check.sh") | crontab -
```

### 7.2 Weekly Operations ✅

#### Performance Baseline Update
```bash
# Weekly performance baseline update
cat > /usr/local/bin/weekly-baseline-update.sh << 'EOL'
#!/bin/bash

echo "Starting weekly performance baseline update"

# Run comprehensive benchmarks
php /var/www/ekspedisi-quran/artisan monitoring:benchmarks --comprehensive --set-baselines

# Generate performance report
php /var/www/ekspedisi-quran/artisan monitoring:report --weekly --export=/tmp/weekly-performance-report.json

# Database optimization
mysqlcheck --optimize ekspedisi_quran

# Clear old logs and optimize storage
php /var/www/ekspedisi-quran/artisan storage:cleanup --old-logs
php /var/www/ekspedisi-quran/artisan storage:optimize

echo "Weekly baseline update completed"
EOL

chmod +x /usr/local/bin/weekly-baseline-update.sh

# Add to crontab (Sunday 2 AM)
(crontab -l 2>/dev/null; echo "0 2 * * 0 /usr/local/bin/weekly-baseline-update.sh") | crontab -
```

### 7.3 Maintenance Windows ✅

#### Planned Maintenance Script
```bash
# Planned maintenance script
cat > /usr/local/bin/planned-maintenance.sh << 'EOL'
#!/bin/bash

echo "Starting planned maintenance window"

# Enable maintenance mode
php /var/www/ekspedisi-quran/artisan down --refresh=15 --secret="maintenance-$(date +%s)"

# Create backup before maintenance
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
mysqldump ekspedisi_quran > /var/backups/ekspedisi-quran/maintenance_$BACKUP_DATE.sql
tar -czf /var/backups/ekspedisi-quran/maintenance_storage_$BACKUP_DATE.tar.gz storage/

# System updates (if scheduled)
if [ "$1" = "system-update" ]; then
    apt update && apt upgrade -y
fi

# Application updates (if scheduled)
if [ "$1" = "app-update" ]; then
    /usr/local/bin/update-ekspedisi.sh
fi

# Database maintenance
mysqlcheck --repair --optimize ekspedisi_quran

# Storage cleanup and optimization
php /var/www/ekspedisi-quran/artisan storage:cleanup --comprehensive
php /var/www/ekspedisi-quran/artisan storage:optimize

# Clear and warm caches
php /var/www/ekspedisi-quran/artisan cache:clear
php /var/www/ekspedisi-quran/artisan cache:manage warm

# Restart all services
systemctl restart redis-server
systemctl restart mysql  
systemctl restart php8.1-fpm
systemctl reload nginx
systemctl restart supervisor

# Wait for services to stabilize
sleep 30

# Disable maintenance mode
php /var/www/ekspedisi-quran/artisan up

# Run post-maintenance health check
php /var/www/ekspedisi-quran/artisan monitoring:health-check --comprehensive

echo "Planned maintenance completed"
EOL

chmod +x /usr/local/bin/planned-maintenance.sh
```

---

## 8. Performance Validation Scripts

### 8.1 Automated Validation Suite ✅

#### Comprehensive Performance Test
```bash
# Create comprehensive performance validation
cat > /usr/local/bin/validate-performance.sh << 'EOL'
#!/bin/bash

RESULTS_FILE="/tmp/performance-validation-$(date +%Y%m%d_%H%M%S).json"

echo "Starting comprehensive performance validation..."

# Run application performance tests
php /var/www/ekspedisi-quran/artisan monitoring:benchmarks --comprehensive --export=$RESULTS_FILE

# Extract key metrics
RESPONSE_TIME=$(jq -r '.database.simple_query_time' $RESULTS_FILE)
CACHE_HIT_TIME=$(jq -r '.cache.cache_hit_time' $RESULTS_FILE)
MEMORY_USAGE=$(jq -r '.memory.large_dataset_increase' $RESULTS_FILE)

# Validate against thresholds
VALIDATION_PASSED=true

if (( $(echo "$RESPONSE_TIME > 100" | bc -l) )); then
    echo "FAIL: Database response time too slow: ${RESPONSE_TIME}ms"
    VALIDATION_PASSED=false
fi

if (( $(echo "$CACHE_HIT_TIME > 5" | bc -l) )); then
    echo "FAIL: Cache response time too slow: ${CACHE_HIT_TIME}ms"  
    VALIDATION_PASSED=false
fi

if (( $(echo "$MEMORY_USAGE > 100" | bc -l) )); then
    echo "FAIL: Memory usage too high: ${MEMORY_USAGE}MB"
    VALIDATION_PASSED=false
fi

if [ "$VALIDATION_PASSED" = true ]; then
    echo "PASS: All performance validations passed"
    exit 0
else
    echo "FAIL: Performance validation failed"
    # Send alert
    echo "Performance validation failed. See attached report." | \
        mail -s "Performance Validation Alert" -a $RESULTS_FILE admin@your-domain.com
    exit 1
fi
EOL

chmod +x /usr/local/bin/validate-performance.sh
```

### 8.2 Continuous Monitoring ✅

#### Real-time Performance Monitor
```bash
# Real-time performance monitoring dashboard
cat > /usr/local/bin/performance-dashboard.sh << 'EOL'
#!/bin/bash

while true; do
    clear
    echo "=== Ekspedisi Quran Performance Dashboard ==="
    echo "Last updated: $(date)"
    echo "=============================================="
    
    # System metrics
    echo "System Load: $(uptime | awk '{print $10,$11,$12}')"
    echo "Memory Usage: $(free | awk 'NR==2{printf "%.0f%%", $3*100/$2}')"
    echo "Disk Usage: $(df / | awk 'NR==2{print $5}')"
    
    # Service status
    echo -e "\nServices:"
    for service in nginx php8.1-fpm mysql redis-server supervisor; do
        status=$(systemctl is-active $service)
        echo "  $service: $status"
    done
    
    # Queue status
    echo -e "\nQueue Workers:"
    supervisorctl status ekspedisi-queue-* | grep -E "(RUNNING|STOPPED|FATAL)"
    
    # Quick performance test
    echo -e "\nQuick Performance Test:"
    RESPONSE_TIME=$(curl -w "%{time_total}" -o /dev/null -s http://localhost/)
    echo "  Response Time: ${RESPONSE_TIME}s"
    
    # Redis info
    echo -e "\nRedis Status:"
    redis-cli -a $REDIS_PASSWORD info memory | grep used_memory_human
    
    sleep 10
done
EOL

chmod +x /usr/local/bin/performance-dashboard.sh
```

---

## 9. Troubleshooting Guides

### 9.1 Common Issues ❗

#### Slow Performance
**Symptoms**: Response times > 2 seconds
**Diagnosis**:
```bash
# Check system load
uptime

# Check memory usage
free -h

# Check slow queries
tail -f /var/log/mysql/slow.log

# Check PHP-FPM status
systemctl status php8.1-fpm
```

**Resolution**:
```bash
# Clear caches
php artisan cache:clear
php artisan view:clear

# Restart PHP-FPM
systemctl restart php8.1-fpm

# Optimize database
mysqlcheck --optimize ekspedisi_quran
```

#### Queue Processing Issues
**Symptoms**: Jobs stuck in queue, high failed job count
**Diagnosis**:
```bash
# Check queue workers
supervisorctl status ekspedisi-queue-*

# Check queue sizes
php artisan queue:size redis
php artisan queue:failed
```

**Resolution**:
```bash
# Restart queue workers
supervisorctl restart ekspedisi-queue-*

# Clear failed jobs if needed
php artisan queue:flush

# Process stuck jobs
php artisan queue:retry all
```

#### High Memory Usage
**Symptoms**: Memory usage > 90%
**Diagnosis**:
```bash
# Check process memory usage
ps aux --sort=-%mem | head -10

# Check PHP memory usage
php -r "echo 'Memory Limit: ' . ini_get('memory_limit') . PHP_EOL;"
```

**Resolution**:
```bash
# Restart PHP-FPM pool
systemctl restart php8.1-fpm

# Clear cache to free memory
php artisan cache:clear

# Optimize PHP configuration
# Edit /etc/php/8.1/fpm/conf.d/99-ekspedisi-optimization.ini
```

### 9.2 Emergency Contacts 📞

#### Escalation Procedure
1. **Level 1**: Development Team
2. **Level 2**: System Administrator
3. **Level 3**: DevOps Team Lead
4. **Level 4**: CTO/Technical Director

#### Contact Information
```
Development Team: dev-team@your-domain.com
System Admin: sysadmin@your-domain.com
DevOps Lead: devops-lead@your-domain.com
Emergency: +1-XXX-XXX-XXXX
```

---

## 10. Success Metrics & KPIs

### 10.1 Performance Targets ✅

| Metric | Target | Monitoring Method |
|--------|--------|-------------------|
| Response Time (P95) | < 200ms | Automated testing every 5 min |
| Database Query Time | < 100ms | Slow query log monitoring |
| Cache Hit Rate | > 85% | Redis monitoring |
| Memory Usage | < 80% | System monitoring |
| Disk Usage | < 85% | Automated alerts |
| Queue Processing | < 1000 backlog | Queue monitoring |
| Uptime | 99.9% | Service monitoring |
| Error Rate | < 1% | Application monitoring |

### 10.2 Business Metrics 📊

| Metric | Target | Frequency |
|--------|--------|-----------|
| Concurrent Users | 100+ supported | Real-time |
| Daily Transactions | Track trends | Daily |
| Certificate Generation | < 30s average | Per request |
| File Upload Success | > 99% | Daily |
| User Session Length | Track average | Weekly |

---

## Final Deployment Checklist

### Pre-Go-Live Validation ✅

- [ ] **Infrastructure**: All services configured and tested
- [ ] **Application**: Code deployed and optimized
- [ ] **Database**: Migrations applied, indexes created
- [ ] **Performance**: Benchmarks meet targets
- [ ] **Security**: SSL, firewall, access controls verified
- [ ] **Monitoring**: All alerts configured and tested
- [ ] **Backup**: Backup systems verified
- [ ] **Documentation**: All procedures documented
- [ ] **Team**: Emergency contacts and procedures shared

### Go-Live Checklist ✅

- [ ] **Final backup**: Complete system backup created
- [ ] **Performance test**: Load testing passed
- [ ] **Security scan**: Vulnerability assessment passed
- [ ] **Monitoring active**: All monitoring systems operational
- [ ] **Team on standby**: Support team available
- [ ] **Rollback plan**: Rollback procedures tested and ready

### Post-Go-Live Monitoring ✅

- [ ] **First 24 hours**: Intensive monitoring and support
- [ ] **First week**: Daily health checks and performance reviews
- [ ] **First month**: Weekly performance and stability reports
- [ ] **Ongoing**: Automated monitoring and alerting

---

## Summary

This comprehensive deployment checklist ensures successful production deployment of the performance-optimized Ekspedisi Quran application with:

✅ **Complete Infrastructure Setup**  
✅ **Comprehensive Monitoring & Alerting**  
✅ **Emergency Response Procedures**  
✅ **Performance Validation**  
✅ **Operational Excellence**  

The application is now ready for production deployment with enterprise-grade monitoring, performance optimization, and operational procedures.

---

**Document Version**: 1.0  
**Created**: August 15, 2025  
**Owner**: DevOps Team  
**Review Cycle**: Monthly  
**Next Review**: September 15, 2025
