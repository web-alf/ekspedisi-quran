<script>
    import { onMount, onDestroy, tick } from 'svelte';
    import { writable } from 'svelte/store';
    import { page } from '@inertiajs/svelte';
    import JobProgressModal from './JobProgressModal.svelte';

    export let showNotifications = true;
    export let isAuthenticated = false; // Add authentication prop

    let activeJobs = writable([]);
    let loading = false;
    let showModal = false;
    let refreshInterval;
    let lastNotificationCount = 0;
    let authStateResolved = false;
    let authCheckTimeout;
    let componentMounted = false;

    // Reactively check authentication status from page props as well
    $: currentUser = $page.props.auth?.user;
    $: pageAuthenticated = !!currentUser?.id;
    
    // Robust authentication state - wait for both prop and page state to align
    $: isUserAuthenticated = isAuthenticated && pageAuthenticated && authStateResolved;

    // Debounced authentication state handler to prevent race conditions
    $: if (componentMounted) {
        handleAuthStateChange(isAuthenticated, pageAuthenticated);
    }

    /**
     * Handle authentication state changes with debouncing to prevent race conditions
     */
    function handleAuthStateChange(propAuth, pageAuth) {
        // Clear any existing timeout
        if (authCheckTimeout) {
            clearTimeout(authCheckTimeout);
        }

        // Debounce auth state changes to prevent rapid polling starts/stops
        authCheckTimeout = setTimeout(async () => {
            // Wait for next tick to ensure all reactive updates are complete
            await tick();
            
            // Check if both auth sources agree and are truthy
            const bothAuthenticated = propAuth && pageAuth;
            const bothUnauthenticated = !propAuth && !pageAuth;
            
            if (bothAuthenticated || bothUnauthenticated) {
                authStateResolved = true;
                
                if (bothAuthenticated) {
                    console.log('JobProgressIndicator: Authentication confirmed, starting polling');
                    startPolling();
                } else {
                    console.log('JobProgressIndicator: Authentication lost, stopping polling');
                    stopPolling();
                }
            } else {
                // Auth sources don't agree - wait a bit more
                authStateResolved = false;
                console.log('JobProgressIndicator: Auth state mismatch, waiting for resolution');
                
                // If still mismatched after longer delay, assume unauthenticated for safety
                setTimeout(() => {
                    if (!authStateResolved) {
                        console.log('JobProgressIndicator: Auth state timeout, stopping polling for safety');
                        stopPolling();
                        authStateResolved = true;
                    }
                }, 2000);
            }
        }, 300); // 300ms debounce
    }

    function startPolling() {
        // Double-check authentication before starting
        if (!isUserAuthenticated || refreshInterval) {
            return;
        }

        console.log('JobProgressIndicator: Starting job polling');
        loadActiveJobs();
        refreshInterval = setInterval(loadActiveJobs, 5000);
    }

    function stopPolling() {
        if (refreshInterval) {
            console.log('JobProgressIndicator: Stopping job polling');
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
        
        // Clear auth timeout as well
        if (authCheckTimeout) {
            clearTimeout(authCheckTimeout);
            authCheckTimeout = null;
        }
        
        // Clear active jobs when stopping polling
        activeJobs.set([]);
    }

    onMount(async () => {
        // Mark component as mounted to enable reactive auth handling
        componentMounted = true;
        
        // Wait for initial page props to settle
        await tick();
        
        console.log('JobProgressIndicator: Component mounted, auth state:', {
            propAuth: isAuthenticated,
            pageAuth: pageAuthenticated,
            currentUser: currentUser?.id
        });
        
        // Initial authentication check with a short delay to allow page props to stabilize
        setTimeout(() => {
            handleAuthStateChange(isAuthenticated, pageAuthenticated);
        }, 100);
    });

    onDestroy(() => {
        console.log('JobProgressIndicator: Component destroying, cleaning up');
        componentMounted = false;
        stopPolling();
    });

    async function loadActiveJobs() {
        // Triple-check authentication before making API call
        if (!isUserAuthenticated || !authStateResolved || !componentMounted) {
            console.log('JobProgressIndicator: Skipping API call - not authenticated or not ready', {
                isUserAuthenticated,
                authStateResolved,
                componentMounted
            });
            return;
        }

        try {
            loading = true;
            
            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (!csrfToken) {
                console.warn('JobProgressIndicator: No CSRF token found, stopping polling');
                stopPolling();
                return;
            }
            
            const response = await fetch('/api/jobs/active', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                const jobs = data.data?.jobs || [];
                
                // Show notification for newly completed jobs
                if (showNotifications && jobs.length < $activeJobs.length) {
                    const completedCount = $activeJobs.length - jobs.length;
                    if (completedCount > 0) {
                        showCompletionNotification(completedCount);
                    }
                }
                
                activeJobs.set(jobs);
            } else if (response.status === 401) {
                // Handle authentication failure - force auth state reset
                console.log('JobProgressIndicator: Session expired (401), stopping polling and resetting auth state');
                authStateResolved = false;
                stopPolling();
                
                // Try to reload page after a delay if user was previously authenticated
                setTimeout(() => {
                    if (window.location.pathname.includes('/admin') || window.location.pathname.includes('/warehouse')) {
                        console.log('JobProgressIndicator: Redirecting to login due to session expiry');
                        window.location.href = '/login';
                    }
                }, 1000);
                
            } else if (response.status === 419) {
                // Handle CSRF token expiry - try to refresh page
                console.log('JobProgressIndicator: CSRF token expired (419), attempting page refresh');
                authStateResolved = false;
                stopPolling();
                
                // Reload page to get new CSRF token
                setTimeout(() => {
                    window.location.reload();
                }, 500);
                
            } else if (response.status === 429) {
                // Handle rate limiting - slow down polling
                console.warn('JobProgressIndicator: Rate limit exceeded (429), slowing down polling');
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = setInterval(loadActiveJobs, 15000); // Poll every 15 seconds instead
                }
            } else if (response.status === 403) {
                // Handle authorization failure - user may not have permission
                console.warn('JobProgressIndicator: Access forbidden (403), stopping polling');
                stopPolling();
            } else {
                // For other errors, log and continue with current data (but slow down polling)
                console.warn('JobProgressIndicator: API error:', response.status, response.statusText);
                
                // Slow down polling on persistent errors
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = setInterval(loadActiveJobs, 10000); // Poll every 10 seconds
                }
            }
        } catch (err) {
            console.error('JobProgressIndicator: Network error loading active jobs:', err);
            
            // On network errors, slow down polling but don't stop completely
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = setInterval(loadActiveJobs, 15000); // Poll every 15 seconds
            }
        } finally {
            loading = false;
        }
    }

    function showCompletionNotification(count) {
        // Create a simple browser notification
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Sertifikat Selesai', {
                body: `${count} pekerjaan sertifikat telah selesai.`,
                icon: '/favicon.ico'
            });
        }
    }

    function requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    function openJobModal() {
        showModal = true;
    }

    function closeJobModal() {
        showModal = false;
        // Only refresh if authenticated and polling is active
        if (isUserAuthenticated && authStateResolved && refreshInterval) {
            loadActiveJobs();
        }
    }

    function getJobTypeIcon(jobType) {
        if (jobType.includes('certificate')) {
            return '📜';
        }
        return '📋';
    }

    function getStatusColor(status) {
        switch (status) {
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'processing': return 'bg-blue-100 text-blue-800';
            case 'completed': return 'bg-green-100 text-green-800';
            case 'failed': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }

    // Request notification permission on first interaction (only once)
    let notificationPermissionRequested = false;
    
    onMount(() => {
        if (!notificationPermissionRequested) {
            requestNotificationPermission();
            notificationPermissionRequested = true;
        }
    });
</script>

<!-- Job Progress Indicator -->
{#if $activeJobs.length > 0}
    <div class="fixed bottom-4 right-4 z-40">
        <!-- Main Indicator Button -->
        <button
            on:click={openJobModal}
            class="bg-white border border-gray-300 rounded-lg shadow-lg p-3 hover:shadow-xl transition-shadow duration-200 max-w-sm"
        >
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    {#if loading}
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                    {:else}
                        <div class="relative">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5l7-7 7 7M9 20h6" />
                            </svg>
                            <div class="absolute -top-1 -right-1 h-4 w-4 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-medium">
                                {$activeJobs.length}
                            </div>
                        </div>
                    {/if}
                </div>
                
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">
                        {$activeJobs.length} Pekerjaan Aktif
                    </p>
                    <p class="text-sm text-gray-500 truncate">
                        Klik untuk lihat detail
                    </p>
                </div>
            </div>

            <!-- Progress Summary -->
            <div class="mt-2 space-y-1">
                {#each $activeJobs.slice(0, 2) as job}
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center space-x-1">
                            <span>{getJobTypeIcon(job.job_type)}</span>
                            <span class="truncate max-w-32">{job.title}</span>
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {getStatusColor(job.status)}">
                            {job.status_display}
                        </span>
                    </div>
                    
                    {#if job.progress_percentage !== null}
                        <div class="w-full bg-gray-200 rounded-full h-1">
                            <div 
                                class="h-1 rounded-full transition-all duration-300 {job.status === 'processing' ? 'bg-blue-500' : job.status === 'completed' ? 'bg-green-500' : 'bg-gray-400'}"
                                style="width: {job.progress_percentage}%"
                            ></div>
                        </div>
                    {/if}
                {/each}
                
                {#if $activeJobs.length > 2}
                    <p class="text-xs text-gray-500 text-center mt-1">
                        +{$activeJobs.length - 2} pekerjaan lainnya
                    </p>
                {/if}
            </div>
        </button>

        <!-- Mini Status Indicators -->
        <div class="mt-2 flex space-x-1">
            {#each $activeJobs.slice(0, 5) as job}
                <div 
                    class="w-2 h-2 rounded-full {job.status === 'processing' ? 'bg-blue-500' : job.status === 'completed' ? 'bg-green-500' : job.status === 'failed' ? 'bg-red-500' : 'bg-yellow-500'}"
                    title="{job.title} - {job.status_display}"
                ></div>
            {/each}
        </div>
    </div>
{/if}

<!-- Floating Notification for New Completions -->
{#if showNotifications}
    <!-- This would be implemented with a toast notification system -->
{/if}

<!-- Job Progress Modal -->
<JobProgressModal
    isOpen={showModal}
    onClose={closeJobModal}
/>

<style>
    /* Custom animations for progress indicators */
    @keyframes pulse-blue {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    
    .animate-pulse-blue {
        animation: pulse-blue 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>