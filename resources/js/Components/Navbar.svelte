<script>
import { getImageUrl } from '../utils/formHelpers.js';
    import { page } from '@inertiajs/svelte';
    
    export let settings = {};
    let isMobileMenuOpen = false;
    
    // Get auth info from page props
    $: isAuthenticated = $page.props.auth?.user ? true : false;
    $: currentUser = $page.props.auth?.user || null;
    
    // Function to check if current path matches
    function isActive(path) {
        if (path === '/') {
            return $page.url === '/';
        }
        if (path.startsWith('#')) {
            return $page.url.includes(path);
        }
        return $page.url.startsWith(path);
    }
    
    // Toggle mobile menu
    function toggleMobileMenu() {
        isMobileMenuOpen = !isMobileMenuOpen;
    }
    
    // Close mobile menu when clicking outside
    function closeMobileMenu() {
        isMobileMenuOpen = false;
    }
    
    // Handle smooth scroll for anchor links
    function handleSmoothScroll(event, targetId) {
        event.preventDefault();
        const target = document.getElementById(targetId);
        if (target) {
            const offsetTop = target.offsetTop - 80;
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
        }
        closeMobileMenu();
    }
</script>

<nav class="fixed top-0 left-0 right-0 z-[999999] bg-white/95 backdrop-blur-sm border-b border-gray-200 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="flex justify-between items-center py-3 lg:py-4">
            <!-- Logo -->
            <div class="flex items-center">
                <div class="w-32 h-12 lg:w-40 lg:h-16">
                    <a href="/">
                        <img 
                            src={getImageUrl(settings.app_logo, "/images/logo-ekspedisi-quran.webp")} 
                            alt="{settings.app_name || 'Ekspedisi Qur\'an'} Logo" 
                            class="w-full h-full object-contain" 
                        />
                    </a>
                </div>
            </div>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-6 lg:space-x-8">
                <a 
                    href="/" 
                    class="text-gray-700 hover:text-[#eb3434] transition-colors font-medium relative group text-sm lg:text-base"
                    class:text-[#eb3434]={isActive('/')}
                >
                    Home
                    <span class="absolute -bottom-2 left-0 w-full h-0.5 bg-[#eb3434] transform scale-x-0 transition-transform duration-300 group-hover:scale-x-100" class:scale-x-100={isActive('/')}></span>
                </a>
                <a 
                    href="/tracking" 
                    class="text-gray-700 hover:text-[#eb3434] transition-colors font-medium relative group text-sm lg:text-base"
                    class:text-[#eb3434]={isActive('/tracking')}
                >
                    Cek Resi
                    <span class="absolute -bottom-2 left-0 w-full h-0.5 bg-[#eb3434] transform scale-x-0 transition-transform duration-300 group-hover:scale-x-100" class:scale-x-100={isActive('/tracking')}></span>
                </a>
                <a 
                    href="/mushaf-request" 
                    class="text-gray-700 hover:text-[#eb3434] transition-colors font-medium relative group text-sm lg:text-base"
                    class:text-[#eb3434]={isActive('/mushaf-request')}
                >
                    Permintaan Mushaf
                    <span class="absolute -bottom-2 left-0 w-full h-0.5 bg-[#eb3434] transform scale-x-0 transition-transform duration-300 group-hover:scale-x-100" class:scale-x-100={isActive('/mushaf-request')}></span>
                </a>
                <a 
                    href="/mushaf-tracking" 
                    class="text-gray-700 hover:text-[#eb3434] transition-colors font-medium relative group text-sm lg:text-base"
                    class:text-[#eb3434]={isActive('/mushaf-tracking')}
                >
                    Cek Permintaan
                    <span class="absolute -bottom-2 left-0 w-full h-0.5 bg-[#eb3434] transform scale-x-0 transition-transform duration-300 group-hover:scale-x-100" class:scale-x-100={isActive('/mushaf-tracking')}></span>
                </a>
                {#if isAuthenticated}
                    <a 
                        href="/dashboard"
                        class="px-3 py-2 lg:px-4 lg:py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors duration-300 font-medium text-sm lg:text-base flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Dashboard
                    </a>
                {:else}
                    <a 
                        href="/login"
                        class="px-3 py-2 lg:px-4 lg:py-2 text-[#eb3434] border border-[#eb3434] rounded-lg hover:bg-[#eb3434] hover:text-white transition-colors duration-300 font-medium text-sm lg:text-base"
                    >
                        Login
                    </a>
                {/if}
            </div>
            
            <!-- Mobile Menu Button -->
            <button 
                on:click={toggleMobileMenu}
                class="md:hidden p-2 text-gray-700 hover:text-[#eb3434] transition-colors duration-300"
                aria-label="Toggle mobile menu"
            >
                {#if isMobileMenuOpen}
                    <!-- Close Icon -->
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                {:else}
                    <!-- Hamburger Icon -->
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                {/if}
            </button>
        </div>
        
    </div>
    
    <!-- Mobile Menu Overlay -->
    {#if isMobileMenuOpen}
        <div 
            class="fixed inset-0 bg-black/30 backdrop-blur-sm md:hidden z-[999998]" 
            role="button"
            tabindex="-1"
            on:click={closeMobileMenu}
            on:keydown={(e) => e.key === 'Enter' || e.key === ' ' || e.key === 'Escape' ? closeMobileMenu() : null}
        ></div>
    {/if}

    <!-- Mobile Menu -->
    {#if isMobileMenuOpen}
        <div class="fixed top-16 left-0 right-0 bg-white shadow-xl md:hidden z-[999999] border-t border-gray-200">
            <div class="px-4 py-4 space-y-2">
                <a 
                    href="/" 
                    on:click={closeMobileMenu}
                    class="block px-4 py-3 text-base font-medium text-gray-700 hover:text-[#eb3434] hover:bg-gray-50 rounded-lg transition-colors duration-300"
                    class:text-[#eb3434]={isActive('/')}
                    class:bg-red-50={isActive('/')}
                >
                    Home
                </a>
                <a 
                    href="/tracking" 
                    on:click={closeMobileMenu}
                    class="block px-4 py-3 text-base font-medium text-gray-700 hover:text-[#eb3434] hover:bg-gray-50 rounded-lg transition-colors duration-300"
                    class:text-[#eb3434]={isActive('/tracking')}
                    class:bg-red-50={isActive('/tracking')}
                >
                    Cek Resi
                </a>
                <a 
                    href="/mushaf-request" 
                    on:click={closeMobileMenu}
                    class="block px-4 py-3 text-base font-medium text-gray-700 hover:text-[#eb3434] hover:bg-gray-50 rounded-lg transition-colors duration-300"
                    class:text-[#eb3434]={isActive('/mushaf-request')}
                    class:bg-red-50={isActive('/mushaf-request')}
                >
                    Permintaan Mushaf
                </a>
                <a 
                    href="/mushaf-tracking" 
                    on:click={closeMobileMenu}
                    class="block px-4 py-3 text-base font-medium text-gray-700 hover:text-[#eb3434] hover:bg-gray-50 rounded-lg transition-colors duration-300"
                    class:text-[#eb3434]={isActive('/mushaf-tracking')}
                    class:bg-red-50={isActive('/mushaf-tracking')}
                >
                    Cek Permintaan
                </a>
                <div class="border-t border-gray-200 pt-3 mt-3">
                    {#if isAuthenticated}
                        <a 
                            href="/dashboard"
                            on:click={closeMobileMenu}
                            class="block px-4 py-3 text-base font-medium bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors duration-300 text-center flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                            Dashboard
                        </a>
                    {:else}
                        <a 
                            href="/login"
                            on:click={closeMobileMenu}
                            class="block px-4 py-3 text-base font-medium text-[#eb3434] border border-[#eb3434] rounded-lg hover:bg-[#eb3434] hover:text-white transition-colors duration-300 text-center"
                        >
                            Login
                        </a>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</nav>

<style>
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Better touch targets for mobile */
    @media (max-width: 768px) {
        button, a {
            min-height: 44px;
            min-width: 44px;
        }
    }
</style> 