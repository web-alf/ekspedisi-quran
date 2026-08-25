<script>
    import { page } from '@inertiajs/svelte';
    import Navbar from '@/Components/Navbar.svelte';
    import Footer from '@/Components/Footer.svelte';
    
    // Get settings from page props
    $: settings = $page.props.settings || {};
</script>

<svelte:head>
    <title>Ekspedisi Qur'an - Sebarkan Kebaikan ke Pelosok Nusantara</title>
    <meta name="description" content="Bantu kirimkan mushaf Al-Quran ke pelosok Indonesia. Cek status pengiriman dan download sertifikat kontribusi Anda.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<div class="min-h-screen bg-white font-cairo flex flex-col">
    <Navbar {settings} />
    
    <main class="flex-1">
        <slot />
    </main>

    <Footer {settings} />
</div>

<style>
    :global(.font-cairo) {
        font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
    }
    
    /* Smooth scrolling */
    :global(html) {
        scroll-behavior: smooth;
    }
    
    /* Smooth scrolling enhanced untuk semua browser */
    :global(*) {
        scroll-behavior: smooth;
    }
    
    /* Offset untuk fixed navbar */
    :global(section) {
        scroll-margin-top: 64px;
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
    
    /* Print styles */
    @media print {
        :global(nav), :global(footer), :global(button) {
            display: none !important;
        }
        
        :global(.pt-16) {
            padding-top: 0 !important;
        }
    }
    
    /* Smooth scrolling for internal links */
    :global(.scroll-smooth-link) {
        cursor: pointer;
    }
</style> 
