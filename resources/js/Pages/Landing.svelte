<script>
  import { onMount } from 'svelte';
  import { inertia, router } from '@inertiajs/svelte';
  import PublicLayout from '@/Layouts/PublicLayout.svelte';
  import GallerySection from '@/Components/GallerySection.svelte';
  import VideoSection from '@/Components/VideoSection.svelte';
  import { formatPageTitle, pageTitles, generateMetaDescription } from '@/utils/seo.js';
  
  // Add missing props declarations
  export const errors = {};
  export const auth = {};
  export const flash = {};
  export let mapData = []; // Add mapData prop for distribution visualization
  export let stats = {}; // Dynamic statistics data
  export let settings = {}; // Settings from admin panel
  export let testimonials = []; // Testimonials from database
  export let galleries = []; // Gallery images from database
  export let videos = []; // Videos from database
  export const activities = []; // Activities prop that's being passed but not used
  export const chartsData = {}; // Charts data prop that's being passed but not used
  
  export let faqs = []; // FAQs from database
  export let sectionOrder = []; // Section order from admin panel

  // Build a map for quick lookup
  $: enabledSections = Array.isArray(sectionOrder)
    ? sectionOrder.filter(s => s.enabled)
    : [];

  // SEO data
  const pageTitle = formatPageTitle(pageTitles.home);
  const metaDescription = generateMetaDescription('home');

  // Debug: Log settings when component mounts
  onMount(() => {
    console.log('Landing page settings:', settings);
    console.log('landing_donation_link value:', settings.landing_donation_link);
  });

  // Event handlers for buttons
  // Event handlers for buttons with enhanced debugging
  function handleTrackingClick(event) {
    console.log("Tracking button clicked - Event details:", event);
    console.log("Event target:", event.target);
    console.log("Current target:", event.currentTarget);
    console.log("Navigating to /tracking...");
    
    try {
      router.visit("/tracking");
      console.log("Navigation initiated successfully");
    } catch (error) {
      console.error("Navigation error:", error);
    }
  }

  function handleDonationClick(event) {
    console.log("Donation button clicked - Event details:", event);
    console.log("Event target:", event.target);
    console.log("Current target:", event.currentTarget);
    console.log("Opening donation link...");

    try {
      // Get donation link from settings (admin-configurable)
      const donationUrl = settings.landing_donation_link || "https://alfatihah.com/campaign/ayo-bantu-maksimalkan-aktivitas-belajar-mengaji-dan-kirimkan-quran-ke-seluruh-penjuru-negeri";

      // Check if it's an internal link or external
      if (donationUrl.startsWith('/')) {
        // Internal link - use Inertia router
        router.visit(donationUrl);
      } else {
        // External link - open in new tab
        window.open(donationUrl, "_blank");
      }
      console.log("Donation link opened successfully:", donationUrl);
    } catch (error) {
      console.error("Error opening donation link:", error);
    }
  }  
  // Number formatting function
  function formatNumber(number) {
    if (!number) return '0';
    return number.toLocaleString('id-ID');
  }
  
  // Helper to get correct file URL
  function getFileUrl(value) {
    if (!value) return null;
    if (value.startsWith('http')) return value;
    // Files in public/images/ should be accessed directly
    if (value.startsWith('/images/')) {
      return value;
    }
    // Files starting with /storage/ are already correct
    if (value.startsWith('/storage/')) {
      return value;
    }
    // For other paths starting with /, prepend /storage
    if (value.startsWith('/')) {
      return `/storage${value}`;
    }
    // For relative paths, prepend /storage/
    return `/storage/${value}`;
  }
  
  let resiInput = '';
  let currentTestimonial = 0;
  let currentGalleryIndex = 0;
  let isGalleryPlaying = true;
  let openFaqIndex = null;

  // About section video lightbox state
  let showAboutVideoLightbox = false;
  // Default video URL - used when admin hasn't set a custom URL
  const DEFAULT_ABOUT_VIDEO_URL = 'https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP';
  let aboutVideoUrl = settings.landing_about_video_url || DEFAULT_ABOUT_VIDEO_URL;

  // Extract YouTube video ID from URL
  function getYoutubeVideoId(url) {
    if (!url) return null;
    const shortMatch = url.match(/youtu\.be\/([^?]+)/);
    if (shortMatch) return shortMatch[1];
    const watchMatch = url.match(/[?&]v=([^&]+)/);
    if (watchMatch) return watchMatch[1];
    const embedMatch = url.match(/embed\/([^?]+)/);
    if (embedMatch) return embedMatch[1];
    return null;
  }

  // Get YouTube thumbnail URL
  function getYoutubeThumbnailUrl(url) {
    const videoId = getYoutubeVideoId(url);
    return videoId ? `https://img.youtube.com/vi/${videoId}/hqdefault.jpg` : null;
  }

  // Get YouTube embed URL
  function getYoutubeEmbedUrl(url) {
    const videoId = getYoutubeVideoId(url);
    return videoId ? `https://www.youtube.com/embed/${videoId}?autoplay=1` : null;
  }

  // Open about video lightbox
  function openAboutVideoLightbox() {
    if (!aboutVideoUrl) return;
    showAboutVideoLightbox = true;
    document.body.style.overflow = 'hidden';
  }

  // Close about video lightbox
  function closeAboutVideoLightbox() {
    showAboutVideoLightbox = false;
    document.body.style.overflow = '';
  }

  // Map variables
  let map;
  let isMapInitialized = false;
  let provinceLayer;
  let markerLayer;
  let L = null; // Will hold Leaflet module
  
  // Use testimonials from database props directly
  
  // Use galleries and faqs from database props directly
  
  // Add CSS for location markers
  const markerStyle = `
    <style>
      .location-marker {
        transition: transform 0.2s, filter 0.2s;
      }
      
      .location-marker:hover {
        transform: scale(1.2) !important;
        filter: brightness(1.1) drop-shadow(0 4px 12px rgba(235, 52, 52, 0.4));
        z-index: 1000;
      }
      
      .location-marker div {
        cursor: pointer;
      }
    </style>
  `;
  
  // Inject styles
  if (typeof document !== 'undefined') {
    const styleElement = document.createElement('div');
    styleElement.innerHTML = markerStyle;
    document.head.appendChild(styleElement.firstElementChild);
  }
  
  // Fungsi untuk mendapatkan warna berdasarkan jumlah distribusi
  function getColorByDistributionDensity(total) {
    return total > 1000 ? '#800026' :
           total > 500  ? '#BD0026' :
           total > 200  ? '#E31A1C' :
           total > 100  ? '#FC4E2A' :
           total > 50   ? '#FD8D3C' :
           total > 20   ? '#FEB24C' :
           total > 10   ? '#FED976' :
                          '#FFEDA0';
  }
  
  onMount(async () => {
    // Load Leaflet dynamically and initialize the map
    try {
      // Import Leaflet CSS first
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
      link.crossOrigin = '';
      document.head.appendChild(link);
      
      // Wait for CSS to load
      await new Promise((resolve) => {
        if (link.sheet) {
          resolve();
        } else {
          link.onload = resolve;
        }
      });
      
      // Import Leaflet module
      const leafletModule = await import('leaflet');
      
      // Handle different module structures
      if (leafletModule.default) {
        L = leafletModule.default;
      } else if (leafletModule.L) {
        L = leafletModule.L;
      } else {
        L = leafletModule;
      }
      
      // Verify L is properly loaded
      if (!L || typeof L.map !== 'function') {
        throw new Error('Leaflet failed to load properly');
      }
      
      // Wait for DOM to be ready and container to exist
      const waitForContainer = () => {
        return new Promise((resolve) => {
          const checkContainer = () => {
            const container = document.getElementById('distribution-map');
            if (container && container.offsetParent !== null) {
              resolve();
            } else {
              setTimeout(checkContainer, 50);
            }
          };
          checkContainer();
        });
      };
      
      await waitForContainer();
      initializeMap();
      
    } catch (error) {
      console.error('Failed to load Leaflet:', error);
      // Show error in the map container
      const mapContainer = document.getElementById('distribution-map');
      if (mapContainer) {
        mapContainer.innerHTML = `
          <div class="flex items-center justify-center h-full">
            <div class="text-center text-red-600">
              <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="font-medium">Error loading map</p>
              <p class="text-sm">${error.message}</p>
            </div>
          </div>
        `;
      }
    }
  
  
  
    // Auto-rotate testimonials and gallery
    const testimonialInterval = setInterval(() => {
      currentTestimonial = (currentTestimonial + 1) % testimonials.length;
    }, 5000);

    const galleryInterval = setInterval(() => {
      if (isGalleryPlaying) {
        currentGalleryIndex = (currentGalleryIndex + 1) % galleries.length;
      }
    }, 4000);
    
    // Smooth scrolling for internal links
    const smoothScrollLinks = document.querySelectorAll('.scroll-smooth-link');
    smoothScrollLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = link.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
          const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
          window.scrollTo({
            top: offsetTop,
            behavior: 'smooth'
          });
        }
      });
    });
    
    return () => {
      clearInterval(testimonialInterval);
      clearInterval(galleryInterval);
      if (map) {
        map.remove();
      }
    };
  });
  
  // Fungsi untuk menambahkan legenda distribusi
  function addDistributionLegend() {
    // Buat elemen legenda di luar peta
    const mapContainer = document.getElementById('distribution-map');
    const legendElement = document.createElement('div');
    legendElement.className = 'map-legend bg-white p-3 rounded-lg shadow-md mt-3 border border-gray-200';
    
    // Buat konten legenda dalam satu baris
    const legendContent = document.createElement('div');
    legendContent.className = 'flex items-center flex-wrap gap-x-3 gap-y-2';
    
    // Judul legenda
    const legendTitle = document.createElement('div');
    legendTitle.className = 'font-semibold text-sm mr-2';
    legendTitle.textContent = 'Distribusi Al-Qur\'an (Selesai):';
    legendContent.appendChild(legendTitle);
    
    // Array untuk level distribusi
    const grades = [
      { range: '0–10', color: '#FFEDA0' },
      { range: '10–20', color: '#FED976' },
      { range: '20–50', color: '#FEB24C' },
      { range: '50–100', color: '#FD8D3C' },
      { range: '100–200', color: '#FC4E2A' },
      { range: '200–500', color: '#E31A1C' },
      { range: '500–1000', color: '#BD0026' },
      { range: '1000+', color: '#800026' }
    ];
    
    // Tambahkan setiap item legenda dalam satu baris
    grades.forEach(grade => {
      const legendItem = document.createElement('div');
      legendItem.className = 'flex items-center text-xs';
      
      const colorBox = document.createElement('span');
      colorBox.className = 'inline-block mr-1';
      colorBox.style.backgroundColor = grade.color;
      colorBox.style.width = '16px';
      colorBox.style.height = '16px';
      
      const label = document.createElement('span');
      label.textContent = `${grade.range} unit`;
      
      legendItem.appendChild(colorBox);
      legendItem.appendChild(label);
      legendContent.appendChild(legendItem);
    });
    
    legendElement.appendChild(legendContent);
    
    // Temukan container map-container
    const mapContainerParent = mapContainer.parentElement.parentElement;
    
    // Tambahkan legenda setelah container peta
    mapContainerParent.appendChild(legendElement);
  }

  function initializeMap() {
    if (typeof window === 'undefined' || isMapInitialized) return;
    
    // Make sure Leaflet is available and properly loaded
    if (!L || typeof L.map !== 'function') {
      console.error('Leaflet is not properly loaded');
      return;
    }
    
    // Get the map container and ensure it exists
    const mapContainer = document.getElementById('distribution-map');
    if (!mapContainer) {
      console.error('Map container not found');
      return;
    }
    
    // Clear any existing map instance
    if (map) {
      try {
        map.remove();
      } catch (e) {
        console.warn('Error removing existing map:', e);
      }
      map = null;
    }
    
    try {
      // Create the map centered on Indonesia with appropriate bounds
      map = L.map('distribution-map', {
        center: [-2.5, 118], // Pusat Indonesia
        zoom: 5,
        minZoom: 5, // Limit zoom out to show only Indonesia
        maxZoom: 15, // Allow more zoom in for detail
        maxBounds: [
          [-15, 90], // Southwest corner (diperluas untuk mencegah user bisa scroll terlalu jauh)
          [10, 145]   // Northeast corner (diperluas untuk mencegah user bisa scroll terlalu jauh)
        ],
        maxBoundsViscosity: 1.0, // Maksimal "kekuatan" dari maxBounds, mencegah user sama sekali untuk keluar dari batas
        scrollWheelZoom: false,   // Disable scroll wheel zoom for home page
        dragging: true,
        tap: true,
        zoomControl: true        // Enable default zoom control
      }).setView([-2.5, 118], 5);
    
    // Hide loading indicator once map is initialized
    const mapContainerElement = document.getElementById('distribution-map');
    const loadingIndicator = mapContainerElement?.querySelector('.absolute.inset-0');
    if (loadingIndicator) {
      loadingIndicator.style.display = 'none';
    }
    
    // Add tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 18
    }).addTo(map);
    
    // Gunakan data pengiriman 'diterima'; jika fallback server mengirim 'completed', tetap tampilkan
    let completedData = Array.isArray(mapData)
      ? mapData.filter(item => item.status === 'diterima' || item.status === 'completed')
      : [];
    
    // If no valid data, add dummy data for demonstration
    if (completedData.length === 0) {
      completedData = [
        {
          id: 'dummy1',
          nama_lembaga: 'Masjid Al-Hikmah (Demo)',
          provinsi: 'Jawa Barat',
          kota_kabupaten: 'Bandung',
          lat: -6.9175,
          lng: 107.6191,
          status: 'completed',
          jumlah_mushaf: 50,
          nama_penerima: 'Masjid Al-Hikmah'
        },
        {
          id: 'dummy2',
          nama_lembaga: 'Pesantren Al-Falah (Demo)',
          provinsi: 'Jawa Tengah',
          kota_kabupaten: 'Semarang',
          lat: -7.0051,
          lng: 110.4381,
          status: 'completed',
          jumlah_mushaf: 100,
          nama_penerima: 'Pesantren Al-Falah'
        },
        {
          id: 'dummy3',
          nama_lembaga: 'Masjid Baiturrahman (Demo)',
          provinsi: 'Aceh',
          kota_kabupaten: 'Banda Aceh',
          lat: 5.5483,
          lng: 95.3238,
          status: 'completed',
          jumlah_mushaf: 75,
          nama_penerima: 'Masjid Baiturrahman'
        },
        {
          id: 'dummy4',
          nama_lembaga: 'Masjid Istiqlal (Demo)',
          provinsi: 'DKI Jakarta',
          kota_kabupaten: 'Jakarta Pusat',
          lat: -6.1702,
          lng: 106.8311,
          status: 'completed',
          jumlah_mushaf: 200,
          nama_penerima: 'Masjid Istiqlal'
        },
        {
          id: 'dummy5',
          nama_lembaga: 'Masjid Raya Surabaya (Demo)',
          provinsi: 'Jawa Timur',
          kota_kabupaten: 'Surabaya',
          lat: -7.2575,
          lng: 112.7521,
          status: 'completed',
          jumlah_mushaf: 150,
          nama_penerima: 'Masjid Raya Surabaya'
        }
      ];
    }
    
    // Calculate total distribution by province
    const distributionByProvince = {};
    completedData.forEach(item => {
      const province = item.provinsi;
      if (!distributionByProvince[province]) {
        distributionByProvince[province] = 0;
      }
      distributionByProvince[province] += item.jumlah_mushaf;
    });
    
    // Load GeoJSON provinsi Indonesia
    fetch('/data/geojson/indonesia-province-simple.json')
      .then(response => {
        if (!response.ok) {
          throw new Error('GeoJSON file could not be loaded');
        }
        return response.json();
      })
      .then(data => {
        // Log untuk debugging
        
        // Tambahkan layer GeoJSON dengan warna berdasarkan distribusi
        provinceLayer = L.geoJSON(data, {
          style: function(feature) {
            // Ambil nama provinsi dari GeoJSON
            // Perhatikan: perlu menyesuaikan properti sesuai dengan struktur file GeoJSON Anda
            const provinceName = feature.properties.name || feature.properties.provinsi || feature.properties.NAME || feature.properties.Propinsi;
            
            // Dapatkan total distribusi untuk provinsi ini
            const totalDistribution = distributionByProvince[provinceName] || 0;
            
            return {
              fillColor: getColorByDistributionDensity(totalDistribution),
              weight: 1,
              opacity: 1,
              color: 'white',
              dashArray: '',
              fillOpacity: 0.7
            };
          },
          onEachFeature: function(feature, layer) {
            const provinceName = feature.properties.name || feature.properties.provinsi || feature.properties.NAME || feature.properties.Propinsi;
            const totalDistribution = distributionByProvince[provinceName] || 0;
            
            // Calculate additional stats for this province
            const provinceData = completedData.filter(item => item.provinsi === provinceName);
            const totalLembaga = new Set(provinceData.map(item => item.nama_penerima)).size;
            const totalShipments = provinceData.length;
            
            // Add hover tooltip (keep the original hover functionality)
            const tooltipContent = `
              <div class="province-tooltip">
                <h4 class="font-semibold text-sm mb-2">${provinceName}</h4>
                <div class="text-xs space-y-1">
                  <div>📚 Total Mushaf: <b>${totalDistribution.toLocaleString('id-ID')}</b></div>
                  <div>🏢 Jumlah Lembaga: <b>${totalLembaga}</b></div>
                  
                </div>
              </div>
            `;
            
            // Smart tooltip positioning based on province location
            const bounds = layer.getBounds();
            const center = bounds.getCenter();
            let direction = 'top';
            let offset = [0, -10];
            
            // Check if province is in northern part of Indonesia (like Aceh)
            if (center.lat > 0) {
              direction = 'bottom';
              offset = [0, 10];
            }
            // Check if province is on the eastern edge
            else if (center.lng > 130) {
              direction = 'left';
              offset = [-10, 0];
            }
            // Check if province is on the western edge
            else if (center.lng < 100) {
              direction = 'right';
              offset = [10, 0];
            }
            
            layer.bindTooltip(tooltipContent, {
              permanent: false,
              direction: direction,
              offset: offset,
              className: 'custom-tooltip'
            });
            
            // Add hover effect (keep the original red border hover)
            layer.on({
              mouseover: function(e) {
                layer.setStyle({
                  weight: 2,
                  color: '#eb3434', // Use project's red color
                  dashArray: '',
                  fillOpacity: 0.9
                });
                
                if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                  layer.bringToFront();
                }
              },
              mouseout: function(e) {
                provinceLayer.resetStyle(layer);
              },
              // Keep zoom functionality but prevent blue border
              click: function(e) {
                map.fitBounds(layer.getBounds());
                layer.setStyle({
                  weight: 1,
                  color: 'white',
                  dashArray: '',
                  fillOpacity: 0.7
                });
              }
            });
          }
        }).addTo(map);
        
        // Tambahkan legenda untuk distribusi di luar peta
        addDistributionLegend();
      })
      .catch(error => {
        console.error('Error loading GeoJSON:', error);
        // Fallback: show distribution legend only
        addDistributionLegend();
      });
      
      isMapInitialized = true;
      
    } catch (error) {
      console.error('Error initializing map:', error);
      
      // Show error in the map container
      const mapContainer = document.getElementById('distribution-map');
      if (mapContainer) {
        const loadingIndicator = mapContainer.querySelector('.absolute.inset-0');
        if (loadingIndicator) {
          loadingIndicator.innerHTML = `
            <div class="flex items-center justify-center h-full">
              <div class="text-center text-red-600">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-medium">Error initializing map</p>
                <p class="text-sm">${error.message}</p>
              </div>
            </div>
          `;
        }
      }
      
      // Still show distribution legend even if map fails
      try {
        addDistributionLegend();
      } catch (legendError) {
        console.error('Error adding distribution legend:', legendError);
      }
    }
  }
  
  function checkResi() {
    if (resiInput.trim()) {
      window.location.href = `/tracking/${resiInput.trim()}`;
    }
  }
  

  function setGalleryIndex(index) {
    currentGalleryIndex = index;
    isGalleryPlaying = false;
    setTimeout(() => { isGalleryPlaying = true; }, 10000);
  }

  function toggleFaq(index) {
    openFaqIndex = openFaqIndex === index ? null : index;
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

<PublicLayout>
  <div class="font-cairo">
    {#each enabledSections as section}
      {#if section.id === 'hero'}
        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-white to-red-50 overflow-hidden">
          <!-- Islamic Pattern Background -->
          <div class="absolute inset-0 opacity-30 islamic-geometric z-0"></div>

          <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24">

            <!-- Main Content Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center min-h-[80vh]">

              <!-- Image Section - Appears FIRST on mobile -->
              <div class="relative order-1 lg:order-2 mb-8 lg:mb-0">
                <!-- Background Gradient -->
                <div class="absolute inset-0 bg-gradient-to-br from-[#eb3434]/20 to-[#eb3434]/5 rounded-2xl"></div>

                <!-- Main Hero Image -->
                <img
                  src={getFileUrl(settings.landing_hero_image || "/images/hero-ekspedisi-quran.webp")}
                  alt="{settings.landing_title || 'Ekspedisi Qur\'an'} - {settings.landing_description || 'Menyebarkan kebaikan ke pelosok Indonesia'}"
                  class="w-full h-auto aspect-[4/4] sm:aspect-[16/10] lg:aspect-[4/4] object-cover rounded-2xl shadow-2xl relative z-0"
                  loading="lazy"
                />

                <!-- Floating Stats Card -->
                {#if settings.landing_stats_enabled === '1'}
                <div class="absolute -bottom-4 -left-4 sm:-bottom-6 sm:-left-6 bg-white p-3 sm:p-6 rounded-xl shadow-lg border border-gray-100 z-0">
                  <div class="text-xl sm:text-2xl font-bold text-[#eb3434] mb-1">{formatNumber(stats.totalMushaf || 0)}</div>
                  <div class="text-xs sm:text-sm text-gray-600">Mushaf Tersalurkan</div>
                </div>
                {/if}

                <!-- Floating Achievement Badge -->
                <div class="absolute -top-4 -right-4 sm:-top-6 sm:-right-6 bg-[#eb3434] text-white p-3 sm:p-4 rounded-full shadow-lg z-0">
                  <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                </div>
              </div>

              <!-- Content Section - Appears SECOND on mobile -->
              <div class="text-center lg:text-left order-2 lg:order-1">
                <!-- Main Headline -->
                <div class="mb-6 lg:mb-8">
                  <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold text-gray-900 mb-4 lg:mb-6 leading-tight">
                    <span class="block text-[#eb3434]">{settings.landing_title || 'Ekspedisi Qur\'an'}</span>
                    <span class="block">{settings.landing_subtitle || 'Distribusi Al-Quran untuk Seluruh Indonesia'}</span>
                  </h1>
                  <p class="text-base sm:text-lg md:text-xl text-gray-600 leading-relaxed max-w-lg mx-auto lg:mx-0">
                    {settings.landing_description || 'Platform distribusi Al-Qur\'an yang menghubungkan donatur dengan lembaga-lembaga pendidikan Islam di seluruh Indonesia. Bergabunglah dalam misi mulia menyebarkan Al-Qur\'an ke pelosok nusantara.'}
                  </p>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 lg:gap-4 justify-center lg:justify-start hero-buttons">
                  <button
                    on:click={handleTrackingClick}
                    type="button"
                    class="px-6 sm:px-8 py-3 sm:py-4 bg-[#eb3434] text-white font-semibold rounded-lg shadow-lg hover:bg-red-600 transform hover:scale-105 transition-all duration-300 text-sm sm:text-base cursor-pointer"
                  >
                    Cek Resi Pengiriman
                  </button>
                  <button
                    on:click={handleDonationClick}
                    type="button"
                    class="px-6 sm:px-8 py-3 sm:py-4 border-2 border-[#eb3434] text-[#eb3434] font-semibold rounded-lg hover:bg-[#eb3434] hover:text-white transform hover:scale-105 transition-all duration-300 text-sm sm:text-base cursor-pointer"
                  >
                    Donasi Sekarang
                  </button>
                </div>
              </div>

            </div>
          </div>
        </section>
      {:else if section.id === 'about'}
        <!-- Tentang Program -->
        <section id="tentang" class="py-20 bg-white relative" style="z-index: 1;">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-gray-900 mb-6">Tentang Program {settings.landing_title || 'Ekspedisi Qur\'an'}</h2>
              <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                {settings.landing_description || 'Program mulia untuk menyalurkan mushaf Al-Quran ke seluruh pelosok Nusantara, khususnya daerah-daerah yang membutuhkan dan sulit dijangkau.'}
              </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
              <div class="space-y-6">
                <div class="flex items-start space-x-4">
                  <div class="flex-shrink-0 w-12 h-12 bg-[#eb3434] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Mushaf Berkualitas</h3>
                    <p class="text-gray-600">Setiap mushaf dipilih dengan standar kualitas tinggi dan mudah dibaca untuk semua kalangan.</p>
                  </div>
                </div>

                <div class="flex items-start space-x-4">
                  <div class="flex-shrink-0 w-12 h-12 bg-[#eb3434] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Jangkauan Luas</h3>
                    <p class="text-gray-600">Menjangkau seluruh Nusantara hingga ke pelosok yang sulit diakses transportasi umum.</p>
                  </div>
                </div>

                <div class="flex items-start space-x-4">
                  <div class="flex-shrink-0 w-12 h-12 bg-[#eb3434] rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Transparan & Terpercaya</h3>
                    <p class="text-gray-600">Sistem tracking real-time dan sertifikat kontribusi untuk transparansi penuh.</p>
                  </div>
                </div>
              </div>

              <div class="relative max-w-md mx-auto lg:max-w-none">
                <!-- Video Thumbnail with Pulsing Play Button (Default or Custom URL) -->
                <button
                  type="button"
                  class="block w-full text-left relative group cursor-pointer"
                  on:click={openAboutVideoLightbox}
                  aria-label="Play video tentang program"
                >
                  <div class="relative rounded-2xl shadow-2xl overflow-hidden bg-gray-900" style="aspect-ratio: 16/9;">
                    <img
                      src={getYoutubeThumbnailUrl(aboutVideoUrl)}
                      alt="Tentang Program Ekspedisi Qur'an"
                      class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                      loading="lazy"
                    />
                    <!-- Dark Overlay on Hover -->
                    <div class="absolute inset-0 bg-black/20 group-hover:bg-black/30 transition-colors duration-300"></div>

                    <!-- Pulsing Play Button -->
                    <div class="absolute inset-0 flex items-center justify-center">
                      <!-- Pulse rings -->
                      <div class="absolute w-24 h-24 bg-white/30 rounded-full animate-ping"></div>
                      <div class="absolute w-20 h-20 bg-white/20 rounded-full animate-pulse"></div>
                      <!-- Play button -->
                      <div class="relative w-20 h-20 bg-[#eb3434] rounded-full flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-10 h-10 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M8 5v14l11-7z"/>
                        </svg>
                      </div>
                    </div>
                  </div>
                </button>

                <div class="absolute z-20 -bottom-6 -left-6 bg-[#eb3434] text-white p-6 rounded-xl shadow-lg pointer-events-none">
                  <div class="text-2xl font-bold">100%</div>
                  <div class="text-sm">Amanah & Transparan</div>
                </div>
              </div>
            </div>
          </div>
        </section>
      {:else if section.id === 'map'}
        <!-- Peta Penyebaran Distribusi Al-Qur'an -->
        {#if settings.landing_map_enabled === '1'}
        <section id="peta-distribusi" class="py-20 bg-gray-50 relative" style="z-index: 10;">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-gray-900 mb-6">Peta Penyebaran Distribusi Al-Qur'an</h2>
              <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Lihat sebaran distribusi mushaf Al-Qur'an dari program Ekspedisi Qur'an ke seluruh pelosok Nusantara.
              </p>
            </div>

            <!-- Map Container with Beautiful Frame -->
            <div class="p-1 bg-gradient-to-r from-[#eb3434] to-[#f59e0b] rounded-lg shadow-lg hover:shadow-xl transition-all duration-300">
              <div class="bg-white p-4 rounded-lg">
                <!-- Info Card for Map -->
                <div class="bg-blue-50 p-3 rounded-lg mb-3 text-sm text-gray-600 border-l-4 border-blue-500">
                  <p>Visualisasi distribusi Al-Qur'an yang telah selesai. Warna pada peta menunjukkan intensitas distribusi, semakin gelap warna menandakan semakin tinggi jumlah Al-Qur'an yang telah disalurkan. Klik pada provinsi untuk melihat detail.</p>
                </div>

                <!-- Map Container -->
                <div id="distribution-map" class="w-full h-[400px] rounded-lg border border-gray-200 bg-gray-100 relative overflow-hidden">
                  <!-- Loading indicator -->
                  <div class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 rounded-lg z-10">
                    <div class="text-center">
                      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[#eb3434]"></div>
                      <p class="mt-4 text-gray-600">Memuat peta distribusi...</p>
                    </div>
                  </div>
                </div>

                <!-- Legend will be added here by JavaScript -->
              </div>
            </div>

            <!-- Distribution Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-10">
              <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 border-t-4 border-[#eb3434]">
                <div class="text-4xl font-bold text-[#eb3434] mb-2">{formatNumber(stats.provinces || 0)}</div>
                <div class="text-gray-600 text-lg">Provinsi Terjangkau</div>
              </div>

              <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 border-t-4 border-[#eb3434]">
                <div class="text-4xl font-bold text-[#eb3434] mb-2">{formatNumber(stats.cities || 0)}</div>
                <div class="text-gray-600 text-lg">Kota/Kabupaten</div>
              </div>

              <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 border-t-4 border-[#eb3434]">
                <div class="text-4xl font-bold text-[#eb3434] mb-2">{formatNumber(stats.institutions || 0)}</div>
                <div class="text-gray-600 text-lg">Lembaga Penerima</div>
              </div>

              <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 border-t-4 border-[#eb3434]">
                <div class="text-4xl font-bold text-[#eb3434] mb-2">{formatNumber(stats.totalMushaf || 0)}</div>
                <div class="text-gray-600 text-lg">Mushaf Tersalurkan</div>
              </div>
            </div>
          </div>
        </section>
        {/if}
      {:else if section.id === 'tracking'}
        <!-- Cek Resi -->
        <section id="cek-resi" class="py-20 bg-white">
          <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-gray-900 mb-6">Cek Status Pengiriman</h2>
              <p class="text-xl text-gray-600">Lacak perjalanan mushaf Al-Quran Anda dengan mudah dan real-time</p>
            </div>

            <div class="max-w-2xl mx-auto">
              <!-- Cek Resi -->
              <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow duration-300">
                <div class="text-center mb-6">
                  <div class="w-16 h-16 bg-[#eb3434] rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                  </div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-3">Pantau Status Pengiriman</h3>
                  <p class="text-gray-600 text-lg">Masukkan nomor resi Anda untuk melihat status terkini perjalanan mushaf Al-Quran</p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                  <input
                    type="text"
                    bind:value={resiInput}
                    placeholder="Masukkan nomor resi (EQ-20250525-0001)"
                    class="flex-1 px-4 py-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent text-center sm:text-left"
                  />
                  <button
                    on:click={checkResi}
                    class="px-8 py-4 bg-[#eb3434] text-white font-semibold rounded-lg hover:bg-red-600 transition-colors duration-300 whitespace-nowrap"
                  >
                    Cek Sekarang
                  </button>
                </div>

                <div class="mt-6 text-center">
                  <p class="text-sm text-gray-500">💡 Tip: Nomor resi dapat ditemukan di email konfirmasi atau SMS yang Anda terima</p>
                  {#if settings.contact_email}
                  <p class="text-sm text-gray-500 mt-2">📧 Butuh bantuan? Hubungi: <a href="mailto:{settings.contact_email}" class="text-[#eb3434] hover:underline">{settings.contact_email}</a></p>
                  {/if}
                </div>
              </div>
            </div>
          </div>
        </section>
      {:else if section.id === 'gallery'}
        <!-- Galeri Ekspedisi -->
        <GallerySection {settings} {galleries} />
      {:else if section.id === 'video'}
        <!-- Video Ekspedisi -->
        <VideoSection {settings} {videos} />
      {:else if section.id === 'testimonials'}
        <!-- Testimoni & Cerita Penerima -->
        {#if settings.landing_testimonials_enabled === '1' && testimonials.length > 0}
        <section class="py-20 bg-white">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-gray-900 mb-6">Testimoni & Cerita Penerima</h2>
              <p class="text-xl text-gray-600">Suara-suara dari mereka yang telah merasakan manfaat program Ekspedisi Qur'an</p>
            </div>

            <div class="relative">
              <!-- Testimonial Card -->
              <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12 max-w-4xl mx-auto">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-8">
                  <div class="flex-shrink-0">
                    <img
                      src={testimonials[currentTestimonial]?.avatar_url || testimonials[currentTestimonial]?.image_url}
                      alt={testimonials[currentTestimonial]?.name}
                      class="w-24 h-24 rounded-full object-cover border-4 border-[#eb3434]"
                      loading="lazy"
                    />
                  </div>
                  <div class="flex-1 text-center md:text-left">
                    <div class="text-[#eb3434] mb-4">
                      <svg class="w-8 h-8 mx-auto md:mx-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h4v10h-10z"/>
                      </svg>
                    </div>
                    <blockquote class="text-lg md:text-xl text-gray-700 mb-6 leading-relaxed">
                      "{testimonials[currentTestimonial]?.quote}"
                    </blockquote>
                    <div>
                      <cite class="text-lg font-semibold text-gray-900 not-italic">
                        {testimonials[currentTestimonial]?.name}
                      </cite>
                      <p class="text-[#eb3434] font-medium">
                        {testimonials[currentTestimonial]?.location}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Testimonial Navigation -->
              <div class="flex justify-center mt-8 space-x-2">
                {#each testimonials as _, index}
                  <button
                    on:click={() => currentTestimonial = index}
                    class="w-3 h-3 rounded-full transition-all duration-300 {index === currentTestimonial ? 'bg-[#eb3434] scale-125' : 'bg-gray-300 hover:bg-gray-400'}"
                  ></button>
                {/each}
              </div>
            </div>
          </div>
        </section>
        {/if}
      {:else if section.id === 'faq'}
        <!-- FAQ Section -->
        {#if settings.landing_faqs_enabled === '1' && faqs.length > 0}
        <section class="py-20 bg-gray-50">
          <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
              <h2 class="text-4xl font-bold text-gray-900 mb-6">Pertanyaan yang Sering Diajukan</h2>
              <p class="text-xl text-gray-600">Temukan jawaban atas pertanyaan umum tentang program Ekspedisi Qur'an</p>
            </div>

            <div class="space-y-4">
              {#each faqs as faq, index}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                  <button
                    on:click={() => toggleFaq(index)}
                    class="w-full px-6 py-4 text-left flex justify-between items-center hover:bg-gray-50 transition-colors duration-200"
                  >
                    <span class="font-semibold text-gray-900">{faq.question}</span>
                    <svg
                      class="w-5 h-5 text-gray-500 transform transition-transform duration-200 {openFaqIndex === index ? 'rotate-45' : ''}"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                  </button>
                  {#if openFaqIndex === index}
                    <div class="px-6 pb-4">
                      <p class="text-gray-600 leading-relaxed">{faq.answer}</p>
                    </div>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
        </section>
        {/if}
      {:else if section.id === 'cta'}
        <!-- Call to Action -->
        <section id="donasi" class="py-20 bg-[#eb3434] text-white">
          <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold mb-6">Mari Bersama Menyebarkan Kebaikan</h2>
            <p class="text-xl mb-8 opacity-90">
              Setiap donasi Anda akan menjadi investasi akhirat yang tak ternilai.
              Bergabunglah dengan ribuan orang baik lainnya dalam misi mulia ini.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
              <button
                on:click={handleDonationClick}
                type="button"
                class="inline-block px-8 py-4 bg-white text-[#eb3434] font-semibold rounded-lg hover:bg-gray-100 transform hover:scale-105 transition-all duration-300 text-center cursor-pointer"
              >
                Mulai Berdonasi
              </button>
              <button
                on:click={() => document.getElementById('tentang').scrollIntoView({ behavior: 'smooth' })}
                type="button"
                class="px-8 py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-[#eb3434] transform hover:scale-105 transition-all duration-300"
              >
                Pelajari Lebih Lanjut
              </button>
            </div>
          </div>
        </section>
      {/if}
    {/each}
  </div>

  <!-- About Section Video Lightbox -->
  {#if showAboutVideoLightbox && aboutVideoUrl}
    <button
      type="button"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/95 w-full h-full"
      aria-label="Close video"
      on:click={closeAboutVideoLightbox}
    >
      <div
        class="relative w-full max-w-5xl mx-4"
        role="presentation"
        on:click|stopPropagation={() => {}}
      >
        <!-- Close Button -->
        <button
          on:click={closeAboutVideoLightbox}
          class="absolute -top-12 right-0 bg-white text-gray-800 rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
          aria-label="Close video"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <!-- Video Embed -->
        <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 16/9;">
          <iframe
            src={getYoutubeEmbedUrl(aboutVideoUrl)}
            title="Tentang Program Ekspedisi Qur'an"
            class="w-full h-full"
            style="aspect-ratio: 16/9; width: 100%; height: 100%; border: none;"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            loading="eager"
          ></iframe>
        </div>
      </div>
    </button>
  {/if}

</PublicLayout>

<style>
  .font-cairo {
    font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
  }
  
  /* Fade in animation */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  
  
  .islamic-geometric {
    background-image:
      repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.08) 4px, rgba(235, 52, 52, 0.08) 6px),
      repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.06) 4px, rgba(235, 52, 52, 0.06) 6px),
      radial-gradient(circle at 25% 25%, rgba(235, 52, 52, 0.1) 1px, transparent 1px),
      radial-gradient(circle at 75% 75%, rgba(235, 52, 52, 0.1) 1px, transparent 1px);
    background-size: 12px 12px, 12px 12px, 8px 8px, 8px 8px;
  }
  
  /* Map styles */
  #distribution-map {
    animation: fadeIn 0.8s ease-in-out;
    position: relative !important;
    z-index: 1 !important;
    isolation: isolate !important;
    transform: translateZ(0) !important;
  }
  

  /* Ensure hero section buttons have proper z-index and are clickable */
  .hero-buttons {
    position: relative;
    z-index: 100 !important;
    pointer-events: auto !important;
  }

  .hero-buttons button {
    position: relative;
    z-index: 101 !important;
    pointer-events: auto !important;
  }
  
  
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  
  
  
  /* Loading spinner */
  @keyframes spin {
    to { transform: rotate(360deg); }
  }
  
  .animate-spin {
    animation: spin 1s linear infinite;
  }
  
  /* Section-specific z-index management */
  #peta-distribusi {
    position: relative;
    z-index: 1;
  }
  
  #peta-distribusi .max-w-7xl {
    position: relative;
    z-index: 2;
  }
  
  /* Ensure section content stays above map */
  #peta-distribusi h2,
  #peta-distribusi p,
  #peta-distribusi .grid {
    position: relative;
    z-index: 3;
  }
  
  /* Map container isolation */
  .p-1.bg-gradient-to-r {
    position: relative;
    z-index: 1;
    isolation: isolate;
  }
  
  /* Prevent any elements from overlapping the map */
  #distribution-map {
    position: relative !important;
    z-index: 1 !important;
    isolation: isolate !important;
    transform: translateZ(0) !important;
  }
  

  /* Ensure peta distribusi section is properly layered */
  #peta-distribusi {
    position: relative;
    z-index: 10 !important;
    isolation: isolate;
  }

  #peta-distribusi * {
    position: relative;
    z-index: inherit;
  }

  /* Ensure hero section buttons have proper z-index */
  .hero-buttons {
    position: relative;
    z-index: 100 !important;
    pointer-events: auto !important;
  }

  .hero-buttons button {
    position: relative;
    z-index: 101 !important;
    pointer-events: auto !important;
  }  
  
  
</style>
