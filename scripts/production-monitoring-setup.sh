#!/bin/bash

# Production Monitoring Setup Script
# Sets up comprehensive monitoring and alerting for production deployment

set -e

echo "🔧 Setting up Production Monitoring System..."
echo "=============================================="

# Configuration
MONITORING_DIR="/etc/ekspedisi/monitoring"
LOG_DIR="/var/log/ekspedisi"
SCRIPTS_DIR="/usr/local/bin"
ALERT_EMAIL=${ALERT_EMAIL:-"admin@your-domain.com"}
APP_DIR="/var/www/ekspedisi-quran"

# Create directories
sudo mkdir -p $MONITORING_DIR $LOG_DIR
sudo chown ekspedisi:ekspedisi $LOG_DIR

echo "📁 Creating monitoring directories..."

# 1. System Health Monitoring
echo "🏥 Setting up system health monitoring..."

cat > /tmp/system-health-monitor.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/system-health.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"

# Thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=85
LOAD_THRESHOLD=4.0

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

alert_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $ALERT_FILE
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $LOG_FILE
    
    # Send email alert if configured
    if command -v mail &> /dev/null; then
        echo "$1" | mail -s "System Alert: $1" $ALERT_EMAIL
    fi
}

# Check CPU usage
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}')
if (( $(echo "$CPU_USAGE > $CPU_THRESHOLD" | bc -l) )); then
    alert_message "High CPU usage: ${CPU_USAGE}%"
fi

# Check memory usage
MEMORY_USAGE=$(free | awk 'FNR==2{printf "%.0f", ($3/($3+$7))*100}')
if [ $MEMORY_USAGE -gt $MEMORY_THRESHOLD ]; then
    alert_message "High memory usage: ${MEMORY_USAGE}%"
fi

# Check disk usage
DISK_USAGE=$(df / | awk 'FNR==2{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt $DISK_THRESHOLD ]; then
    alert_message "High disk usage: ${DISK_USAGE}%"
fi

# Check system load
LOAD_1MIN=$(uptime | awk '{print $10}' | sed 's/,//')
if (( $(echo "$LOAD_1MIN > $LOAD_THRESHOLD" | bc -l) )); then
    alert_message "High system load: $LOAD_1MIN"
fi

# Check critical services
services=("nginx" "php8.1-fpm" "mysql" "redis-server" "supervisor")
for service in "${services[@]}"; do
    if ! systemctl is-active --quiet $service; then
        alert_message "Service $service is not running"
        # Attempt automatic restart
        systemctl restart $service
        if systemctl is-active --quiet $service; then
            log_message "Successfully restarted $service"
        else
            alert_message "Failed to restart $service - manual intervention required"
        fi
    fi
done

log_message "System health check completed - CPU: ${CPU_USAGE}%, Memory: ${MEMORY_USAGE}%, Disk: ${DISK_USAGE}%, Load: $LOAD_1MIN"
EOL

sudo mv /tmp/system-health-monitor.sh $SCRIPTS_DIR/
sudo chmod +x $SCRIPTS_DIR/system-health-monitor.sh

# 2. Application Performance Monitoring
echo "⚡ Setting up application performance monitoring..."

cat > /tmp/app-performance-monitor.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/app-performance.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"
APP_DIR="/var/www/ekspedisi-quran"

# Thresholds
RESPONSE_TIME_THRESHOLD=2000  # milliseconds
DB_QUERY_THRESHOLD=100       # milliseconds
CACHE_HIT_THRESHOLD=85       # percentage
QUEUE_SIZE_THRESHOLD=1000    # jobs

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

alert_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $ALERT_FILE
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $LOG_FILE
    
    if command -v mail &> /dev/null; then
        echo "$1" | mail -s "Performance Alert: $1" $ALERT_EMAIL
    fi
}

# Test application response time
RESPONSE_TIME=$(curl -w "%{time_total}" -o /dev/null -s http://localhost/)
RESPONSE_TIME_MS=$(echo "$RESPONSE_TIME * 1000" | bc | cut -d. -f1)

if [ $RESPONSE_TIME_MS -gt $RESPONSE_TIME_THRESHOLD ]; then
    alert_message "Slow application response: ${RESPONSE_TIME_MS}ms"
fi

# Test database performance
DB_START=$(php -r "echo microtime(true);")
php $APP_DIR/artisan tinker --execute="DB::select('SELECT 1'); exit;" > /dev/null 2>&1
DB_END=$(php -r "echo microtime(true);")
DB_TIME_MS=$(echo "($DB_END - $DB_START) * 1000" | bc | cut -d. -f1)

if [ $DB_TIME_MS -gt $DB_QUERY_THRESHOLD ]; then
    alert_message "Slow database response: ${DB_TIME_MS}ms"
fi

# Check cache performance
if command -v redis-cli &> /dev/null; then
    REDIS_INFO=$(redis-cli -a $REDIS_PASSWORD info stats | grep keyspace_hits)
    HITS=$(echo $REDIS_INFO | grep -o '[0-9]*')
    REDIS_INFO_MISSES=$(redis-cli -a $REDIS_PASSWORD info stats | grep keyspace_misses)
    MISSES=$(echo $REDIS_INFO_MISSES | grep -o '[0-9]*')
    
    if [ $HITS -gt 0 ] && [ $MISSES -gt 0 ]; then
        HIT_RATE=$(echo "scale=2; $HITS / ($HITS + $MISSES) * 100" | bc)
        HIT_RATE_INT=$(echo $HIT_RATE | cut -d. -f1)
        
        if [ $HIT_RATE_INT -lt $CACHE_HIT_THRESHOLD ]; then
            alert_message "Low cache hit rate: ${HIT_RATE}%"
        fi
    fi
fi

# Check queue sizes
if [ -f $APP_DIR/artisan ]; then
    HIGH_QUEUE=$(php $APP_DIR/artisan queue:size redis high 2>/dev/null || echo 0)
    DEFAULT_QUEUE=$(php $APP_DIR/artisan queue:size redis default 2>/dev/null || echo 0)
    LOW_QUEUE=$(php $APP_DIR/artisan queue:size redis low 2>/dev/null || echo 0)
    FAILED_JOBS=$(php $APP_DIR/artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo 0)
    
    TOTAL_QUEUE=$((HIGH_QUEUE + DEFAULT_QUEUE + LOW_QUEUE))
    
    if [ $TOTAL_QUEUE -gt $QUEUE_SIZE_THRESHOLD ]; then
        alert_message "High queue backlog: $TOTAL_QUEUE jobs"
    fi
    
    if [ $FAILED_JOBS -gt 50 ]; then
        alert_message "High failed job count: $FAILED_JOBS"
    fi
    
    log_message "Performance check - Response: ${RESPONSE_TIME_MS}ms, DB: ${DB_TIME_MS}ms, Queue: $TOTAL_QUEUE, Failed: $FAILED_JOBS"
fi
EOL

sudo mv /tmp/app-performance-monitor.sh $SCRIPTS_DIR/
sudo chmod +x $SCRIPTS_DIR/app-performance-monitor.sh

# 3. Storage and Capacity Monitoring
echo "💾 Setting up storage monitoring..."

cat > /tmp/storage-monitor.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/storage-monitor.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"
APP_DIR="/var/www/ekspedisi-quran"

# Thresholds
STORAGE_WARNING_THRESHOLD=80
STORAGE_CRITICAL_THRESHOLD=90
LOG_SIZE_THRESHOLD=1024  # MB

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

alert_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $ALERT_FILE
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $LOG_FILE
    
    if command -v mail &> /dev/null; then
        echo "$1" | mail -s "Storage Alert: $1" $ALERT_EMAIL
    fi
}

# Check application storage usage
STORAGE_USAGE=$(df $APP_DIR/storage | awk 'NR==2{print $5}' | sed 's/%//')

if [ $STORAGE_USAGE -gt $STORAGE_CRITICAL_THRESHOLD ]; then
    alert_message "Critical storage usage: ${STORAGE_USAGE}%"
    # Trigger emergency cleanup
    php $APP_DIR/artisan storage:cleanup --emergency
elif [ $STORAGE_USAGE -gt $STORAGE_WARNING_THRESHOLD ]; then
    alert_message "High storage usage: ${STORAGE_USAGE}%"
    # Trigger routine cleanup
    php $APP_DIR/artisan storage:cleanup --routine
fi

# Check log file sizes
if [ -d "$APP_DIR/storage/logs" ]; then
    LOG_SIZE=$(du -sm $APP_DIR/storage/logs | cut -f1)
    if [ $LOG_SIZE -gt $LOG_SIZE_THRESHOLD ]; then
        alert_message "Large log files: ${LOG_SIZE}MB"
        # Rotate logs
        find $APP_DIR/storage/logs -name "*.log" -mtime +7 -exec gzip {} \;
        find $APP_DIR/storage/logs -name "*.log.gz" -mtime +30 -delete
    fi
fi

# Run storage monitoring if available
if [ -f $APP_DIR/artisan ]; then
    php $APP_DIR/artisan storage:monitor --alerts-only >> $LOG_FILE 2>&1
fi

log_message "Storage check completed - Usage: ${STORAGE_USAGE}%, Logs: ${LOG_SIZE}MB"
EOL

sudo mv /tmp/storage-monitor.sh $SCRIPTS_DIR/
sudo chmod +x $SCRIPTS_DIR/storage-monitor.sh

# 4. Automated Backup Verification
echo "🔄 Setting up backup verification..."

cat > /tmp/backup-verify.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/backup-verify.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"
BACKUP_DIR="/var/backups/ekspedisi-quran"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

alert_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $ALERT_FILE
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $LOG_FILE
    
    if command -v mail &> /dev/null; then
        echo "$1" | mail -s "Backup Alert: $1" $ALERT_EMAIL
    fi
}

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    alert_message "Backup directory does not exist: $BACKUP_DIR"
    exit 1
fi

# Check for recent database backup
RECENT_DB_BACKUP=$(find $BACKUP_DIR -name "*.sql" -mtime -1 | head -1)
if [ -z "$RECENT_DB_BACKUP" ]; then
    alert_message "No recent database backup found"
else
    # Verify backup integrity
    if mysql ekspedisi_quran_test < $RECENT_DB_BACKUP 2>/dev/null; then
        log_message "Database backup verification successful"
        mysql -e "DROP DATABASE IF EXISTS ekspedisi_quran_test;"
    else
        alert_message "Database backup verification failed"
    fi
fi

# Check for recent storage backup
RECENT_STORAGE_BACKUP=$(find $BACKUP_DIR -name "*storage*.tar.gz" -mtime -1 | head -1)
if [ -z "$RECENT_STORAGE_BACKUP" ]; then
    alert_message "No recent storage backup found"
else
    # Verify backup integrity
    if tar -tzf $RECENT_STORAGE_BACKUP > /dev/null 2>&1; then
        log_message "Storage backup verification successful"
    else
        alert_message "Storage backup verification failed"
    fi
fi

log_message "Backup verification completed"
EOL

sudo mv /tmp/backup-verify.sh $SCRIPTS_DIR/
sudo chmod +x $SCRIPTS_DIR/backup-verify.sh

# 5. Security Monitoring
echo "🔒 Setting up security monitoring..."

cat > /tmp/security-monitor.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/security-monitor.log"
ALERT_FILE="/var/log/ekspedisi/alerts.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

alert_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $ALERT_FILE
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> $LOG_FILE
    
    if command -v mail &> /dev/null; then
        echo "$1" | mail -s "Security Alert: $1" $ALERT_EMAIL
    fi
}

# Check for failed login attempts
FAILED_LOGINS=$(grep "authentication failure" /var/log/auth.log | grep "$(date '+%b %d')" | wc -l)
if [ $FAILED_LOGINS -gt 20 ]; then
    alert_message "High number of failed login attempts: $FAILED_LOGINS"
fi

# Check SSL certificate expiry
if command -v openssl &> /dev/null; then
    CERT_EXPIRY=$(openssl x509 -in /etc/letsencrypt/live/your-domain.com/fullchain.pem -noout -dates 2>/dev/null | grep notAfter | cut -d= -f2)
    if [ ! -z "$CERT_EXPIRY" ]; then
        EXPIRY_EPOCH=$(date -d "$CERT_EXPIRY" +%s)
        CURRENT_EPOCH=$(date +%s)
        DAYS_UNTIL_EXPIRY=$(( (EXPIRY_EPOCH - CURRENT_EPOCH) / 86400 ))
        
        if [ $DAYS_UNTIL_EXPIRY -lt 30 ]; then
            alert_message "SSL certificate expires in $DAYS_UNTIL_EXPIRY days"
        fi
    fi
fi

# Check for suspicious network activity
CONNECTIONS=$(netstat -an | grep ":80\|:443" | grep ESTABLISHED | wc -l)
if [ $CONNECTIONS -gt 200 ]; then
    alert_message "High number of network connections: $CONNECTIONS"
fi

# Check file integrity for critical files
CRITICAL_FILES=("/var/www/ekspedisi-quran/.env" "/etc/nginx/sites-enabled/ekspedisi-quran")
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        CURRENT_HASH=$(md5sum "$file" | cut -d' ' -f1)
        STORED_HASH_FILE="/etc/ekspedisi/monitoring/$(basename $file).md5"
        
        if [ -f "$STORED_HASH_FILE" ]; then
            STORED_HASH=$(cat "$STORED_HASH_FILE")
            if [ "$CURRENT_HASH" != "$STORED_HASH" ]; then
                alert_message "File integrity check failed for $file"
            fi
        else
            echo "$CURRENT_HASH" > "$STORED_HASH_FILE"
        fi
    fi
done

log_message "Security check completed - Failed logins: $FAILED_LOGINS, Connections: $CONNECTIONS"
EOL

sudo mv /tmp/security-monitor.sh $SCRIPTS_DIR/
sudo chmod +x $SCRIPTS_DIR/security-monitor.sh

# 6. Comprehensive Health Check
echo "🏥 Setting up comprehensive health check..."

cat > /tmp/comprehensive-health-check.sh << 'EOL'
#!/bin/bash

LOG_FILE="/var/log/ekspedisi/comprehensive-health.log"
REPORT_FILE="/tmp/health-report-$(date +%Y%m%d_%H%M%S).json"
APP_DIR="/var/www/ekspedisi-quran"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

echo "Starting comprehensive health check..."

# Run Laravel health check if available
if [ -f $APP_DIR/artisan ]; then
    php $APP_DIR/artisan monitoring:health-check --format=json --export=$REPORT_FILE
    
    # Extract overall status
    OVERALL_STATUS=$(jq -r '.overall_status' $REPORT_FILE 2>/dev/null || echo "unknown")
    
    log_message "Laravel health check completed - Status: $OVERALL_STATUS"
    
    # Send report if critical
    if [ "$OVERALL_STATUS" = "critical" ]; then
        if command -v mail &> /dev/null; then
            echo "Critical health issues detected. See attached report." | \
                mail -s "CRITICAL: Health Check Alert" -a $REPORT_FILE $ALERT_EMAIL
        fi
    fi
fi

# Run system monitoring scripts
$SCRIPTS_DIR/system-health-monitor.sh
$SCRIPTS_DIR/app-performance-monitor.sh
$SCRIPTS_DIR/storage-monitor.sh
$SCRIPTS_DIR/security-monitor.sh

log_message "Comprehensive health check completed"

# Cleanup temporary files older than 7 days
find /tmp -name "health-report-*.json" -mtime +7 -delete
EOL

sudo mv /tmp/comprehensive-health-check.sh $SCRIPTS_DIR/
sudo chmod +x $SCRIPTS_DIR/comprehensive-health-check.sh

# 7. Create monitoring configuration
echo "⚙️ Creating monitoring configuration..."

sudo tee $MONITORING_DIR/config << EOF
# Ekspedisi Quran Monitoring Configuration
ALERT_EMAIL=$ALERT_EMAIL
MONITORING_ENABLED=true
LOG_RETENTION_DAYS=30
ALERT_COOLDOWN_MINUTES=30
COMPREHENSIVE_CHECK_INTERVAL=900  # 15 minutes
QUICK_CHECK_INTERVAL=300          # 5 minutes
