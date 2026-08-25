<script>
    import PublicLayout from '@/Layouts/PublicLayout.svelte';
    import { toast } from '../../utils/notifications.js';
    export let settings = {};
    export let pengiriman;
    export let trackingHistory = [];
    export let statusHistory = [];

    // Helper function to format phone number for WhatsApp
    function formatPhoneForWhatsApp(phone) {
        if (!phone) return '6281234567890'; // fallback
        
        // Remove all non-digit characters
        let cleanPhone = phone.replace(/\D/g, '');
        
        // Convert Indonesian landline/mobile to international format
        if (cleanPhone.startsWith('021') || cleanPhone.startsWith('022') || cleanPhone.startsWith('024') || cleanPhone.startsWith('061')) {
            // Landline: 021-xxx-xxxx becomes 6221xxxxxxx
            cleanPhone = '62' + cleanPhone;
        } else if (cleanPhone.startsWith('08')) {
            // Mobile: 08xx-xxxx-xxxx becomes 628xxxxxxxxx
            cleanPhone = '62' + cleanPhone.substring(1);
        } else if (cleanPhone.startsWith('8')) {
            // Mobile without leading 0: 8xx-xxxx-xxxx becomes 628xxxxxxxxx
            cleanPhone = '62' + cleanPhone;
        } else if (!cleanPhone.startsWith('62')) {
            // Add Indonesian country code if not present
            cleanPhone = '62' + cleanPhone;
        }
        
        return cleanPhone;
    }
    export let certificateUrls = [];
    export let no_resi;
    
    // Global Inertia props (automatically passed by Laravel)
    // other page props not used here are omitted to avoid build warnings
    
    // Tambahkan fungsi untuk handle image modal
    let showImageModal = false;
    let currentImageIndex = 0;
    let currentImages = [];
    
    function openImageModal(images, index = 0) {
        currentImages = images;
        currentImageIndex = index;
        showImageModal = true;
    }
    
    function closeImageModal() {
        showImageModal = false;
        currentImages = [];
        currentImageIndex = 0;
    }
    
    function nextImage() {
        currentImageIndex = (currentImageIndex + 1) % currentImages.length;
    }
    
    function prevImage() {
        currentImageIndex = (currentImageIndex - 1 + currentImages.length) % currentImages.length;
    }
    
    // Keyboard navigation for image modal
    function handleKeydown(event) {
        if (!showImageModal) return;
        
        switch(event.key) {
            case 'Escape':
                closeImageModal();
                break;
            case 'ArrowRight':
                nextImage();
                break;
            case 'ArrowLeft':
                prevImage();
                break;
        }
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'Tanggal tidak valid';
        
        // Jika sudah dalam format string yang benar, return langsung
        if (typeof dateString === 'string' && dateString.includes('/')) {
            return dateString;
        }
        
        try {
            const date = new Date(dateString);
            
            // Check if date is valid
            if (isNaN(date.getTime())) {
                return 'Tanggal tidak valid';
            }
            
            return date.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            console.error('Error formatting date:', error, dateString);
            return 'Tanggal tidak valid';
        }
    }
    
    function getStatusColor(statusName) {
        const colors = {
            'Pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'Dikemas': 'bg-blue-100 text-blue-800 border-blue-200',
            'Dikirim': 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'Diterima': 'bg-green-100 text-green-800 border-green-200',
            'Batal': 'bg-red-100 text-red-800 border-red-200'
        };
        return colors[statusName] || 'bg-gray-100 text-gray-800 border-gray-200';
    }
    
    
    // Share functionality
    function shareTracking() {
        const url = window.location.href;
        const title = `Tracking Wakaf Al-Qur'an - ${pengiriman.no_resi}`;
        const text = `Status pengiriman wakaf Al-Qur'an ${pengiriman.no_resi}: ${pengiriman.status?.nama || 'Unknown'}`;
        
        // Try Web Share API first (mobile browsers)
        if (navigator.share) {
            navigator.share({
                title: title,
                text: text,
                url: url
            }).catch(err => {});
        } else {
            // Fallback: show share options
            showShareModal = true;
        }
    }
    
    function copyToClipboard() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            toast.success('Link tracking berhasil disalin!');
            showShareModal = false;
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }
    
    function shareToWhatsApp() {
        const url = window.location.href;
        const text = `Status pengiriman wakaf Al-Qur'an ${pengiriman.no_resi}: ${pengiriman.status?.nama || 'Unknown'}`;
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`;
        window.open(whatsappUrl, '_blank');
        showShareModal = false;
    }
    
    function shareToTelegram() {
        const url = window.location.href;
        const text = `Status pengiriman wakaf Al-Qur'an ${pengiriman.no_resi}: ${pengiriman.status?.nama || 'Unknown'}`;
        const telegramUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
        window.open(telegramUrl, '_blank');
        showShareModal = false;
    }
    
    function shareToFacebook() {
        const url = window.location.href;
        const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
        window.open(facebookUrl, '_blank');
        showShareModal = false;
    }
    
    function shareToTwitter() {
        const url = window.location.href;
        const text = `Status pengiriman wakaf Al-Qur'an ${pengiriman.no_resi}: ${pengiriman.status?.nama || 'Unknown'}`;
        const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`;
        window.open(twitterUrl, '_blank');
        showShareModal = false;
    }
    
    let showShareModal = false;

    // Combine and sort status history and tracking history by date
    $: combinedHistory = [
        // Map statusHistory to common format
        ...statusHistory.map(item => ({
            type: 'status_history',
            status: item.status_to,
            tanggal_update: item.formatted_date,
            lokasi: item.lokasi,
            keterangan: item.catatan,
            foto_dokumentasi: item.foto_dokumentasi || [],
            user: item.creator,
            raw_date: new Date(item.created_at)
        })),
        // Map trackingHistory to common format
        ...trackingHistory.map(item => ({
            type: 'tracking_history',
            status: item.status,
            tanggal_update: item.tanggal_update,
            lokasi: item.lokasi,
            keterangan: item.keterangan,
            foto_dokumentasi: item.foto_dokumentasi || [],
            user: item.user,
            raw_date: new Date(item.tanggal_update.split(' ')[0].split('/').reverse().join('-') + ' ' + item.tanggal_update.split(' ')[1])
        }))
    ].sort((a, b) => b.raw_date - a.raw_date);
</script>

<svelte:window on:keydown={handleKeydown} />

<svelte:head>
    <title>Tracking {no_resi} - Ekspedisi Quran</title>
    <meta name="description" content="Status pengiriman wakaf Al-Qur'an dengan nomor resi {no_resi}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<PublicLayout {settings}>
    <div class="font-cairo pt-24 lg:pt-32">
        {#if pengiriman}
            <!-- Success - Show Tracking Details -->
            <section class="py-8 lg:py-12">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Header Status Card -->
                    <div class="bg-gradient-to-br from-white to-red-50 rounded-3xl shadow-2xl border border-gray-100 overflow-hidden mb-8">
                        <!-- Header dengan gradient -->
                        <div class="bg-gradient-to-r from-[#eb3434] to-red-600 px-6 lg:px-8 py-6 lg:py-8 text-white">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                                <div class="mb-4 md:mb-0">
                                    <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold mb-2">📦 {pengiriman.no_resi}</h1>
                                    <p class="text-red-100 text-base lg:text-lg">
                                        Wakaf Al-Qur'an dari <strong>{pengiriman.wakaf_item?.wakif_name || pengiriman.donatur?.nama_donatur || 'Unknown'}</strong>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="inline-flex items-center px-4 lg:px-6 py-2 lg:py-3 bg-white/20 backdrop-blur rounded-xl border border-white/30">
                                        <span class="mr-2 lg:mr-3 text-xl lg:text-2xl">{pengiriman.status?.icon || '📦'}</span>
                                        <div class="text-left">
                                            <div class="text-xs lg:text-sm text-red-100">Status Terkini</div>
                                            <div class="font-bold text-base lg:text-lg">{pengiriman.status?.nama || 'Unknown'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detail Content -->
                        <div class="p-6 lg:p-8">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                                <!-- Left Column - Detail Wakaf -->
                                <div class="space-y-6 lg:space-y-8">
                                    <div>
                                        <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-4 lg:mb-6 flex items-center">
                                            <span class="w-6 h-6 lg:w-8 lg:h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-2 lg:mr-3">
                                                <span class="text-white text-xs lg:text-sm">📋</span>
                                            </span>
                                            Detail Wakaf
                                        </h2>
                                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
                                            <div class="space-y-3 lg:space-y-4">
                                                <div class="flex justify-between items-center py-2 lg:py-3 border-b border-gray-100">
                                                    <span class="text-gray-600 font-medium text-sm lg:text-base">Jenis Al-Qur'an:</span>
                                                    <span class="font-semibold text-gray-900 text-sm lg:text-base">{pengiriman.jenis_quran?.nama_jenis || 'Unknown'}</span>
                                                </div>
                                                <div class="flex justify-between items-center py-2 lg:py-3 border-b border-gray-100">
                                                    <span class="text-gray-600 font-medium text-sm lg:text-base">Jumlah:</span>
                                                    <span class="font-semibold text-gray-900 text-sm lg:text-base">{pengiriman.jumlah_quran} mushaf</span>
                                                </div>
                                                <div class="flex justify-between items-center py-2 lg:py-3 {pengiriman.catatan ? 'border-b border-gray-100' : ''}">
                                                    <span class="text-gray-600 font-medium text-sm lg:text-base">Tanggal Wakaf:</span>
                                                    <span class="font-semibold text-gray-900 text-sm lg:text-base">{pengiriman.formatted_tanggal_wakaf || 'Unknown'}</span>
                                                </div>
                                                {#if pengiriman.catatan}
                                                <div class="flex justify-between items-center py-2 lg:py-3">
                                                    <span class="text-gray-600 font-medium text-sm lg:text-base">Catatan:</span>
                                                    <span class="font-semibold text-gray-900 text-sm lg:text-base">{pengiriman.catatan}</span>
                                                </div>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>

                                    {#if pengiriman.alamat_tujuan}
                                        <div>
                                            <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-4 lg:mb-6 flex items-center">
                                                <span class="w-6 h-6 lg:w-8 lg:h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-2 lg:mr-3">
                                                    <span class="text-white text-xs lg:text-sm">📍</span>
                                                </span>
                                                Alamat Tujuan
                                            </h2>
                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
                                                <p class="text-gray-900 font-medium mb-3 text-sm lg:text-base">{pengiriman.alamat_tujuan}</p>
                                                {#if pengiriman.nama_penerima}
                                                    <div class="flex items-center text-xs lg:text-sm text-gray-600 mb-2">
                                                        <span class="w-4 h-4 bg-gray-400 rounded-full flex items-center justify-center mr-2">
                                                            <span class="text-white text-xs">👤</span>
                                                        </span>
                                                        Penerima: <strong class="ml-1">{pengiriman.nama_penerima}</strong>
                                                    </div>
                                                {/if}
                                                {#if pengiriman.no_hp_penerima}
                                                    <div class="flex items-center text-xs lg:text-sm text-gray-600">
                                                        <span class="w-4 h-4 bg-gray-400 rounded-full flex items-center justify-center mr-2">
                                                            <span class="text-white text-xs">📱</span>
                                                        </span>
                                                        No. HP: {pengiriman.no_hp_penerima}
                                                    </div>
                                                {/if}
                                            </div>
                                        </div>
                                    {/if}

                                    <!-- Certificate Download Section -->
                                    {#if certificateUrls && certificateUrls.length > 0}
                                        <div>
                                            <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-4 lg:mb-6 flex items-center">
                                                <span class="w-6 h-6 lg:w-8 lg:h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-2 lg:mr-3">
                                                    <span class="text-white text-xs lg:text-sm">📜</span>
                                                </span>
                                                Sertifikat Wakaf
                                            </h2>
                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
                                                <div class="space-y-4">
                                                    {#each certificateUrls as certificate}
                                                        <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg border border-green-200">
                                                            <div class="flex items-center space-x-3">
                                                                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                                                                    <span class="text-white text-lg">📜</span>
                                                                </div>
                                                                <div>
                                                                    <h3 class="font-semibold text-gray-900 text-sm lg:text-base">
                                                                        Sertifikat #{certificate.nomor_sertifikat}
                                                                    </h3>
                                                                    <p class="text-xs lg:text-sm text-gray-600">
                                                                        Batch: {certificate.batch_code}
                                                                    </p>
                                                                    {#if certificate.wakif_names && certificate.wakif_names.length > 0}
                                                                        <p class="text-xs lg:text-sm text-green-700 font-medium">
                                                                            Wakif: {certificate.wakif_names.join(', ')}
                                                                        </p>
                                                                    {/if}
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
                                                                    class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors text-xs lg:text-sm"
                                                                >
                                                                    <span class="mr-1">📥</span>
                                                                    Download
                                                                </a>
                                                            </div>
                                                        </div>
                                                    {/each}
                                                </div>
                                                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                    <p class="text-xs lg:text-sm text-blue-800 flex items-start">
                                                        <span class="text-blue-500 mr-2 mt-0.5">ℹ️</span>
                                                        <span>Sertifikat ini adalah bukti bahwa wakaf Al-Qur'an Anda telah tersalurkan. Simpan sebagai dokumentasi amal jariyah Anda.</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    {/if}
                                    

                                </div>

                                <!-- Right Column - Timeline Status -->
                                <div>
                                    <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-4 lg:mb-6 flex items-center">
                                        <span class="w-6 h-6 lg:w-8 lg:h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-2 lg:mr-3">
                                            <span class="text-white text-xs lg:text-sm">📈</span>
                                        </span>
                                        Riwayat Status
                                    </h2>
                                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
                                        <!-- Timeline Container -->
                                        <div class="relative space-y-0">
                                            {#if combinedHistory && combinedHistory.length > 0}
                                                {#each combinedHistory as tracking, index}
                                                    <div class="relative flex timeline-item">
                                                        <!-- Timeline Line -->
                                                        {#if index < combinedHistory.length - 1}
                                                            <div class="absolute left-6 top-12 w-0.5 h-full bg-gray-300 z-0" style="height: calc(100% + 2rem);"></div>
                                                        {/if}
                                                        
                                                        <!-- Timeline Icon -->
                                                        <div class="flex-shrink-0 flex flex-col items-center">
                                                            <div class="w-12 h-12 bg-[#eb3434] rounded-full flex items-center justify-center shadow-lg z-10 border-2 border-white">
                                                                <span class="text-white text-lg">{tracking.status?.icon || '📦'}</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Timeline Content -->
                                                        <div class="ml-4 flex-1 pb-8">
                                                            <div class="bg-gray-50 rounded-lg p-4 shadow-sm">
                                                                <!-- Status Badge, Location and Date -->
                                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                                                                    <div class="flex flex-wrap items-center gap-2">
                                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {getStatusColor(tracking.status?.nama)} w-fit">
                                                                            {tracking.status?.nama || 'Unknown'}
                                                                        </span>
                                                                        {#if tracking.lokasi}
                                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                                                                <span class="mr-2">📍</span>
                                                                                {tracking.lokasi}
                                                                            </span>
                                                                        {/if}
                                                                    </div>
                                                                    <span class="text-xs text-gray-500 font-medium">
                                                                        {tracking.tanggal_update}
                                                                    </span>
                                                                </div>
                                                                
                                                                <!-- Keterangan -->
                                                                {#if tracking.keterangan}
                                                                    <div class="mb-3">
                                                                        <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                                                                            <div class="flex items-start">
                                                                                <span class="text-blue-500 mr-2 mt-0.5 flex-shrink-0">💬</span>
                                                                                <div class="flex-1">
                                                                                    <p class="text-sm font-medium text-blue-900 mb-1">Keterangan:</p>
                                                                                    <p class="text-sm text-blue-800 leading-relaxed">{tracking.keterangan}</p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                {/if}
                                                                
                                                                <!-- Foto Dokumentasi -->
                                                                {#if tracking.foto_dokumentasi && tracking.foto_dokumentasi.length > 0}
                                                                    <div class="mb-3">
                                                                        <div class="flex items-center gap-2 mb-3">
                                                                            <span class="text-gray-600">📸</span>
                                                                            <p class="text-sm font-medium text-gray-700">Foto Dokumentasi:</p>
                                                                        </div>
                                                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                                                            {#each tracking.foto_dokumentasi as photo, photoIndex}
                                                                                <div class="relative group cursor-pointer">
                                                                                    <button 
                                                                                        on:click={() => openImageModal(tracking.foto_dokumentasi, photoIndex)}
                                                                                        class="block w-full rounded-xl overflow-hidden border-2 border-gray-200 hover:border-[#eb3434] transition-colors shadow-sm bg-white"
                                                                                    >
                                                                                        <img 
                                                                                            src={photo} 
                                                                                            alt="Dokumentasi {tracking.status?.nama}"
                                                                                            class="w-full h-auto object-contain"
                                                                                            loading="lazy"
                                                                                        />
                                                                                    </button>
                                                                                </div>
                                                                            {/each}
                                                                        </div>
                                                                        {#if tracking.foto_dokumentasi.length > 3}
                                                                            <p class="text-xs text-gray-500 mt-2 text-center">
                                                                                Klik foto untuk melihat semua {tracking.foto_dokumentasi.length} dokumentasi
                                                                            </p>
                                                                        {/if}
                                                                    </div>
                                                                {/if}

                                                                <!-- Petugas -->
                                                                {#if tracking.user}
                                                                    <div class="text-xs text-gray-500 border-t border-gray-100 pt-2">
                                                                        Diupdate oleh: <strong class="text-gray-700">{tracking.user.name}</strong>
                                                                    </div>
                                                                {/if}
                                                            </div>
                                                        </div>
                                                    </div>
                                                {/each}
                                            {:else}
                                                <div class="text-center py-8 lg:py-12 text-gray-500">
                                                    <span class="text-4xl lg:text-6xl mb-3 lg:mb-4 block">📝</span>
                                                    <p class="text-base lg:text-lg font-medium">Belum ada riwayat tracking</p>
                                                    <p class="text-xs lg:text-sm">Data tracking akan muncul ketika admin melakukan update status dengan dokumentasi</p>
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 lg:gap-4 justify-center px-4 sm:px-0">
                        <a href="/tracking" class="inline-flex items-center justify-center px-6 lg:px-8 py-3 lg:py-4 bg-[#eb3434] hover:bg-red-600 text-white font-semibold rounded-xl transition-colors shadow-lg text-sm lg:text-base">
                            <span class="mr-2">🔍</span>
                            Lacak Resi Lain
                        </a>
                        <button on:click={shareTracking} class="inline-flex items-center justify-center px-6 lg:px-8 py-3 lg:py-4 border-2 border-[#eb3434] text-[#eb3434] hover:bg-[#eb3434] hover:text-white font-semibold rounded-xl transition-colors text-sm lg:text-base">
                            <span class="mr-2">📤</span>
                            Bagikan
                        </button>
                    </div>
                </div>
            </section>
        {:else}
            <!-- Not Found -->
            <section class="py-8 lg:py-12">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center py-12 lg:py-20">
                        <div class="inline-flex items-center justify-center w-20 h-20 lg:w-24 lg:h-24 bg-red-100 rounded-full mb-6 lg:mb-8">
                            <span class="text-red-500 text-3xl lg:text-4xl">❌</span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-4">
                            Nomor Resi Tidak Ditemukan
                        </h1>
                        <p class="text-lg lg:text-xl text-gray-600 mb-6 lg:mb-8">
                            Nomor resi <strong class="text-[#eb3434]">"{no_resi}"</strong> tidak ditemukan dalam sistem kami.
                        </p>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 lg:p-8 max-w-lg mx-auto mb-8 lg:mb-12">
                            <h2 class="font-bold text-yellow-800 mb-4 text-base lg:text-lg">💡 Tips Pencarian:</h2>
                            <ul class="text-yellow-700 space-y-2 lg:space-y-3 text-left text-sm lg:text-base">
                                <li class="flex items-start space-x-2">
                                    <span class="text-yellow-600 mt-1">•</span>
                                    <span>Pastikan format resi benar: <code class="bg-yellow-200 px-2 py-1 rounded text-xs lg:text-sm">EQ-YYYY-XXXXX</code></span>
                                </li>
                                <li class="flex items-start space-x-2">
                                    <span class="text-yellow-600 mt-1">•</span>
                                    <span>Periksa kembali nomor yang dimasukkan</span>
                                </li>
                                <li class="flex items-start space-x-2">
                                    <span class="text-yellow-600 mt-1">•</span>
                                    <span>Resi mungkin belum diproses dalam sistem (tunggu 1-2 jam)</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-3 lg:gap-4 justify-center">
                            <a href="/tracking" class="inline-flex items-center justify-center px-6 lg:px-8 py-3 lg:py-4 bg-[#eb3434] hover:bg-red-600 text-white font-semibold rounded-xl transition-colors shadow-lg text-sm lg:text-base">
                                <span class="mr-2">🔍</span>
                                Coba Lagi
                            </a>
                            <a href="tel:+{formatPhoneForWhatsApp(settings.contact_phone)}" class="inline-flex items-center justify-center px-6 lg:px-8 py-3 lg:py-4 border-2 border-[#eb3434] text-[#eb3434] hover:bg-[#eb3434] hover:text-white font-semibold rounded-xl transition-colors text-sm lg:text-base">
                                <span class="mr-2">📞</span>
                                Hubungi CS
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        {/if}
    </div>
    
    <!-- Share Modal -->
    {#if showShareModal}
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" role="button" tabindex="0" aria-label="Tutup dialog share" on:click|self={() => showShareModal = false} on:keydown={(e) => { if (e.key==='Escape' || e.key==='Enter' || e.key===' ') { e.preventDefault(); showShareModal = false; } }}>
            <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4" role="dialog" aria-modal="true">
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Bagikan Tracking</h3>
                    <p class="text-gray-600 text-sm">Pilih platform untuk membagikan status tracking</p>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <!-- WhatsApp -->
                    <button on:click={shareToWhatsApp} class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                            <span class="text-white text-xl">📱</span>
                        </div>
                        <span class="text-sm font-medium text-gray-700">WhatsApp</span>
                    </button>
                    
                    <!-- Telegram -->
                    <button on:click={shareToTelegram} class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors group">
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                            <span class="text-white text-xl">✈️</span>
                        </div>
                        <span class="text-sm font-medium text-gray-700">Telegram</span>
                    </button>
                    
                    <!-- Facebook -->
                    <button on:click={shareToFacebook} class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors group">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                            <span class="text-white text-xl">📘</span>
                        </div>
                        <span class="text-sm font-medium text-gray-700">Facebook</span>
                    </button>
                    
                    <!-- Twitter -->
                    <button on:click={shareToTwitter} class="flex flex-col items-center p-4 bg-sky-50 hover:bg-sky-100 rounded-xl transition-colors group">
                        <div class="w-12 h-12 bg-sky-500 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                            <span class="text-white text-xl">🐦</span>
                        </div>
                        <span class="text-sm font-medium text-gray-700">Twitter</span>
                    </button>
                </div>
                
                <!-- Copy Link -->
                <button on:click={copyToClipboard} class="w-full flex items-center justify-center p-3 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors mb-4">
                    <span class="mr-2">🔗</span>
                    <span class="font-medium text-gray-700">Salin Link</span>
                </button>
                
                <!-- Close Button -->
                <button on:click={() => showShareModal = false} class="w-full p-3 text-gray-500 hover:text-gray-700 transition-colors font-medium">
                    Tutup
                </button>
            </div>
        </div>
    {/if}
    
    <!-- Image Modal -->
    {#if showImageModal && currentImages.length > 0}
        <div class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 p-4" role="button" tabindex="0" aria-label="Tutup dialog gambar" on:click|self={closeImageModal} on:keydown={(e) => { if (e.key==='Escape' || e.key==='Enter' || e.key===' ') { e.preventDefault(); closeImageModal(); } }}>
            <div class="relative max-w-4xl max-h-full w-full h-full flex items-center justify-center" role="dialog" aria-modal="true">
                <!-- Close Button -->
                <button 
                    on:click={closeImageModal}
                    class="absolute top-4 right-4 z-10 w-10 h-10 bg-black bg-opacity-50 hover:bg-opacity-75 rounded-full flex items-center justify-center text-white transition-all"
                >
                    <span class="text-xl">✕</span>
                </button>
                
                <!-- Image Counter -->
                {#if currentImages.length > 1}
                    <div class="absolute top-4 left-4 z-10 px-3 py-1 bg-black bg-opacity-50 rounded-full text-white text-sm font-medium">
                        {currentImageIndex + 1} / {currentImages.length}
                    </div>
                {/if}
                
                <!-- Previous Button -->
                {#if currentImages.length > 1}
                    <button 
                        on:click={prevImage}
                        class="absolute left-4 top-1/2 transform -translate-y-1/2 z-10 w-12 h-12 bg-black bg-opacity-50 hover:bg-opacity-75 rounded-full flex items-center justify-center text-white transition-all"
                    >
                        <span class="text-2xl">‹</span>
                    </button>
                {/if}
                
                <!-- Next Button -->
                {#if currentImages.length > 1}
                    <button 
                        on:click={nextImage}
                        class="absolute right-4 top-1/2 transform -translate-y-1/2 z-10 w-12 h-12 bg-black bg-opacity-50 hover:bg-opacity-75 rounded-full flex items-center justify-center text-white transition-all"
                    >
                        <span class="text-2xl">›</span>
                    </button>
                {/if}
                
                <!-- Main Image -->
                <img 
                    src={currentImages[currentImageIndex]} 
                    alt="Dokumentasi {currentImageIndex + 1}"
                    class="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
                />
            </div>
        </div>
    {/if}
</PublicLayout>

<style>
    .font-cairo {
        font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
    }
    
    /* Smooth scrolling */
    :global(html) {
        scroll-behavior: smooth;
    }
    
    /* Enhanced responsive layout */
    @media (max-width: 640px) {
        .space-y-6 {
            gap: 1rem;
        }
        
        .grid-cols-1 {
            gap: 1.5rem;
        }
    }
    
    /* Print styles */
    @media print {
        .font-cairo {
            padding-top: 0 !important;
        }
        
        button, .print-hide {
            display: none !important;
        }
        
        .shadow-2xl, .shadow-lg, .shadow-sm {
            box-shadow: none !important;
            border: 1px solid #e5e7eb !important;
        }
        
        .bg-gradient-to-r, .bg-gradient-to-br {
            background: #dc2626 !important;
            color: white !important;
        }
        
        .text-white {
            color: white !important;
        }
        
        .backdrop-blur {
            backdrop-filter: none !important;
        }
    }
    
    /* Enhanced mobile touch targets */
    @media (max-width: 768px) {
        button, a {
            min-height: 44px;
            touch-action: manipulation;
        }
    }
    
    /* Timeline styling - Clean and functional version */
    .timeline-item {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
        position: relative;
    }
    
    .timeline-item:nth-child(1) { animation-delay: 0.1s; }
    .timeline-item:nth-child(2) { animation-delay: 0.2s; }
    .timeline-item:nth-child(3) { animation-delay: 0.3s; }
    .timeline-item:nth-child(4) { animation-delay: 0.4s; }
    .timeline-item:nth-child(5) { animation-delay: 0.5s; }
    
    /* Timeline vertical line positioning */
    .timeline-item .absolute {
        left: 1.5rem; /* Center of 48px icon (24px) */
        transform: translateX(-50%);
        z-index: 1;
    }
    
    /* Timeline icons should be above the line */
    .timeline-item .z-10 {
        position: relative;
        z-index: 10;
    }
    
    /* Share Modal Styling */
    .fixed.inset-0 {
        backdrop-filter: blur(4px);
    }
    
    /* Modal Animation */
    .bg-white.rounded-2xl {
        animation: modalSlideUp 0.3s ease-out;
    }
    
    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    /* Share button hover effects */
    :global(.group:hover .group-hover\\:scale-110) {
        transform: scale(1.1);
    }
    
    /* Image modal animations */
    .fixed.inset-0 img {
        animation: imageZoomIn 0.3s ease-out;
    }
    
    @keyframes imageZoomIn {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    /* Image hover effects - Enhanced */
    .group:hover img {
        transform: scale(1.05);
    }
    
    /* Tooltip positioning */
    :global(.group:hover .absolute.-top-8) {
        z-index: 50;
    }
    
    /* Loading skeleton for images */
    img[loading="lazy"] {
        background-color: #f3f4f6;
        background-image: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }
    
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
    
    /* Enhanced focus states for accessibility */
    :global(button:focus), :global(a:focus), :global(input:focus) {
        outline: 2px solid #eb3434;
        outline-offset: 2px;
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    ::-webkit-scrollbar-thumb {
        background: #eb3434;
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: #d12d2d;
    }
</style>
