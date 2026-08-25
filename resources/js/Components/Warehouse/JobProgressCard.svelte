<script>
    import { onMount, onDestroy } from 'svelte';
    import { router } from '@inertiajs/svelte';

    export let job;
    export let autoRefresh = true;
    export let refreshInterval = 2000; // 2 seconds

    let refreshTimer;
    let progressPercent = job?.progress_percentage || 0;
    let status = job?.status || 'pending';

    // Status colors
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-800'
    };

    // Status displays
    const statusDisplays = {
        pending: 'Menunggu',
        processing: 'Diproses',
        completed: 'Selesai',
        failed: 'Gagal',
        cancelled: 'Dibatalkan'
    };

    function refreshJobProgress() {
        if (!job?.job_id || status === 'completed' || status === 'failed' || status === 'cancelled') {
            stopRefresh();
            return;
        }

        fetch(`/admin/warehouse/job-progress/${job.job_id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                job = { ...job, ...data.data };
                progressPercent = job.progress_percentage;
                status = job.status;

                // Stop refreshing if job is complete
                if (['completed', 'failed', 'cancelled'].includes(status)) {
                    stopRefresh();
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing job progress:', error);
        });
    }

    function startRefresh() {
        if (autoRefresh && ['pending', 'processing'].includes(status)) {
            refreshTimer = setInterval(refreshJobProgress, refreshInterval);
        }
    }

    function stopRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    function cancelJob() {
        if (!confirm('Apakah Anda yakin ingin membatalkan job ini?')) return;

        fetch('/admin/warehouse/job-monitor/cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ job_id: job.job_id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                status = 'cancelled';
                stopRefresh();
                alert('Job berhasil dibatalkan');
            } else {
                alert('Gagal membatalkan job: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error cancelling job:', error);
            alert('Terjadi kesalahan saat membatalkan job');
        });
    }

    function retryJob() {
        if (!confirm('Apakah Anda yakin ingin menjalankan ulang job ini?')) return;

        fetch('/admin/warehouse/job-monitor/retry', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ job_id: job.job_id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                status = 'pending';
                progressPercent = 0;
                startRefresh();
                alert('Job berhasil dijadwalkan ulang');
            } else {
                alert('Gagal menjadwalkan ulang job: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error retrying job:', error);
            alert('Terjadi kesalahan saat menjadwalkan ulang job');
        });
    }

    onMount(() => {
        startRefresh();
    });

    onDestroy(() => {
        stopRefresh();
    });

    // Reactive statement to restart refresh when status changes
    $: if (status === 'processing' && autoRefresh && !refreshTimer) {
        startRefresh();
    }
</script>

<div class="bg-white rounded-lg shadow-md p-4 border">
    <div class="flex justify-between items-start mb-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{job?.title || 'Unknown Job'}</h3>
            <p class="text-sm text-gray-600">Job ID: {job?.job_id || 'N/A'}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {statusColors[status] || 'bg-gray-100 text-gray-800'}">
            {statusDisplays[status] || status}
        </span>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
        <div class="flex justify-between text-sm text-gray-600 mb-1">
            <span>Progress</span>
            <span>{progressPercent.toFixed(1)}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div 
                class="bg-blue-600 h-2 rounded-full transition-all duration-300 ease-out"
                style="width: {progressPercent}%"
            ></div>
        </div>
    </div>

    <!-- Progress Details -->
    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
        <div>
            <span class="text-gray-600">Total Items:</span>
            <span class="font-medium">{job?.total_items || 0}</span>
        </div>
        <div>
            <span class="text-gray-600">Processed:</span>
            <span class="font-medium text-green-600">{job?.processed_items || 0}</span>
        </div>
        <div>
            <span class="text-gray-600">Failed:</span>
            <span class="font-medium text-red-600">{job?.failed_items || 0}</span>
        </div>
        <div>
            <span class="text-gray-600">Success Rate:</span>
            <span class="font-medium">{job?.success_rate || 0}%</span>
        </div>
    </div>

    <!-- Timestamps -->
    <div class="text-xs text-gray-500 mb-4">
        <div>Created: {job?.created_at || 'N/A'}</div>
        {#if job?.started_at}
            <div>Started: {job.started_at}</div>
        {/if}
        {#if job?.completed_at}
            <div>Completed: {job.completed_at}</div>
        {/if}
        {#if job?.duration_formatted}
            <div>Duration: {job.duration_formatted}</div>
        {/if}
    </div>

    <!-- Error Message -->
    {#if status === 'failed' && job?.error_message}
        <div class="bg-red-50 border border-red-200 rounded-md p-3 mb-4">
            <div class="text-sm text-red-800">
                <strong>Error:</strong> {job.error_message}
            </div>
        </div>
    {/if}

    <!-- Action Buttons -->
    <div class="flex gap-2">
        {#if status === 'pending' || status === 'processing'}
            <button
                type="button"
                onclick={cancelJob}
                class="inline-flex items-center px-3 py-1.5 border border-red-300 text-sm font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
                Cancel
            </button>
        {/if}
        
        {#if status === 'failed'}
            <button
                type="button"
                onclick={retryJob}
                class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-sm font-medium rounded text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Retry
            </button>
        {/if}
        
        <button
            type="button"
            onclick={() => refreshJobProgress()}
            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
        >
            Refresh
        </button>
    </div>
</div>
