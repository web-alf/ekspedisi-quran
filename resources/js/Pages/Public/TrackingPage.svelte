<script>
    import PublicLayout from '../../Layouts/PublicLayout.svelte';
    import { inertia } from '@inertiajs/svelte';
    import { formatPageTitle, pageTitles, generateMetaDescription } from '@/utils/seo.js';
    
    export let searchQuery = '';
    export let error = null;
    export let settings = {};

    // SEO data
    const pageTitle = formatPageTitle(pageTitles.publicTracking);
    const metaDescription = generateMetaDescription('tracking');
    
    let isLoading = false;
    let trackingInput = searchQuery;
    
    function handleSearch() {
        if (!trackingInput.trim()) {
            error = 'Masukkan nomor resi terlebih dahulu';
            return;
        }
        
        isLoading = true;
        error = null;
        
        // Redirect ke URL tracking
        window.location.href = `/tracking/${trackingInput.trim()}`;
    }
    
    function handleKeyPress(event) {
        if (event.key === 'Enter') {
            handleSearch();
        }
    }
</script>

<svelte:head>
    <title>{pageTitle}</title>
    <meta name="description" content="{metaDescription}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="{pageTitle}">
    <meta property="og:description" content="{metaDescription}">
    
    <!-- Twitter -->
    <meta property="twitter:title" content="{pageTitle}">
    <meta property="twitter:description" content="{metaDescription}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<PublicLayout {settings}>
    <div class="font-cairo">
        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-white to-red-50 overflow-hidden pt-32 pb-20">
            <div class="absolute inset-0 opacity-30 islamic-geometric"></div>
            
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-[#eb3434] rounded-full mb-6">
                        <span class="text-white text-3xl">🔍</span>
                    </div>
                    <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
                        <span class="block">Lacak Status</span>
                        <span class="block text-[#eb3434]">Wakaf Al-Qur'an</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                        Masukkan nomor resi untuk melihat status pengiriman wakaf Al-Qur'an Anda secara real-time
                    </p>
                    
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
                                <div class="flex-1 w-full relative">
                                    <input
                                        bind:value={trackingInput}
                                        on:keypress={handleKeyPress}
                                        type="text"
                                        placeholder="Contoh: EQ-2025-00123"
                                        class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-lg focus:border-[#eb3434] focus:outline-none focus:ring-4 focus:ring-red-100 transition-all"
                                    />
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-6">
                                        <span class="text-gray-400">📦</span>
                                    </div>
                                </div>
                                <button
                                    on:click={handleSearch}
                                    disabled={isLoading}
                                    class="w-full md:w-auto px-8 py-4 bg-[#eb3434] hover:bg-red-600 disabled:bg-gray-400 text-white text-lg font-semibold rounded-lg transition-colors flex items-center justify-center space-x-2 shadow-lg"
                                >
                                    {#if isLoading}
                                        <span class="animate-spin">⏳</span>
                                        <span>Mencari...</span>
                                    {:else}
                                        <span>🔍</span>
                                        <span>Lacak Sekarang</span>
                                    {/if}
                                </button>
                            </div>
                            
                            {#if error}
                                <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-600 font-medium text-center">❌ {error}</p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto mb-16">
                    <div class="bg-white rounded-xl shadow-lg p-6 text-center border border-gray-100">
                        <div class="w-12 h-12 bg-[#eb3434] rounded-lg flex items-center justify-center mx-auto mb-4">
                            <span class="text-white text-xl">⚡</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Real-time</h3>
                        <p class="text-gray-600 text-sm">Update status secara langsung dari sistem kami</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 text-center border border-gray-100">
                        <div class="w-12 h-12 bg-[#eb3434] rounded-lg flex items-center justify-center mx-auto mb-4">
                            <span class="text-white text-xl">🔒</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Aman</h3>
                        <p class="text-gray-600 text-sm">Data tracking Anda dilindungi dengan keamanan tinggi</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 text-center border border-gray-100">
                        <div class="w-12 h-12 bg-[#eb3434] rounded-lg flex items-center justify-center mx-auto mb-4">
                            <span class="text-white text-xl">📱</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Mobile Friendly</h3>
                        <p class="text-gray-600 text-sm">Akses dari smartphone kapan saja dan di mana saja</p>
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
    
    :global(html) {
        scroll-behavior: smooth;
    }
    
    * {
        scroll-behavior: smooth;
    }
    
    section {
        scroll-margin-top: 80px;
    }
    
    .islamic-geometric {
        background-image:
            repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.08) 4px, rgba(235, 52, 52, 0.08) 6px),
            repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.06) 4px, rgba(235, 52, 52, 0.06) 6px),
            radial-gradient(circle at 25% 25%, rgba(235, 52, 52, 0.1) 1px, transparent 1px),
            radial-gradient(circle at 75% 75%, rgba(235, 52, 52, 0.1) 1px, transparent 1px);
        background-size: 12px 12px, 12px 12px, 8px 8px, 8px 8px;
    }
    
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
    
    /* removed unused .scroll-smooth-link */
</style>
