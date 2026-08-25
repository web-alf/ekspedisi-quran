<script>
  import { router, page } from '@inertiajs/svelte';
  import { onMount, tick } from 'svelte';
  import { slide } from 'svelte/transition';
  import { cubicOut } from 'svelte/easing';
  import ToastContainer from '../Components/ToastContainer.svelte';
  import DialogContainer from '../Components/DialogContainer.svelte';
  import FlashMessage from '../Components/FlashMessage.svelte';
  import JobProgressIndicator from '../Components/Certificate/JobProgressIndicator.svelte';
  import { logout } from '../utils/auth.js';
  import { getImageUrl } from '../utils/formHelpers.js';
  
  let showUserMenu = false;
  let sidebarOpen = false;
  let sidebarCollapsed = false; // Track sidebar collapse state
  let activeDropdown = null; // Track which dropdown is open
  let submenuTopPosition = 0; // Track submenu position
  
  // Force reactivity
  let dropdownUpdateCounter = 0;
  
  // Get current user safely
  $: currentUser = $page.props.auth?.user || {};
  $: userPermissions = currentUser.permissions || [];
  $: userRolesDisplay = currentUser.roles_display || [];
  $: primaryRoleDisplay = userRolesDisplay[0] || 'User';
  
  // Get settings for logo
  $: settings = $page.props.settings || {};
  
  

  // Clean reactivity tracking
  $: dropdownUpdateCounter; // Trigger reactivity
  
  
  
  // Navigation items based on permissions
  $: navigationItems = getNavigationItems(userPermissions);
  
  // Track if user has manually interacted with dropdowns
  let userInteracted = false;

  // BEST PRACTICE: Auto-open dropdown for current route (but allow manual override)
  $: if (navigationItems && navigationItems.length > 0 && $page && $page.url && !userInteracted) {
    const currentPath = typeof $page.url === 'string' ? $page.url : $page.url.pathname;
    
    // Find which dropdown should be active based on current route
    let shouldBeActive = null;
    navigationItems.forEach(item => {
      if (item.dropdown && item.children) {
        const hasActiveChild = item.children.some(child => {
          if (!child.route) return false;
          return currentPath === child.route || currentPath.startsWith(child.route + '/') || currentPath.startsWith(child.route + '?');
        });
        if (hasActiveChild) {
          shouldBeActive = item.label;
        }
      }
    });
    
    // Only auto-open if no dropdown is manually controlled and user hasn't interacted
    if (shouldBeActive && activeDropdown !== shouldBeActive) {
      activeDropdown = shouldBeActive;
      dropdownUpdateCounter++;
    }
  }

  // Close dropdown when route changes
  $: if ($page && $page.url) {
    activeDropdown = null;
  }
  
  function getAllNavigationItems() {
    return [
      { 
        label: 'Dashboard', 
        route: '/admin/dashboard',
        requiredPermissions: ['dashboard.view']
      },
      { 
        label: 'Kelola Donatur', 
        route: '/admin/donatur',
        requiredPermissions: ['donatur.read']
      },
      { 
        label: 'Pengiriman', 
        route: '/admin/pengiriman',
        requiredPermissions: ['shipments.read']
      },
      { 
        label: 'Sertifikat', 
        route: null,
        dropdown: true,
        requiredPermissions: ['certificates.read', 'templates.read'],
        children: [
          {
            label: 'Manajemen Sertifikat',
            route: '/admin/certificates',
            requiredPermissions: ['certificates.read']
          },
          {
            label: 'Template Sertifikat',
            route: '/admin/certificate-templates',
            requiredPermissions: ['templates.read']
          }
        ]
      },
      {
        label: 'Permintaan Mushaf',
        route: '/admin/mushaf-requests',
        requiredPermissions: ['mushaf-requests.read']
      },
      { 
        label: 'Manajemen Gudang', 
        route: null,
        dropdown: true,
        requiredPermissions: ['warehouse.dashboard', 'supervisor.warehouse.monitor'],
        children: [
          {
            label: 'Dasbor Gudang',
            route: '/admin/warehouse',
            requiredPermissions: ['warehouse.dashboard']
          },
          {
            label: 'Proses Packing',
            route: '/admin/warehouse/packing',
            requiredPermissions: ['warehouse.packing.view']
          },
          {
            label: 'Box Scanner',
            route: '/admin/warehouse/box-scanner',
            requiredPermissions: ['warehouse.dashboard']
          },
          {
            label: 'Laporan Kinerja',
            route: '/admin/warehouse/performance',
            requiredPermissions: ['warehouse.performance.view']
          },
          {
            label: 'Monitor Gudang',
            route: '/admin/supervisor/warehouse-monitor',
            requiredPermissions: ['supervisor.warehouse.monitor']
          },
          {
            label: 'Analitik Kinerja',
            route: '/admin/supervisor/performance-report',
            requiredPermissions: ['supervisor.performance.reports']
          },
          {
            label: 'Pelacakan Kerdus',
            route: '/admin/box-tracking',
            requiredPermissions: ['warehouse.boxes.view']
          }
        ]
      },
      { 
        label: 'Manajemen Pengguna', 
        route: null,
        dropdown: true,
        requiredPermissions: ['users.read'],
        children: [
          {
            label: 'Kelola Pengguna',
            route: '/admin/users',
            requiredPermissions: ['users.read']
          },
          {
            label: 'Kelola Peran',
            route: '/admin/roles',
            requiredPermissions: ['roles.read']
          },
          {
            label: 'Kelola Izin Akses',
            route: '/admin/permissions',
            requiredPermissions: ['permissions.read']
          }
        ]
      },
      {
        label: 'Konten Landing',
        route: null,
        dropdown: true,
        requiredPermissions: ['settings.read'],
        children: [
          {
            label: 'Halaman Utama',
            route: '/admin/settings/landing-content/landing',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'Umum',
            route: '/admin/settings/landing-content/general',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'Kontak',
            route: '/admin/settings/landing-content/contact',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'Media Sosial',
            route: '/admin/settings/landing-content/social',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'SEO',
            route: '/admin/settings/landing-content/seo',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'Legal & Kebijakan',
            route: '/admin/settings/landing-content/legal',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'Testimonial',
            route: '/admin/testimonials',
            requiredPermissions: ['settings.write']
          },
          {
            label: 'Gallery',
            route: '/admin/galleries',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'Video',
            route: '/admin/videos',
            requiredPermissions: ['settings.read']
          },
          {
            label: 'FAQ',
            route: '/admin/faqs',
            requiredPermissions: ['settings.write']
          }
        ]
      }
    ];
  }

  function getNavigationItems(permissions) {
    const allItems = getAllNavigationItems();
    const currentUserRole = $page.props.auth?.user?.role;
    
    // Debug logging (disabled in production)
    if (import.meta.env.DEV) {
      console.log('getNavigationItems Debug:', {
        currentUserRole,
        permissions: permissions?.length > 0 ? permissions : 'NO PERMISSIONS',
        allItemsCount: allItems.length
      });
    }
    
    const filtered = allItems.filter(item => {
      // Permission-based access control
      if (!item.requiredPermissions || item.requiredPermissions.length === 0) {
        return false; // No permissions defined = no access
      }
      
      // For dropdown items, check if user has permission for parent or any children
      if (item.dropdown && item.children) {
        const hasParentPermission = item.requiredPermissions.some(permission => permissions.includes(permission));
        const hasChildPermission = item.children.some(child => 
          child.requiredPermissions ? child.requiredPermissions.some(permission => permissions.includes(permission)) : false
        );
        
        return hasParentPermission || hasChildPermission;
      }
      
      // For regular items - check permissions
      return item.requiredPermissions.some(permission => permissions.includes(permission));
    });
    
    if (import.meta.env.DEV) {
      console.log('Filtered navigation items:', filtered.map(item => item.label));
    }
    return filtered;
  }
  
  // Helper function to check if user has permission (use centralized utility)
  // function hasPermission(permission) {
  //   return userPermissions.includes(permission);
  // }
  
  function isCurrentRoute(route) {
    if (!route || !$page || !$page.url) return false;
    
    // Get current pathname from page - handle both string and URL object
    const currentPath = typeof $page.url === 'string' ? $page.url : $page.url.pathname;
    if (!currentPath) return false;
    
    // Special case for dashboard - exact match only
    if (route === '/admin/dashboard') {
      return currentPath === '/dashboard' || currentPath === '/admin/dashboard';
    }
    
    // For dropdown parent routes, don't highlight if any child route is active
    const isDropdownParent = navigationItems.some(item => 
      item.dropdown && item.children && item.children.some(child => child.route === route)
    );
    
    if (isDropdownParent) {
      return currentPath === route; // Only exact match for dropdown parents
    }
    
    // For regular menu items and dropdown children:
    // Check if current path starts with the route (to include sub-routes)
    // Also check for exact match with query parameters (e.g., /admin/pengiriman?mode=scan-status)
    return currentPath === route || currentPath.startsWith(route + '/') || currentPath.startsWith(route + '?');
  }
  
  function isDropdownActive(item) {
    if (!item.dropdown || !item.children || !$page || !$page.url) return false;
    const currentPath = typeof $page.url === 'string' ? $page.url : $page.url.pathname;
    if (!currentPath) return false;
    return item.children.some(child => {
      if (!child.route) return false;
      // Check if current path matches child route or is a sub-route of it
      // Also check for exact match with query parameters
      return currentPath === child.route || currentPath.startsWith(child.route + '/') || currentPath.startsWith(child.route + '?');
    });
  }
  
  // Logout function is now imported from auth utils
  
  function toggleDropdown(itemLabel, event = null) {
    // BEST PRACTICE: Simple and intuitive behavior
    
    // Mark that user has manually interacted with dropdowns
    userInteracted = true;
    
    if (activeDropdown === itemLabel) {
      // Same dropdown clicked - toggle closed
      activeDropdown = null;
    } else {
      // Different dropdown clicked - open directly (close others automatically)
      activeDropdown = itemLabel;
      
      // Calculate submenu position when collapsed
      if (sidebarCollapsed && event && event.target) {
        const rect = event.target.closest('div').getBoundingClientRect();
        submenuTopPosition = rect.top;
      }
    }
    
    // Debug logging
    if (import.meta.env.DEV) {
      console.log('Toggle dropdown:', {
        itemLabel,
        activeDropdown,
        sidebarCollapsed,
        userPermissions: userPermissions.length
      });
    }
    
    dropdownUpdateCounter++;
  }
  
  function getVisibleChildren(children, permissions) {
    if (!children || !Array.isArray(children)) return [];
    if (!permissions || !Array.isArray(permissions)) return [];
    
    if (import.meta.env.DEV) {
      console.log('getVisibleChildren Debug:', {
        childrenCount: children.length,
        permissionsCount: permissions.length,
        children: children.map(c => ({ label: c.label, route: c.route, permissions: c.requiredPermissions }))
      });
    }
    
    const visible = children.filter(child => {
      // First check permissions - user must have required permissions
      const hasPermission = child.requiredPermissions ? 
        child.requiredPermissions.some(permission => permissions.includes(permission)) : 
        true;
      
      if (import.meta.env.DEV) {
        console.log(`Child "${child.label}" permission check:`, {
          hasPermission,
          requiredPermissions: child.requiredPermissions,
          userPermissions: permissions
        });
      }
      
      if (!hasPermission) {
        return false;
      }

      // Permission check is sufficient
      return true;
    });
    
    if (import.meta.env.DEV) {
      console.log('Visible children result:', visible.map(c => c.label));
    }
    return visible;
  }
  
  // Load and save sidebar collapse state
  onMount(() => {
    // Load saved collapse state from localStorage
    const savedCollapsed = localStorage.getItem('sidebarCollapsed');
    if (savedCollapsed !== null) {
      sidebarCollapsed = JSON.parse(savedCollapsed);
    }

    function handleClickOutside(event) {
      // Don't close if clicking on navigation area or dropdown menu
      const clickedNav = event.target.closest('nav');
      const clickedDropdown = event.target.closest('[data-dropdown-menu]');
      const clickedButton = event.target.closest('button');
      
      if (!clickedNav && !clickedDropdown && !clickedButton) {
        activeDropdown = null;
        showUserMenu = false;
      }
    }
    
    document.addEventListener('click', handleClickOutside);
    
    return () => {
      document.removeEventListener('click', handleClickOutside);
    };
  });

  // Close sidebar on mobile when clicking outside
  function closeSidebarOnMobile() {
    if (window.innerWidth < 1024) { // Changed to lg breakpoint
      sidebarOpen = false;
    }
  }

  function toggleSidebarCollapse() {
    sidebarCollapsed = !sidebarCollapsed;
    // Save state to localStorage
    localStorage.setItem('sidebarCollapsed', JSON.stringify(sidebarCollapsed));
    // Close dropdown when collapsing
    if (sidebarCollapsed) {
      activeDropdown = null;
    }
  }

  function getMenuIcon(label) {
    const iconMapping = {
      // Main menu items
      'Dashboard': '📊',
      'Kelola Donatur': '👥',
      'Pengiriman': '🚚',
      'Sertifikat': '🎓',
      'Permintaan Mushaf': '📖',
      'Manajemen Gudang': '🏭',
      'Manajemen Pengguna': '👤',
      'Konten Landing': '🎨',
      
      // Sertifikat submenu
      'Manajemen Sertifikat': '📜',
      'Template Sertifikat': '📝',
      
      
      // Warehouse submenu
      'Dasbor Gudang': '🏠',
      'Proses Packing': '📦',
      'Box Scanner': '📱',
      'Laporan Kinerja': '📄',
      'Monitor Gudang': '👁️',
      'Analitik Kinerja': '📉',
      'Pelacakan Kerdus': '🔍',
      
      // User management submenu
      'Kelola Pengguna': '👥',
      'Kelola Peran': '🎭',
      'Kelola Izin Akses': '🔐',
      
      // Content submenu
      'Halaman Utama': '🏠',
      'Umum': '⚙️',
      'Kontak': '📞',
      'Media Sosial': '📱',
      'SEO': '🔍',
      'Legal & Kebijakan': '📋',
      'Testimonial': '💭',
      'Gallery': '🖼️',
      'Video': '🎬',
      'FAQ': '❓'
    };
    return iconMapping[label] || '📄';
  }

</script>

<svelte:head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>


<div class="min-h-screen bg-gray-50 font-cairo flex">
  <!-- Sidebar -->
  <div class="fixed inset-y-0 left-0 z-[9998] bg-white shadow-xl border-r border-gray-200 transform {sidebarOpen ? 'translate-x-0' : '-translate-x-full'} transition-all duration-300 ease-in-out lg:translate-x-0 lg:fixed lg:flex lg:flex-col lg:shadow-xl lg:z-[9998] {sidebarCollapsed ? 'w-20' : 'w-72'}" style="overflow: visible;">
    <!-- Logo -->
    <div class="flex items-center justify-between h-16 px-6 bg-gradient-to-r from-[#eb3434] to-red-600 border-b border-red-500">
      <a href="/" class="flex items-center {sidebarCollapsed ? 'justify-center w-full lg:w-auto' : ''}">
        <img src={getImageUrl(settings.admin_logo, "/images/logo-ekspedisiquran-putih.webp")} alt="Ekspedisi Qur'an Logo" class="{sidebarCollapsed ? 'h-8' : 'h-10'} w-auto" />
      </a>
      <!-- Collapse button for desktop -->
      {#if !sidebarCollapsed}
        <button
          type="button"
          class="hidden lg:block p-2 rounded-lg text-white hover:bg-white/20 transition-colors"
          on:click={toggleSidebarCollapse}
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7" />
          </svg>
        </button>
      {:else}
        <button
          type="button"
          class="hidden lg:block p-2 rounded-lg text-white hover:bg-white/20 transition-colors"
          on:click={toggleSidebarCollapse}
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5l7 7-7 7" />
          </svg>
        </button>
      {/if}
      <!-- Close button for mobile -->
      <button
        type="button"
        class="lg:hidden p-2 rounded-lg text-white hover:bg-white/20 transition-colors"
        on:click={() => sidebarOpen = false}
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    
    <!-- Navigation -->
    <nav class="mt-4 px-4 pb-4 overflow-y-auto overflow-x-visible flex-1 relative z-10">
      <div class="space-y-2">
        {#each navigationItems as item}
          {#if item.dropdown && item.children}
            <!-- Dropdown Section -->
            {@const visibleChildren = getVisibleChildren(item.children, userPermissions)}
            {@const hasVisibleChildren = visibleChildren.length > 0}
            <div class="space-y-1 relative">
              <button 
                on:click|stopPropagation={(e) => toggleDropdown(item.label, e)}
                class="group w-full flex items-center {sidebarCollapsed ? 'justify-center' : 'justify-between'} px-4 py-3 text-sm font-medium rounded-xl transition-all duration-200 relative z-20 pointer-events-auto {hasVisibleChildren ? 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' : 'text-gray-400'} {isDropdownActive(item) ? 'bg-gray-100 text-gray-900 shadow-sm' : ''}"
                type="button"
                title={sidebarCollapsed ? item.label : ''}
              >
                {#if sidebarCollapsed}
                  <span class="text-lg">{getMenuIcon(item.label)}</span>
                {:else}
                  <span class="font-semibold">{item.label}</span>
                  <svg class="ml-auto w-5 h-5 transform transition-transform duration-200 {activeDropdown === item.label ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                {/if}
              </button>
              
              {#if activeDropdown === item.label && !sidebarCollapsed}
                <div class="ml-4 space-y-1 border-l-2 border-gray-200 pl-4" 
                     transition:slide="{{ duration: 200, easing: cubicOut }}">
                  {#each visibleChildren as child (child.label)}
                    <a 
                      href={child.route}
                      class="group flex items-center px-3 py-2 text-sm text-gray-600 rounded-lg hover:text-gray-900 hover:bg-gray-50 transition-all duration-200 {isCurrentRoute(child.route) ? 'bg-[#eb3434] text-white font-semibold shadow-sm' : ''} relative z-20 pointer-events-auto"
                      on:click={closeSidebarOnMobile}
                    >
                      <span class="truncate">{child.label}</span>
                    </a>
                  {/each}
                </div>
              {/if}
              
              <!-- Collapsed sidebar submenu dropdown -->
              <!-- Debug: {JSON.stringify({activeDropdown, itemLabel: item.label, sidebarCollapsed, hasVisibleChildren})} -->
              {#if activeDropdown === item.label && sidebarCollapsed}
                <div class="fixed bg-white border border-gray-200 rounded-lg shadow-2xl py-2 z-[99999] min-w-64 max-w-sm"
                     style="left: {sidebarCollapsed ? '80px' : '280px'}; top: {submenuTopPosition}px;"
                     data-dropdown-menu>
                  <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b border-gray-100 bg-gray-50">
                    {item.label}
                  </div>
                  {#if visibleChildren && visibleChildren.length > 0}
                    {#each visibleChildren as child (child.label)}
                      <a 
                        href={child.route}
                        class="block px-4 py-3 text-sm text-gray-700 hover:text-gray-900 hover:bg-gray-50 transition-all duration-200 {isCurrentRoute(child.route) ? 'bg-[#eb3434] text-white font-semibold' : ''}"
                        on:click={() => { closeSidebarOnMobile(); activeDropdown = null; }}
                      >
                        <span class="flex items-center">
                          <span class="text-sm mr-3">{getMenuIcon(child.label)}</span>
                          <span class="truncate">{child.label}</span>
                        </span>
                      </a>
                    {/each}
                  {:else}
                    <div class="px-4 py-3 text-sm text-gray-500">
                      Tidak ada submenu yang tersedia
                    </div>
                  {/if}
                </div>
              {/if}
            </div>
          {:else}
            <!-- Regular Menu Item -->
            <a 
              href={item.route} 
              class="group flex items-center {sidebarCollapsed ? 'justify-center' : ''} px-4 py-3 text-sm font-medium text-gray-700 rounded-xl hover:text-gray-900 hover:bg-gray-100 transition-all duration-200 {isCurrentRoute(item.route) ? 'bg-[#eb3434] text-white font-semibold shadow-sm' : ''} relative z-20 pointer-events-auto"
              on:click={closeSidebarOnMobile}
              title={sidebarCollapsed ? item.label : ''}
            >
              {#if sidebarCollapsed}
                <span class="text-lg">{getMenuIcon(item.label)}</span>
              {:else}
                <span class="truncate">{item.label}</span>
              {/if}
            </a>
          {/if}
        {/each}
      </div>
    </nav>
  </div>

  <!-- Mobile overlay -->
  {#if sidebarOpen}
    <div
      class="fixed inset-0 z-[9997] bg-gray-600 bg-opacity-75 lg:hidden"
      role="button"
      tabindex="0"
      aria-label="Tutup sidebar"
      on:click={() => sidebarOpen = false}
      on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sidebarOpen = false; } }}
    ></div>
  {/if}

  <!-- Main content -->
  <div class="flex-1 flex flex-col min-h-screen sidebar-transition {sidebarCollapsed ? 'main-content-collapsed' : 'main-content-expanded'}">
    <!-- Top bar -->
    <div class="sticky top-0 z-[9990] flex-shrink-0 flex h-16 bg-white shadow border-b border-gray-200">
      <!-- Mobile menu button -->
      <button
        type="button"
        class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-[#eb3434] lg:hidden"
        on:click={() => sidebarOpen = !sidebarOpen}
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      
      <!-- Top bar content -->
      <div class="flex-1 px-6 flex justify-between items-center">
        <div class="flex-1 flex items-center">
          <!-- Logo in navbar for mobile and when sidebar is collapsed -->
          <a href="/" class="flex items-center lg:hidden">
            <img src={getImageUrl(settings.app_logo, "/images/logo-ekspedisi-quran.webp")} alt="Ekspedisi Qur'an Logo" class="h-10 w-auto" />
          </a>
        </div>
        
        <!-- User menu -->
        <div class="ml-4 flex items-center">
          <div class="relative">
            <button 
              on:click={() => showUserMenu = !showUserMenu}
              class="flex items-center space-x-3 hover:bg-gray-50 rounded-xl p-3 transition-colors"
            >
              <div class="w-10 h-10 bg-[#eb3434] rounded-full flex items-center justify-center">
                <span class="text-sm font-semibold text-white">
                  {currentUser.name?.charAt(0) || 'A'}
                </span>
              </div>
              <div class="text-sm text-left hidden lg:block">
                <p class="font-semibold text-gray-900">{currentUser.name || 'Admin'}</p>
                <p class="text-gray-500">{primaryRoleDisplay}</p>
              </div>
              <svg class="w-4 h-4 text-gray-400 hidden lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            {#if showUserMenu}
              <div class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg ring-1 ring-black ring-opacity-5 z-[9999]">
                <div class="p-1">
                  <button
                    on:click={logout}
                    class="flex items-center w-full px-4 py-3 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  >
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                  </button>
                </div>
              </div>
            {/if}
          </div>
        </div>
      </div>
    </div>

    <!-- Main content area -->
    <main class="flex-1 bg-gray-50">
      <div class="px-6 py-8">
        <slot />
      </div>
    </main>
  </div>
</div>

<!-- Flash Message Handler (Global) -->
<FlashMessage />

<!-- Toast Notifications -->
<ToastContainer />

<!-- Dialog Container -->
<DialogContainer />

<!-- Job Progress Indicator -->
<JobProgressIndicator showNotifications={true} isAuthenticated={!!currentUser.id} />

<style>
  .font-cairo {
    font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
  }
  
  :global(body) {
    font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
  }
  
  /* Ensure smooth sidebar collapse transition */
  .sidebar-transition {
    transition: width 300ms ease-in-out, margin-left 300ms ease-in-out;
  }
  
  /* Ensure proper layout on different screen sizes */
  @media (min-width: 1024px) {
    .main-content-collapsed {
      margin-left: 5rem; /* 80px / 16px = 5rem */
    }
    
    .main-content-expanded {
      margin-left: 18rem; /* 288px / 16px = 18rem */
    }
  }
</style>
