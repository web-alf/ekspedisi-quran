<script>
    export let no_resi;
    export let settings = {};
    import { inertia } from '@inertiajs/svelte';
    import PublicLayout from '../../Layouts/PublicLayout.svelte';
</script>

<svelte:head>
    <title>Resi {no_resi} Tidak Ditemukan - Ekspedisi Quran</title>
    <meta name="description" content="Nomor resi {no_resi} tidak ditemukan dalam sistem tracking Ekspedisi Quran" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<PublicLayout {settings}>
    <div class="font-cairo pt-16 lg:pt-20">
        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-white to-red-50 overflow-hidden py-16 lg:py-20">
            <!-- Islamic Pattern Background -->
            <div class="absolute inset-0 opacity-30 islamic-geometric"></div>
            
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Main Content -->
                <div class="text-center mb-8 lg:mb-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 lg:w-20 lg:h-20 bg-[#eb3434] rounded-full mb-4 lg:mb-6">
                        <span class="text-white text-2xl lg:text-3xl">❌</span>
                    </div>
                    <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4 lg:mb-6 leading-tight">
                        <span class="block">Resi Tidak Ditemukan</span>
                        <span class="block text-[#eb3434] text-2xl md:text-4xl lg:text-5xl font-mono">{no_resi}</span>
                    </h1>
                    <p class="text-lg lg:text-xl text-gray-600 mb-6 lg:mb-8 max-w-2xl mx-auto">
                        Maaf, nomor resi yang Anda masukkan tidak ditemukan dalam sistem kami
                    </p>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-center space-y-3 sm:space-y-0 sm:space-x-4">
                        <a 
                            href="/tracking" 
                            class="w-full sm:w-auto px-6 lg:px-8 py-3 lg:py-4 bg-[#eb3434] hover:bg-red-600 text-white text-base lg:text-lg font-semibold rounded-lg transition-colors flex items-center justify-center space-x-2 shadow-lg"
                        >
                            <span>🔍</span>
                            <span>Coba Lagi</span>
                        </a>
                        <a 
                            href="/" 
                            class="w-full sm:w-auto px-6 lg:px-8 py-3 lg:py-4 bg-white hover:bg-gray-50 text-gray-700 text-base lg:text-lg font-semibold rounded-lg transition-colors flex items-center justify-center space-x-2 shadow-lg border border-gray-200"
                        >
                            <span>🏠</span>
                            <span>Kembali ke Beranda</span>
                        </a>
                    </div>
                </div>

                <!-- Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 max-w-4xl mx-auto">
                    <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 text-center border border-gray-100 animate-card">
                        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-[#eb3434] rounded-lg flex items-center justify-center mx-auto mb-3 lg:mb-4">
                            <span class="text-white text-lg lg:text-xl">📝</span>
                        </div>
                        <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-2">Periksa Format</h3>
                        <p class="text-gray-600 text-sm lg:text-base">
                            Pastikan format nomor resi sesuai: 
                            <code class="bg-gray-100 px-2 py-1 rounded text-xs lg:text-sm font-mono">EQ-YYYY-XXXXX</code>
                        </p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 text-center border border-gray-100 animate-card">
                        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-[#eb3434] rounded-lg flex items-center justify-center mx-auto mb-3 lg:mb-4">
                            <span class="text-white text-lg lg:text-xl">⏳</span>
                        </div>
                        <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-2">Tunggu Sebentar</h3>
                        <p class="text-gray-600 text-sm lg:text-base">Data resi mungkin belum masuk ke sistem (tunggu 1-2 jam)</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 text-center border border-gray-100 animate-card">
                        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-[#eb3434] rounded-lg flex items-center justify-center mx-auto mb-3 lg:mb-4">
                            <span class="text-white text-lg lg:text-xl">📞</span>
                        </div>
                        <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-2">Hubungi Kami</h3>
                        <p class="text-gray-600 text-sm lg:text-base">Jika masalah berlanjut, silakan hubungi customer service</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</PublicLayout>

<style>
    .font-cairo {
        font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
    }
    
    /* Islamic pattern enhancement */
    .islamic-geometric {
        background-image:
            repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.08) 4px, rgba(235, 52, 52, 0.08) 6px),
            repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.06) 4px, rgba(235, 52, 52, 0.06) 6px),
            radial-gradient(circle at 25% 25%, rgba(235, 52, 52, 0.1) 1px, transparent 1px),
            radial-gradient(circle at 75% 75%, rgba(235, 52, 52, 0.1) 1px, transparent 1px);
        background-size: 12px 12px, 12px 12px, 8px 8px, 8px 8px;
    }
    
    /* Smooth scrolling */
    :global(html) {
        scroll-behavior: smooth;
    }
    
    /* Enhanced mobile touch targets */
    @media (max-width: 768px) {
        :global(button), :global(a) {
            min-height: 44px;
            touch-action: manipulation;
        }
    }
    
    /* Enhanced focus states for accessibility */
    :global(button:focus), :global(a:focus) {
        outline: 2px solid #eb3434;
        outline-offset: 2px;
    }
    
    /* Animation for cards */
    .animate-card {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .animate-card:nth-child(1) { animation-delay: 0.1s; }
    .animate-card:nth-child(2) { animation-delay: 0.2s; }
    .animate-card:nth-child(3) { animation-delay: 0.3s; }
    
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
