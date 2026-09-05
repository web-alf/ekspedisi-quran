<script>
    import AdminLayout from '../../../Layouts/AdminLayout.svelte';
    import FlashMessage from '../../../Components/FlashMessage.svelte';
    import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
    import { router } from '@inertiajs/svelte';
    
    // Required props
    export let pengiriman;
    export const statusHistory = [];
    export const statusList = [];
    export const currentStatus = null;
    export const trackingHistory = [];
    
    // Global Inertia props (automatically passed by Laravel)
    export const auth = null;
    export const errors = {};
    export const flash = {};
    
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
    
    function formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(amount);
    }
    
    function handleEdit() {
        router.visit(`/admin/pengiriman/${pengiriman.id}/edit`);
    }
    
    function handleUpdateStatus() {
        router.visit(`/admin/pengiriman/${pengiriman.id}/update-status`);
    }
    
    function handleBack() {
        router.visit('/admin/pengiriman');
    }
    
    function getStatusIcon(statusSlug) {
        const icons = {
            'pending': '⏳',
            'dikemas': '📦',
            'dikirim': '🚚',
            'diterima': '✅',
            'batal': '❌',
            'proses': '⚙️',
            'siap': '📋'
        };
        return icons[statusSlug] || '📋';
    }
    
</script>

<svelte:head>
    <title>Detail Pengiriman #{pengiriman.no_resi} - Ekspedisi Quran</title>
</svelte:head>

<AdminLayout>
    <div class="py-12">
        <div class="px-6 py-8">
            <!-- Header -->
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Detail Pengiriman #{pengiriman.no_resi}
                    </h1>
                    <p class="text-gray-600">
                        Wakaf Al-Quran dari {pengiriman.donatur?.nama_donatur || 'Donatur tidak diketahui'}
                    </p>
                </div>
                
                <div class="mt-4 sm:mt-0 flex gap-3">
                    <button
                        on:click={handleBack}
                        class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md transition-colors"
                    >
                        <HeroIcon name="arrow-left" class="w-5 h-5 mr-2" />
                        Kembali
                    </button>
                    
                    <button
                        on:click={handleEdit}
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors"
                    >
                        <HeroIcon name="pencil-square" class="w-5 h-5 mr-2" />
                        Edit
                    </button>
                    
                    <button
                        on:click={handleUpdateStatus}
                        class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors"
                    >
                        <HeroIcon name="arrow-path" class="w-5 h-5 mr-2" />
                        Update Status
                    </button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Information Card -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <!-- Header card -->
                        <div class="px-6 py-4 bg-gradient-to-r from-red-600 to-red-700 text-white">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div>
                                    <h2 class="text-xl font-semibold">{pengiriman.no_resi}</h2>
                                    <p class="text-red-100">
                                        {pengiriman.jumlah_quran} mushaf • {pengiriman.jenisQuran?.nama_jenis || pengiriman.jenis_quran?.nama_jenis || 'Jenis tidak diketahui'}
                                    </p>
                                </div>
                                <div class="mt-3 md:mt-0">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 border border-white/30">
                                        Status: {pengiriman.status?.nama || 'Status tidak diketahui'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Donatur Information -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Donatur</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Nama Donatur</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.donatur?.nama_donatur || '-'}</p>
                                        </div>
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Kode Donatur</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.donatur?.kode_donatur || '-'}</p>
                                        </div>
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">No. HP</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.donatur?.no_hp || '-'}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Wakif Information -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Wakif</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Nama Wakif</div>
                                            <p class="mt-1 text-sm text-gray-900 font-medium">{pengiriman.wakaf_item?.wakif_name || pengiriman.donatur?.nama_donatur || '-'}</p>
                                        </div>
                                        {#if pengiriman.wakaf_item?.relationship_to_donatur}
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Hubungan</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.wakaf_item.relationship_to_donatur}</p>
                                        </div>
                                        {/if}
                                        {#if pengiriman.wakaf_item?.doa_request}
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Doa</div>
                                            <p class="mt-1 text-sm text-blue-900 italic">"{pengiriman.wakaf_item.doa_request}"</p>
                                        </div>
                                        {/if}
                                    </div>
                                </div>
                                
                                <!-- Pengiriman Information -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Tanggal Wakaf</div>
                                            <p class="mt-1 text-sm text-gray-900">{formatDate(pengiriman.tanggal_wakaf)}</p>
                                        </div>
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Jenis Al-Quran</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.jenisQuran?.nama_jenis || pengiriman.jenis_quran?.nama_jenis || 'Belum diset'}</p>
                                        </div>
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Jumlah</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.jumlah_quran} mushaf</p>
                                        </div>
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Status</div>
                                            <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{pengiriman.status?.warna || 'gray'}-100 text-{pengiriman.status?.warna || 'gray'}-800">
                                                {pengiriman.status?.nama || 'Status tidak diketahui'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Delivery Information -->
                                <div class="md:col-span-3">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Penerima</h3>
                                    {#if !pengiriman.alamat_tujuan && !pengiriman.nama_penerima}
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <p class="text-sm text-yellow-800">
                                                <HeroIcon name="exclamation-triangle" class="w-4 h-4 inline mr-1" />
                                                Alamat pengiriman belum diset. Silakan edit pengiriman untuk mengatur alamat tujuan.
                                            </p>
                                        </div>
                                    {/if}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <div class="block text-sm font-medium text-gray-500">Nama Penerima</div>
                                                <p class="mt-1 text-sm text-gray-900">{pengiriman.nama_penerima || '-'}</p>
                                            </div>
                                            <div>
                                                <div class="block text-sm font-medium text-gray-500">No. HP Penerima</div>
                                                <p class="mt-1 text-sm text-gray-900">{pengiriman.no_hp_penerima || '-'}</p>
                                            </div>
                                            <div class="md:col-span-2">
                                                <div class="block text-sm font-medium text-gray-500">Alamat Tujuan</div>
                                                <p class="mt-1 text-sm text-gray-900">{pengiriman.alamat_tujuan || '-'}</p>
                                            </div>
                                        </div>
                                </div>
                                
                                <!-- Notes -->
                                {#if pengiriman.catatan}
                                    <div class="md:col-span-3">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Catatan</h3>
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <p class="text-sm text-gray-700">{pengiriman.catatan}</p>
                                        </div>
                                    </div>
                                {/if}
                                
                                <!-- System Information -->
                                <div class="md:col-span-3 pt-4 border-t border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Sistem</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Dibuat Oleh</div>
                                            <p class="mt-1 text-sm text-gray-900">{pengiriman.creator?.name || '-'}</p>
                                        </div>
                                        <div>
                                            <div class="block text-sm font-medium text-gray-500">Dibuat Pada</div>
                                            <p class="mt-1 text-sm text-gray-900">{formatDate(pengiriman.created_at)}</p>
                                        </div>
                                    </div>
                                </div>
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
    /* Additional styling if needed */
</style>
