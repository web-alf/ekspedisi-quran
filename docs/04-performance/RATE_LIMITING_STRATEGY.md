# API Rate Limiting Strategy

This document outlines the comprehensive rate limiting implementation for the Ekspedisi Quran Laravel application.

## Overview

The application implements a multi-layered rate limiting strategy to protect against abuse, ensure system stability, and provide different access levels based on user roles and endpoint sensitivity.

## Rate Limiting Architecture

### 1. **Custom Rate Limiters**

All rate limiters are configured in `AppServiceProvider::configureRateLimiters()` method:

```php
// Example: Authentication Rate Limiter
RateLimiter::for('login', function (Request $request) {
    $key = strtolower($request->input('email')).'|'.$request->ip();
    return [
        Limit::perMinute(5)->by($key),        // 5 attempts per minute per email/IP
        Limit::perDay(50)->by($key),          // 50 attempts per day per email/IP
    ];
});
```

### 2. **Middleware Chain**

Rate limiting is applied through a combination of:
- `throttle:{limiter_name}` - Laravel's built-in throttle middleware
- `rate-limit-handler` - Custom middleware for IP whitelisting and logging

## Rate Limiter Categories

### Authentication Protection
- **Rate Limiter**: `login`
- **Limits**: 5 attempts/minute, 50 attempts/day per email/IP
- **Purpose**: Prevent brute force attacks
- **Applied to**: `/login`, password reset endpoints

### Public API Protection
- **Rate Limiter**: `wilayah-public`
- **Limits**: 60 requests/minute, 1000 requests/day per IP
- **Purpose**: Protect geographic data endpoints
- **Applied to**: `/api/wilayah/*`

- **Rate Limiter**: `tracking-public`
- **Limits**: 30 requests/minute, 300 requests/day per IP
- **Purpose**: Prevent tracking system abuse
- **Applied to**: `/tracking/*`, `/api/tracking/*`

### Form Submission Protection
- **Rate Limiter**: `mushaf-request`
- **Limits**: 5 requests/hour, 10 requests/day per IP
- **Purpose**: Prevent spam submissions
- **Applied to**: `/mushaf-request/*`

### Certificate Downloads
- **Rate Limiter**: `certificate-download`
- **Limits**: 5 downloads/minute, 20 downloads/hour per IP
- **Purpose**: Prevent certificate download abuse
- **Applied to**: Certificate download and preview endpoints

### Authenticated User Operations
- **Rate Limiter**: `authenticated-api`
- **Limits**: 
  - Guest users: 30/minute, 300/hour
  - Regular users: 100/minute, 1000/hour
  - Admin users: 200/minute, 2000/hour
- **Purpose**: Role-based API access control
- **Applied to**: General authenticated endpoints

### Warehouse Operations
- **Rate Limiter**: `warehouse-operations`
- **Limits**: 300/minute, 2000/hour (high limits for scanning)
- **Purpose**: Support high-speed barcode scanning operations
- **Applied to**: `/admin/warehouse/*`

### Administrative Operations
- **Rate Limiter**: `admin-operations`
- **Limits**: 150/minute, 1500/hour
- **Purpose**: General admin operation throttling
- **Applied to**: `/admin/*` (general admin routes)

### Bulk Operations
- **Rate Limiter**: `bulk-operations`
- **Limits**: 10/minute, 50/hour (conservative)
- **Purpose**: Protect against resource-intensive bulk operations
- **Applied to**: All bulk update/generation endpoints

### Export Operations
- **Rate Limiter**: `exports`
- **Limits**: 5/minute, 20/hour
- **Purpose**: Control resource-intensive export operations
- **Applied to**: All export endpoints

### QR Code Generation
- **Rate Limiter**: `qr-generation`
- **Limits**: 50/minute, 500/hour
- **Purpose**: Control QR code generation resources
- **Applied to**: QR generation and display endpoints

### Job Monitoring
- **Rate Limiter**: `job-monitoring`
- **Limits**: 120/minute, 1000/hour
- **Purpose**: Support frequent job status checks
- **Applied to**: Job progress and monitoring endpoints

### System Operations
- **Rate Limiter**: `system-operations`
- **Limits**: 20/minute, 100/hour (very restrictive)
- **Purpose**: Protect expensive system operations
- **Applied to**: Cache management, performance monitoring

## IP Whitelisting

### Configuration
IP whitelisting is configured in `config/rate-limiting.php`:

```php
'whitelist_ips' => [
    '127.0.0.1',           // Localhost
    '::1',                 // IPv6 localhost
    // Add your admin/monitoring IPs here
],
```

### Supported Formats
- Individual IPs: `192.168.1.100`
- CIDR subnets: `192.168.1.0/24`
- IPv6 addresses: `::1`

### Implementation
The `RateLimitHandler` middleware checks for whitelisted IPs before applying rate limits:

```php
private function isWhitelistedIP(string $ip): bool
{
    $whitelistedIPs = config('rate-limiting.whitelist_ips', []);
    // Check exact matches and subnet ranges
}
```

## Custom Rate Limit Responses

### API Responses
For API requests (`/api/*` or JSON expected):
```json
{
    "error": "Rate limit exceeded",
    "message": "Too many requests. Please try again later.",
    "retry_after": 60,
    "retry_after_human": "1 minutes"
}
```

### Web Responses
For web requests, users are redirected back with an error message.

### HTTP Headers
All responses include standard rate limiting headers:
- `Retry-After`: Seconds to wait
- `X-RateLimit-Reset`: Timestamp when limit resets
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining

## Monitoring and Alerting

### Logging
Rate limiting events are logged for monitoring:
```php
Log::warning('Rate limit exceeded', [
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'path' => $request->path(),
    'method' => $request->method(),
    'user_id' => $request->user()?->id,
    'retry_after' => $retryAfter
]);
```

### Configuration
Monitoring settings in `config/rate-limiting.php`:
```php
'monitoring' => [
    'enabled' => env('RATE_LIMIT_MONITORING', true),
    'log_channel' => env('RATE_LIMIT_LOG_CHANNEL', 'stack'),
    'alert_threshold' => env('RATE_LIMIT_ALERT_THRESHOLD', 0.8),
],
```

## Security Features

### Suspicious Pattern Detection
The system can be configured to detect and block suspicious request patterns:
```php
'security' => [
    'block_suspicious_patterns' => env('RATE_LIMIT_BLOCK_SUSPICIOUS', true),
    'max_failed_attempts' => env('RATE_LIMIT_MAX_FAILED_ATTEMPTS', 10),
    'lockout_duration' => env('RATE_LIMIT_LOCKOUT_DURATION', 300),
],
```

### User Agent Tracking
Optional user agent tracking for abuse detection:
```php
'track_user_agents' => env('RATE_LIMIT_TRACK_USER_AGENTS', true),
```

## Performance Considerations

### Cache Storage
Rate limiting uses configurable cache storage:
```php
'cache_store' => env('RATE_LIMIT_CACHE_STORE', null), // Uses default cache
```

For high-traffic applications, Redis is recommended:
```env
RATE_LIMIT_CACHE_STORE=redis
```

### Optimization Tips
1. Use Redis for cache storage in production
2. Consider separate Redis instance for rate limiting
3. Monitor rate limit hit ratios
4. Adjust limits based on actual usage patterns

## Environment Configuration

### Key Environment Variables
```env
# Rate Limiting
RATE_LIMIT_DEFAULT=60
RATE_LIMIT_CACHE_STORE=redis
RATE_LIMIT_MONITORING=true
RATE_LIMIT_LOG_CHANNEL=stack
RATE_LIMIT_ALERT_THRESHOLD=0.8

# Security
RATE_LIMIT_BLOCK_SUSPICIOUS=true
RATE_LIMIT_MAX_FAILED_ATTEMPTS=10
RATE_LIMIT_LOCKOUT_DURATION=300
RATE_LIMIT_TRACK_USER_AGENTS=true
```

## Testing Rate Limits

### Manual Testing
Use tools like `curl` or Postman to test rate limits:
```bash
# Test login rate limiting
for i in {1..10}; do
  curl -X POST http://localhost/login \
    -d "email=test@example.com&password=wrong" \
    -H "Content-Type: application/x-www-form-urlencoded"
done
```

### Automated Testing
Create feature tests for rate limiting:
```php
public function test_login_rate_limiting()
{
    // Attempt login multiple times
    // Assert 429 response after limit exceeded
}
```

## Route Coverage

### Protected Routes by Category

#### Authentication Routes
- `/login` (POST) - `throttle:login`
- Password reset routes - `throttle:auth`

#### Public API Routes
- `/api/wilayah/*` - `throttle:wilayah-public`
- `/api/tracking/*` - `throttle:tracking-public`
- `/tracking/*` - `throttle:tracking-public`

#### Form Submission Routes
- `/mushaf-request/*` - `throttle:mushaf-request`
- `/mushaf-tracking/*` - `throttle:tracking-public`

#### Admin Routes
- `/admin/*` - `throttle:admin-operations`
- `/admin/warehouse/*` - `throttle:warehouse-operations`

#### Bulk Operations
- All bulk update endpoints - `throttle:bulk-operations`
- QR bulk generation - `throttle:bulk-operations`
- Certificate bulk generation - `throttle:bulk-operations`

#### Exports
- All export endpoints - `throttle:exports`
- Performance reports - `throttle:exports`

#### System Operations
- Cache management - `throttle:system-operations`
- Performance monitoring - `throttle:system-operations`
- Bulk operations analytics - `throttle:system-operations`

## Maintenance

### Adjusting Limits
1. Monitor usage patterns in logs
2. Update limits in `AppServiceProvider::configureRateLimiters()`
3. Test changes in staging environment
4. Deploy with careful monitoring

### Adding New Endpoints
1. Identify appropriate rate limiter category
2. Add throttle middleware to route definition
3. Update documentation
4. Test thoroughly

### Troubleshooting
Common issues and solutions:
1. **Rate limits too strict**: Check logs for legitimate user patterns
2. **Rate limits bypassed**: Verify middleware chain order
3. **Performance issues**: Consider Redis for cache storage
4. **IP whitelisting not working**: Check IP format and middleware order

## Conclusion

This comprehensive rate limiting strategy provides:
- **Security**: Protection against brute force and abuse
- **Stability**: Prevention of system overload
- **Flexibility**: Different limits for different user types
- **Monitoring**: Full visibility into rate limiting events
- **Scalability**: Redis-backed storage for high traffic

The implementation balances security with usability, ensuring legitimate users have a smooth experience while protecting against malicious activities.