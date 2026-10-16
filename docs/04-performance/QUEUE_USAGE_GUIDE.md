# Queue System Usage Guide

## Overview

The new queue system allows warehouse operations to run in the background, providing immediate response to users while processing large datasets efficiently.

## Key Features

### 1. Bulk Status Updates
Replace the old synchronous `updateStatus` with `bulkUpdateStatus`:

```javascript
// OLD WAY (blocks UI for 45+ seconds)
POST /admin/warehouse/box-scanner/update-status
{
  "box_id": 123,
  "new_status_id": 5
}

// NEW WAY (returns immediately)
POST /admin/warehouse/box-scanner/bulk-update-status
{
  "box_ids": [123, 124, 125],
  "new_status_id": 5,
  "address": "Optional address",
  "notes": "Optional notes"
}
```

### 2. Bulk Box Operations
Process multiple boxes with various operations:

```javascript
POST /admin/warehouse/box-scanner/bulk-box-operation
{
  "box_codes": ["KB-20250815-001-QUR-01", "KB-20250815-002-QUR-01"],
  "operation": "seal", // seal, unseal, validate, archive
  "parameters": {
    "seal_code": "CUSTOM_SEAL_123"
  }
}
```

### 3. Job Progress Monitoring
Track job progress in real-time:

```javascript
// Get job progress
GET /admin/warehouse/job-progress/{job_id}

// Get job history
GET /admin/warehouse/job-history?limit=20&status=completed

// Cancel active job
POST /admin/warehouse/job-monitor/cancel
{
  "job_id": "job_123"
}

// Retry failed job
POST /admin/warehouse/job-monitor/retry
{
  "job_id": "job_123"
}
```

## Frontend Integration

### Using the JobProgressCard Component

```svelte
<script>
  import JobProgressCard from '$lib/Components/Warehouse/JobProgressCard.svelte';
  
  let activeJobs = [];
  
  // When starting a bulk operation
  function startBulkUpdate() {
    fetch('/admin/warehouse/box-scanner/bulk-update-status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        box_ids: selectedBoxIds,
        new_status_id: selectedStatusId
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Add to active jobs for monitoring
        activeJobs = [...activeJobs, {
          job_id: data.data.job_id,
          title: `Update ${data.data.box_count} boxes`,
          status: 'pending',
          progress_percentage: 0
        }];
      }
    });
  }
</script>

<!-- Display active jobs -->
{#each activeJobs as job}
  <JobProgressCard {job} autoRefresh={true} />
{/each}
```

## Queue Worker Management

### Starting Queue Workers

```bash
# Start a queue worker
php artisan queue:work

# Start with specific options
php artisan queue:work --timeout=3600 --tries=3 --max-jobs=100

# Start multiple workers (recommended for production)
php artisan queue:work --queue=default &
php artisan queue:work --queue=warehouse &
php artisan queue:work --queue=documents &
```

### Monitoring Queue

```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Clear all jobs from queue
php artisan queue:flush
```

## Testing the System

### Test Commands

```bash
# Test bulk status update
php artisan queue:test status --user=1

# Test bulk box processing
php artisan queue:test box --user=1

# Process the test jobs
php artisan queue:work --stop-when-empty
```

### Manual Testing

1. **Test Bulk Status Update**:
   ```bash
   curl -X POST http://your-app.test/admin/warehouse/box-scanner/bulk-update-status \
     -H "Content-Type: application/json" \
     -d '{
       "box_ids": [1, 2, 3],
       "new_status_id": 5
     }'
   ```

2. **Monitor Progress**:
   ```bash
   curl http://your-app.test/admin/warehouse/job-progress/JOB_ID_HERE
   ```

## Performance Benefits

### Before Queue System
- ❌ 45+ second wait times
- ❌ UI freezes during processing
- ❌ Memory exhaustion with large datasets
- ❌ No error recovery
- ❌ No progress feedback

### After Queue System
- ✅ < 1 second response time
- ✅ Non-blocking background processing
- ✅ Handles unlimited dataset size
- ✅ Automatic retry with backoff
- ✅ Real-time progress tracking

## Error Handling

### Automatic Retry
Jobs automatically retry 3 times with exponential backoff:
- 1st retry: after 1 minute
- 2nd retry: after 5 minutes  
- 3rd retry: after 15 minutes

### Manual Recovery
Users can manually retry failed jobs through the UI or API:

```javascript
POST /admin/warehouse/job-monitor/retry
{
  "job_id": "failed_job_id"
}
```

### Error Notifications
- Failed jobs are logged with detailed error messages
- Users receive notifications about job completion/failure
- Error details are available in the job monitor dashboard

## Production Deployment

### Queue Configuration
```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE_RETRY_AFTER=90
```

### Supervisor Configuration
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/path/to/project
autostart=true
autorestart=true
numprocs=3
user=www-data
```

### Monitoring
- Setup queue monitoring dashboard at `/admin/warehouse/job-monitor`
- Configure alerts for failed jobs
- Monitor queue size and worker health

## Best Practices

1. **Chunking**: Keep chunk sizes reasonable (100-500 items)
2. **Timeouts**: Set appropriate timeouts for long-running operations
3. **Memory**: Monitor memory usage and implement cleanup
4. **Logging**: Log important operations for debugging
5. **Testing**: Always test with sample data first
6. **Monitoring**: Keep an eye on queue size and failed jobs

## Troubleshooting

### Common Issues

1. **Jobs Not Processing**
   ```bash
   # Check if workers are running
   ps aux | grep "queue:work"
   
   # Check queue table
   php artisan tinker
   >>> DB::table('jobs')->count()
   ```

2. **Memory Issues**
   ```bash
   # Increase memory limit
   php -d memory_limit=512M artisan queue:work
   ```

3. **Failed Jobs**
   ```bash
   # View failed jobs
   php artisan queue:failed
   
   # Retry specific job
   php artisan queue:retry job_id
   ```

4. **Progress Not Updating**
   - Check if JobProgress model is being created
   - Verify database connections
   - Check for JavaScript errors in browser console

---

The queue system provides a robust, scalable solution for handling bulk warehouse operations with excellent user experience and comprehensive monitoring capabilities.
