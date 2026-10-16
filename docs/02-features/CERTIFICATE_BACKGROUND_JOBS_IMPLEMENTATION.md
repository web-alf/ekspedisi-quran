# Certificate Background Jobs Implementation

## Overview

This document describes the complete implementation of background job processing for PDF certificate generation in the Ekspedisi Al-Quran system. The implementation moves heavy PDF generation tasks from blocking request threads to Laravel queue jobs, preventing server overwhelm during bulk certificate generation.

## Architecture Overview

### Core Components

1. **Job Classes** - Background job implementations
2. **API Controllers** - Job status monitoring endpoints  
3. **Service Classes** - Certificate generation and file management
4. **Frontend Components** - User progress tracking interfaces
5. **Cleanup System** - Automated file maintenance

### Queue Structure

- **certificates**: Primary queue for certificate generation jobs
- **notifications**: Queue for sending user notifications
- **maintenance**: Low-priority queue for cleanup tasks

## Implementation Details

### 1. Job Classes

#### GenerateSingleCertificateJob
- **Purpose**: Generate individual batch certificates
- **Queue**: certificates
- **Timeout**: 30 minutes
- **Features**: Progress tracking, error handling, cleanup

```php
GenerateSingleCertificateJob::dispatch($wakafBatchId, $options, $userId);
```

#### GenerateBulkCertificatesJob  
- **Purpose**: Generate multiple certificates with batching
- **Queue**: certificates
- **Timeout**: 2 hours
- **Features**: Chunk processing, ZIP creation, memory management

```php
GenerateBulkCertificatesJob::dispatch($wakafBatchIds, $options, $userId);
```

#### CertificateGenerationJob (Legacy)
- **Purpose**: Consolidated certificate generation for donatur
- **Queue**: certificates
- **Updated**: Modern Laravel patterns, improved error handling

### 2. Progress Tracking System

#### JobProgress Model
- Tracks job status, progress percentage, and results
- Stores metadata and error messages
- Provides scopes for filtering and statistics

#### TracksProgress Trait
- Shared functionality for job progress tracking
- Automatic notification sending
- Standardized progress reporting

### 3. API Endpoints

#### Job Monitoring API (`/api/jobs`)
- `GET /active` - Get active jobs for user
- `GET /recent` - Get job history  
- `GET /stats` - Get job statistics
- `GET /{jobId}` - Get specific job details
- `POST /{jobId}/cancel` - Cancel running job
- `DELETE /{jobId}` - Delete job record

### 4. Frontend Components

#### JobProgressIndicator.svelte
- Floating indicator showing active jobs
- Real-time progress updates
- Click to open detailed modal

#### JobProgressModal.svelte  
- Tabbed interface (Active, Recent, Statistics)
- Progress bars and status indicators
- Job cancellation and deletion
- Download links for completed jobs

### 5. Controller Updates

#### CertificateController
- **Smart Processing**: Determines async vs sync based on complexity
- **Bulk Operations**: Always processed asynchronously  
- **Error Handling**: Consistent error responses
- **Job Dispatching**: Proper job queuing with user tracking

### 6. Notification System

#### CertificateJobCompletedNotification
- Email and database notifications
- Smart sending based on job importance
- Download links and results summary
- Admin notifications for failed jobs

### 7. File Storage Management

#### CertificateStorageService
- Storage statistics and analysis
- Orphaned file detection and cleanup
- File integrity validation
- Storage recommendations

#### CleanupOldCertificatesJob
- Automated cleanup of old files
- Configurable retention periods
- Safe cleanup with validation
- Backup creation support

### 8. Command Line Tools

#### certificates:cleanup
- Storage statistics display
- Dry-run simulation
- Configurable cleanup types
- Force cleanup option

```bash
# Show storage stats
php artisan certificates:cleanup --stats

# Dry run simulation  
php artisan certificates:cleanup --dry-run --days=30

# Run actual cleanup
php artisan certificates:cleanup --days=30 --type=all
```

## Configuration

### Queue Configuration
```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

### Job Timeouts
- Single certificates: 30 minutes
- Bulk operations: 2 hours  
- Cleanup operations: 1 hour

### Memory Management
- Chunk processing for large operations
- Garbage collection between chunks
- Cleanup of partial files on failure

## Usage Examples

### Dispatch Single Certificate Job
```php
// In controller
$job = GenerateSingleCertificateJob::dispatch(
    $wakafBatch->id,
    ['template_id' => $templateId],
    auth()->id()
);

return response()->json([
    'success' => true,
    'message' => 'Sertifikat sedang diproses di background',
    'job_dispatched' => true
]);
```

### Dispatch Bulk Certificate Job
```php
$job = GenerateBulkCertificatesJob::dispatch(
    $batchIds,
    [
        'template_id' => $templateId,
        'create_zip' => true
    ],
    auth()->id()
);
```

### Monitor Job Progress (Frontend)
```javascript
// API call to get active jobs
const response = await fetch('/api/jobs/active');
const data = await response.json();
const activeJobs = data.data.jobs;
```

## Performance Optimizations

### Memory Management
- Process items in small chunks (5-10 items)
- Clear memory between chunks
- Cleanup temporary files automatically

### Database Optimization  
- Eager loading of relationships
- Indexed queries for job progress
- Batch updates for progress tracking

### File System Optimization
- Organized directory structure by date
- Automatic cleanup of old files
- Storage statistics and monitoring

## Error Handling

### Job Failure Scenarios
1. **Template not found** - Clear error message, no retry
2. **File system errors** - Retry with backoff
3. **Memory exhaustion** - Automatic cleanup and retry
4. **Database connectivity** - Retry with exponential backoff

### User Notification
- Immediate notification for failed jobs
- Email notifications for important jobs
- Admin notifications for critical failures

### Cleanup and Recovery
- Automatic cleanup of partial files
- Job progress tracking for all operations
- Manual retry capabilities

## Monitoring and Maintenance

### Job Queue Monitoring
```bash
# Check queue status
php artisan queue:work

# Monitor failed jobs  
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Storage Monitoring
```bash
# Check storage stats
php artisan certificates:cleanup --stats

# Clean up old files
php artisan certificates:cleanup --days=30
```

### Performance Monitoring
- Job progress API provides processing statistics
- Frontend dashboard shows active job counts
- Database storage optimization recommendations

## Security Considerations

### Access Control
- Job progress API requires authentication
- Users can only see their own jobs
- Admin notifications for sensitive failures

### File Security
- Generated files stored in protected directories
- Public access via controlled download endpoints
- Automatic cleanup of old files

### Input Validation
- Template ID validation
- Batch ID validation  
- User permission checks

## Deployment Requirements

### Queue Worker Setup
```bash
# Install supervisor for queue worker management
sudo apt-get install supervisor

# Configure supervisor (included in deployment/)
sudo cp deployment/queue-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start queue-worker:*
```

### Database Tables
- job_progress table for tracking (migration included)
- jobs table for Laravel queue (built-in)
- failed_jobs table for error tracking (built-in)

### Storage Requirements
- Adequate disk space for certificate files
- Regular backup of important certificates
- Monitoring of storage usage

## Testing

### Job Processing Tests
```php
// Test single certificate generation
$job = new GenerateSingleCertificateJob($batchId, [], 1);
$job->handle(app(BatchCertificateService::class));

// Test bulk processing
$job = new GenerateBulkCertificatesJob([1, 2, 3], [], 1);
$job->handle(app(BatchCertificateService::class));
```

### API Endpoint Tests
```php
// Test job progress API
$response = $this->get('/api/jobs/active');
$response->assertStatus(200);
$response->assertJsonStructure(['success', 'data']);
```

## Benefits Achieved

### Performance Improvements
- ✅ Non-blocking certificate generation
- ✅ Improved user experience during bulk operations
- ✅ Server resource management
- ✅ Scalable job processing

### User Experience
- ✅ Real-time progress tracking
- ✅ Background processing notifications
- ✅ Bulk download capabilities (ZIP files)
- ✅ Clear error reporting

### System Reliability
- ✅ Automatic retry on failure
- ✅ Graceful error handling
- ✅ File cleanup and maintenance
- ✅ Progress persistence

### Administrative Benefits
- ✅ Storage monitoring and cleanup
- ✅ Job performance statistics
- ✅ Automated maintenance tasks
- ✅ Error notification system

## Future Enhancements

### Possible Improvements
1. **Redis Queue**: For better performance and features
2. **Job Batching**: Laravel's native job batching features
3. **Webhook Notifications**: External system notifications
4. **Advanced Scheduling**: Cron-based cleanup scheduling
5. **Performance Analytics**: Detailed job performance metrics

### Monitoring Enhancements
1. **Grafana Integration**: Visual monitoring dashboards
2. **Alert System**: Proactive error notifications
3. **Capacity Planning**: Storage and processing capacity metrics

This implementation provides a robust, scalable solution for certificate generation that significantly improves system performance and user experience while maintaining data integrity and security.