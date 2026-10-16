# Performance Testing Suite

This directory contains comprehensive performance testing tools and scripts for validating all system optimizations.

## Available Performance Tests

### 🚀 Quick Performance Validation
```bash
# Run complete validation suite
./scripts/performance/complete-validation.sh

# Individual component tests
php artisan cache:performance-test
php artisan queue:health-check
php artisan storage:monitor
php artisan monitoring:health-check
```

### 📊 Database Performance Tests
```bash
# Run database optimization tests
php artisan test tests/Unit/Performance/DatabaseOptimizationTest

# Test specific query performance
php artisan tinker --execute="
\$start = microtime(true);
\$result = \App\Models\Pengiriman::with(['donatur', 'jenisQuran'])->paginate(20);
echo 'Query time: ' . round((microtime(true) - \$start) * 1000, 2) . 'ms';
"
```

### ⚡ Cache Performance Tests
```bash
# Comprehensive cache testing
php artisan test tests/Unit/Performance/CachePerformanceTest

# Cache management commands
php artisan cache:manage warm     # Warm all caches
php artisan cache:manage stats    # Show cache statistics
php artisan cache:manage health   # Check cache health
```

### 🔄 Queue Performance Tests
```bash
# Queue system tests
php artisan test tests/Feature/Performance/QueuePerformanceTest

# Queue monitoring
php artisan queue:monitor          # Real-time queue monitoring
php artisan queue:test            # Test queue operations
```

### 🎯 Integration Tests
```bash
# Complete integration testing
php artisan test tests/Feature/Integration/OptimizationIntegrationTest

# Test all optimizations working together
php artisan test --filter="optimization"
```

### 📈 Load Testing
```bash
# Basic load testing (requires application running)
php scripts/performance/load-test.php

# Custom load test with specific parameters
php scripts/performance/load-test.php http://your-domain.com
```

## Performance Monitoring Commands

### System Health Monitoring
```bash
# Complete system health check
php artisan monitoring:health-check

# Collect performance metrics
php artisan monitoring:collect-metrics

# Setup monitoring infrastructure
php artisan monitoring:setup
```

### Cache Management
```bash
# Performance testing
php artisan cache:performance-test

# Cache management
php artisan cache:manage warm|stats|health|clear

# Clear specific cache types
php artisan cache:clear
```

### Queue Management
```bash
# Queue health and monitoring
php artisan queue:health-check
php artisan queue:monitor

# Process queue jobs
php artisan queue:work --queue=high,certificates,warehouse,default,low
```

### Storage Management
```bash
# Storage monitoring and optimization
php artisan storage:monitor

# Storage optimization
php artisan storage:optimize
```

## Test Files Structure

```
tests/
├── Unit/Performance/
│   ├── DatabaseOptimizationTest.php    # Database query performance
│   └── CachePerformanceTest.php        # Cache system performance
├── Feature/Performance/
│   └── QueuePerformanceTest.php        # Queue processing performance
└── Feature/Integration/
    └── OptimizationIntegrationTest.php # Full integration testing
```

## Performance Scripts

```
scripts/performance/
├── complete-validation.sh              # Complete validation suite
├── performance-test-suite.php          # Comprehensive testing
├── load-test.php                       # Load testing script
├── validate-optimizations.php          # Basic validation
└── reports/                           # Generated reports
```

## Expected Performance Benchmarks

### Database Performance
- Simple queries: < 10ms
- Complex queries with joins: < 50ms
- Search queries: < 30ms
- Pagination: < 50ms

### Cache Performance
- Cache hits: < 5ms
- Cache misses: 50-90% faster than direct DB
- Speed improvement: 5-20x depending on data

### Queue Performance
- Job dispatch: < 10ms
- Job processing: Variable (depends on job type)
- Success rate: > 95%
- Retry mechanism: Automatic for failed jobs

### Memory Usage
- Large dataset loading: < 10MB increase
- Cache storage: < 5MB for typical operations
- No memory leaks in repeated operations

## Continuous Performance Testing

### Daily Checks
```bash
# Run daily performance validation
php artisan monitoring:health-check
php artisan queue:health-check
php artisan storage:monitor
```

### Weekly Analysis
```bash
# Comprehensive weekly testing
./scripts/performance/complete-validation.sh > reports/weekly-$(date +%Y%m%d).log
```

### Pre-deployment Testing
```bash
# Before deploying to production
php artisan test tests/Unit/Performance/
php artisan test tests/Feature/Performance/
./scripts/performance/complete-validation.sh
```

## Performance Alerts

The monitoring system will alert on:
- Database queries > 100ms
- Cache hit rate < 80%
- Queue processing failures > 5%
- Storage usage > 90%
- Memory usage spikes
- Failed job accumulation

## Troubleshooting Performance Issues

### Database Slow Queries
1. Check query execution plans
2. Verify indexes are being used
3. Review N+1 query patterns
4. Consider query optimization

### Cache Issues
1. Verify Redis connection
2. Check cache invalidation logic
3. Monitor cache hit rates
4. Review cache key strategies

### Queue Problems
1. Monitor queue worker status
2. Check failed job logs
3. Verify Redis queue connection
4. Review job retry logic

### Storage Issues
1. Monitor disk space usage
2. Check file permission issues
3. Review cleanup procedures
4. Verify storage optimization

## Production Deployment Checklist

- [ ] All performance tests passing
- [ ] Cache warming configured
- [ ] Queue workers set up with Supervisor
- [ ] Monitoring alerts configured
- [ ] Storage monitoring active
- [ ] Database indexes verified
- [ ] Security permissions corrected
- [ ] Performance benchmarks documented

---

**Last Updated:** August 16, 2025  
**Version:** 1.0  
**Maintainer:** Performance Optimizer Agent
