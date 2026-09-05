<script>
    import { onMount, onDestroy } from 'svelte';
    import { writable } from 'svelte/store';
    import HeroIcon from '../UI/HeroIcon.svelte';

    export let isOpen = false;
    export let onClose = () => {};

    let activeJobs = writable([]);
    let recentJobs = writable([]);
    let jobStats = writable({});
    let loading = true;
    let error = null;
    let activeTab = 'active';
    let refreshInterval;
    let showDetails = {};

    // Auto-refresh active jobs every 3 seconds
    onMount(() => {
        if (isOpen) {
            loadJobData();
            refreshInterval = setInterval(loadJobData, 3000);
        }
    });

    onDestroy(() => {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });

    $: if (isOpen) {
        loadJobData();
        refreshInterval = setInterval(loadJobData, 3000);
    } else {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    }

    async function loadJobData() {
        try {
            loading = true;
            error = null;

            const [activeResponse, recentResponse, statsResponse] = await Promise.all([
                fetch('/api/jobs/active'),
                fetch('/api/jobs/recent?limit=10'),
                fetch('/api/jobs/stats')
            ]);

            if (activeResponse.ok) {
                const activeData = await activeResponse.json();
                activeJobs.set(activeData.data.jobs || []);
            }

            if (recentResponse.ok) {
                const recentData = await recentResponse.json();
                recentJobs.set(recentData.data.jobs || []);
            }

            if (statsResponse.ok) {
                const statsData = await statsResponse.json();
                jobStats.set(statsData.data || {});
            }

        } catch (err) {
            error = err.message;
            console.error('Failed to load job data:', err);
        } finally {
            loading = false;
        }
    }

    async function cancelJob(jobId) {
        try {
            const response = await fetch(`/api/jobs/${jobId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                loadJobData(); // Refresh data
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Gagal membatalkan pekerjaan');
            }
        } catch (err) {
            console.error('Failed to cancel job:', err);
            alert('Error: Gagal membatalkan pekerjaan');
        }
    }

    async function deleteJob(jobId) {
        if (!confirm('Yakin ingin menghapus riwayat pekerjaan ini?')) {
            return;
        }

        try {
            const response = await fetch(`/api/jobs/${jobId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                loadJobData(); // Refresh data
            } else {
                const errorData = await response.json();
                alert(errorData.message || 'Gagal menghapus riwayat pekerjaan');
            }
        } catch (err) {
            console.error('Failed to delete job:', err);
            alert('Error: Gagal menghapus riwayat pekerjaan');
        }
    }

    function toggleJobDetails(jobId) {
        showDetails[jobId] = !showDetails[jobId];
        showDetails = { ...showDetails };
    }

    function getStatusIcon(status) {
        switch (status) {
            case 'pending': return '⏳';
            case 'processing': return '⚙️';
            case 'completed': return '✅';
            case 'failed': return '❌';
            default: return '📋';
        }
    }

    function getStatusColor(status) {
        switch (status) {
            case 'pending': return 'text-yellow-600 bg-yellow-100';
            case 'processing': return 'text-blue-600 bg-blue-100';
            case 'completed': return 'text-green-600 bg-green-100';
            case 'failed': return 'text-red-600 bg-red-100';
            default: return 'text-gray-600 bg-gray-100';
        }
    }

    function formatProgressBar(job) {
        const percentage = job.progress_percentage || 0;
        let colorClass = 'bg-blue-500';
        
        if (job.status === 'completed') colorClass = 'bg-green-500';
        else if (job.status === 'failed') colorClass = 'bg-red-500';
        else if (job.status === 'processing') colorClass = 'bg-blue-500';
        
        return { percentage, colorClass };
    }
</script>

{#if isOpen}
<!-- Modal Background -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <!-- Modal Container -->
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b">
            <h2 class="text-xl font-semibold text-gray-900">Progress Pekerjaan Sertifikat</h2>
            <button 
                on:click={onClose}
                class="text-gray-400 hover:text-gray-600 transition-colors"
            >
                <HeroIcon name="x-mark" class="w-6 h-6" />
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex flex-col h-[600px]">
            <!-- Tab Navigation -->
            <div class="flex border-b bg-gray-50">
                <button 
                    class="px-4 py-3 font-medium text-sm transition-colors {activeTab === 'active' ? 'text-blue-600 border-b-2 border-blue-600 bg-white' : 'text-gray-500 hover:text-gray-700'}"
                    on:click={() => activeTab = 'active'}
                >
                    Pekerjaan Aktif ({$activeJobs.length})
                </button>
                <button 
                    class="px-4 py-3 font-medium text-sm transition-colors {activeTab === 'recent' ? 'text-blue-600 border-b-2 border-blue-600 bg-white' : 'text-gray-500 hover:text-gray-700'}"
                    on:click={() => activeTab = 'recent'}
                >
                    Riwayat ({$recentJobs.length})
                </button>
                <button 
                    class="px-4 py-3 font-medium text-sm transition-colors {activeTab === 'stats' ? 'text-blue-600 border-b-2 border-blue-600 bg-white' : 'text-gray-500 hover:text-gray-700'}"
                    on:click={() => activeTab = 'stats'}
                >
                    Statistik
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-6">
                {#if loading}
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="ml-2 text-gray-600">Memuat data...</span>
                    </div>
                {/if}

                {#if error}
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <HeroIcon name="x-circle" class="h-5 w-5 text-red-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-800">Error: {error}</p>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Active Jobs Tab -->
                {#if activeTab === 'active' && !loading}
                    {#if $activeJobs.length === 0}
                        <div class="text-center py-8">
                            <HeroIcon name="inbox-stack" class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada pekerjaan aktif</h3>
                            <p class="mt-1 text-sm text-gray-500">Semua pekerjaan sertifikat telah selesai.</p>
                        </div>
                    {:else}
                        <div class="space-y-4">
                            {#each $activeJobs as job}
                                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <span class="text-2xl">{getStatusIcon(job.status)}</span>
                                            <div>
                                                <h4 class="font-medium text-gray-900">{job.title}</h4>
                                                <p class="text-sm text-gray-600">{job.created_at}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {getStatusColor(job.status)}">
                                                {job.status_display}
                                            </span>
                                            {#if job.can_cancel}
                                                <button 
                                                    on:click={() => cancelJob(job.id)}
                                                    class="text-red-600 hover:text-red-900 text-sm font-medium"
                                                >
                                                    Batalkan
                                                </button>
                                            {/if}
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    {#if job.progress_percentage !== null}
                                        {@const progressInfo = formatProgressBar(job)}
                                        <div class="mt-3">
                                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                                <span>{job.processed_items}/{job.total_items} item</span>
                                                <span>{progressInfo.percentage}%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div 
                                                    class="h-2 rounded-full transition-all duration-300 {progressInfo.colorClass}" 
                                                    style="width: {progressInfo.percentage}%"
                                                ></div>
                                            </div>
                                        </div>
                                    {/if}

                                    <!-- Estimated Completion -->
                                    {#if job.estimated_completion}
                                        <div class="mt-2 text-sm text-gray-600">
                                            <span>Estimasi selesai: {job.estimated_completion.estimated_completion_formatted}</span>
                                            <span class="ml-2">({job.estimated_completion.processing_speed} item/menit)</span>
                                        </div>
                                    {/if}

                                    <!-- Duration -->
                                    {#if job.duration_formatted}
                                        <div class="mt-1 text-sm text-gray-500">
                                            Durasi: {job.duration_formatted}
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {/if}
                {/if}

                <!-- Recent Jobs Tab -->
                {#if activeTab === 'recent' && !loading}
                    {#if $recentJobs.length === 0}
                        <div class="text-center py-8">
                            <HeroIcon name="clock" class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada riwayat</h3>
                            <p class="mt-1 text-sm text-gray-500">Riwayat pekerjaan akan muncul di sini.</p>
                        </div>
                    {:else}
                        <div class="space-y-4">
                            {#each $recentJobs as job}
                                <div class="bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <span class="text-2xl">{getStatusIcon(job.status)}</span>
                                            <div>
                                                <h4 class="font-medium text-gray-900">{job.title}</h4>
                                                <p class="text-sm text-gray-600">{job.created_at}</p>
                                                {#if job.completed_at}
                                                    <p class="text-sm text-gray-500">Selesai: {job.completed_at}</p>
                                                {/if}
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {getStatusColor(job.status)}">
                                                {job.status_display}
                                            </span>
                                            <button 
                                                on:click={() => toggleJobDetails(job.id)}
                                                class="text-gray-400 hover:text-gray-600"
                                            >
                                                <HeroIcon name="chevron-down" class="w-5 h-5" />
                                            </button>
                                            <button 
                                                on:click={() => deleteJob(job.id)}
                                                class="text-red-400 hover:text-red-600"
                                            >
                                                <HeroIcon name="trash" class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Job Results Summary -->
                                    {#if job.status === 'completed' && job.results}
                                        <div class="mt-3 grid grid-cols-3 gap-4 text-sm">
                                            <div class="text-center">
                                                <div class="font-semibold text-green-600">{job.results.certificates_generated || job.processed_items}</div>
                                                <div class="text-gray-600">Berhasil</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="font-semibold text-red-600">{job.results.certificates_failed || job.failed_items}</div>
                                                <div class="text-gray-600">Gagal</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="font-semibold text-blue-600">{job.success_rate}%</div>
                                                <div class="text-gray-600">Tingkat Berhasil</div>
                                            </div>
                                        </div>
                                    {/if}

                                    <!-- Error Message for Failed Jobs -->
                                    {#if job.status === 'failed' && job.error_message}
                                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                                            <p class="text-sm text-red-800">Error: {job.error_message}</p>
                                        </div>
                                    {/if}

                                    <!-- Expandable Details -->
                                    {#if showDetails[job.id]}
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <div class="grid grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <span class="font-medium text-gray-700">Jenis:</span>
                                                    <span class="text-gray-600">{job.job_type}</span>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-gray-700">Durasi:</span>
                                                    <span class="text-gray-600">{job.duration_formatted || '-'}</span>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-gray-700">Total Item:</span>
                                                    <span class="text-gray-600">{job.total_items}</span>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-gray-700">Diproses:</span>
                                                    <span class="text-gray-600">{job.processed_items}</span>
                                                </div>
                                            </div>

                                            {#if job.results && job.results.has_zip}
                                                <div class="mt-3">
                                                    <a 
                                                        href={job.results.zip_download_url} 
                                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 gap-1.5"
                                                    >
                                                        <HeroIcon name="arrow-down-tray" class="h-4 w-4" />
                                                        Download ZIP
                                                    </a>
                                                </div>
                                            {/if}
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {/if}
                {/if}

                <!-- Statistics Tab -->
                {#if activeTab === 'stats' && !loading}
                    <div class="space-y-6">
                        <!-- General Stats -->
                        {#if $jobStats.general}
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Statistik Umum (7 hari terakhir)</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">{$jobStats.general.total_jobs || 0}</div>
                                        <div class="text-sm text-gray-600">Total Pekerjaan</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">{$jobStats.general.completed || 0}</div>
                                        <div class="text-sm text-gray-600">Selesai</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-red-600">{$jobStats.general.failed || 0}</div>
                                        <div class="text-sm text-gray-600">Gagal</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-yellow-600">{$jobStats.general.active || 0}</div>
                                        <div class="text-sm text-gray-600">Aktif</div>
                                    </div>
                                </div>
                            </div>
                        {/if}

                        <!-- Certificate Stats -->
                        {#if $jobStats.certificates}
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Statistik Sertifikat</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-indigo-600">{$jobStats.certificates.certificate_jobs || 0}</div>
                                        <div class="text-sm text-gray-600">Pekerjaan Sertifikat</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-emerald-600">{$jobStats.certificates.certificates_generated_today || 0}</div>
                                        <div class="text-sm text-gray-600">Dibuat Hari Ini</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-amber-600">{$jobStats.certificates.active_certificate_jobs || 0}</div>
                                        <div class="text-sm text-gray-600">Sedang Diproses</div>
                                    </div>
                                </div>
                            </div>
                        {/if}

                        <!-- Performance Stats -->
                        {#if $jobStats.general && $jobStats.general.avg_duration}
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Performa</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-purple-600">{Math.round($jobStats.general.avg_success_rate || 0)}%</div>
                                        <div class="text-sm text-gray-600">Rata-rata Tingkat Berhasil</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-cyan-600">{Math.round(($jobStats.general.avg_duration || 0) / 60)}m</div>
                                        <div class="text-sm text-gray-600">Rata-rata Durasi</div>
                                    </div>
                                </div>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-between p-6 border-t bg-gray-50">
            <div class="text-sm text-gray-500">
                Data diperbarui otomatis setiap 3 detik
            </div>
            <div class="flex space-x-3">
                <button 
                    on:click={loadJobData}
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 gap-2"
                >
                    <HeroIcon name="arrow-path" class="h-4 w-4" />
                    Refresh
                </button>
                <button 
                    on:click={onClose}
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
{/if}