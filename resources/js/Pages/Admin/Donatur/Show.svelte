<script>
    import AdminLayout from '../../../Layouts/AdminLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import { toast, dialog } from '../../../utils/notifications.js';
    
    // Required props
    export let donatur;
    export let certificateUrls = [];
    
    // Format helper for general dates (with day and time)
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
    
    // Format helper for donation date (simple date only)
    function formatDonationDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
    
    function handleEdit() {
        router.visit(`/admin/donatur/${donatur.id}/edit`);
    }
    
    function handleBack() {
        router.visit('/admin/donatur');
    }
    
    async function handleDelete() {
        const confirmed = await dialog.confirmDelete(`donatur ${donatur.nama_donatur}`);
        if (confirmed) {
            router.delete(`/admin/donatur/${donatur.id}`, {
                onSuccess: () => {
                    toast.success('Donatur berhasil dihapus');
                    // Redirect to index after successful deletion
                    router.visit('/admin/donatur');
                },
                onError: (errors) => {
                    console.error('Delete error:', errors);
                    
                    // Display specific error message from backend
                    let errorMessage = 'Gagal menghapus donatur';
                    if (errors.error) {
                        errorMessage = errors.error;
                    }
                    
                    toast.error(errorMessage, 'Error', { duration: 8000 });
                }
            });
        }
    }
    
    // Calculate totals
    $: totalQuran = (donatur.total_a5_count || 0) + (donatur.total_a6_count || 0) + (donatur.total_iqra_count || 0);
    $: totalPengiriman = donatur.pengiriman?.length || 0;
    
    // Check if donatur has active shipments (cannot be deleted)
    $: hasActiveShipments = donatur.wakaf_items?.some(item => 
        item.pengiriman?.status?.nama && item.pengiriman.status.nama !== 'Batal'
    ) || false;
</script>

<AdminLayout>
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Detail Donatur</h1>
                    <p class="text-gray-600">Informasi lengkap donatur dan riwayat donasi</p>
                </div>
                
                <div class="flex items-center space-x-3">
                    <button
                        on:click={handleBack}
                        class="px-4 py-2 text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        ← Kembali
                    </button>
                    
                    <button
                        on:click={handleEdit}
                        class="px-4 py-2 text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Edit
                    </button>
                    
                    <button
                        on:click={handleDelete}
                        class="px-4 py-2 text-white bg-red-600 border border-red-600 rounded-lg hover:bg-red-700 transition-colors {hasActiveShipments ? 'opacity-75' : ''}"
                        title={hasActiveShipments ? 'Donatur memiliki pengiriman aktif - perlu dibatalkan terlebih dahulu' : 'Hapus donatur dan semua data terkait'}
                    >
                        Hapus
                        {#if hasActiveShipments}
                            <span class="ml-1 text-yellow-200">⚠️</span>
                        {/if}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Donatur Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Info -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Donatur</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="block text-sm font-medium text-gray-500 mb-1">Kode Donatur</p>
                            <p class="text-gray-900 font-mono bg-gray-50 px-3 py-2 rounded border">{donatur.kode_donatur}</p>
                        </div>
                        
                        <div>
                            <p class="block text-sm font-medium text-gray-500 mb-1">Nama Donatur</p>
                            <p class="text-gray-900">{donatur.nama_donatur}</p>
                        </div>
                        
                        <div>
                            <p class="block text-sm font-medium text-gray-500 mb-1">No. HP</p>
                            <p class="text-gray-900">{donatur.no_hp || '-'}</p>
                        </div>
                        
                        <div>
                            <p class="block text-sm font-medium text-gray-500 mb-1">Email</p>
                            <p class="text-gray-900">{donatur.email_donatur || '-'}</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <p class="block text-sm font-medium text-gray-500 mb-1">Alamat</p>
                            <p class="text-gray-900">{donatur.alamat_donatur || '-'}</p>
                        </div>
                        
                        <div>
                            <p class="block text-sm font-medium text-gray-500 mb-1">Donasi Terakhir</p>
                            <p class="text-gray-900">{formatDonationDate(donatur.donation_date)}</p>
                        </div>
                        
                        <div>
                            <p class="block text-sm font-medium text-gray-500 mb-1">Dibuat Oleh</p>
                            <p class="text-gray-900">{donatur.creator?.name || '-'}</p>
                        </div>
                    </div>
                </div>

                <!-- Donation Summary -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Donasi</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-blue-600">{donatur.total_a5_count || 0}</div>
                            <div class="text-sm text-blue-700">Al-Qur'an A5</div>
                        </div>
                        
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-600">{donatur.total_a6_count || 0}</div>
                            <div class="text-sm text-green-700">Al-Qur'an A6</div>
                        </div>
                        
                        <div class="bg-purple-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-purple-600">{donatur.total_iqra_count || 0}</div>
                            <div class="text-sm text-purple-700">IQRA</div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-gray-600">{totalQuran}</div>
                            <div class="text-sm text-gray-700">Total Al-Qur'an</div>
                        </div>
                    </div>
                </div>

                <!-- Wakaf Items -->
                {#if donatur.wakaf_items && donatur.wakaf_items.length > 0}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Daftar Item Wakaf</h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Resi</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atas Nama</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                {#each donatur.wakaf_items as item}
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                        {item.pengiriman?.no_resi || '-'}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        {item.wakaf_type || item.pengiriman?.jenis_quran?.nama_jenis || '-'}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        {item.wakif_name || donatur.nama_donatur}
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {item.pengiriman?.status?.nama === 'Proses Pemesanan' ? 'bg-purple-100 text-purple-800' : 
                                              item.pengiriman?.status?.nama === 'Diterima Penerima' ? 'bg-green-100 text-green-800' : 
                                             'bg-blue-100 text-blue-800'}">
                                             {item.pengiriman?.status?.nama || 'Proses Pemesanan'}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        {#if item.pengiriman?.id}
                                        <a 
                                            href="/admin/pengiriman/{item.pengiriman.id}"
                                            class="text-blue-600 hover:text-blue-900 text-sm font-medium"
                                        >
                                            Lihat Detail
                                        </a>
                                        {/if}
                                    </td>
                                </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
                {/if}

                <!-- Certificates Section -->
                {#if certificateUrls && certificateUrls.length > 0}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Sertifikat Wakaf</h2>
                    
                    <div class="space-y-4">
                        {#each certificateUrls as certificate}
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-lg">📜</span>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            Sertifikat #{certificate.nomor_sertifikat}
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            Batch: {certificate.batch_code}
                                        </p>
                                        {#if certificate.generated_at}
                                        <p class="text-xs text-gray-500">
                                            Dibuat: {formatDate(certificate.generated_at)}
                                        </p>
                                        {/if}
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a 
                                        href={certificate.download_url} 
                                        target="_blank"
                                        class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors text-sm"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Download
                                    </a>
                                    <a 
                                        href={certificate.download_url + '?preview=1'} 
                                        target="_blank"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg transition-colors text-sm"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Preview
                                    </a>
                                </div>
                            </div>
                        </div>
                        {/each}
                    </div>
                    
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-800 flex items-start">
                            <svg class="w-4 h-4 mr-2 mt-0.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Sertifikat ini dapat diunduh dan dibagikan kepada donatur sebagai bukti wakaf Al-Qur'an yang telah tersalurkan.</span>
                        </p>
                    </div>
                </div>
                {/if}
            </div>

            <!-- Sidebar Stats -->
            <div class="space-y-6">
                <!-- Quick Stats -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistik</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pengiriman</span>
                            <span class="font-semibold text-gray-900">{totalPengiriman}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Al-Qur'an</span>
                            <span class="font-semibold text-gray-900">{totalQuran}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Bergabung Sejak</span>
                            <span class="font-semibold text-gray-900">{formatDonationDate(donatur.created_at)}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi</h3>
                    
                    <div class="space-y-3">
                        <button
                            on:click={handleEdit}
                            class="w-full px-4 py-2 text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Edit Donatur
                        </button>
                        
                        
                        <div>
                            <button
                                on:click={handleDelete}
                                class="w-full px-4 py-2 text-white bg-red-600 border border-red-600 rounded-lg hover:bg-red-700 transition-colors {hasActiveShipments ? 'opacity-75' : ''}"
                                title={hasActiveShipments ? 'Donatur memiliki pengiriman aktif - perlu dibatalkan terlebih dahulu' : 'Hapus donatur dan semua data terkait'}
                            >
                                Hapus Donatur
                                {#if hasActiveShipments}
                                    <span class="ml-2 text-yellow-200">⚠️</span>
                                {/if}
                            </button>
                            {#if hasActiveShipments}
                                <p class="mt-1 text-xs text-orange-600">
                                    ⚠️ Donatur memiliki pengiriman aktif. Ubah status menjadi "Batal" terlebih dahulu.
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
