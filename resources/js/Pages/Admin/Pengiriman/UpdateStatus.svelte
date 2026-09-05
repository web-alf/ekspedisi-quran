<script>
    import AdminLayout from '../../../Layouts/AdminLayout.svelte';
    import FlashMessage from '../../../Components/FlashMessage.svelte';
    import DokumentasiUpload from '../../../Components/DokumentasiUpload.svelte';
    import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { toast } from '../../../utils/notifications.js';

    // Required props
    export let pengiriman;
    export let statusList;
    export let currentStatus;
    export let trackingHistory = [];

    // Global Inertia props (automatically passed by Laravel)
    export const auth = null;
    export const errors = {};
    export const flash = {};

    let selectedStatusId = null;
    let catatan = '';
    let lokasi = '';
    let dokumentasi = [];
    let loading = false;
    let latitude = null;
    let longitude = null;
    let dokumentasiUploadComponent; // Bind DokumentasiUpload component
    
    // Format helper
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Set default selected status to first in the list
    $: {
        if (statusList && statusList.length > 0 && !selectedStatusId) {
            selectedStatusId = statusList[0].id;
        }
    }
    
    // Get user's location if available
    function getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((position) => {
                latitude = position.coords.latitude;
                longitude = position.coords.longitude;
            }, (error) => {
                // console.error("Error getting location:", error);
            });
        }
    }

    // Handle dokumentasi change from DokumentasiUpload component
    function handleDokumentasiChange(event) {
        const detail = event.detail;

        // Update dokumentasi array with files from component
        if (detail.mode === 'camera') {
            // Files from camera capture
            dokumentasi = detail.files || [];
        } else {
            // Files from file upload
            dokumentasi = detail.files || [];
        }
    }
    
    // Handle form submission
    function handleSubmit(event) {
        event.preventDefault();

        if (loading) return;

        loading = true;

        // Prepare FormData for file upload
        const formData = new FormData();
        formData.append('status_id', selectedStatusId);

        if (catatan) {
            formData.append('catatan', catatan);
        }

        if (lokasi) {
            formData.append('lokasi', lokasi);
        }

        if (latitude) {
            formData.append('latitude', latitude);
        }

        if (longitude) {
            formData.append('longitude', longitude);
        }

        // Get files from DokumentasiUpload component if available
        if (dokumentasiUploadComponent) {
            const files = dokumentasiUploadComponent.getFiles();
            if (files && files.length > 0) {
                files.forEach((file, index) => {
                    formData.append(`dokumentasi[${index}]`, file);
                });
            }
        }
        // Fallback: use dokumentasi array directly
        else if (dokumentasi && dokumentasi.length > 0) {
            Array.from(dokumentasi).forEach((file, index) => {
                formData.append(`dokumentasi[${index}]`, file);
            });
        }

        // Submit using Inertia router with FormData
        router.post(`/admin/pengiriman/${pengiriman.id}/update-status`, formData, {
            forceFormData: true,
            onSuccess: () => {
                // Will redirect automatically on success
            },
            onError: (errors) => {
                // console.error('Submit errors:', errors);
            },
            onFinish: () => {
                loading = false;
            }
        });
    }
    
    // Initialize
    onMount(() => {
        getUserLocation();
    });
</script>

<svelte:head>
    <title>Update Status Pengiriman - Ekspedisi Quran</title>
</svelte:head>

<AdminLayout>
    <div class="py-12">
        <div class="px-6 py-8">
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-bold text-gray-900">
                    Update Status Pengiriman #{pengiriman.no_resi}
                </h1>
                
                <div class="mt-4 sm:mt-0">
                    <a href={`/admin/pengiriman/${pengiriman.id}`} class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md transition-colors">
                        <HeroIcon name="arrow-left" class="w-5 h-5 mr-2" />
                        Kembali
                    </a>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <!-- Header card -->
                <div class="px-6 py-4 bg-gradient-to-r from-red-600 to-red-700 text-white">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">{pengiriman.no_resi}</h2>
                            <p class="text-red-100">
                                Wakaf dari {pengiriman.donatur?.nama_donatur || 'Unknown'} ({pengiriman.jumlah_quran} mushaf)
                            </p>
                        </div>
                        <div class="mt-3 md:mt-0">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 border border-white/30">
                                Status: {currentStatus.nama}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Left column - Update form -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Update Status Pengiriman</h3>
                            
                            <form on:submit={handleSubmit} class="space-y-6">
                                <!-- Status selection -->
                                <div>
                                    <label for="status_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Status Baru
                                    </label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        {#each statusList as status (status.id)}
                                            <div>
                                                <label class="flex items-center p-3 rounded-lg border {selectedStatusId && selectedStatusId === status.id ? 'border-red-500 bg-red-50' : status.disabled ? 'border-gray-200 bg-gray-50 cursor-not-allowed' : 'border-gray-200 hover:bg-gray-50'} {status.disabled ? 'cursor-not-allowed' : 'cursor-pointer'} transition-colors">
                                                    <input type="radio" name="status_id" value={status.id} bind:group={selectedStatusId} class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300" disabled={status.disabled} />
                                                    <div class="ml-3">
                                                        <span class="flex items-center text-sm font-medium {status.disabled ? 'text-gray-500' : 'text-gray-900'}">
                                                            <span class="mr-2 text-lg">{status.icon || '📦'}</span>
                                                            {status.nama}
                                                        </span>
                                                        <p class="text-xs {status.disabled ? 'text-gray-400' : 'text-gray-500'} mt-1">{status.deskripsi}</p>
                                                    </div>
                                                </label>
                                            </div>
                                        {/each}
                                    </div>
                                    {#if errors.status_id}
                                        <p class="text-red-500 text-sm mt-1">{errors.status_id}</p>
                                    {/if}
                                </div>
                                
                                <!-- Catatan -->
                                <div>
                                    <label for="catatan" class="block text-sm font-medium text-gray-700 mb-1">
                                        Catatan
                                    </label>
                                    <textarea 
                                        id="catatan" 
                                        name="catatan" 
                                        rows="3" 
                                        bind:value={catatan}
                                        class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm {errors.catatan ? 'border-red-300' : 'border-gray-300'} rounded-md"
                                        placeholder="Berikan catatan untuk status ini (opsional)"
                                    ></textarea>
                                    {#if errors.catatan}
                                        <p class="text-red-500 text-sm mt-1">{errors.catatan}</p>
                                    {/if}
                                </div>
                                
                                <!-- Lokasi -->
                                <div>
                                    <label for="lokasi" class="block text-sm font-medium text-gray-700 mb-1">
                                        Lokasi
                                    </label>
                                    <div class="flex">
                                        <input 
                                            type="text" 
                                            id="lokasi" 
                                            name="lokasi" 
                                            bind:value={lokasi}
                                            class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm {errors.lokasi ? 'border-red-300' : 'border-gray-300'} rounded-md"
                                            placeholder="Lokasi update status (opsional)"
                                        />
                                        <button 
                                            type="button"
                                            on:click={getUserLocation}
                                            class="ml-2 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            <HeroIcon name="map-pin" class="w-5 h-5" />
                                        </button>
                                    </div>
                                    {#if latitude && longitude}
                                        <p class="text-xs text-gray-500 mt-1">
                                            Koordinat: {latitude.toFixed(6)}, {longitude.toFixed(6)}
                                            <input type="hidden" name="latitude" value={latitude} />
                                            <input type="hidden" name="longitude" value={longitude} />
                                        </p>
                                    {/if}
                                    {#if errors.lokasi}
                                        <p class="text-red-500 text-sm mt-1">{errors.lokasi}</p>
                                    {/if}
                                </div>
                                
                                <!-- Dokumentasi Upload with Camera -->
                                <div>
                                    <DokumentasiUpload
                                        bind:this={dokumentasiUploadComponent}
                                        maxPhotos={5}
                                        label="Dokumentasi (Foto)"
                                        showToggle={true}
                                        defaultMode="upload"
                                        allowModeSwitch={true}
                                        on:photosChanged={handleDokumentasiChange}
                                    />

                                    {#if errors.dokumentasi}
                                        <p class="text-red-500 text-sm mt-1">{Array.isArray(errors.dokumentasi) ? errors.dokumentasi[0] : errors.dokumentasi}</p>
                                    {/if}
                                </div>
                                
                                <!-- Error messages -->
                                {#if Object.keys(errors).length > 0}
                                    <div class="rounded-md bg-red-50 p-4 border border-red-200">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <HeroIcon name="x-circle" class="w-5 h-5 text-red-400" />
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-red-800">
                                                    Terjadi kesalahan saat menyimpan:
                                                </h3>
                                                <div class="mt-2 text-sm text-red-700">
                                                    <ul class="list-disc list-inside space-y-1">
                                                        {#each Object.entries(errors) as [field, message]}
                                                            <li>{Array.isArray(message) ? message[0] : message}</li>
                                                        {/each}
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                {/if}
                                
                                <!-- Submit button -->
                                <div class="flex justify-end">
                                    <button 
                                        type="submit"
                                        disabled={loading}
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {#if loading}
                                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Menyimpan...
                                        {:else}
                                            Update Status
                                        {/if}
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Right column - Current timeline -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Status Timeline</h3>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                {#if trackingHistory && trackingHistory.length > 0}
                                    <div class="relative space-y-0">
                                        {#each trackingHistory as history, index}
                                            <div class="relative flex timeline-item">
                                                <!-- Timeline Line -->
                                                {#if index < trackingHistory.length - 1}
                                                    <div class="absolute left-6 top-12 w-0.5 h-full bg-gray-300 z-0" style="height: calc(100% + 1rem);"></div>
                                                {/if}
                                                
                                                <!-- Timeline Icon Container -->
                                                <div class="flex-shrink-0 flex flex-col items-center">
                                                    <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center shadow-lg z-10 border-2 border-white">
                                                        <span class="text-white text-lg">{history.status?.icon || '📦'}</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Timeline Content -->
                                                <div class="ml-4 flex-1 pb-6">
                                                    <div class="bg-white rounded-lg p-4 shadow-sm">
                                                        <!-- Status Badge and Date -->
                                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-{history.status?.warna || 'gray'}-100 text-{history.status?.warna || 'gray'}-800 border border-{history.status?.warna || 'gray'}-200 w-fit">
                                                                {history.status?.nama || 'Unknown'}
                                                            </span>
                                                            <span class="text-xs text-gray-500 font-medium">
                                                                {history.tanggal_update}
                                                            </span>
                                                        </div>
                                                        
                                                        <!-- Additional Info -->
                                                        <div class="space-y-2">
                                                            {#if history.keterangan}
                                                                <div class="bg-gray-50 rounded p-3 border-l-4 border-red-500">
                                                                    <p class="text-sm text-gray-700">{history.keterangan}</p>
                                                                </div>
                                                            {/if}
                                                            
                                                            {#if history.lokasi}
                                                                <div class="flex items-center text-xs text-gray-500">
                                                                    <HeroIcon name="map-pin" class="w-4 h-4 mr-1" />
                                                                    Lokasi: {history.lokasi}
                                                                </div>
                                                            {/if}
                                                            
                                                            {#if history.petugas}
                                                                <p class="text-xs text-gray-500">
                                                                    Diupdate oleh: <strong class="text-gray-700">{history.petugas}</strong>
                                                                </p>
                                                            {/if}
                                                            
                                                            <!-- Show dokumentasi if available -->
                                                            {#if history.foto_dokumentasi && history.foto_dokumentasi.length > 0}
                                                                <div class="mt-2">
                                                                    <p class="text-xs font-medium text-gray-500 mb-1">Dokumentasi:</p>
                                                                    <div class="grid grid-cols-3 gap-2">
                                                                        {#each history.foto_dokumentasi as foto}
                                                                            <a href={foto} target="_blank" class="block">
                                                                                <img src={foto} alt="Dokumentasi" class="h-16 w-full object-cover rounded">
                                                                            </a>
                                                                        {/each}
                                                                    </div>
                                                                </div>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <div class="text-center py-8 text-gray-500">
                                        <span class="text-4xl mb-3 block">📝</span>
                                        <p class="text-base font-medium">Belum ada riwayat tracking</p>
                                        <p class="text-sm">Tracking akan muncul setelah status diupdate</p>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>

<!-- Flash Messages -->
<FlashMessage />

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
</style>
