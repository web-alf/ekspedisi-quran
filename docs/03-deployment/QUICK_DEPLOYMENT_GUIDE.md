# Quick Production Deployment Guide

**🚀 Fast Track to Production Deployment**

This guide provides a streamlined path to deploy the performance-optimized Ekspedisi Quran application to production with comprehensive monitoring.

## Prerequisites ✅

- Ubuntu 22.04 LTS server
- Domain name with DNS configured
- SSL certificate ready (Let's Encrypt)
- Database credentials prepared
- SMTP settings for alerts

## Step 1: Environment Setup (15 minutes)

```bash
# Clone repository
git clone https://github.com/your-org/ekspedisi-quran.git
cd ekspedisi-quran

# Make scripts executable
chmod +x scripts/*.sh

# Set environment variables
export ALERT_EMAIL="admin@your-domain.com"
export DB_PASSWORD="your_secure_password"
export REDIS_PASSWORD="your_redis_password"
```

## Step 2: Infrastructure Setup (30 minutes)

```bash
# Install and configure all services
sudo ./scripts/server-setup.sh

# Setup production monitoring
sudo ./scripts/production-monitoring-setup.sh

# Configure domain and SSL
sudo sed -i 's/your-domain.com/actual-domain.com/g' /etc/nginx/sites-available/ekspedisi-quran
sudo certbot --nginx -d actual-domain.com
```

## Step 3: Application Deployment (20 minutes)

```bash
# Deploy application
./scripts/deploy-production.sh

# Configure environment
cp .env.production .env
nano .env  # Update with your settings

# Run migrations and optimization
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder
php artisan cache:manage warm
```

## Step 4: Validation & Testing (15 minutes)

```bash
# Run comprehensive validation
./scripts/production-validation-suite.sh

# Check monitoring dashboard
./scripts/monitoring-dashboard.sh

# Test emergency procedures
./scripts/emergency-response-toolkit.sh status
```

## Step 5: Go-Live (5 minutes)

```bash
# Final health check
php artisan monitoring:health-check --comprehensive

# Remove maintenance mode (if enabled)
php artisan up

# Monitor for first hour
watch -n 30 './scripts/emergency-response-toolkit.sh status'
```

## Key Configuration Files

### Environment (.env)
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ekspedisi_quran
DB_USERNAME=ekspedisi
DB_PASSWORD=your_password

CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password

QUEUE_CONNECTION=redis

MONITORING_ENABLED=true
MONITORING_ALERT_EMAIL=admin@your-domain.com
```

### Nginx Configuration
- Located: `/etc/nginx/sites-available/ekspedisi-quran`
- SSL termination with security headers
- Rate limiting and caching
- PHP-FPM integration

### Supervisor Queue Workers
- High priority: 2 workers
- Default priority: 3 workers  
- Low priority: 1 worker
- Auto-restart on failure

## Monitoring Endpoints

### Health Checks
- Application: `https://your-domain.com/health`
- API: `https://your-domain.com/api/health`
- Admin: `https://your-domain.com/admin/monitoring`

### Log Locations
- Application: `/var/www/ekspedisi-quran/storage/logs/`
- System: `/var/log/ekspedisi/`
- Nginx: `/var/log/nginx/`
- MySQL: `/var/log/mysql/`

## Quick Commands Reference

### Emergency Response
```bash
# Check system status
./scripts/emergency-response-toolkit.sh status

# Enable maintenance mode
./scripts/emergency-response-toolkit.sh maintenance-on

# Database recovery
./scripts/emergency-response-toolkit.sh database-recovery

# Queue recovery
./scripts/emergency-response-toolkit.sh queue-recovery

# Rollback application
./scripts/emergency-response-toolkit.sh rollback 20250815_140000
```

### Monitoring
```bash
# Real-time dashboard
./scripts/monitoring-dashboard.sh

# Daily summary
./scripts/monitoring-summary.sh

# Health check
php artisan monitoring:health-check

# Performance benchmarks
php artisan monitoring:benchmarks
```

### Maintenance
```bash
# Daily cleanup
php artisan storage:cleanup
php artisan cache:prune

# Performance optimization  
php artisan cache:manage warm
mysqlcheck --optimize ekspedisi_quran

# Log rotation
logrotate /etc/logrotate.d/ekspedisi-monitoring
```

## Performance Expectations

### Response Times
- Home page: < 200ms
- Admin panel: < 300ms
- API endpoints: < 100ms
- Database queries: < 50ms

### Capacity
- Concurrent users: 100+
- Queue throughput: 1000+ jobs/minute
- Storage: Optimized for 10GB+ data
- Memory usage: < 80% of available

## Troubleshooting Quick Fixes

### High Memory Usage
```bash
systemctl restart php8.1-fpm
php artisan cache:clear
./scripts/emergency-response-toolkit.sh storage-cleanup
```

### Slow Database
```bash
mysqlcheck --optimize ekspedisi_quran
php artisan cache:manage warm
mysql -e "SHOW PROCESSLIST;"
```

### Queue Backup
```bash
supervisorctl restart ekspedisi-queue-*
php artisan queue:flush
php artisan queue:retry all
```

### SSL Issues
```bash
certbot renew --dry-run
systemctl reload nginx
openssl s_client -connect your-domain.com:443
```

## Success Verification

After deployment, verify these metrics:

✅ **Response Time**: < 200ms (curl test)  
✅ **SSL Grade**: A+ (SSL Labs test)  
✅ **Uptime**: 100% (initial 24 hours)  
✅ **Security**: No vulnerabilities (security scan)  
✅ **Performance**: All benchmarks green  
✅ **Monitoring**: All alerts configured  

## Next Steps

1. **Monitor for 24 hours** - Watch dashboards and alerts
2. **Performance tune** - Adjust based on real traffic
3. **Backup verify** - Test backup/restore procedures  
4. **Team training** - Train support team on procedures
5. **Documentation** - Update runbooks with any changes

## Support

- **Documentation**: See `docs/` directory for detailed guides
- **Emergency**: Use emergency response toolkit
- **Monitoring**: Check monitoring dashboard for system status
- **Logs**: Review logs in `/var/log/ekspedisi/` for issues

---

**Total Deployment Time**: ~85 minutes  
**Difficulty**: Intermediate  
**Prerequisites**: Linux system administration knowledge  
**Result**: Production-ready application with enterprise monitoring
