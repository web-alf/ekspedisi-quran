<script>
  import { router } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import { onMount } from 'svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { can } from '../../../utils/permissions.js';
  
  // Props from Inertia
  export let mushafRequests = { data: [], links: [], from: 0, to: 0, total: 0 };
  export const errors = {};
  export const auth = {};
  export const flash = {};
  export const settings = {};
  
  // Add CSS for location markers
  const markerStyle = '<' + 'style>' + `
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
  ` + '</' + 'style>';
  
  // Inject styles
  if (typeof document !== 'undefined') {
    const styleElement = document.createElement('div');
    styleElement.innerHTML = markerStyle;
    document.head.appendChild(styleElement.firstElementChild);
  }
  export let filters = {};
  export let mapData = [];
  export let stats = {
    total: 0,
    pending: 0,
    reviewed: 0,
    approved: 0,
    rejected: 0,
    processed: 0,
    completed: 0
  };
  
  // Initialize filter values to prevent undefined errors
  $: if (!filters.search) filters.search = '';
  $: if (!filters.status) filters.status = '';
  $: if (!filters.start_date) filters.start_date = '';
  $: if (!filters.end_date) filters.end_date = '';
  
  let search = filters.search || '';
  let statusFilter = filters.status || '';
  let startDate = filters.start_date || '';
  let endDate = filters.end_date || '';
  let showDeleteModal = false;
  let requestToDelete = null;
  let showImportModal = false;
  let importFile = null;
  let importFileInput = null;
  
  // Permission checks
  $: canRead = can.mushafRequests.read();
  $: canDelete = can.mushafRequests.delete();
  
  // Current date for max date attribute
  let currentDate = new Date().toISOString().split('T')[0];
  
  // Map variables
  let map;
  let isMapInitialized = false;
  let provinceLayer;
  let L = null; // Will hold Leaflet module
  
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
  });
  
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
  
  // Fungsi untuk mendapatkan teks status
  function getStatusText(status) {
    const statusMap = {
      'pending': 'Menunggu Review',
      'reviewed': 'Sedang Direview',
      'approved': 'Disetujui',
      'rejected': 'Ditolak',
      'processed': 'Sudah Diproses',
      'completed': 'Selesai'
    };
    return statusMap[status] || status;
  }
  
  // Fungsi untuk mendapatkan ikon status
  function getStatusIcon(status) {
    const iconMap = {
      'pending': 'clock',
      'reviewed': 'eye',
      'approved': 'check-circle',
      'rejected': 'x-circle',
      'processed': 'cube',
      'completed': 'sparkles'
    };
    return iconMap[status] || 'document-text';
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
        scrollWheelZoom: true,   // Enable scroll wheel zoom
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
    
    // Filter data for completed distributions only
    let completedData = Array.isArray(mapData) ? mapData.filter(item => item.status === 'completed') : [];
    
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
                  <div>📦 Total Pengiriman: <b>${totalShipments}</b></div>
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
  
  // Debounce search
  let searchTimeout;
  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }
  
  function applyFilters() {
    const params = {};
    if (search) params.search = search;
    if (statusFilter) params.status = statusFilter;
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    
    router.get('/admin/mushaf-requests', params, {
      preserveState: true,
      preserveScroll: true
    });
  }
  
  function resetFilters() {
    search = '';
    statusFilter = '';
    startDate = '';
    endDate = '';
    router.get('/admin/mushaf-requests');
  }
  
  function confirmDelete(request) {
    requestToDelete = request;
    showDeleteModal = true;
  }
  
  function handleDelete() {
    if (requestToDelete) {
      router.delete(`/admin/mushaf-requests/${requestToDelete.id}`, {
        onSuccess: () => {
          showDeleteModal = false;
          requestToDelete = null;
        }
      });
    }
  }
  
  function cancelDelete() {
    showDeleteModal = false;
    requestToDelete = null;
  }
  
  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
  function getStatusBadge(status) {
    const statusMap = {
      'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
      'reviewed': 'bg-blue-100 text-blue-800 border-blue-200',
      'approved': 'bg-green-100 text-green-800 border-green-200',
      'rejected': 'bg-red-100 text-red-800 border-red-200',
      'processed': 'bg-purple-100 text-purple-800 border-purple-200',
      'completed': 'bg-emerald-100 text-emerald-800 border-emerald-200'
    };
    return statusMap[status] || 'bg-gray-100 text-gray-800 border-gray-200';
  }

  function getCategoryStats() {
    // Define kategori mapping
    const categoryMapping = {
      // Kategori 1: Lembaga Pendidikan & Pembinaan
      'Pondok Pesantren': 'Lembaga Pendidikan & Pembinaan',
      'Rumah Tahfidz/Rumah Qur\'an': 'Lembaga Pendidikan & Pembinaan',
      'TPQ/TPA/Madin': 'Lembaga Pendidikan & Pembinaan',
      'Sekolah/Madrasah': 'Lembaga Pendidikan & Pembinaan',
      
      // Kategori 2: Komunitas & Dakwah Kemasyarakatan
      'Masjid/Mushola/Majelis Taklim/Jamaah Masjid': 'Komunitas & Dakwah Kemasyarakatan',
      'Masyarakat/Jamaah Alfatihah': 'Komunitas & Dakwah Kemasyarakatan',
      'Organisasi/Paguyuban/Event Sosial/Komunitas': 'Komunitas & Dakwah Kemasyarakatan',
      'Santri & Karyawan Alfatihah': 'Komunitas & Dakwah Kemasyarakatan',
      
      // Kategori 3: Lembaga Sosial & Pemerintahan
      'Yayasan': 'Lembaga Sosial & Pemerintahan',
      'Instansi Pemerintah': 'Lembaga Sosial & Pemerintahan',
      'RS/Klinik/Puskesmas': 'Lembaga Sosial & Pemerintahan',
      
      // Kategori 4: Penerima Manfaat Khusus
      'Lapas/Rutan': 'Penerima Manfaat Khusus',
      'Penghafal Mushaf': 'Penerima Manfaat Khusus'
    };
    
    const categoryStats = {
      'Lembaga Pendidikan & Pembinaan': {
        count: 0,
        totalMushaf: 0,
        totalIqra: 0,
        icon: 'academic-cap'
      },
      'Komunitas & Dakwah Kemasyarakatan': {
        count: 0,
        totalMushaf: 0,
        totalIqra: 0,
        icon: 'user-group'
      },
      'Lembaga Sosial & Pemerintahan': {
        count: 0,
        totalMushaf: 0,
        totalIqra: 0,
        icon: 'building-office-2'
      },
      'Penerima Manfaat Khusus': {
        count: 0,
        totalMushaf: 0,
        totalIqra: 0,
        icon: 'heart'
      }
    };
    
    if (mushafRequests.data && mushafRequests.data.length > 0) {
      mushafRequests.data.forEach(request => {
        // Only count requests with status 'completed' (selesai)
        if (request.status !== 'completed') {
          return;
        }
        
        const subCategory = request.kategori_lembaga;
        const mainCategory = categoryMapping[subCategory] || 'Tidak Dikategorikan';
        
        if (categoryStats[mainCategory]) {
          categoryStats[mainCategory].count++;
          categoryStats[mainCategory].totalMushaf += request.jumlah_mushaf || 0;
          categoryStats[mainCategory].totalIqra += request.jumlah_iqra || 0;
        } else {
          // Handle uncategorized
          if (!categoryStats['Tidak Dikategorikan']) {
            categoryStats['Tidak Dikategorikan'] = {
              count: 0,
              totalMushaf: 0,
              totalIqra: 0,
              icon: 'question-mark-circle'
            };
          }
          categoryStats['Tidak Dikategorikan'].count++;
          categoryStats['Tidak Dikategorikan'].totalMushaf += request.jumlah_mushaf || 0;
          categoryStats['Tidak Dikategorikan'].totalIqra += request.jumlah_iqra || 0;
        }
      });
    }
    
    // Don't remove empty categories - show all 4 main categories even if 0
    
    return categoryStats;
  }

  function getTotalDistribution() {
    if (!mushafRequests.data || mushafRequests.data.length === 0) return 0;
    
    return mushafRequests.data.reduce((total, request) => {
      // Only count requests with status 'completed' (selesai)
      if (request.status !== 'completed') {
        return total;
      }
      return total + (request.jumlah_mushaf || 0) + (request.jumlah_iqra || 0);
    }, 0);
  }

  function exportData() {
    // Build query parameters based on current filters
    const params = new URLSearchParams();

    if (search) params.append('search', search);
    if (statusFilter) params.append('status', statusFilter);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    // Create the export URL
    const exportUrl = `/admin/mushaf-requests-export?${params.toString()}`;

    // Trigger download
    window.location.href = exportUrl;
  }

  function downloadTemplate() {
    window.location.href = '/admin/mushaf-requests-template';
  }

  function openImportModal() {
    showImportModal = true;
  }

  function closeImportModal() {
    showImportModal = false;
    importFile = null;
    if (importFileInput) importFileInput.value = '';
  }

  function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
      // Validate file type
      const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
      if (!validTypes.includes(file.type)) {
        showError('Format File Tidak Valid', 'Hanya file Excel (.xlsx, .xls) yang diperbolehkan. Hanya file Excel (.xlsx, .xls) yang diperbolehkan.');
        event.target.value = '';
        return;
      }

      // Validate file size (max 10MB)
      if (file.size > 10 * 1024 * 1024) {
        showError('File Terlalu Besar', 'Ukuran file maksimal 10MB. Maksimal 10MB.');
        event.target.value = '';
        return;
      }

      importFile = file;
    }
  }

  function submitImport() {
    if (!importFile) {
      showWarning('File Belum Dipilih', 'Silakan pilih file Excel terlebih dahulu');
      return;
    }

    console.log('Submitting import with file:', importFile.name);

    // Get CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const formData = new FormData();
    formData.append('file', importFile);
    formData.append('_token', csrfToken);

    router.post('/admin/mushaf-requests-import', formData, {
      forceFormData: true,
      preserveState: false,
      preserveScroll: false,
      onBefore: () => {
        console.log('Import started...');
      },
      onSuccess: (page) => {
        console.log('Import success response:', page);
        closeImportModal();
      },
      onError: (errors) => {
        console.error('Import error:', errors);
        showError('Error Import', 'Error saat import: ' + (errors.file || errors.message || 'Unknown error'));
      },
      onFinish: () => {
        console.log('Import request finished');
      }
    });
  }
</script>

<AdminLayout>
  <!-- Page Header -->
  <div class="mb-6 lg:mb-8">
    <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Manajemen Permintaan Mushaf</h2>
        <p class="text-gray-600">Kelola permintaan mushaf dari lembaga dan pesantren</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          on:click={downloadTemplate}
          class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white font-medium text-sm hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 gap-1.5"
          title="Download template Excel untuk import data"
        >
          <HeroIcon name="document-text" class="w-4 h-4" />
          <span>Template</span>
        </button>
        <button
          on:click={openImportModal}
          class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white font-medium text-sm hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 gap-1.5"
        >
          <HeroIcon name="arrow-up-tray" class="w-4 h-4" />
          <span>Import Excel</span>
        </button>
        <button
          on:click={exportData}
          class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white font-medium text-sm hover:bg-green-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 gap-1.5"
        >
          <HeroIcon name="arrow-down-tray" class="w-4 h-4" />
          <span>Export Excel</span>
        </button>
      </div>
    </div>
  </div>

    <!-- Distribution Map -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 border border-gray-100 hover:shadow-lg transition-shadow duration-300">
      <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
        <HeroIcon name="map-pin" class="h-5 w-5 text-[#eb3434]" />
        <span>Peta Penyebaran Distribusi Al-Qur'an</span>
      </h3>
      
      <!-- Tambahkan card untuk info peta -->
      <!-- Gunakan gradient dan border untuk peta -->
      <div class="p-1 bg-gradient-to-r from-[#eb3434] to-[#f59e0b] rounded-lg shadow-lg hover:shadow-xl transition-all duration-300">
        <div class="bg-white p-4 rounded-lg">
          <!-- Info Card for Map -->
          <div class="bg-blue-50 p-3 rounded-lg mb-3 text-sm text-gray-600 border-l-4 border-blue-500">
            <p>Visualisasi sebaran permintaan mushaf dari lembaga di seluruh Indonesia. Warna pada peta menunjukkan intensitas permintaan per provinsi.</p>
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
    </div>

    <!-- Category Statistics -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-100 hover:shadow-lg transition-shadow duration-300">
      <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
        <HeroIcon name="building-office-2" class="h-5 w-5 text-[#eb3434]" />
        <span>Statistik Distribusi berdasarkan Kategori Lembaga</span>
      </h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {#each Object.entries(getCategoryStats()) as [category, data]}
          <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-lg bg-red-50 text-[#eb3434] flex items-center justify-center">
                  <HeroIcon name={data.icon} class="w-5 h-5" />
                </div>
                <h4 class="font-medium text-gray-800 text-sm">{category}</h4>
              </div>
              <span class="text-xs bg-[#eb3434] text-white px-2 py-1 rounded-full">{data.count}</span>
            </div>
            <div class="space-y-1">
              <div class="flex justify-between text-xs">
                <span class="text-gray-600">Total Mushaf:</span>
                <span class="font-semibold text-gray-800">{data.totalMushaf.toLocaleString('id-ID')}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-600">Total IQRA:</span>
                <span class="font-semibold text-gray-800">{data.totalIqra.toLocaleString('id-ID')}</span>
              </div>
              <div class="flex justify-between text-xs border-t border-gray-300 pt-1">
                <span class="text-gray-700 font-medium">Total:</span>
                <span class="font-bold text-[#eb3434]">{(data.totalMushaf + data.totalIqra).toLocaleString('id-ID')}</span>
              </div>
            </div>
          </div>
        {/each}
      </div>
      
      <!-- Summary -->
      <div class="mt-4 p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">
        <div class="flex justify-between items-center">
          <span class="text-sm text-gray-700">Total keseluruhan distribusi:</span>
          <span class="text-lg font-bold text-blue-700">{getTotalDistribution().toLocaleString('id-ID')} unit</span>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
        <div class="text-sm font-medium text-gray-500">Total</div>
        <div class="mt-1 text-2xl font-semibold text-gray-900">{stats.total}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
        <div class="text-sm font-medium text-gray-500">Menunggu Review</div>
        <div class="mt-1 text-2xl font-semibold text-yellow-600">{stats.pending}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
        <div class="text-sm font-medium text-gray-500">Sedang Direview</div>
        <div class="mt-1 text-2xl font-semibold text-blue-600">{stats.reviewed}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
        <div class="text-sm font-medium text-gray-500">Disetujui</div>
        <div class="mt-1 text-2xl font-semibold text-green-600">{stats.approved}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
        <div class="text-sm font-medium text-gray-500">Sudah Diproses</div>
        <div class="mt-1 text-2xl font-semibold text-purple-600">{stats.processed}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
        <div class="text-sm font-medium text-gray-500">Selesai</div>
        <div class="mt-1 text-2xl font-semibold text-emerald-600">{stats.completed}</div>
      </div>
    </div>
    
    <!-- Filters & Search -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
      <div class="flex flex-col space-y-4">
        <!-- Search and Status Filters -->
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input
              type="text"
              bind:value={search}
              on:input={handleSearch}
              placeholder="Cari berdasarkan nama lembaga, nomor resi, provinsi, atau alamat..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
          <div class="flex gap-4">
            <select
              bind:value={statusFilter}
              on:change={applyFilters}
              class="w-48 pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-[#eb3434] focus:border-[#eb3434]"
            >
              <option value="">Semua Status</option>
              <option value="pending">Menunggu Review</option>
              <option value="reviewed">Sedang Direview</option>
              <option value="approved">Disetujui</option>
              <option value="rejected">Ditolak</option>
              <option value="processed">Sudah Diproses</option>
              <option value="completed">Selesai</option>
            </select>
            <button
              on:click={resetFilters}
              class="px-4 py-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
            >
              Reset
            </button>
          </div>
        </div>
        
        <!-- Date Range Filter -->
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <input
              id="start_date"
              type="date"
              bind:value={startDate}
              on:change={applyFilters}
              max={currentDate}
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
          <div class="flex-1">
            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
            <input
              id="end_date"
              type="date"
              bind:value={endDate}
              on:change={applyFilters}
              max={currentDate}
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
        </div>
      </div>
      
      <!-- Active Filters -->
      {#if startDate || endDate || statusFilter || search}
        <div class="mt-4 flex flex-wrap gap-2">
          {#if search}
            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
              <span>Pencarian: {search}</span>
              <button 
                on:click={() => { search = ''; applyFilters(); }}
                class="ml-2 text-blue-600 hover:text-blue-800"
              >
                &times;
              </button>
            </div>
          {/if}
          
          {#if statusFilter}
            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
              <span>Status: {getStatusText(statusFilter)}</span>
              <button 
                on:click={() => { statusFilter = ''; applyFilters(); }}
                class="ml-2 text-purple-600 hover:text-purple-800"
              >
                &times;
              </button>
            </div>
          {/if}
          
          {#if startDate}
            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
              <span>Mulai: {startDate}</span>
              <button 
                on:click={() => { startDate = ''; applyFilters(); }}
                class="ml-2 text-blue-600 hover:text-blue-800"
              >
                &times;
              </button>
            </div>
          {/if}
          
          {#if endDate}
            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
              <span>Sampai: {endDate}</span>
              <button 
                on:click={() => { endDate = ''; applyFilters(); }}
                class="ml-2 text-blue-600 hover:text-blue-800"
              >
                &times;
              </button>
            </div>
          {/if}
        </div>
      {/if}
    </div>
    
    <!-- Requests Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Informasi Permintaan
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Kontak
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Tanggal
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Aksi
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            {#if mushafRequests.data && mushafRequests.data.length > 0}
              {#each mushafRequests.data as request}
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-4">
                    <div>
                      <div class="text-sm font-medium text-gray-900">{request.nama_lembaga}</div>
                      {#if request.kategori_lembaga}
                        <div class="text-xs text-gray-500">Kategori: {request.kategori_lembaga}</div>
                      {/if}
                      <div class="text-sm text-gray-600">
                        {#if request.alamat_detail || request.kelurahan_desa || request.kecamatan || request.kota_kabupaten || request.provinsi}
                          {[request.alamat_detail, request.kelurahan_desa, request.kecamatan, request.kota_kabupaten, request.provinsi].filter(Boolean).join(', ')}
                        {:else}
                          {request.alamat_lengkap}
                        {/if}
                        {#if request.kode_pos}
                          <span class="text-xs text-gray-400 ml-1">{request.kode_pos}</span>
                        {/if}
                      </div>
                      <div class="text-xs text-gray-500 mt-1">No. Request: {request.no_request}</div>
                      <div class="text-xs text-gray-500 flex gap-2">
                        <span>Mushaf: {request.jumlah_mushaf}</span>
                        {#if request.jumlah_mushaf_a5 > 0}<span>A5: {request.jumlah_mushaf_a5}</span>{/if}
                        {#if request.jumlah_mushaf_a6 > 0}<span>A6: {request.jumlah_mushaf_a6}</span>{/if}
                        <span>IQRA: {request.jumlah_iqra}</span>
                        <span class="font-medium">Total: {request.jumlah_mushaf + request.jumlah_iqra}</span>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div>
                      <div class="text-sm text-gray-900">{request.nama_pengurus_1}</div>
                      <div class="text-sm text-gray-500">{request.whatsapp_pengurus_1}</div>
                      <div class="text-sm text-gray-500">{request.jabatan_pengurus_1}</div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {getStatusBadge(request.status)}">
                      <HeroIcon name={getStatusIcon(request.status)} class="w-3.5 h-3.5 mr-1" />
                      <span>{getStatusText(request.status)}</span>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-gray-900">{formatDate(request.created_at)}</div>
                  </td>
                  <td class="px-6 py-4 text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                      <button
                        on:click={() => router.visit(`/admin/mushaf-requests/${request.id}`)}
                        class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                        title="Lihat Detail"
                      >
                        <HeroIcon name="eye" class="w-4 h-4" />
                      </button>
                      {#if request.status !== 'completed'}
                        <button
                          on:click={() => confirmDelete(request)}
                          class="text-red-600 hover:text-red-900 p-1 hover:bg-red-50 rounded transition-colors"
                          title="Hapus"
                        >
                          <HeroIcon name="trash" class="w-4 h-4" />
                        </button>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/each}
            {:else}
              <tr>
                <td colspan="5" class="px-6 py-12 text-center">
                  <div class="text-gray-500">
                    <HeroIcon name="document-text" class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                    <p class="text-lg font-medium">Tidak ada permintaan mushaf</p>
                    <p class="text-sm">Belum ada permintaan mushaf yang masuk</p>
                  </div>
                </td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      {#if mushafRequests.links && mushafRequests.links.length > 0 && mushafRequests.total > 0}
        <div class="px-6 py-4 border-t border-gray-200">
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500">
              Menampilkan {mushafRequests.from} - {mushafRequests.to} dari {mushafRequests.total} data
            </div>
            <div class="flex gap-2">
              {#each mushafRequests.links as link}
                <button
                  class="px-3 py-1 rounded {link.active ? 'bg-[#eb3434] text-white' : 'bg-white text-gray-700 hover:bg-gray-50'}"
                  class:disabled={!link.url}
                  on:click={() => link.url && router.visit(link.url)}
                >
                  {@html link.label}
                </button>
              {/each}
            </div>
          </div>
        </div>
      {/if}
    </div>
</AdminLayout>

<!-- Delete Confirmation Modal -->
{#if showDeleteModal}
  <div class="fixed inset-0 overflow-y-auto" style="z-index: 9999;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="modal-overlay fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        on:click={cancelDelete}
        on:keydown={(e) => e.key === 'Escape' && cancelDelete()}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="modal-content inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
            <HeroIcon name="exclamation-triangle" class="h-6 w-6 text-red-600" />
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
              Hapus Permintaan
            </h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Apakah Anda yakin ingin menghapus permintaan ini? Tindakan ini tidak dapat dibatalkan.
              </p>
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            on:click={handleDelete}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Hapus
          </button>
          <button
            type="button"
            on:click={cancelDelete}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:mt-0 sm:w-auto sm:text-sm"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Import Modal -->
{#if showImportModal}
  <div class="fixed inset-0 overflow-y-auto" style="z-index: 9999;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="modal-overlay fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        on:click={closeImportModal}
        on:keydown={(e) => e.key === 'Escape' && closeImportModal()}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="modal-content inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
            <HeroIcon name="arrow-up-tray" class="h-6 w-6 text-indigo-600" />
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
              Import Data Mushaf Request
            </h3>
            <div class="mt-4">
              <p class="text-sm text-gray-500 mb-4">
                Upload file Excel (.xlsx atau .xls) untuk import data permintaan mushaf secara bulk.
              </p>

              <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-500 transition-colors">
                <input
                  type="file"
                  accept=".xlsx,.xls"
                  on:change={handleFileSelect}
                  bind:this={importFileInput}
                  class="hidden"
                  id="import-file-input"
                />
                <label for="import-file-input" class="cursor-pointer">
                  <HeroIcon name="arrow-up-tray" class="mx-auto h-12 w-12 text-gray-400" />
                  <p class="mt-2 text-sm text-gray-600">
                    {importFile ? importFile.name : 'Klik untuk memilih file atau drag & drop'}
                  </p>
                  <p class="mt-1 text-xs text-gray-500">
                    Excel files up to 10MB
                  </p>
                </label>
              </div>

              <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-xs text-blue-800">
                  <strong>💡 Tips:</strong> Download template terlebih dahulu untuk format yang benar.
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-2">
          <button
            type="button"
            on:click={submitImport}
            disabled={!importFile}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Import
          </button>
          <button
            type="button"
            on:click={closeImportModal}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Flash Messages -->
<FlashMessage />

<style>
  .font-cairo {
    font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
  }
  
  /* Animasi untuk peta */
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  #distribution-map {
    animation: fadeIn 0.8s ease-in-out;
    position: relative !important;
    z-index: 1 !important;
    isolation: isolate !important;
    transform: translateZ(0) !important;
  }

  /* Ensure map and its children stay below modals */
  #distribution-map,
  #distribution-map * {
    z-index: 1 !important;
  }

  /* Modal z-index hierarchy - overlay behind, content in front */
  div[role="dialog"] {
    z-index: 10000 !important;
  }

  .modal-overlay {
    z-index: 1 !important;
    position: absolute !important;
  }

  .modal-content {
    z-index: 2 !important;
    position: relative !important;
  }
  
  
  /* Styling untuk legenda di luar peta */
  .map-legend {
    animation: fadeIn 0.8s ease-in-out;
  }
  
</style>
