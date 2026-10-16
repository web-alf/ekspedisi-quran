# Redis Queue System Deployment Guide

## Overview
This guide covers the deployment and configuration of the Redis-based queue system for the Ekspedisi Quran Laravel application. The system is designed for production-ready queue processing with proper monitoring, error handling, and scalability.

## Table of Contents
1. [System Architecture](#system-architecture)
2. [Prerequisites](#prerequisites)
3. [Installation Steps](#installation-steps)
4. [Queue Configuration](#queue-configuration)
5. [Supervisor Setup](#supervisor-setup)
6. [Monitoring & Health Checks](#monitoring--health-checks)
7. [Troubleshooting](#troubleshooting)
8. [Maintenance](#maintenance)

## System Architecture

### Queue Hierarchy
```
├── High Priority (redis-high)
│   ├── Queue: high
│   ├── Workers: 2
│   └── Use: Critical operations requiring immediate processing
├── Certificate Queue (redis-certificates)  
│   ├── Queue: certificates
│   ├── Workers: 3
│   └── Use: Certificate generation jobs (bulk & single)
├── Warehouse Queue (redis-warehouse)
│   ├── Queue: warehouse  
│   ├── Workers: 2
│   └── Use: Warehouse operations, box processing, status updates
├── Default Queue (redis)
│   ├── Queue: default
│   ├── Workers: 2
│   └── Use: General application jobs
└── Low Priority (redis-low)
    ├── Queue: low
    ├── Workers: 1
    └── Use: Cleanup, maintenance tasks
```

### Redis Database Allocation
- **Database 0**: Default/General Redis data
- **Database 1**: Cache storage
- **Database 2**: Queue storage (isolated for performance)

## Prerequisites

### System Requirements
- Redis Server 6.0+ 
- PHP 8.1+ with Redis extension (phpredis recommended)
- Supervisor for process management
- Sufficient RAM for queue workers (minimum 2GB recommended)

### Redis Installation
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install redis-server

# CentOS/RHEL
sudo yum install redis

# macOS with Homebrew
brew install redis
```

### Supervisor Installation
```bash
# Ubuntu/Debian
sudo apt install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

## Installation Steps

### 1. Configure Environment Variables
Copy queue configuration to your `.env` file:
```bash
# Copy example configuration
cp .env.queue-example .env.queue-config
```

Add to your `.env`:
```bash
# Queue Driver
QUEUE_CONNECTION=redis

# Redis Queue Configuration
REDIS_QUEUE_CONNECTION=queue
REDIS_QUEUE_DB=2
REDIS_QUEUE=default
REDIS_QUEUE_HIGH=high
REDIS_QUEUE_LOW=low
REDIS_QUEUE_CERTIFICATES=certificates
REDIS_QUEUE_WAREHOUSE=warehouse
REDIS_QUEUE_RETRY_AFTER=600
REDIS_QUEUE_BLOCK_FOR=5
REDIS_READ_TIMEOUT=60
```

### 2. Test Redis Connection
```bash
# Test Redis connectivity
php artisan tinker
Redis::connection('queue')->ping(); // Should return "PONG"
exit
```

### 3. Clear Configuration Cache
```bash
php artisan config:cache
php artisan queue:restart
```

### 4. Install Supervisor Configuration

Copy supervisor configuration files:
```bash
# Copy supervisor configs to system directory
sudo cp deployment/supervisor/*.conf /etc/supervisor/conf.d/

# Update paths in configuration files if needed
sudo sed -i 's|/var/www/ekspedisi-quran|'$(pwd)'|g' /etc/supervisor/conf.d/ekspedisi-queue-*.conf

# Update user in configuration files
sudo sed -i 's|user=www-data|user='$(whoami)'|g' /etc/supervisor/conf.d/ekspedisi-queue-*.conf
```

### 5. Start Supervisor Services
```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start all queue workers
sudo supervisorctl start ekspedisi-queue-high:*
sudo supervisorctl start ekspedisi-queue-certificates:*
sudo supervisorctl start ekspedisi-queue-warehouse:*
sudo supervisorctl start ekspedisi-queue-default:*
sudo supervisorctl start ekspedisi-queue-low:*

# Check status
sudo supervisorctl status
```

## Queue Configuration

### Job Types and Queue Assignment

| Job Class | Queue | Connection | Priority | Timeout | Memory |
|-----------|--------|------------|----------|---------|--------|
| `GenerateBulkCertificatesJob` | certificates | redis-certificates | High | 7200s | 1024MB |
| `GenerateSingleCertificateJob` | certificates | redis-certificates | High | 1800s | 1024MB |
| `BulkBoxProcessingJob` | warehouse | redis-warehouse | Medium | 3600s | 512MB |
| `BulkStatusUpdateJob` | warehouse | redis-warehouse | Medium | 3600s | 512MB |
| `BulkItemAssignmentJob` | warehouse | redis-warehouse | Medium | 3600s | 512MB |
| `CertificateGenerationJob` | certificates | redis-certificates | High | 3600s | 1024MB |
| `CleanupOldCertificatesJob` | low | redis-low | Low | 3600s | 256MB |

### Queue Worker Configuration

Each queue has optimized settings:

```bash
# High Priority Queue
php artisan queue:work redis-high --queue=high --sleep=3 --tries=3 --max-time=3600 --timeout=600 --memory=512

# Certificate Queue  
php artisan queue:work redis-certificates --queue=certificates --sleep=3 --tries=2 --max-time=7200 --timeout=3600 --memory=1024

# Warehouse Queue
php artisan queue:work redis-warehouse --queue=warehouse --sleep=3 --tries=3 --max-time=3600 --timeout=1800 --memory=512

# Default Queue
php artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --timeout=300 --memory=256

# Low Priority Queue
php artisan queue:work redis-low --queue=low --sleep=5 --tries=2 --max-time=7200 --timeout=1800 --memory=256
```

## Supervisor Setup

### Configuration Files Location
All supervisor configuration files are stored in:
```
deployment/supervisor/
├── queue-high.conf
├── queue-certificates.conf  
├── queue-warehouse.conf
├── queue-default.conf
└── queue-low.conf
```

### Key Configuration Settings

```ini
[program:ekspedisi-queue-certificates]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ekspedisi-quran/artisan queue:work redis-certificates --queue=certificates --sleep=3 --tries=2 --max-time=7200 --timeout=3600 --memory=1024
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/ekspedisi-queue-certificates.log
stopwaitsecs=7200
```

### Managing Workers
```bash
# Start specific queue workers
sudo supervisorctl start ekspedisi-queue-certificates:*

# Stop workers
sudo supervisorctl stop ekspedisi-queue-certificates:*

# Restart workers  
sudo supervisorctl restart ekspedisi-queue-certificates:*

# View logs
sudo tail -f /var/log/ekspedisi-queue-certificates.log
```

## Monitoring & Health Checks

### Built-in Commands

#### Queue Health Check
```bash
# Basic health check
php artisan queue:health-check

# Detailed health check with custom thresholds
php artisan queue:health-check --detailed --alert-threshold=50 --failed-threshold=5
```

#### Real-time Queue Monitor
```bash
# Monitor for 5 minutes, checking every 10 seconds
php artisan queue:monitor --interval=10 --duration=300

# Export monitoring data to CSV
php artisan queue:monitor --interval=5 --duration=600 --export-csv=queue-monitor.csv
```

### Manual Queue Inspection

```bash
# Check queue sizes
php artisan queue:monitor --interval=1 --duration=1

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Redis Queue Inspection

```bash
# Connect to Redis
redis-cli -n 2

# Check queue sizes
LLEN ekspedisi_quran_database_queues:high
LLEN ekspedisi_quran_database_queues:certificates  
LLEN ekspedisi_quran_database_queues:warehouse
LLEN ekspedisi_quran_database_queues:default
LLEN ekspedisi_quran_database_queues:low

# View queue contents (first 10 jobs)
LRANGE ekspedisi_quran_database_queues:certificates 0 9
```

## Troubleshooting

### Common Issues

#### 1. Workers Not Processing Jobs
```bash
# Check supervisor status
sudo supervisorctl status

# Check Redis connection
php artisan tinker
Redis::connection('queue')->ping();

# Check queue configuration
php artisan config:show queue
```

#### 2. High Memory Usage
```bash
# Monitor worker memory usage
ps aux | grep "queue:work"

# Restart workers to clear memory
sudo supervisorctl restart ekspedisi-queue-certificates:*

# Adjust memory limits in supervisor config
```

#### 3. Jobs Failing Repeatedly
```bash
# Check failed jobs table
php artisan queue:failed

# View specific failed job
php artisan queue:failed 1

# Check application logs
tail -f storage/logs/laravel.log
```

#### 4. Redis Connection Issues
```bash
# Check Redis service status
sudo systemctl status redis

# Test Redis connectivity
redis-cli ping

# Check Redis memory usage
redis-cli info memory
```

### Performance Optimization

#### Redis Configuration
Edit `/etc/redis/redis.conf`:
```bash
# Increase max memory
maxmemory 2gb
maxmemory-policy allkeys-lru

# Enable persistence for queue data
save 900 1
save 300 10
save 60 10000

# Optimize for queue workload
tcp-keepalive 60
timeout 0
```

#### Laravel Queue Configuration
Optimize `config/queue.php`:
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'queue',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 600,
    'block_for' => 5,  // Reduces CPU usage
    'after_commit' => false,
],
```

## Maintenance

### Daily Tasks
```bash
# Health check (can be added to cron)
php artisan queue:health-check --detailed

# Clean up old failed jobs (older than 7 days)
php artisan queue:failed --since="-7 days" | xargs -I {} php artisan queue:forget {}
```

### Weekly Tasks
```bash
# Clean up completed job progress records (older than 14 days)  
php artisan cleanup:old-certificates --days=14

# Monitor Redis memory usage
redis-cli info memory

# Backup Redis queue data if needed
redis-cli --rdb /backup/redis-queue-backup.rdb
```

### Scaling Guidelines

#### When to Scale Up
- Queue sizes consistently > 100 jobs
- Average job processing time > 5 minutes
- Worker memory usage > 80%
- Failed job rate > 5%

#### Scaling Options
1. **Increase Worker Processes**: Edit `numprocs` in supervisor config
2. **Add More Queues**: Create specialized queues for specific job types
3. **Distribute Across Servers**: Use Redis cluster or separate Redis instances
4. **Optimize Jobs**: Implement job chunking and optimize processing logic

### Monitoring in Production

#### Set up Automated Alerts
```bash
# Add to crontab for automated monitoring
# Check every 5 minutes
*/5 * * * * cd /var/www/ekspedisi-quran && php artisan queue:health-check --alert-threshold=50 --failed-threshold=10 || echo "Queue health check failed" | mail -s "Queue Alert" admin@example.com
```

#### Log Rotation
```bash
# Add to logrotate configuration
sudo tee /etc/logrotate.d/ekspedisi-queue << EOF
/var/log/ekspedisi-queue-*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    create 644 www-data www-data
    postrotate
        supervisorctl restart ekspedisi-queue-*:*
    endscript
}
EOF
```

## Security Considerations

### Redis Security
```bash
# Bind Redis to specific interface
bind 127.0.0.1

# Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command KEYS ""

# Set password authentication
requirepass your-secure-password
```

### File Permissions
```bash
# Ensure proper permissions
chmod 755 /var/www/ekspedisi-quran
chmod 644 /etc/supervisor/conf.d/ekspedisi-queue-*.conf
chmod 600 /var/www/ekspedisi-quran/.env
```

This completes the Redis Queue System deployment guide. Follow these steps carefully for a production-ready queue system with proper monitoring and reliability.