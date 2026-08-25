# Performance Regression Testing System

This document describes the comprehensive automated performance regression testing system implemented for the Ekspedisi Quran application.

## 🎯 Overview

The performance regression testing system ensures that future code changes don't degrade the performance optimizations we've implemented. It includes:

1. **Automated Performance Tests** - Run on every pull request and main branch push
2. **Performance Baselines** - Stored and compared automatically
3. **Performance Budgets** - Strict limits that prevent performance regressions
4. **Continuous Monitoring** - Daily performance checks and alerts
5. **Comprehensive Reporting** - Detailed analysis and recommendations

## 🚀 Quick Start

### Running Performance Tests Locally

```bash
# Run comprehensive performance test suite
./scripts/performance/run-performance-tests.sh

# Run specific performance commands
php artisan performance:benchmark
php artisan performance:analyze-queries
php artisan performance:check-budgets
```

### CI/CD Integration

The system automatically runs on:
- **Pull Requests** - Compares against baseline from main branch
- **Main Branch** - Creates new baseline for future comparisons
- **Daily Schedule** - Monitors performance trends
- **Release Tags** - Creates performance snapshots

## 📊 Performance Test Suite Components

### 1. Performance Benchmark Command

**Command**: `php artisan performance:benchmark`

Tests core application performance:

```bash
# Basic usage
php artisan performance:benchmark

# JSON output for CI
php artisan performance:benchmark --output=json --iterations=5

# Quick test with fewer iterations
php artisan performance:benchmark --iterations=3 --warmup=1
```

**What it tests**:
- Database query performance (simple, complex, aggregation)
- Cache operations (read, write, hit/miss ratios)
- Memory usage patterns
- Query optimization effectiveness
- Warehouse operation performance

### 2. Query Analysis Command

**Command**: `php artisan performance:analyze-queries`

Analyzes database queries for performance issues:

```bash
# Analyze with default threshold (100ms)
php artisan performance:analyze-queries

# Custom slow query threshold
php artisan performance:analyze-queries --slow-threshold=50

# JSON output
php artisan performance:analyze-queries --output=json
```

**What it detects**:
- Slow queries above threshold
- N+1 query problems
- Missing indexes
- Inefficient LIKE queries
- Unoptimized ORDER BY clauses

### 3. Performance Budget Checker

**Command**: `php artisan performance:check-budgets`

Enforces strict performance limits:

```bash
# Check all budgets
php artisan performance:check-budgets

# Strict mode (tighter limits)
php artisan performance:check-budgets --strict

# JSON output for CI
php artisan performance:check-budgets --output=json
```

**Budget Categories**:
- **Database Queries**: Max execution times for different query types
- **Cache Performance**: Hit ratios and response times
- **Memory Usage**: Limits on memory consumption
- **Response Times**: Maximum acceptable response times
- **Frontend Budgets**: Bundle sizes and Core Web Vitals

### 4. Results Analysis Command

**Command**: `php artisan performance:analyze-results`

Compares current performance against baseline:

```bash
# Compare against baseline
php artisan performance:analyze-results --compare-baseline=baseline/

# Custom regression threshold
php artisan performance:analyze-results --threshold=15

# JSON output
php artisan performance:analyze-results --output=json
```

### 5. Report Generation Command

**Command**: `php artisan performance:generate-report`

Creates comprehensive performance reports:

```bash
# Generate markdown report
php artisan performance:generate-report --format=markdown

# Include recommendations
php artisan performance:generate-report --include-recommendations

# Save to file
php artisan performance:generate-report --output=report.md
```

## 🔧 Configuration

### Performance Budgets

Performance budgets are defined in `CheckBudgetsCommand.php`:

```php
// Example budget configuration
'database_queries' => [
    'simple_count_max_time' => 10, // ms
    'complex_join_max_time' => 50, // ms
    'max_queries_per_request' => 15,
],
'cache_performance' => [
    'cache_hit_max_time' => 5, // ms
    'min_hit_ratio' => 0.8, // 80%
],
'memory_usage' => [
    'max_memory_per_request' => 100, // MB
    'large_dataset_max_memory' => 50, // MB
],
```

### Frontend Performance Budgets

Configured in `lighthouserc.js`:

```javascript
assertions: {
  'categories:performance': ['error', { minScore: 0.8 }],
  'first-contentful-paint': ['error', { maxNumericValue: 2500 }],
  'largest-contentful-paint': ['error', { maxNumericValue: 4000 }],
  'resource-summary:script:size': ['error', { maxNumericValue: 500000 }],
}
```

## 🔄 CI/CD Workflow

### GitHub Actions Workflow

The `.github/workflows/performance-tests.yml` workflow:

1. **Setup Environment**: PHP, Node.js, MySQL, Redis
2. **Install Dependencies**: Composer and NPM packages
3. **Build Assets**: Frontend compilation
4. **Database Setup**: Migrations and test data seeding
5. **Performance Tests**: All test suites
6. **Baseline Comparison**: Compare against previous results
7. **Budget Validation**: Enforce performance limits
8. **Report Generation**: Create detailed reports
9. **Artifact Storage**: Save results and reports

### Workflow Triggers

```yaml
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]
  schedule:
    - cron: '0 2 * * *' # Daily at 2 AM UTC
```

### Failure Conditions

The CI will fail if:
- Critical performance budgets are exceeded
- Performance regressions > 20% are detected
- More than 50% of queries become slow
- Frontend Core Web Vitals fail thresholds

## 📈 Monitoring and Alerting

### Scheduled Performance Monitoring

```php
// In routes/console.php
Schedule::command('performance:benchmark --output=json --iterations=3')
    ->weekly()
    ->withoutOverlapping();

Schedule::command('performance:check-budgets --output=json')
    ->daily()
    ->withoutOverlapping();
```

### Performance Metrics Tracked

1. **Response Times**
   - Dashboard load time
   - Listing page performance
   - Search operation speed
   - Warehouse operation speed

2. **Database Performance**
   - Query execution times
   - Query count per request
   - Index usage efficiency
   - Connection pool utilization

3. **Cache Performance**
   - Hit/miss ratios
   - Cache operation times
   - Memory usage
   - Eviction rates

4. **Memory Usage**
   - Peak memory consumption
   - Memory efficiency ratios
   - Large dataset handling
   - Memory leak detection

5. **Frontend Performance**
   - Bundle sizes
   - Core Web Vitals (LCP, FCP, CLS, TBT)
   - Resource loading times
   - JavaScript execution times

## 🚨 Alert System

### Critical Performance Issues

When critical issues are detected:

1. **Immediate Alerts**
   - GitHub PR comments with performance results
   - CI build failures for critical regressions
   - Slack notifications (if configured)

2. **Daily Monitoring**
   - Performance trend analysis
   - Budget violation reports
   - Recommendation summaries

### Alert Thresholds

- **Critical**: >50% performance regression or budget violation
- **High**: 20-50% performance regression
- **Medium**: 10-20% performance regression
- **Low**: <10% performance regression

## 📋 Performance Test Reports

### Report Formats

1. **Markdown Reports** (`.md`)
   - Human-readable format
   - Suitable for GitHub PR comments
   - Includes recommendations and charts

2. **JSON Reports** (`.json`)
   - Machine-readable format
   - For CI/CD integration
   - Includes all metrics and metadata

3. **HTML Reports** (`.html`)
   - Rich visual format
   - Interactive charts and graphs
   - Detailed drill-down capabilities

### Report Sections

1. **Executive Summary**
   - Overall performance grade
   - Critical issues count
   - Key metrics comparison

2. **Benchmark Results**
   - Database performance metrics
   - Cache performance data
   - Memory usage analysis

3. **Query Analysis**
   - Slow query identification
   - N+1 query detection
   - Index usage recommendations

4. **Budget Status**
   - Budget violations
   - Threshold compliance
   - Trend analysis

5. **Recommendations**
   - Immediate actions required
   - Next sprint improvements
   - Long-term optimizations

## 🛠️ Maintenance

### Regular Tasks

1. **Update Baselines** (Monthly)
   - Review and approve new performance baselines
   - Adjust budgets based on application growth
   - Update test data scenarios

2. **Review Budgets** (Quarterly)
   - Assess budget appropriateness
   - Tighten budgets for critical paths
   - Add new performance metrics

3. **Test Suite Maintenance** (As needed)
   - Add new performance test scenarios
   - Update test data for realistic loads
   - Improve test coverage

### Performance Baseline Management

```bash
# Update baseline after significant optimizations
git checkout main
./scripts/performance/run-performance-tests.sh
# Commit new baseline files

# Compare feature branch against baseline
git checkout feature-branch
php artisan performance:analyze-results --compare-baseline=baseline/
```

## 🔍 Troubleshooting

### Common Issues

1. **Tests Timeout**
   - Increase test timeouts in CI
   - Optimize test data size
   - Check database performance

2. **Flaky Results**
   - Increase warmup iterations
   - Ensure consistent test environment
   - Review test data consistency

3. **False Positives**
   - Adjust regression thresholds
   - Review baseline validity
   - Check for environmental factors

### Debug Commands

```bash
# Run single performance test with verbose output
php artisan performance:benchmark --iterations=1 -v

# Check specific query performance
php artisan performance:analyze-queries --slow-threshold=10 -v

# Test budget validation
php artisan performance:check-budgets --strict -v
```

## 📚 Best Practices

### Writing Performance Tests

1. **Use Realistic Data**
   - Test with production-like data volumes
   - Include edge cases and worst-case scenarios
   - Maintain consistent test data

2. **Isolate Tests**
   - Use database transactions when possible
   - Clear caches between tests
   - Avoid test interdependencies

3. **Measure What Matters**
   - Focus on user-impacting metrics
   - Test critical user journeys
   - Include both average and worst-case scenarios

### Performance Budget Guidelines

1. **Set Realistic Budgets**
   - Based on actual user requirements
   - Consider device and network constraints
   - Allow for reasonable growth

2. **Progressive Tightening**
   - Start with achievable budgets
   - Gradually tighten over time
   - Celebrate improvements

3. **Regular Review**
   - Assess budget effectiveness
   - Adjust based on user feedback
   - Update for new features

## 🎯 Success Metrics

### Key Performance Indicators

1. **Regression Prevention**
   - Number of performance regressions caught
   - Time to detect performance issues
   - Performance improvement trends

2. **Budget Compliance**
   - Budget violation frequency
   - Budget achievement percentage
   - Performance stability over time

3. **Development Efficiency**
   - Time to run performance tests
   - Developer adoption of tools
   - Performance-aware development practices

## 🚀 Future Enhancements

### Planned Improvements

1. **Real User Monitoring (RUM)**
   - Client-side performance tracking
   - User experience metrics
   - Geographic performance analysis

2. **Advanced Analytics**
   - Machine learning for anomaly detection
   - Predictive performance modeling
   - Automated optimization suggestions

3. **Integration Improvements**
   - Slack/Teams notifications
   - Performance dashboards
   - Automated performance reports

### Performance Testing Roadmap

- **Phase 1**: Basic regression testing ✅
- **Phase 2**: Advanced monitoring and alerting (In Progress)
- **Phase 3**: Real user monitoring
- **Phase 4**: AI-powered optimization
- **Phase 5**: Automated performance tuning

---

## 📞 Support

For questions about the performance testing system:

1. Check this documentation first
2. Review existing performance reports
3. Run diagnostic commands
4. Contact the development team

**Remember**: Performance is everyone's responsibility! 🚀
