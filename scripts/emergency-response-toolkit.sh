#!/bin/bash

# Emergency Response Toolkit
# Quick response tools for production incidents

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-/var/www/ekspedisi-quran}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/ekspedisi-quran}"
LOG_FILE="/var/log/ekspedisi/emergency-response.log"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_emergency() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] EMERGENCY: $1" | tee -a $LOG_FILE
}

show_help() {
    echo "Emergency Response Toolkit"
    echo "========================="
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Available Commands:"
    echo "  status              - Quick system status check"
    echo "  database-recovery   - Emergency database recovery"
    echo "  cache-recovery      - Emergency cache system recovery"
    echo "  queue-recovery      - Emergency queue system recovery"
    echo "  maintenance-on      - Enable emergency maintenance mode"
    echo "  maintenance-off     - Disable maintenance mode"
    echo "  high-load-response  - Response to high system load"
    echo "  rollback <date>     - Rollback to previous backup"
    echo "  service-restart     - Restart all critical services"
    echo "  storage-cleanup     - Emergency storage cleanup"
    echo "  security-lockdown   - Emergency security measures"
    echo "  health-check        - Comprehensive health check"
    echo ""
    echo "Examples:"
    echo "  $0 status"
    echo "  $0 database-recovery"
    echo "  $0 rollback 20250815_143000"
    echo "  $0 high-load-response"
}

quick_status() {
    echo -e "${BLUE}🔍 Quick System Status Check${NC}"
    echo "================================"
    
    # System load
    LOAD=$(uptime | awk '{print $10}' | sed 's/,//')
    echo "System Load: $LOAD"
    
    # Memory usage
    MEM_USAGE=$(free | awk 'NR==2{printf "%.0f%%", $3*100/$2}')
    echo "Memory Usage: $MEM_USAGE"
    
    # Disk usage
    DISK_USAGE=$(df / | awk 'NR==2{print $5}')
    echo "Disk Usage: $DISK_USAGE"
    
    # Service status
    echo ""
    echo "Critical Services:"
    for service in nginx php8.1-fpm mysql redis-server supervisor; do
        if systemctl is-active --quiet $service; then
            echo -e "  ${GREEN}✓${NC} $service"
        else
            echo -e "  ${RED}✗${NC} $service"
        fi
    done
    
    # Queue workers
    echo ""
    echo "Queue Workers:"
    RUNNING_WORKERS=$(supervisorctl status ekspedisi-queue-* | grep RUNNING | wc -l)
    TOTAL_WORKERS=$(supervisorctl status ekspedisi-queue-* | wc -l)
    echo "  Running: $RUNNING_WORKERS/$TOTAL_WORKERS"
    
    # Application response
    echo ""
    RESPONSE_TIME=$(curl -w "%{time_total}" -o /dev/null -s http://localhost/ 2>/dev/null || echo "FAILED")
    echo "App Response Time: $RESPONSE_TIME seconds"
}

database_recovery() {
    echo -e "${RED}🆘 Emergency Database Recovery${NC}"
    echo "================================="
    
    log_emergency "Starting database recovery procedure"
    
    # Check MySQL status
    if ! systemctl is-active --quiet mysql; then
        echo "MySQL is down. Attempting restart..."
        systemctl restart mysql
        sleep 10
    fi
    
    # Test connection
    if mysql -u ekspedisi -p$DB_PASSWORD ekspedisi_quran -e "SELECT 1;" &>/dev/null; then
        echo -e "${GREEN}✓${NC} Database connection restored"
        log_emergency "Database connection successful"
    else
        echo -e "${RED}✗${NC} Database connection failed"
        log_emergency "Database connection failed - manual intervention required"
        
        # Check for InnoDB recovery
        echo "Checking for InnoDB recovery..."
        if grep -q "InnoDB: Starting crash recovery" /var/log/mysql/error.log; then
            echo "InnoDB crash recovery in progress..."
            # Wait for recovery
            sleep 30
        fi
        
        return 1
    fi
    
    # Restart dependent services
    echo "Restarting dependent services..."
    systemctl restart php8.1-fpm
    supervisorctl restart ekspedisi-queue-*
    
    log_emergency "Database recovery completed"
}

cache_recovery() {
    echo -e "${RED}🆘 Emergency Cache Recovery${NC}"
    echo "============================"
    
    log_emergency "Starting cache recovery procedure"
    
    # Restart Redis
    echo "Restarting Redis..."
    systemctl restart redis-server
    sleep 5
    
    # Test Redis
    if redis-cli -a $REDIS_PASSWORD ping | grep -q PONG; then
        echo -e "${GREEN}✓${NC} Redis connection restored"
        log_emergency "Redis recovery successful"
    else
        echo -e "${RED}✗${NC} Redis recovery failed - switching to file cache"
        log_emergency "Redis failed - falling back to file cache"
        
        # Fallback to file cache
        if [ -f $APP_DIR/.env ]; then
            sed -i.backup 's/CACHE_STORE=redis/CACHE_STORE=file/' $APP_DIR/.env
            php $APP_DIR/artisan config:cache
            echo "Switched to file cache temporarily"
        fi
    fi
    
    # Restart PHP-FPM to clear opcache
    systemctl restart php8.1-fpm
    
    log_emergency "Cache recovery completed"
}

queue_recovery() {
    echo -e "${RED}🆘 Emergency Queue Recovery${NC}"
    echo "==========================="
    
    log_emergency "Starting queue recovery procedure"
    
    # Check supervisor
    if ! systemctl is-active --quiet supervisor; then
        echo "Supervisor is down. Restarting..."
        systemctl restart supervisor
        sleep 10
    fi
    
    # Restart all queue workers
    echo "Restarting queue workers..."
    supervisorctl restart ekspedisi-queue-*
    sleep 10
    
    # Check worker status
    RUNNING_WORKERS=$(supervisorctl status ekspedisi-queue-* | grep RUNNING | wc -l)
    EXPECTED_WORKERS=6
    
    if [ $RUNNING_WORKERS -eq $EXPECTED_WORKERS ]; then
        echo -e "${GREEN}✓${NC} All queue workers restored ($RUNNING_WORKERS/$EXPECTED_WORKERS)"
        log_emergency "Queue recovery successful - all workers running"
    else
        echo -e "${YELLOW}⚠${NC} Only $RUNNING_WORKERS/$EXPECTED_WORKERS workers running"
        log_emergency "Partial queue recovery - only $RUNNING_WORKERS/$EXPECTED_WORKERS workers"
        
        # Clear failed jobs that might be blocking
        if [ -f $APP_DIR/artisan ]; then
            echo "Clearing failed jobs..."
            php $APP_DIR/artisan queue:flush
        fi
    fi
    
    log_emergency "Queue recovery completed"
}

maintenance_on() {
    echo -e "${YELLOW}🚧 Enabling Emergency Maintenance Mode${NC}"
    echo "======================================"
    
    if [ -f $APP_DIR/artisan ]; then
        SECRET=$(date +%s)
        php $APP_DIR/artisan down --refresh=15 --secret="emergency-$SECRET" --render="errors::503"
        echo "Maintenance mode enabled with secret: emergency-$SECRET"
        echo "Access URL: https://your-domain.com?secret=emergency-$SECRET"
        log_emergency "Emergency maintenance mode enabled with secret: emergency-$SECRET"
    else
        echo "Application not found - creating emergency maintenance page"
        cat > /var/www/html/maintenance.html << 'EOL'
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Mode</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        .container { max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Maintenance</h1>
        <p>We are currently performing emergency maintenance. Please try again in a few minutes.</p>
        <p><em>Updated: $(date)</em></p>
    </div>
</body>
</html>
EOL
    fi
}

maintenance_off() {
    echo -e "${GREEN}✅ Disabling Maintenance Mode${NC}"
    echo "============================"
    
    if [ -f $APP_DIR/artisan ]; then
        php $APP_DIR/artisan up
        echo "Maintenance mode disabled"
        log_emergency "Maintenance mode disabled"
    fi
    
    # Remove emergency maintenance page if it exists
    rm -f /var/www/html/maintenance.html
}

high_load_response() {
    echo -e "${RED}⚡ High Load Emergency Response${NC}"
    echo "==============================="
    
    CURRENT_LOAD=$(uptime | awk '{print $10}' | sed 's/,//')
    LOAD_THRESHOLD=4.0
    
    log_emergency "High load response triggered - Current load: $CURRENT_LOAD"
    
    if (( $(echo "$CURRENT_LOAD > $LOAD_THRESHOLD" | bc -l) )); then
        echo "Critical load detected: $CURRENT_LOAD"
        
        # Enable maintenance mode temporarily
        maintenance_on
        
        # Stop low priority queue workers
        echo "Stopping low priority workers..."
        supervisorctl stop ekspedisi-queue-low:*
        supervisorctl stop ekspedisi-queue-default:1
        
        # Clear non-essential caches
        echo "Clearing caches to free memory..."
        if [ -f $APP_DIR/artisan ]; then
            php $APP_DIR/artisan cache:clear
            php $APP_DIR/artisan view:clear
        fi
        
        # Wait for load to decrease
        echo "Waiting for load to stabilize..."
        sleep 60
        
        NEW_LOAD=$(uptime | awk '{print $10}' | sed 's/,//')
        echo "New load: $NEW_LOAD"
        
        if (( $(echo "$NEW_LOAD < $LOAD_THRESHOLD" | bc -l) )); then
            echo "Load stabilized. Restoring normal operations..."
            supervisorctl start ekspedisi-queue-*
            maintenance_off
            log_emergency "High load response successful - load normalized to $NEW_LOAD"
        else
            echo -e "${RED}Load still critical: $NEW_LOAD${NC}"
            echo "Manual intervention required!"
            log_emergency "High load response failed - load still critical: $NEW_LOAD"
            
            # Send emergency notification
            if command -v mail &> /dev/null; then
                echo "Critical system load persists. Manual intervention required." | \
                    mail -s "EMERGENCY: Critical System Load" admin@your-domain.com
            fi
        fi
    else
        echo "Load is within acceptable range: $CURRENT_LOAD"
    fi
}

rollback_application() {
    local backup_date="$1"
    
    if [ -z "$backup_date" ]; then
        echo "Usage: $0 rollback <backup_date>"
        echo "Available backups:"
        ls -la $BACKUP_DIR/ | grep -E "(database_|storage_)" | head -10
        return 1
    fi
    
    echo -e "${RED}🔄 Emergency Application Rollback${NC}"
    echo "================================="
    
    log_emergency "Starting rollback to backup: $backup_date"
    
    # Check if backup exists
    if [ ! -f "$BACKUP_DIR/database_$backup_date.sql" ]; then
        echo "Backup not found: $BACKUP_DIR/database_$backup_date.sql"
        return 1
    fi
    
    # Enable maintenance mode
    maintenance_on
    
    # Create current backup before rollback
    echo "Creating safety backup..."
    SAFETY_DATE=$(date +%Y%m%d_%H%M%S)
    mysqldump ekspedisi_quran > $BACKUP_DIR/safety_backup_$SAFETY_DATE.sql
    
    # Restore database
    echo "Restoring database..."
    mysql ekspedisi_quran < $BACKUP_DIR/database_$backup_date.sql
    
    # Restore storage if available
    if [ -f "$BACKUP_DIR/storage_$backup_date.tar.gz" ]; then
        echo "Restoring storage..."
        tar -xzf $BACKUP_DIR/storage_$backup_date.tar.gz -C $APP_DIR/
    fi
    
    # Restore environment if available
    if [ -f "$BACKUP_DIR/env_$backup_date" ]; then
        echo "Restoring environment..."
        cp $BACKUP_DIR/env_$backup_date $APP_DIR/.env
    fi
    
    # Clear caches
    echo "Clearing caches..."
    if [ -f $APP_DIR/artisan ]; then
        php $APP_DIR/artisan config:clear
        php $APP_DIR/artisan cache:clear
        php $APP_DIR/artisan view:clear
    fi
    
    # Restart services
    echo "Restarting services..."
    systemctl restart php8.1-fpm
    supervisorctl restart ekspedisi-queue-*
    
    # Disable maintenance mode
    maintenance_off
    
    echo -e "${GREEN}✅ Rollback completed successfully${NC}"
    log_emergency "Rollback to $backup_date completed successfully"
}

service_restart() {
    echo -e "${YELLOW}🔄 Restarting Critical Services${NC}"
    echo "==============================="
    
    log_emergency "Starting critical service restart"
    
    # Restart services in order
    SERVICES=("redis-server" "mysql" "php8.1-fpm" "nginx" "supervisor")
    
    for service in "${SERVICES[@]}"; do
        echo "Restarting $service..."
        systemctl restart $service
        sleep 5
        
        if systemctl is-active --quiet $service; then
            echo -e "  ${GREEN}✓${NC} $service restarted successfully"
        else
            echo -e "  ${RED}✗${NC} $service restart failed"
            log_emergency "Failed to restart $service"
        fi
    done
    
    # Restart queue workers
    echo "Restarting queue workers..."
    supervisorctl restart ekspedisi-queue-*
    
    log_emergency "Critical service restart completed"
}

storage_cleanup() {
    echo -e "${YELLOW}🧹 Emergency Storage Cleanup${NC}"
    echo "============================"
    
    log_emergency "Starting emergency storage cleanup"
    
    # Clear old logs
    echo "Cleaning old logs..."
    find /var/log -name "*.log" -mtime +7 -type f -delete
    find $APP_DIR/storage/logs -name "*.log" -mtime +3 -type f -delete
    
    # Clear temporary files
    echo "Cleaning temporary files..."
    find /tmp -type f -mtime +1 -delete
    rm -rf /tmp/laravel-*
    
    # Clear old cache files
    echo "Cleaning old cache..."
    if [ -f $APP_DIR/artisan ]; then
        php $APP_DIR/artisan cache:clear
        php $APP_DIR/artisan view:clear
    fi
    
    # Clear old backups (keep last 10)
    echo "Cleaning old backups..."
    cd $BACKUP_DIR && ls -t *.sql | tail -n +11 | xargs rm -f
    cd $BACKUP_DIR && ls -t *.tar.gz | tail -n +11 | xargs rm -f
    
    # Run storage optimization
    if [ -f $APP_DIR/artisan ]; then
        php $APP_DIR/artisan storage:cleanup --emergency
    fi
    
    # Show disk usage after cleanup
    echo ""
    echo "Disk usage after cleanup:"
    df -h /
    
    log_emergency "Emergency storage cleanup completed"
}

security_lockdown() {
    echo -e "${RED}🔒 Emergency Security Lockdown${NC}"
    echo "=============================="
    
    log_emergency "Starting emergency security lockdown"
    
    # Enable maintenance mode
    maintenance_on
    
    # Block suspicious IPs (example - customize as needed)
    echo "Implementing IP restrictions..."
    # ufw deny from suspicious_ip_range
    
    # Change sensitive file permissions
    echo "Securing file permissions..."
    chmod 600 $APP_DIR/.env
    chmod -R 750 $APP_DIR/storage
    
    # Restart fail2ban
    echo "Restarting fail2ban..."
    systemctl restart fail2ban
    
    # Generate new session keys
    if [ -f $APP_DIR/artisan ]; then
        echo "Regenerating application key..."
        php $APP_DIR/artisan key:generate --force
    fi
    
    # Clear all sessions
    echo "Clearing all sessions..."
    if [ -f $APP_DIR/artisan ]; then
        php $APP_DIR/artisan session:flush
    fi
    
    echo -e "${YELLOW}⚠ Security lockdown completed${NC}"
    echo "Review and manually disable maintenance mode when ready"
    
    log_emergency "Emergency security lockdown completed"
}

health_check() {
    echo -e "${BLUE}🏥 Comprehensive Health Check${NC}"
    echo "============================="
    
    if [ -f $APP_DIR/artisan ]; then
        php $APP_DIR/artisan monitoring:health-check --comprehensive
    else
        echo "Running basic health check..."
        quick_status
    fi
}

# Main script logic
case "${1:-help}" in
    "status")
        quick_status
        ;;
    "database-recovery")
        database_recovery
        ;;
    "cache-recovery")
        cache_recovery
        ;;
    "queue-recovery")
        queue_recovery
        ;;
    "maintenance-on")
        maintenance_on
        ;;
    "maintenance-off")
        maintenance_off
        ;;
    "high-load-response")
        high_load_response
        ;;
    "rollback")
        rollback_application "$2"
        ;;
    "service-restart")
        service_restart
        ;;
    "storage-cleanup")
        storage_cleanup
        ;;
    "security-lockdown")
        security_lockdown
        ;;
    "health-check")
        health_check
        ;;
    "help"|*)
        show_help
        ;;
esac
