# Comprehensive Monitoring System Guide

## Overview

This document provides a complete guide to the monitoring system implemented for the Ekspedisi Quran application. The monitoring system provides real-time performance metrics, alerting, health checks, and optimization recommendations.

## Features

### 🔍 Metrics Collection
- **System Metrics**: CPU, memory, disk usage, load averages
- **Application Metrics**: Response times, error rates, throughput
- **Database Metrics**: Query performance, connection counts, slow queries
- **Cache Metrics**: Hit rates, response times, storage efficiency
- **Queue Metrics**: Job counts, processing times, failure rates
- **Business Metrics**: Active shipments, completed tasks, user activity
- **Storage Metrics**: File counts, storage usage, health scores

### 🚨 Alerting System
- **Multi-level alerts**: Info, Warning, Critical, Emergency
- **Multiple channels**: Log, Email, Webhook, Slack
- **Cooldown periods**: Prevent alert spam
- **Trend analysis**: Historical context for alerts
- **Suggested actions**: Automated recommendations

### 📊 Performance Benchmarks
- **Database performance**: Query execution times
- **Cache performance**: Read/write operations
- **File I/O performance**: Storage operations
- **Memory performance**: Array and object operations
- **CPU performance**: Mathematical computations
- **Network performance**: DNS and HTTP requests

### 🏥 Health Checks
- **Component health**: Database, cache, storage, queue
- **System health**: Resource usage, permissions
- **Application health**: Configuration, dependencies
- **Security health**: SSL, permissions, configurations

### 📈 Monitoring Dashboard
- **Real-time metrics**: Live system status
- **Historical trends**: Performance over time
- **Alert management**: View and manage alerts
- **Report generation**: Exportable performance reports
- **Optimization recommendations**: Actionable insights

## Installation & Setup

### 1. Install the Monitoring System

```bash
# Set up monitoring infrastructure
php artisan monitoring:setup

# Force re-initialization if needed
php artisan monitoring:setup --force

# Skip specific components
php artisan monitoring:setup --skip-tables --skip-cache
```

### 2. Configure Environment Variables

Add the following to your `.env` file:

```bash
# Enable monitoring
MONITORING_ENABLED=true
MONITORING_COLLECTION_INTERVAL=60
MONITORING_RETENTION_PERIOD=604800

# Alerting configuration
MONITORING_ALERTING_ENABLED=true
MONITORING_ALERT_COOLDOWN=300
MONITORING_EMAIL_ALERTS=true
MONITORING_ALERT_EMAIL=admin@example.com

# Performance thresholds
MONITORING_RESPONSE_TIME_THRESHOLD=2000
MONITORING_MEMORY_THRESHOLD=512
MONITORING_ERROR_RATE_THRESHOLD=5
MONITORING_QUEUE_SIZE_THRESHOLD=1000

# Optional: Webhook alerts
MONITORING_WEBHOOK_ALERTS=false
MONITORING_WEBHOOK_URL=https://hooks.example.com/monitoring

# Optional: Slack alerts
MONITORING_SLACK_ALERTS=false
MONITORING_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
MONITORING_SLACK_CHANNEL=#alerts
```

### 3. Schedule Monitoring Commands

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Collect metrics every minute
    $schedule->command('monitoring:collect-metrics')->everyMinute();
    
    // Health checks every 5 minutes
    $schedule->command('monitoring:health-check')->everyFiveMinutes();
    
    // Performance benchmarks every hour
    $schedule->command('monitoring:benchmarks')->hourly();
    
    // Cleanup old data daily
    $schedule->command('monitoring:cleanup')->daily();
}
```

### 4. Set Up Queue Monitoring (Optional)

For continuous queue monitoring, use Supervisor:

```ini
[program:laravel-queue-monitor]
command=php /path/to/project/artisan queue:monitor --interval=30 --duration=0
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/queue-monitor.log
```

## Usage

### Accessing the Dashboard

Navigate to `/admin/monitoring` to access the main monitoring dashboard.

### Command Line Tools

```bash
# Collect current metrics
php artisan monitoring:collect-metrics

# Run comprehensive health check
php artisan monitoring:health-check

# Run performance benchmarks
php artisan monitoring:benchmarks

# Monitor queue performance
php artisan queue:monitor --interval=10 --duration=300

# System health check with export
php artisan monitoring:health-check --format=json --export=health-report.json
```

### API Endpoints

The monitoring system provides REST API endpoints:

```bash
# Real-time metrics
GET /admin/monitoring/api/metrics

# Historical data
GET /admin/monitoring/api/historical?period=24h

# Health status
GET /admin/monitoring/api/health

# Active alerts
GET /admin/monitoring/api/alerts

# Run benchmarks
POST /admin/monitoring/api/benchmarks/run
```

## Dashboard Features

### 1. System Overview
- Overall health status
- Key performance indicators
- Active alerts summary
- System resource usage

### 2. Real-time Metrics
- Live performance data
- Auto-refreshing charts
- Current system status
- Queue and task monitoring

### 3. Historical Analytics
- Performance trends over time
- Comparative analysis
- Baseline comparisons
- Growth patterns

### 4. Alert Management
- Active alerts dashboard
- Alert history and statistics
- Alert configuration
- Notification preferences

### 5. Performance Reports
- Comprehensive system reports
- Exportable data (JSON, CSV)
- Customizable time periods
- Optimization recommendations

## Alerting System

### Alert Levels

1. **Info**: Informational messages, no action required
2. **Warning**: Performance degradation, monitoring recommended
3. **Critical**: Significant issues, immediate attention needed
4. **Emergency**: System failure, urgent intervention required

### Alert Channels

#### Email Alerts
Configure email alerting:
```bash
MONITORING_EMAIL_ALERTS=true
MONITORING_ALERT_EMAIL=admin@example.com
```

#### Webhook Alerts
Send alerts to external systems:
```bash
MONITORING_WEBHOOK_ALERTS=true
MONITORING_WEBHOOK_URL=https://your-webhook-url.com/alerts
```

#### Slack Integration
```bash
MONITORING_SLACK_ALERTS=true
MONITORING_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
MONITORING_SLACK_CHANNEL=#monitoring
```

### Alert Configuration

Customize alert thresholds in `config/monitoring.php`:

```php
'alert_thresholds' => [
    'response_time' => 2000,     // ms
    'memory_usage' => 512,       // MB
    'cpu_usage' => 80,           // percentage
    'error_rate' => 5,           // percentage
    'queue_size' => 1000,        // jobs
    'disk_usage' => 85,          // percentage
],
```

## Performance Benchmarks

### Available Benchmarks

1. **Database Performance**
   - Simple query execution (100x)
   - Complex join queries
   - Insert operations
   - Connection overhead

2. **Cache Performance**
   - Read operations (1KB data, 100x)
   - Write operations (1KB data, 100x)
   - Large data operations (100KB)
   - Delete operations

3. **File I/O Performance**
   - File read/write operations
   - Directory operations
   - Large file handling

4. **Memory Performance**
   - Array operations
   - String manipulation
   - JSON encoding/decoding
   - Memory usage tracking

5. **CPU Performance**
   - Mathematical operations
   - String processing
   - Regular expressions
   - Hash computations

### Running Benchmarks

```bash
# Run all benchmarks
php artisan monitoring:benchmarks

# Run specific category
php artisan monitoring:benchmarks --category=database

# Set new baselines
php artisan monitoring:benchmarks --set-baselines
```

### Interpreting Results

- **Scores**: 0-100 scale (higher is better)
- **Baselines**: Historical comparison points
- **Trends**: Performance changes over time
- **Recommendations**: Suggested optimizations

## Health Monitoring

### Health Check Categories

1. **Database Health**
   - Connection status
   - Response times
   - Query performance
   - Database size

2. **Cache Health**
   - Read/write performance
   - Connection status
   - Hit rates

3. **Storage Health**
   - Disk space usage
   - File permissions
   - Storage performance

4. **Queue Health**
   - Failed job counts
   - Queue backlogs
   - Worker status

5. **System Health**
   - Memory usage
   - CPU load
   - Disk space
   - Process counts

6. **Application Health**
   - Configuration status
   - Environment settings
   - Dependency status

7. **Security Health**
   - SSL status
   - File permissions
   - Configuration security

### Health Check Commands

```bash
# Basic health check
php artisan monitoring:health-check

# Export to JSON
php artisan monitoring:health-check --format=json --export=health.json

# Export to CSV
php artisan monitoring:health-check --format=csv --export=health.csv

# Set alert threshold
php artisan monitoring:health-check --threshold=critical
```

## Configuration

### Main Configuration File

The monitoring system configuration is in `config/monitoring.php`. Key sections:

#### Metrics Collection
```php
'metrics' => [
    'collection_interval' => 60,
    'retention_period' => 604800,
    'alert_thresholds' => [...],
    'enabled_collectors' => [...],
],
```

#### Performance Monitoring
```php
'performance' => [
    'slow_query_threshold' => 1000,
    'memory_threshold' => 128,
    'request_threshold' => 2000,
    'sampling_rate' => 100,
],
```

#### Alerting
```php
'alerting' => [
    'enabled' => true,
    'cooldown_period' => 300,
    'channels' => ['log', 'email', 'webhook'],
],
```

#### Dashboard
```php
'dashboard' => [
    'refresh_interval' => 30,
    'real_time_updates' => true,
    'chart_data_points' => 50,
],
```

## API Reference

### Authentication

API endpoints require admin authentication. Include session cookies or API tokens.

### Endpoints

#### GET /admin/monitoring/api/metrics
Get current system metrics.

**Response:**
```json
{
  "timestamp": "2024-01-15T10:30:00Z",
  "system": {
    "memory": {"usage_mb": 256, "peak_mb": 312},
    "cpu": {"load_1min": 1.2},
    "disk": {"usage_percentage": 45}
  },
  "performance": {
    "avg_response_time_ms": 150,
    "error_rate_percentage": 0.5,
    "requests_per_minute": 120
  }
}
```

#### GET /admin/monitoring/api/historical
Get historical metrics data.

**Parameters:**
- `period`: Time period (1h, 6h, 24h, 7d)

#### GET /admin/monitoring/api/health
Get system health status.

**Response:**
```json
{
  "overall_status": "healthy",
  "components": {
    "database": {"status": "healthy", "response_time_ms": 15},
    "cache": {"status": "healthy", "read_time_ms": 2},
    "storage": {"status": "warning", "usage_percentage": 85}
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

#### POST /admin/monitoring/api/benchmarks/run
Run performance benchmarks.

**Response:**
```json
{
  "success": true,
  "results": {
    "benchmarks": {...},
    "scores": {...},
    "comparison": {...}
  }
}
```

## Troubleshooting

### Common Issues

#### 1. Metrics Not Collecting
- Check if monitoring is enabled: `MONITORING_ENABLED=true`
- Verify cache is working: `php artisan cache:clear`
- Check scheduled tasks: `php artisan schedule:list`

#### 2. Alerts Not Sending
- Verify email configuration: `php artisan config:cache`
- Check webhook URL accessibility
- Review alert cooldown periods

#### 3. Dashboard Not Loading
- Clear application cache: `php artisan cache:clear`
- Check database connectivity
- Verify user permissions

#### 4. Performance Issues
- Increase collection interval if needed
- Reduce retention period for historical data
- Optimize database queries

### Debug Commands

```bash
# Test cache connectivity
php artisan tinker
> Cache::put('test', 'value', 60); Cache::get('test');

# Test database connectivity
php artisan tinker
> DB::select('SELECT 1');

# Check monitoring status
php artisan monitoring:collect-metrics --verbose

# Validate configuration
php artisan config:show monitoring
```

### Log Files

Monitoring logs are stored in:
- `storage/logs/laravel.log` - General application logs
- `storage/logs/monitoring.log` - Monitoring-specific logs
- `storage/logs/performance.log` - Performance monitoring logs

## Optimization Recommendations

### System Optimization

1. **Memory Usage**
   - Monitor memory-intensive operations
   - Implement proper garbage collection
   - Optimize data structures
   - Use memory-efficient algorithms

2. **Database Performance**
   - Add indexes for slow queries
   - Optimize query structure
   - Implement query caching
   - Use connection pooling

3. **Cache Strategy**
   - Implement aggressive caching
   - Use appropriate TTL values
   - Monitor hit rates
   - Consider Redis optimization

4. **Queue Management**
   - Monitor queue sizes
   - Optimize job processing
   - Use appropriate queue drivers
   - Implement job batching

### Monitoring Optimization

1. **Collection Frequency**
   - Balance between granularity and performance
   - Use longer intervals for stable systems
   - Increase frequency during incidents

2. **Data Retention**
   - Store detailed data for shorter periods
   - Aggregate older data
   - Implement data archiving

3. **Alert Tuning**
   - Adjust thresholds based on normal patterns
   - Implement alert escalation
   - Use cooldown periods effectively

## Security Considerations

### Access Control
- Restrict monitoring dashboard access
- Use role-based permissions
- Implement IP whitelisting if needed

### Data Privacy
- Mask sensitive information in logs
- Encrypt stored metrics data
- Implement data retention policies

### API Security
- Use authentication for API endpoints
- Implement rate limiting
- Validate all inputs

## Integration

### External Monitoring Tools

#### Prometheus Integration
```php
// config/monitoring.php
'integrations' => [
    'prometheus' => [
        'enabled' => true,
        'endpoint' => '/metrics',
    ],
],
```

#### Grafana Integration
Configure Grafana to read from the monitoring API endpoints.

#### New Relic Integration
```bash
NEWRELIC_ENABLED=true
NEWRELIC_API_KEY=your-api-key
```

### Custom Metrics

Add custom business metrics:

```php
// In your application code
$metricsService = app(MetricsCollectionService::class);
$metricsService->recordCustomMetric('orders_processed', $count);
```

## Maintenance

### Regular Tasks

1. **Daily**
   - Review alerts and performance
   - Check system health
   - Monitor storage usage

2. **Weekly**
   - Review performance trends
   - Update alert thresholds
   - Clean up old data

3. **Monthly**
   - Update performance baselines
   - Review monitoring configuration
   - Assess optimization opportunities

### Data Cleanup

```bash
# Manual cleanup
php artisan monitoring:cleanup

# Configure automatic cleanup
php artisan schedule:list
```

## Support

### Getting Help

1. **Documentation**: Review this guide and inline documentation
2. **Logs**: Check application and monitoring logs
3. **Commands**: Use built-in diagnostic commands
4. **Configuration**: Verify all settings in `config/monitoring.php`

### Reporting Issues

When reporting monitoring issues, include:
- Current configuration
- Relevant log files
- Steps to reproduce
- Expected vs actual behavior
- System environment details

---

**Note**: This monitoring system is designed to provide comprehensive insights into your application's performance and health. Regular monitoring and proactive optimization will help maintain optimal system performance.
