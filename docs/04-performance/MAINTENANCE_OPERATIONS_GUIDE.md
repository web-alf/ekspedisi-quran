# Maintenance & Operations Guide

**Project**: Ekspedisi Quran Application  
**Version**: 2.0.0  
**Date**: August 15, 2025  
**Audience**: DevOps, System Administrators, Senior Developers

## Table of Contents

1. [Daily Operations](#daily-operations)
2. [Weekly Maintenance](#weekly-maintenance)
3. [Monthly Tasks](#monthly-tasks)
4. [Monitoring Procedures](#monitoring-procedures)
5. [Alerting Response](#alerting-response)
6. [Performance Tuning](#performance-tuning)
7. [Troubleshooting Procedures](#troubleshooting-procedures)
8. [Emergency Response](#emergency-response)
9. [Capacity Planning](#capacity-planning)
10. [Backup and Recovery](#backup-and-recovery)

## Daily Operations

### Morning Health Check (9:00 AM)

#### System Status Review
```bash
# Check overall system health
php artisan monitoring:health-check

# Review overnight alerts
php artisan monitoring:collect-metrics --summary

# Check queue status
php artisan queue:monitor --status
```

#### Performance Dashboard Review
1. Navigate to `/admin/monitoring`
2. Review key metrics:
   - Response times (target: <250ms P95)
   - Error rates (target: <2%)
   - Memory usage (alert: >80%)
   - Queue backlog (alert: >1000 jobs)
   - Cache hit rates (target: >85%)

#### Critical Alerts Review
```bash
# Check active critical alerts
curl -s http://localhost/admin/monitoring/api/alerts | jq '.critical[]'

# Review failed jobs from last 24 hours
php artisan queue:failed --since="24 hours ago"
```

### Continuous Monitoring (Every 15 Minutes)

#### Automated Health Checks
```bash
# Add to crontab for continuous monitoring
*/15 * * * * /path/to/scripts/quick-health-check.sh

# Script content:
#!/bin/bash
LOG_FILE="/var/log/ekspedisi-health.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Check critical services
if ! php artisan monitoring:health-check --quick --threshold=critical; then
    echo "[$TIMESTAMP] CRITICAL: System health check failed" >> $LOG_FILE
    # Send immediate alert
    php artisan monitoring:alert --level=critical --message="System health check failed"
fi
```

### End of Day Tasks (6:00 PM)

#### Performance Summary
```bash
# Generate daily performance report
php artisan monitoring:collect-metrics --daily-summary > /tmp/daily-report.json

# Check cache optimization opportunities
php artisan cache:manage stats --optimize-suggestions

# Review storage usage trends
php artisan storage:monitor --daily-summary
```

#### Queue Cleanup
```bash
# Clear completed jobs older than 24 hours
php artisan queue:prune-batches --hours=24

# Retry any failed jobs that can be safely retried
php artisan queue:retry --queue=default --failed-since="4 hours ago"
```

## Weekly Maintenance

### Monday: Performance Review

#### Cache Performance Analysis
```bash
# Analyze cache hit rates by service
php artisan cache:manage stats --detailed

# Optimize cache TTL based on usage patterns
php artisan cache:manage optimize --auto-adjust

# Warm cache for the week ahead
php artisan cache:manage warm --priority=high
```

#### Database Performance Review
```bash
# Analyze slow queries from the past week
php artisan db:monitor --slow-queries --since="7 days ago"

# Check for missing indexes
php artisan db:analyze --missing-indexes

# Update table statistics
php artisan db:optimize --analyze-tables
```

### Wednesday: System Optimization

#### Queue Performance Tuning
```bash
# Analyze queue job performance trends
php artisan queue:monitor --historical --days=7

# Optimize chunk sizes for bulk operations
php artisan queue:optimize --analyze-performance

# Review and adjust worker configuration
supervisorctl status ekspedisi-queue-*
```

#### Storage Optimization
```bash
# Clean up old files and optimize storage
php artisan storage:cleanup --days=30

# Optimize images and regenerate thumbnails
php artisan storage:optimize --images

# Check for orphaned files
php artisan storage:audit --find-orphans
```

### Friday: Security and Backup Review

#### Security Audit
```bash
# Check security configurations
php artisan monitoring:health-check --security

# Review file permissions
find storage/ -type f ! -perm 644 -ls
find storage/ -type d ! -perm 755 -ls

# Check for outdated dependencies
composer audit
npm audit
```

#### Backup Verification
```bash
# Verify backup integrity
php artisan backup:test --verify-integrity

# Test database restore procedure
php artisan backup:restore --test-mode

# Check backup storage usage
php artisan backup:monitor --storage-usage
```

## Monthly Tasks

### First Monday: Comprehensive Review

#### Performance Baseline Update
```bash
# Update performance baselines
php artisan monitoring:benchmarks --set-baselines

# Generate comprehensive performance report
php artisan monitoring:report --monthly --export=/tmp/monthly-performance.pdf

# Review capacity trends
php artisan monitoring:capacity-analysis --forecast=3months
```

#### System Configuration Review
```bash
# Review and update monitoring thresholds
php artisan monitoring:configure --review-thresholds

# Update cache configurations based on usage patterns
php artisan cache:configure --optimize-ttl

# Review queue configuration for optimal performance
php artisan queue:configure --optimize-workers
```

### Mid-Month: Infrastructure Assessment

#### Resource Utilization Analysis
```bash
# Analyze resource usage trends
php artisan monitoring:resources --trend-analysis --days=30

# Identify optimization opportunities
php artisan system:analyze --recommendations

# Plan capacity adjustments
php artisan capacity:plan --forecast-growth
```

#### Technology Stack Review
```bash
# Check for available updates
composer outdated
npm outdated

# Review security advisories
composer audit --format=json > /tmp/security-audit.json

# Plan upgrade schedule
php artisan system:upgrade-plan --security-priority
```

### End of Month: Reporting and Planning

#### Monthly Performance Report
```bash
# Generate executive summary
php artisan reporting:monthly-summary --export=pdf

# Create optimization recommendations
php artisan optimization:recommendations --priority=high

# Plan next month's maintenance schedule
php artisan maintenance:schedule --next-month
```

## Monitoring Procedures

### Real-time Monitoring Setup

#### Dashboard Configuration
```javascript
// Monitor critical metrics every 30 seconds
setInterval(() => {
    fetch('/admin/monitoring/api/metrics')
        .then(response => response.json())
        .then(data => {
            updateDashboard(data);
            checkThresholds(data);
        });
}, 30000);
```

#### Automated Alerting
```bash
# Configure critical alerts (add to crontab)
* * * * * php artisan monitoring:collect-metrics --alert-on-threshold

# Configure alert escalation
*/5 * * * * php artisan monitoring:escalate-alerts --overdue=5min

# Send daily summary reports
0 8 * * * php artisan monitoring:daily-report --email=ops@company.com
```

### Performance Monitoring Protocols

#### Response Time Monitoring
```bash
# Monitor endpoint response times
php artisan monitoring:endpoints --track-response-times

# Alert on slow responses
php artisan monitoring:alert --condition="response_time>2000ms" --action="email,slack"

# Analyze response time trends
php artisan monitoring:trends --metric=response_time --days=7
```

#### Resource Monitoring
```bash
# Monitor system resources
php artisan monitoring:resources --continuous

# Track memory usage patterns
php artisan monitoring:memory --track-leaks

# Monitor disk space growth
php artisan monitoring:storage --growth-rate
```

## Alerting Response

### Alert Priority Levels

#### Level 1: Info (Green)
- **Response Time**: No immediate action required
- **Examples**: Cache warming completed, scheduled maintenance
- **Action**: Log for reference, no notifications

#### Level 2: Warning (Yellow)
- **Response Time**: Review within 2 hours
- **Examples**: High memory usage (>70%), elevated response times
- **Action**: Review metrics, plan optimization

#### Level 3: Critical (Orange)
- **Response Time**: Respond within 30 minutes
- **Examples**: Memory usage >90%, response times >5 seconds
- **Action**: Immediate investigation and mitigation

#### Level 4: Emergency (Red)
- **Response Time**: Immediate response required
- **Examples**: System down, database unavailable, memory exhaustion
- **Action**: Execute emergency response procedures

### Alert Response Procedures

#### Critical Alert Response Checklist
```bash
# 1. Assess current system status
php artisan monitoring:health-check --emergency

# 2. Check resource availability
df -h  # Disk space
free -h  # Memory usage
top  # CPU and process status

# 3. Review recent changes
git log --oneline --since="24 hours ago"
php artisan log:recent --level=error

# 4. Implement immediate fixes
php artisan cache:clear  # Clear problematic cache
php artisan queue:restart  # Restart stuck queues
systemctl restart nginx php8.1-fpm  # Restart services if needed

# 5. Document and escalate if needed
echo "Alert resolved: $(date)" >> /var/log/alert-resolution.log
```

#### Emergency Alert Response
```bash
#!/bin/bash
# Emergency response script

ALERT_TYPE=$1
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
LOG_FILE="/var/log/emergency-response.log"

echo "[$TIMESTAMP] EMERGENCY ALERT: $ALERT_TYPE" >> $LOG_FILE

case $ALERT_TYPE in
    "system_down")
        # Immediate system restart procedures
        systemctl restart nginx mysql php8.1-fpm redis-server
        ;;
    "memory_exhaustion")
        # Clear caches and restart services
        php artisan cache:clear
        php artisan queue:restart
        systemctl restart php8.1-fpm
        ;;
    "database_unavailable")
        # Database recovery procedures
        systemctl restart mysql
        php artisan migrate:status
        ;;
    "storage_full")
        # Emergency storage cleanup
        php artisan storage:emergency-cleanup
        find /var/log -name "*.log" -mtime +7 -delete
        ;;
esac

# Send immediate notification
php artisan monitoring:alert --level=emergency --message="Emergency response executed for $ALERT_TYPE"
```

## Performance Tuning

### Cache Optimization

#### Cache Hit Rate Optimization
```bash
# Analyze cache miss patterns
php artisan cache:analyze --miss-patterns

# Optimize TTL values based on usage
php artisan cache:optimize-ttl --auto-adjust

# Implement predictive caching
php artisan cache:predictive-warm --enable
```

#### Cache Configuration Tuning
```php
// config/performance_cache.php optimization
'ttl' => [
    'dashboard' => env('CACHE_TTL_DASHBOARD', 600),    // Increased from 300
    'reference' => env('CACHE_TTL_REFERENCE', 7200),   // Increased from 3600
    'geographic' => env('CACHE_TTL_GEOGRAPHIC', 86400), // Keep 24 hours
    'user' => env('CACHE_TTL_USER', 3600),             // Increased from 1800
    'query' => env('CACHE_TTL_QUERY', 1800),           // Increased from 900
],
```

### Database Performance Tuning

#### Query Optimization
```bash
# Identify slow queries
php artisan db:slow-queries --threshold=1000ms --since="24 hours ago"

# Suggest index optimizations
php artisan db:optimize-indexes --suggest

# Analyze query execution plans
php artisan db:explain --slow-queries
```

#### Index Optimization Strategy
```sql
-- Monitor index usage
SELECT 
    table_name,
    index_name,
    cardinality,
    INDEX_LENGTH,
    CARDINALITY/INDEX_LENGTH as efficiency
FROM information_schema.statistics 
WHERE table_schema = 'ekspedisi_quran'
ORDER BY efficiency DESC;

-- Add composite indexes for common query patterns
CREATE INDEX idx_pengiriman_compound ON pengiriman (status, tanggal_kirim, donatur_id);
CREATE INDEX idx_mushaf_status_kategori ON mushaf_requests (status, kategori_lembaga);
```

### Queue Performance Tuning

#### Worker Configuration Optimization
```bash
# Analyze worker performance
php artisan queue:monitor --worker-analysis

# Optimize worker counts based on load
supervisorctl stop ekspedisi-queue-default:*
# Update supervisor config with optimal worker count
supervisorctl reread && supervisorctl update
supervisorctl start ekspedisi-queue-default:*
```

#### Job Processing Optimization
```php
// Optimize job chunk sizes based on memory usage
public function handle()
{
    $optimalChunkSize = $this->calculateOptimalChunkSize();
    
    collect($this->items)
        ->chunk($optimalChunkSize)
        ->each(function ($chunk) {
            $this->processChunk($chunk);
            // Force garbage collection after each chunk
            gc_collect_cycles();
        });
}
```

## Troubleshooting Procedures

### Common Issues and Solutions

#### High Memory Usage
**Symptoms**: Memory alerts, slow response times, system instability

**Diagnosis**:
```bash
# Check memory usage by process
ps aux --sort=-%mem | head -10

# Monitor PHP memory usage
php artisan monitoring:memory --detailed

# Check for memory leaks
php artisan queue:monitor --memory-tracking
```

**Solutions**:
```bash
# Immediate fixes
php artisan cache:clear
php artisan queue:restart
systemctl restart php8.1-fpm

# Long-term optimizations
# Reduce queue chunk sizes
# Optimize memory-intensive operations
# Increase server memory if needed
```

#### Slow Database Queries
**Symptoms**: High response times, database alerts

**Diagnosis**:
```bash
# Identify slow queries
php artisan db:slow-queries --real-time

# Check database locks
SHOW PROCESSLIST;
SELECT * FROM information_schema.INNODB_LOCKS;
```

**Solutions**:
```bash
# Add missing indexes
php artisan db:optimize --add-indexes

# Optimize existing queries
php artisan db:optimize --query-analysis

# Update table statistics
ANALYZE TABLE pengiriman, mushaf_requests, donatur;
```

#### Queue Backlog Issues
**Symptoms**: Growing queue size, delayed processing

**Diagnosis**:
```bash
# Check queue sizes
php artisan queue:monitor --sizes

# Review failed jobs
php artisan queue:failed --detailed

# Check worker status
supervisorctl status ekspedisi-queue-*
```

**Solutions**:
```bash
# Restart workers
supervisorctl restart ekspedisi-queue-*

# Clear stuck jobs
php artisan queue:clear --queue=default

# Increase worker count temporarily
# Update supervisor configuration
supervisorctl reread && supervisorctl update
```

#### Cache Performance Issues
**Symptoms**: Low cache hit rates, high database load

**Diagnosis**:
```bash
# Check cache statistics
php artisan cache:manage stats --detailed

# Test cache connectivity
redis-cli ping
php artisan cache:manage health
```

**Solutions**:
```bash
# Warm critical caches
php artisan cache:manage warm --critical

# Optimize cache configuration
php artisan cache:optimize --ttl-analysis

# Verify Redis configuration
redis-cli config get maxmemory*
```

### System Recovery Procedures

#### Full System Recovery
```bash
#!/bin/bash
# Full system recovery script

echo "Starting system recovery procedure..."

# 1. Stop all services
systemctl stop nginx php8.1-fpm

# 2. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Restart core services
systemctl start mysql redis-server
sleep 5

# 4. Verify database connectivity
php artisan migrate:status

# 5. Warm essential caches
php artisan cache:manage warm --essential

# 6. Restart application services
systemctl start php8.1-fpm nginx

# 7. Verify system health
sleep 10
php artisan monitoring:health-check --comprehensive

echo "System recovery completed"
```

#### Database Recovery
```bash
# Database recovery procedures
systemctl stop mysql

# Check for corruption
mysqlcheck --check --all-databases

# Repair if needed
mysqlcheck --repair --all-databases

# Restart and verify
systemctl start mysql
php artisan migrate:status
```

## Emergency Response

### Emergency Contact Procedures

#### On-Call Escalation
1. **Level 1**: System Administrator (Response: 15 minutes)
2. **Level 2**: Senior Developer (Response: 30 minutes)
3. **Level 3**: Technical Lead (Response: 1 hour)
4. **Level 4**: Engineering Manager (Response: 2 hours)

#### Communication Channels
- **Primary**: Slack #emergency-alerts
- **Secondary**: Email alerts to on-call team
- **Tertiary**: SMS for critical emergencies

### Disaster Recovery Procedures

#### Data Backup Verification
```bash
# Verify backup integrity
php artisan backup:verify --comprehensive

# Test restore capability
php artisan backup:test-restore --dry-run

# Document backup status
php artisan backup:status --export=json
```

#### Failover Procedures
```bash
# Database failover (if replica available)
mysql -e "STOP SLAVE; RESET SLAVE ALL; RESET MASTER;"

# Application failover
# Update load balancer configuration
# Redirect traffic to backup server

# Cache failover
# Switch to backup Redis instance
redis-cli --cluster failover
```

## Capacity Planning

### Growth Monitoring

#### Usage Trend Analysis
```bash
# Analyze user growth trends
php artisan analytics:user-growth --forecast=6months

# Monitor storage growth
php artisan storage:growth-analysis --predict-full

# Database size monitoring
php artisan db:size-analysis --growth-rate
```

#### Performance Capacity Assessment
```bash
# Load testing recommendations
php artisan load-test:recommend --current-capacity

# Resource utilization forecasting
php artisan capacity:forecast --metric=all --duration=3months

# Scaling recommendations
php artisan scale:recommend --based-on-trends
```

### Resource Planning

#### Infrastructure Scaling
```bash
# CPU usage trends
php artisan monitoring:cpu --trend-analysis --forecast

# Memory usage forecasting
php artisan monitoring:memory --growth-prediction

# Storage capacity planning
php artisan storage:capacity-plan --growth-rate=20%
```

#### Application Scaling
```bash
# Queue capacity analysis
php artisan queue:capacity-analysis --forecast=3months

# Cache scaling requirements
php artisan cache:scaling-analysis --usage-trends

# Database scaling recommendations
php artisan db:scaling-plan --based-on-growth
```

## Backup and Recovery

### Backup Procedures

#### Daily Backup Tasks
```bash
# Database backup
mysqldump ekspedisi_quran > /backup/db/ekspedisi_$(date +%Y%m%d).sql

# Application files backup
tar -czf /backup/files/storage_$(date +%Y%m%d).tar.gz storage/

# Configuration backup
cp -r config/ /backup/config/$(date +%Y%m%d)/
```

#### Backup Verification
```bash
# Verify backup integrity
php artisan backup:verify --all

# Test restore procedure monthly
php artisan backup:test-restore --verify-data

# Monitor backup storage usage
du -sh /backup/* | sort -h
```

### Recovery Procedures

#### Point-in-Time Recovery
```bash
# Stop application services
systemctl stop nginx php8.1-fpm

# Restore database to specific point
mysql ekspedisi_quran < /backup/db/ekspedisi_20250815.sql

# Restore application files
tar -xzf /backup/files/storage_20250815.tar.gz -C /

# Restart services and verify
systemctl start mysql redis-server php8.1-fpm nginx
php artisan migrate:status
```

#### Full System Recovery
```bash
# Complete system restore from backup
# 1. Fresh system installation
# 2. Restore application code from repository
# 3. Restore database from backup
# 4. Restore uploaded files from backup
# 5. Restore configuration files
# 6. Verify system integrity
```

## Conclusion

This maintenance and operations guide provides comprehensive procedures for maintaining optimal performance of the Ekspedisi Quran application. Regular adherence to these procedures will ensure:

- **System Reliability**: Proactive monitoring prevents issues
- **Optimal Performance**: Regular tuning maintains speed
- **Quick Recovery**: Documented procedures minimize downtime
- **Capacity Planning**: Growth monitoring prevents bottlenecks

### Key Maintenance Schedule

**Daily**: Health checks, alert review, queue monitoring
**Weekly**: Performance analysis, cache optimization, security review
**Monthly**: Baseline updates, capacity planning, comprehensive reporting

Regular execution of these maintenance procedures will maintain the high-performance standards achieved through the optimization implementations.

---

**Document Owner**: DevOps Team  
**Last Updated**: August 15, 2025  
**Review Frequency**: Monthly  
**Next Review**: September 15, 2025