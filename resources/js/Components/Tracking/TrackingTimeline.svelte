<script>
    export let statusHistory = [];
    export let currentStatus = null;
    export let allStatuses = [];
    
    // Sort status list by urutan for proper timeline display
    $: sortedStatusList = [...allStatuses].sort((a, b) => a.urutan - b.urutan);
    
    // Check if status is active (current or passed)
    function isActive(status) {
        if (!currentStatus) return false;
        return status.urutan <= currentStatus.urutan;
    }
    
    // Get color class based on status color
    function colorClass(color) {
        const colors = {
            'gray': 'bg-gray-100 text-gray-800 border-gray-200',
            'blue': 'bg-blue-100 text-blue-800 border-blue-200',
            'indigo': 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'purple': 'bg-purple-100 text-purple-800 border-purple-200',
            'cyan': 'bg-cyan-100 text-cyan-800 border-cyan-200',
            'teal': 'bg-teal-100 text-teal-800 border-teal-200',
            'yellow': 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'green': 'bg-green-100 text-green-800 border-green-200',
            'red': 'bg-red-100 text-red-800 border-red-200'
        };
        return colors[color] || 'bg-gray-100 text-gray-800 border-gray-200';
    }
    
    // Get icon based on status slug
    function getStatusIcon(slug) {
        const icons = {
            'pending': '⏳',
            'dikemas': '📦',
            'dikirim': '🚚',
            'diterima': '✅',
            'batal': '❌',
            'pemesanan': '📝',
            'produksi': '🏭',
            'kedatangan': '📦',
            'packing': '🎁',
            'selesai-packing': '✅',
            'pengiriman': '🚚'
        };
        return icons[slug] || '📋';
    }
    
    // Get the date for a status from history
    function getStatusDate(statusId) {
        const historyItem = statusHistory.find(h => h.status_to && h.status_to.id === statusId);
        return historyItem ? new Date(historyItem.created_at).toLocaleString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : null;
    }
    
    // Get photos associated with a status
    function getStatusPhotos(statusId) {
        const trackingItems = statusHistory.filter(h => h.status_to && h.status_to.id === statusId);
        let photos = [];
        
        trackingItems.forEach(item => {
            if (item.foto_dokumentasi && Array.isArray(item.foto_dokumentasi)) {
                photos = [...photos, ...item.foto_dokumentasi];
            }
        });
        
        return photos;
    }
    
    // Modal for photo viewing
    let showModal = false;
    let currentPhoto = '';
    
    function openPhotoModal(photo) {
        currentPhoto = photo;
        showModal = true;
    }
</script>

<div class="relative py-6">
    <!-- Timeline line -->
    <div class="absolute top-0 bottom-0 left-7 w-0.5 bg-gray-200"></div>
    
    <!-- Status items -->
    {#each sortedStatusList as status, index}
        <div class="relative flex items-start mb-6 last:mb-0 timeline-item">
            <!-- Timeline marker -->
            <div class="flex-shrink-0 h-14 w-14 rounded-full flex items-center justify-center z-10 mr-4 
                    {isActive(status) ? 'bg-[#eb3434] text-white shadow-lg' : 'bg-gray-100 text-gray-400'}">
                <span class="text-2xl">{getStatusIcon(status.slug)}</span>
            </div>
            
            <!-- Status content -->
            <div class="flex-grow pt-0.5">
                <h3 class="font-medium text-lg mb-1">{status.nama}</h3>
                <p class="text-sm text-gray-500">{status.deskripsi}</p>
                
                <!-- Show date if status is active/passed -->
                {#if getStatusDate(status.id)}
                    <p class="text-xs text-gray-400 mt-1">
                        {getStatusDate(status.id)}
                    </p>
                {/if}
                
                <!-- Show detail photo/documentation if available -->
                {#if getStatusPhotos(status.id).length > 0}
                    <div class="mt-2 flex gap-2 overflow-x-auto">
                        {#each getStatusPhotos(status.id) as photo, idx}
                            <img src={photo} 
                                 alt="Dokumentasi {status.nama}"
                                 class="h-16 w-16 object-cover rounded-md cursor-pointer" 
                                 on:click={() => openPhotoModal(photo)}>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    {/each}
</div>

<!-- Photo modal -->
{#if showModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"
         on:click={() => showModal = false}>
        <div class="relative max-w-4xl max-h-screen p-2" on:click|stopPropagation>
            <button on:click={() => showModal = false} 
                    class="absolute top-2 right-2 bg-white rounded-full p-2 shadow-lg z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img src={currentPhoto} alt="Dokumentasi" class="max-w-full max-h-[90vh] rounded-lg">
        </div>
    </div>
{/if}

<style>
    /* Timeline styling */
    .timeline-item {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .timeline-item:nth-child(1) { animation-delay: 0.1s; }
    .timeline-item:nth-child(2) { animation-delay: 0.2s; }
    .timeline-item:nth-child(3) { animation-delay: 0.3s; }
    .timeline-item:nth-child(4) { animation-delay: 0.4s; }
    .timeline-item:nth-child(5) { animation-delay: 0.5s; }
    .timeline-item:nth-child(6) { animation-delay: 0.6s; }
    .timeline-item:nth-child(7) { animation-delay: 0.7s; }
    .timeline-item:nth-child(8) { animation-delay: 0.8s; }
    
    /* Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Modal Animation */
    .fixed.inset-0 {
        backdrop-filter: blur(4px);
    }
    
    /* Responsive adjustments */
    @media (max-width: 640px) {
        .h-14 {
            height: 2.5rem;
            width: 2.5rem;
        }
        
        .left-7 {
            left: 1.25rem;
        }
    }
</style>
