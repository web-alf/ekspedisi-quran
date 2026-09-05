<script>
  import { router } from '@inertiajs/svelte';
  import { useForm } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import { showError, showWarning } from '../../../stores/toast.js';
  import AddressFormIndonesia from '../../../Components/AddressFormIndonesia.svelte';
  import { fade, scale } from 'svelte/transition';
  import { can } from '../../../utils/permissions.js';

  export let mushafRequest;
  export const wakifList = [];
  export const auth = {};
  export const errors = {};
  export const flash = {};
  
  // Permission checks
  $: canUpdate = can.mushafRequests.update();
  $: canRead = can.mushafRequests.read();
  
  let showStatusModal = false;
  let showEditQuantityModal = false;
  let showEditLembagaModal = false;
  let showEditKontakModal = false;
  let showEditFilesModal = false;
  let isLoading = false;
  let imageLoadErrors = {
    foto_santri: false,
    foto_lembaga: false
  };

  // Form for editing lembaga info
  let lembagaForm = {
    nama_lembaga: mushafRequest.nama_lembaga,
    kategori_lembaga: mushafRequest.kategori_lembaga || '',
    alamat_lengkap: mushafRequest.alamat_lengkap,
    provinsi: mushafRequest.provinsi || '',
    kota_kabupaten: mushafRequest.kota_kabupaten || '',
    kecamatan: mushafRequest.kecamatan || '',
    kelurahan_desa: mushafRequest.kelurahan_desa || '',
    kode_pos: mushafRequest.kode_pos || '',
    alamat_detail: mushafRequest.alamat_detail || '',
    latitude: mushafRequest.latitude || '',
    longitude: mushafRequest.longitude || '',
    urgensi_request: mushafRequest.urgensi_request || '',
    sumber_info: mushafRequest.sumber_info || ''
  };

  // Form for editing kontak info
  let kontakForm = {
    nama_pengurus_1: mushafRequest.nama_pengurus_1,
    jabatan_pengurus_1: mushafRequest.jabatan_pengurus_1,
    whatsapp_pengurus_1: mushafRequest.whatsapp_pengurus_1,
    nama_pengurus_2: mushafRequest.nama_pengurus_2 || '',
    jabatan_pengurus_2: mushafRequest.jabatan_pengurus_2 || '',
    whatsapp_pengurus_2: mushafRequest.whatsapp_pengurus_2 || ''
  };

  // Form for editing files
  let filesForm = {
    foto_santri: null,
    foto_lembaga: null,
    file_nama_santri: null,
    delete_foto_santri: false,
    delete_foto_lembaga: false,
    delete_file_nama_santri: false
  };

  // Form for editing approved quantities
  let quantityForm = {
    jumlah_mushaf_approved: mushafRequest.jumlah_mushaf_approved || mushafRequest.jumlah_mushaf,
    jumlah_mushaf_a5_approved: mushafRequest.jumlah_mushaf_a5_approved || mushafRequest.jumlah_mushaf_a5,
    jumlah_mushaf_a6_approved: mushafRequest.jumlah_mushaf_a6_approved || mushafRequest.jumlah_mushaf_a6,
    jumlah_iqra_approved: mushafRequest.jumlah_iqra_approved || mushafRequest.jumlah_iqra,
    catatan_perubahan_jumlah: mushafRequest.catatan_perubahan_jumlah || ''
  };
  
  const statusForm = useForm({
    status: mushafRequest.status,
    catatan_admin: mushafRequest.catatan_admin || ''
  });
  
  // function handleBack() {
  //   router.visit('/admin/mushaf-requests');
  // }
  
  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
  function getStatusLabel(status) {
    const labels = {
      'pending': 'Menunggu Review',
      'reviewed': 'Sedang Direview',
      'approved': 'Disetujui',
      'rejected': 'Ditolak',
      'processed': 'Sudah Diproses',
      'completed': 'Selesai'
    };
    return labels[status] || status;
  }
  
  function getStatusIcon(status) {
    const iconMap = {
      'pending': '⏳',
      'reviewed': '👀',
      'approved': '✅',
      'rejected': '❌',
      'processed': '📦',
      'completed': '🎉'
    };
    return iconMap[status] || '📋';
  }
  
  function getStatusClass(status) {
    const classes = {
      'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
      'reviewed': 'bg-blue-100 text-blue-800 border-blue-200',
      'approved': 'bg-green-100 text-green-800 border-green-200', 
      'rejected': 'bg-red-100 text-red-800 border-red-200',
      'processed': 'bg-purple-100 text-purple-800 border-purple-200',
      'completed': 'bg-emerald-100 text-emerald-800 border-emerald-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-800 border-gray-200';
  }
  
  function getAvailableStatuses(currentStatus) {
    const statusFlow = {
      'pending': ['reviewed', 'rejected'],
      'reviewed': ['approved', 'rejected'],
      'approved': ['processed', 'rejected'],
      'rejected': [],
      'processed': ['completed']
    };
    return statusFlow[currentStatus] || [];
  }
  
  function getJenisMushafList(jenisString) {
    if (!jenisString) return '-';
    try {
      const jenisList = JSON.parse(jenisString);
      return Array.isArray(jenisList) ? jenisList.join(', ') : jenisString;
    } catch {
      return jenisString;
    }
  }
  
  function getFileUrl(path) {
    return `/storage/${path}`;
  }
  
  function getFileName(path) {
    return path.split('/').pop() || 'file';
  }
  
  function handleImageError(type) {
    imageLoadErrors[type] = true;
    imageLoadErrors = { ...imageLoadErrors };
  }

  function openStatusModal() {
    // Reset form dengan status saat ini sebagai default
    statusForm.status = mushafRequest.status;
    statusForm.catatan_admin = mushafRequest.catatan_admin || '';
    showStatusModal = true;
  }

  function openEditQuantityModal() {
    // Reset form dengan data saat ini
    quantityForm = {
      jumlah_mushaf_approved: mushafRequest.jumlah_mushaf_approved || mushafRequest.jumlah_mushaf,
      jumlah_mushaf_a5_approved: mushafRequest.jumlah_mushaf_a5_approved || mushafRequest.jumlah_mushaf_a5,
      jumlah_mushaf_a6_approved: mushafRequest.jumlah_mushaf_a6_approved || mushafRequest.jumlah_mushaf_a6,
      jumlah_iqra_approved: mushafRequest.jumlah_iqra_approved || mushafRequest.jumlah_iqra,
      catatan_perubahan_jumlah: mushafRequest.catatan_perubahan_jumlah || ''
    };
    showEditQuantityModal = true;
  }

  function closeEditQuantityModal() {
    showEditQuantityModal = false;
  }

  function handleUpdateQuantities() {
    isLoading = true;
    router.patch(`/admin/mushaf-requests/${mushafRequest.id}/quantities`, quantityForm, {
      preserveScroll: true,
      onSuccess: () => {
        showEditQuantityModal = false;
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      }
    });
  }

  function handleUpdateLembaga() {
    isLoading = true;

    router.patch(`/admin/mushaf-requests/${mushafRequest.id}/lembaga`, lembagaForm, {
      preserveScroll: true,
      onSuccess: () => {
        showEditLembagaModal = false;
        isLoading = false;
      },
      onError: (errors) => {
        console.error('Update lembaga errors:', errors);
        isLoading = false;
      }
    });
  }

  function handleUpdateKontak() {
    isLoading = true;

    router.patch(`/admin/mushaf-requests/${mushafRequest.id}/kontak`, kontakForm, {
      preserveScroll: true,
      onSuccess: () => {
        showEditKontakModal = false;
        isLoading = false;
      },
      onError: (errors) => {
        console.error('Update kontak errors:', errors);
        isLoading = false;
      }
    });
  }

  function handleUpdateFiles() {
    isLoading = true;

    // Create FormData for file uploads
    const formData = new FormData();

    // Add _method for Laravel to recognize PATCH request
    formData.append('_method', 'PATCH');

    // Append files if selected
    if (filesForm.foto_santri) {
      formData.append('foto_santri', filesForm.foto_santri);
    }
    if (filesForm.foto_lembaga) {
      formData.append('foto_lembaga', filesForm.foto_lembaga);
    }
    if (filesForm.file_nama_santri) {
      formData.append('file_nama_santri', filesForm.file_nama_santri);
    }

    // Append delete flags
    if (filesForm.delete_foto_santri) {
      formData.append('delete_foto_santri', '1');
    }
    if (filesForm.delete_foto_lembaga) {
      formData.append('delete_foto_lembaga', '1');
    }
    if (filesForm.delete_file_nama_santri) {
      formData.append('delete_file_nama_santri', '1');
    }

    // Use router.post with FormData
    router.post(`/admin/mushaf-requests/${mushafRequest.id}/files`, formData, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        showEditFilesModal = false;
        isLoading = false;
        // Reset file form
        filesForm = {
          foto_santri: null,
          foto_lembaga: null,
          file_nama_santri: null,
          delete_foto_santri: false,
          delete_foto_lembaga: false,
          delete_file_nama_santri: false
        };
      },
      onError: (errors) => {
        console.error('Update files errors:', errors);
        isLoading = false;
      }
    });
  }

  // Calculate total approved
  $: totalApproved = (quantityForm.jumlah_mushaf_a5_approved || 0) +
                     (quantityForm.jumlah_mushaf_a6_approved || 0) +
                     (quantityForm.jumlah_iqra_approved || 0);

  $: totalRequested = mushafRequest.jumlah_mushaf + mushafRequest.jumlah_iqra;
  
  function handleStatusUpdate() {
    // Validate that status is actually changing
    if ($statusForm.status === mushafRequest.status) {
      showWarning('Status Sama', 'Status yang dipilih sama dengan status saat ini');
      return;
    }
    
    isLoading = true;
    
    $statusForm.patch(`/admin/mushaf-requests/${mushafRequest.id}/status`, {
      onSuccess: (page) => {
        showStatusModal = false;
        isLoading = false;
        // Update local data if new data is returned
        if (page.props.mushafRequest) {
          mushafRequest = page.props.mushafRequest;
        }
        // Force page refresh to ensure data is current
        window.location.reload();
      },
      onError: (errors) => {
        isLoading = false;
        showError('Gagal Update Status', 'Gagal update status: ' + (errors.message || JSON.stringify(errors)));
      },
      onFinish: () => {
        isLoading = false;
      }
    });
  }
</script>

<svelte:head>
  <title>Detail Permintaan Mushaf - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="p-6">
    <!-- Flash Messages -->
    <FlashMessage />
    
    <!-- Header -->
    <div class="mb-6">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center">
          <button 
            on:click={() => router.visit('/admin/mushaf-requests')}
            class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3 hover:bg-red-600 transition-colors"
          >
            <HeroIcon name="arrow-left" class="w-4 h-4 text-white" />
          </button>
          <div>
            <h1 class="text-lg font-bold text-gray-900">Detail Permintaan Mushaf</h1>
            <p class="text-sm text-gray-500">#{mushafRequest.id} - {mushafRequest.nama_lembaga}</p>
          </div>
        </div>
        
        <div class="flex space-x-2">
          {#if canUpdate && ['pending', 'reviewed', 'approved', 'processed'].includes(mushafRequest.status)}
            <button 
              on:click={openStatusModal}
              class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors"
            >
              <span class="hidden sm:inline">Update Status</span>
              <span class="sm:hidden">Update</span>
            </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Content -->
    <!-- Mushaf Request Info Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-[#eb3434] rounded-full flex items-center justify-center mr-4">
          <HeroIcon name="book-open" class="w-6 h-6 text-white" />
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-medium text-gray-900">{mushafRequest.nama_lembaga}</h3>
          <p class="text-sm text-gray-500">Permintaan #{mushafRequest.id} • {formatDate(mushafRequest.created_at)}</p>
          <div class="flex items-center mt-2 space-x-3">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {getStatusClass(mushafRequest.status)}">
              {getStatusIcon(mushafRequest.status)} {getStatusLabel(mushafRequest.status)}
            </span>
            {#if mushafRequest.whatsapp_pengurus_1}
              <a href="https://wa.me/{mushafRequest.whatsapp_pengurus_1.replace(/^0/, '62')}" 
                 target="_blank" 
                 class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200 transition-colors">
                <HeroIcon name="message-circle" class="w-3 h-3 mr-1" />
                WhatsApp
              </a>
            {/if}
          </div>
        </div>
      </div>
    </div>

    <!-- Request Details -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left Column -->
      <div class="space-y-6">
        <!-- Institution Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Informasi Lembaga</h3>
            {#if canUpdate}
              <button
                type="button"
                on:click={() => showEditLembagaModal = true}
                class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
              >
                <HeroIcon name="pencil-square" class="w-4 h-4 mr-1.5" />
                Edit Informasi Lembaga
              </button>
            {/if}
          </div>
          <dl class="space-y-4">
            <div>
              <dt class="text-sm font-medium text-gray-500">Nama Lembaga</dt>
              <dd class="mt-1 text-sm text-gray-900">{mushafRequest.nama_lembaga}</dd>
            </div>
            {#if mushafRequest.kategori_lembaga}
              <div>
                <dt class="text-sm font-medium text-gray-500">Kategori Lembaga</dt>
                <dd class="mt-1 text-sm text-gray-900">{mushafRequest.kategori_lembaga}</dd>
              </div>
            {/if}
            <div>
              <dt class="text-sm font-medium text-gray-500">Alamat Lengkap</dt>
              <dd class="mt-1 text-sm text-gray-900">
                {#if mushafRequest.alamat_detail || mushafRequest.kelurahan_desa || mushafRequest.kecamatan || mushafRequest.kota_kabupaten || mushafRequest.provinsi}
                  <div class="space-y-1">
                    {#if mushafRequest.alamat_detail}
                      <div>{mushafRequest.alamat_detail}</div>
                    {/if}
                    <div class="text-gray-600">
                      {[mushafRequest.kelurahan_desa, mushafRequest.kecamatan, mushafRequest.kota_kabupaten, mushafRequest.provinsi].filter(Boolean).join(', ')}
                      {#if mushafRequest.kode_pos}
                        <span class="ml-2 text-gray-500">{mushafRequest.kode_pos}</span>
                      {/if}
                    </div>
                    {#if mushafRequest.latitude && mushafRequest.longitude}
                      <div class="text-xs text-gray-500">
                        Koordinat: {parseFloat(mushafRequest.latitude).toFixed(6)}, {parseFloat(mushafRequest.longitude).toFixed(6)}
                      </div>
                    {/if}
                  </div>
                {:else}
                  {mushafRequest.alamat_lengkap}
                {/if}
              </dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Urgensi Permintaan</dt>
              <dd class="mt-1 text-sm text-gray-900">{mushafRequest.urgensi_request}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500">Sumber Informasi</dt>
              <dd class="mt-1 text-sm text-gray-900">{mushafRequest.sumber_info}</dd>
            </div>
          </dl>
        </div>
        
        <!-- Contact Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Informasi Kontak</h3>
            {#if canUpdate}
              <button
                type="button"
                on:click={() => showEditKontakModal = true}
                class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
              >
                <HeroIcon name="pencil-square" class="w-4 h-4 mr-1.5" />
                Edit Kontak
              </button>
            {/if}
          </div>
          <div class="space-y-6">
            <div class="border-b border-gray-100 pb-4">
              <h4 class="text-sm font-medium text-gray-700 mb-3">Pengurus 1</h4>
              <dl class="space-y-2">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Nama</dt>
                  <dd class="text-sm text-gray-900">{mushafRequest.nama_pengurus_1}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Jabatan</dt>
                  <dd class="text-sm text-gray-900">{mushafRequest.jabatan_pengurus_1}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">WhatsApp</dt>
                  <dd class="text-sm text-gray-900">{mushafRequest.whatsapp_pengurus_1}</dd>
                </div>
              </dl>
            </div>
            
            {#if mushafRequest.nama_pengurus_2}
              <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Pengurus 2</h4>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Nama</dt>
                    <dd class="text-sm text-gray-900">{mushafRequest.nama_pengurus_2}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Jabatan</dt>
                    <dd class="text-sm text-gray-900">{mushafRequest.jabatan_pengurus_2}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">WhatsApp</dt>
                    <dd class="text-sm text-gray-900">{mushafRequest.whatsapp_pengurus_2}</dd>
                  </div>
                </dl>
              </div>
            {/if}
          </div>
        </div>
      </div>
      
      <!-- Right Column -->
      <div class="space-y-6">
        <!-- Request Details -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Detail Permintaan</h3>
            {#if mushafRequest.status === 'approved' || mushafRequest.status === 'reviewed'}
              <button
                on:click={openEditQuantityModal}
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors"
                title="Edit jumlah yang disetujui"
              >
                <HeroIcon name="pencil-square" class="w-4 h-4 mr-1" />
                Edit Jumlah
              </button>
            {/if}
          </div>
          <dl class="space-y-4">
            <div class="flex justify-between">
              <dt class="text-sm font-medium text-gray-500">Jumlah Mushaf</dt>
              <dd class="text-sm text-gray-900 font-semibold">{mushafRequest.jumlah_mushaf_approved ?? mushafRequest.jumlah_mushaf}</dd>
            </div>
            {#if (mushafRequest.jumlah_mushaf_a5_approved ?? mushafRequest.jumlah_mushaf_a5 ?? 0) > 0}
              <div class="flex justify-between">
                <dt class="text-sm font-medium text-gray-500">Jumlah Mushaf A5</dt>
                <dd class="text-sm text-gray-900 font-semibold">{mushafRequest.jumlah_mushaf_a5_approved ?? mushafRequest.jumlah_mushaf_a5}</dd>
              </div>
            {/if}
            {#if (mushafRequest.jumlah_mushaf_a6_approved ?? mushafRequest.jumlah_mushaf_a6 ?? 0) > 0}
              <div class="flex justify-between">
                <dt class="text-sm font-medium text-gray-500">Jumlah Mushaf A6</dt>
                <dd class="text-sm text-gray-900 font-semibold">{mushafRequest.jumlah_mushaf_a6_approved ?? mushafRequest.jumlah_mushaf_a6}</dd>
              </div>
            {/if}
            <div class="flex justify-between">
              <dt class="text-sm font-medium text-gray-500">Jumlah IQRA</dt>
              <dd class="text-sm text-gray-900 font-semibold">{mushafRequest.jumlah_iqra_approved ?? mushafRequest.jumlah_iqra}</dd>
            </div>
            <div class="flex justify-between items-start">
              <dt class="text-sm font-medium text-gray-500">Jenis Mushaf</dt>
              <dd class="text-sm text-gray-900 text-right">{getJenisMushafList(mushafRequest.jenis_mushaf_diminta)}</dd>
            </div>
            <div class="pt-4 border-t border-gray-100">
              <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-700">Total Disetujui</dt>
                <dd class="text-lg font-bold text-blue-600">{(mushafRequest.jumlah_mushaf_approved ?? mushafRequest.jumlah_mushaf) + (mushafRequest.jumlah_iqra_approved ?? mushafRequest.jumlah_iqra)}</dd>
              </div>
            </div>
          </dl>
          
          {#if mushafRequest.catatan_admin}
            <div class="mt-6 pt-4 border-t border-gray-100">
              <h4 class="text-sm font-medium text-gray-700 mb-2">Catatan Admin</h4>
              <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">{mushafRequest.catatan_admin}</p>
            </div>
          {/if}
          
          {#if mushafRequest.reviewer}
            <div class="mt-4 pt-4 border-t border-gray-100">
              <div class="flex justify-between text-xs text-gray-500">
                <span>Direview oleh: {mushafRequest.reviewer.name}</span>
                <span>{formatDate(mushafRequest.updated_at)}</span>
              </div>
            </div>
          {/if}
        </div>
        
        <!-- Files -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">File Lampiran</h3>
            {#if canUpdate}
              <button
                type="button"
                on:click={() => showEditFilesModal = true}
                class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
              >
                <HeroIcon name="pencil-square" class="w-4 h-4 mr-1.5" />
                Edit File
              </button>
            {/if}
          </div>
          <div class="space-y-6">
            <div>
              <h4 class="text-sm font-medium text-gray-700 mb-3">Foto Santri</h4>
              {#if mushafRequest.foto_santri_path}
                <div class="space-y-3">
                  {#if canRead}
                  <button 
                    on:click={() => window.open(getFileUrl(mushafRequest.foto_santri_path), '_blank')}
                    class="text-[#eb3434] hover:text-red-600 flex items-center space-x-2 font-medium text-sm"
                  >
                    <HeroIcon name="photo" class="w-4 h-4" />
                    <span>Lihat Foto Santri</span>
                  </button>
                  {/if}
                  {#if !imageLoadErrors.foto_santri}
                    <button type="button" class="block" on:click={() => window.open(getFileUrl(mushafRequest.foto_santri_path), '_blank')} aria-label="Lihat foto santri">
                      <img 
                        src={getFileUrl(mushafRequest.foto_santri_path)}
                        alt="Foto Santri"
                        class="w-32 h-32 object-cover rounded-lg border border-gray-200 hover:opacity-80 transition-opacity"
                        on:error={() => handleImageError('foto_santri')}
                      />
                    </button>
                  {:else}
                    <div class="w-32 h-32 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                      <div class="text-center text-gray-500">
                        <HeroIcon name="photo" class="w-8 h-8 mx-auto mb-2" />
                        <p class="text-xs">Gagal memuat</p>
                      </div>
                    </div>
                  {/if}
                </div>
              {:else}
                <span class="text-gray-500 text-sm">Tidak ada file</span>
              {/if}
            </div>

            <div>
              <h4 class="text-sm font-medium text-gray-700 mb-3">Foto Lembaga</h4>
              {#if mushafRequest.foto_lembaga_path}
                <div class="space-y-3">
                  <button 
                    on:click={() => window.open(getFileUrl(mushafRequest.foto_lembaga_path), '_blank')}
                    class="text-[#eb3434] hover:text-red-600 flex items-center space-x-2 font-medium text-sm"
                  >
                    <HeroIcon name="photo" class="w-4 h-4" />
                    <span>Lihat Foto Lembaga</span>
                  </button>
                  {#if !imageLoadErrors.foto_lembaga}
                    <button type="button" class="block" on:click={() => window.open(getFileUrl(mushafRequest.foto_lembaga_path), '_blank')} aria-label="Lihat foto lembaga">
                      <img 
                        src={getFileUrl(mushafRequest.foto_lembaga_path)}
                        alt="Foto Lembaga"
                        class="w-32 h-32 object-cover rounded-lg border border-gray-200 hover:opacity-80 transition-opacity"
                        on:error={() => handleImageError('foto_lembaga')}
                      />
                    </button>
                  {:else}
                    <div class="w-32 h-32 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                      <div class="text-center text-gray-500">
                        <HeroIcon name="photo" class="w-8 h-8 mx-auto mb-2" />
                        <p class="text-xs">Gagal memuat</p>
                      </div>
                    </div>
                  {/if}
                </div>
              {:else}
                <span class="text-gray-500 text-sm">Tidak ada file</span>
              {/if}
            </div>

            <div>
              <h4 class="text-sm font-medium text-gray-700 mb-3">File Nama Santri</h4>
              {#if mushafRequest.file_nama_santri_path}
                <div class="space-y-3">
                  <div class="flex items-center space-x-3">
                    <button 
                      on:click={() => window.open(getFileUrl(mushafRequest.file_nama_santri_path), '_blank')}
                      class="text-[#eb3434] hover:text-red-600 flex items-center space-x-1 font-medium text-sm"
                    >
                      <HeroIcon name="document-text" class="w-4 h-4" />
                      <span>Lihat File</span>
                    </button>
                    <a 
                      href={getFileUrl(mushafRequest.file_nama_santri_path)}
                      download={getFileName(mushafRequest.file_nama_santri_path)}
                      class="text-green-600 hover:text-green-800 text-sm flex items-center space-x-1 font-medium"
                    >
                      <HeroIcon name="arrow-down-tray" class="w-4 h-4" />
                      <span>Download</span>
                    </a>
                  </div>
                  <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex items-center space-x-2">
                      <HeroIcon name="document-text" class="w-5 h-5 text-gray-400" />
                      <p class="text-sm text-gray-600 font-medium">{getFileName(mushafRequest.file_nama_santri_path)}</p>
                    </div>
                  </div>
                </div>
              {:else}
                <span class="text-gray-500 text-sm">Tidak ada file</span>
              {/if}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pengiriman Info (if processed) -->
    {#if mushafRequest.pengiriman}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <dt class="text-sm font-medium text-gray-500">No. Resi</dt>
            <dd class="mt-1 text-sm text-gray-900 font-mono">{mushafRequest.pengiriman.no_resi}</dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500">Wakif</dt>
            <dd class="mt-1 text-sm text-gray-900">{mushafRequest.pengiriman.wakif?.nama_wakif || '-'}</dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500">Jenis Al-Quran</dt>
            <dd class="mt-1 text-sm text-gray-900">{mushafRequest.pengiriman.jenisQuran?.nama_jenis || '-'}</dd>
          </div>
        </div>
      </div>
    {/if}
  </div>

<!-- Status Update Modal -->
{#if showStatusModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
        on:click={() => showStatusModal = false}
        on:keydown={(e) => e.key === 'Escape' && (showStatusModal = false)}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div 
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <div>
          <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
            Update Status Permintaan
          </h3>
          <div class="mt-4">
            <div class="space-y-4">
              <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select
                  id="status"
                  bind:value={$statusForm.status}
                  class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434] sm:text-sm rounded-md"
                >
                  <option value={mushafRequest.status}>{getStatusLabel(mushafRequest.status)} (Saat Ini)</option>
                  {#each getAvailableStatuses(mushafRequest.status) as status}
                    <option value={status}>{getStatusLabel(status)}</option>
                  {/each}
                </select>
              </div>
              <div>
                <label for="catatan" class="block text-sm font-medium text-gray-700">Catatan</label>
                <textarea
                  id="catatan"
                  bind:value={$statusForm.catatan_admin}
                  rows="3"
                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#eb3434] focus:border-[#eb3434] sm:text-sm"
                  placeholder="Tambahkan catatan jika diperlukan..."
                ></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
          <button
            type="button"
            on:click={handleStatusUpdate}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-[#eb3434] text-base font-medium text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:col-start-2 sm:text-sm transition-colors duration-200"
            disabled={isLoading}
          >
            {isLoading ? 'Memproses...' : 'Update Status'}
          </button>
          <button
            type="button"
            on:click={() => showStatusModal = false}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:mt-0 sm:col-start-1 sm:text-sm transition-colors duration-200"
            disabled={isLoading}
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Edit Quantity Modal -->
{#if showEditQuantityModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        on:click={closeEditQuantityModal}
        on:keydown={(e) => e.key === 'Escape' && closeEditQuantityModal()}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <div>
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <HeroIcon name="pencil-square" class="h-6 w-6 text-blue-600" />
              </div>
              <div class="ml-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                  Edit Jumlah yang Disetujui
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  {mushafRequest.nama_lembaga} - {mushafRequest.no_request}
                </p>
              </div>
            </div>
          </div>

          <!-- Comparison Section -->
          <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-xs font-medium text-gray-500 mb-2">Yang Diajukan</p>
                <div class="space-y-1">
                  <p class="text-sm text-gray-700">A5: <span class="font-semibold">{mushafRequest.jumlah_mushaf_a5}</span></p>
                  <p class="text-sm text-gray-700">A6: <span class="font-semibold">{mushafRequest.jumlah_mushaf_a6}</span></p>
                  <p class="text-sm text-gray-700">IQRA: <span class="font-semibold">{mushafRequest.jumlah_iqra}</span></p>
                  <p class="text-sm font-bold text-gray-900 pt-2 border-t">Total: {totalRequested}</p>
                </div>
              </div>
              <div>
                <p class="text-xs font-medium text-gray-500 mb-2">Yang Disetujui</p>
                <div class="space-y-1">
                  <p class="text-sm text-blue-700">A5: <span class="font-semibold">{quantityForm.jumlah_mushaf_a5_approved || 0}</span></p>
                  <p class="text-sm text-blue-700">A6: <span class="font-semibold">{quantityForm.jumlah_mushaf_a6_approved || 0}</span></p>
                  <p class="text-sm text-blue-700">IQRA: <span class="font-semibold">{quantityForm.jumlah_iqra_approved || 0}</span></p>
                  <p class="text-sm font-bold text-blue-900 pt-2 border-t">Total: {totalApproved}</p>
                </div>
              </div>
            </div>
            {#if totalApproved !== totalRequested}
              <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-xs text-yellow-800">
                  <strong>Perbedaan:</strong>
                  {totalApproved > totalRequested ? '+' : ''}{totalApproved - totalRequested}
                  ({Math.abs(((totalApproved - totalRequested) / totalRequested) * 100).toFixed(1)}%)
                </p>
              </div>
            {/if}
          </div>

          <!-- Form -->
          <div class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label for="jumlah_mushaf_a5_approved" class="block text-sm font-medium text-gray-700 mb-1">
                  Mushaf A5 <span class="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  id="jumlah_mushaf_a5_approved"
                  bind:value={quantityForm.jumlah_mushaf_a5_approved}
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <div>
                <label for="jumlah_mushaf_a6_approved" class="block text-sm font-medium text-gray-700 mb-1">
                  Mushaf A6 <span class="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  id="jumlah_mushaf_a6_approved"
                  bind:value={quantityForm.jumlah_mushaf_a6_approved}
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <div>
                <label for="jumlah_iqra_approved" class="block text-sm font-medium text-gray-700 mb-1">
                  IQRA <span class="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  id="jumlah_iqra_approved"
                  bind:value={quantityForm.jumlah_iqra_approved}
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <div>
              <label for="catatan_perubahan_jumlah" class="block text-sm font-medium text-gray-700 mb-1">
                Catatan Perubahan
              </label>
              <textarea
                id="catatan_perubahan_jumlah"
                bind:value={quantityForm.catatan_perubahan_jumlah}
                rows="3"
                placeholder="Jelaskan alasan perubahan jumlah (opsional)"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
              <p class="mt-1 text-xs text-gray-500">Catatan ini akan terlihat di public tracking</p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex flex-row-reverse gap-3">
          <button
            type="button"
            on:click={handleUpdateQuantities}
            disabled={isLoading}
            class="inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {isLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
          </button>
          <button
            type="button"
            on:click={closeEditQuantityModal}
            disabled={isLoading}
            class="inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm disabled:opacity-50 transition-colors"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Edit Lembaga Modal -->
{#if showEditLembagaModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="edit-info-modal" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        on:click={() => showEditLembagaModal = false}
        on:keydown={(e) => e.key === 'Escape' && (showEditLembagaModal = false)}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <form on:submit|preventDefault={handleUpdateLembaga}>
          <div class="flex items-start justify-between mb-6">
            <div class="flex items-center">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <HeroIcon name="pencil-square" class="h-6 w-6 text-blue-600" />
              </div>
              <div class="ml-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Edit Informasi Lembaga
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  {mushafRequest.nama_lembaga} - {mushafRequest.no_request}
                </p>
              </div>
            </div>
          </div>

          <div class="max-h-[70vh] overflow-y-auto px-1">
            <!-- Informasi Lembaga -->
            <div class="mb-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                  <label for="nama_lembaga" class="block text-sm font-medium text-gray-700 mb-1">
                    Nama Lembaga <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="nama_lembaga"
                    bind:value={lembagaForm.nama_lembaga}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                  />
                </div>

                <div class="md:col-span-2">
                  <label for="kategori_lembaga" class="block text-sm font-medium text-gray-700 mb-1">
                    Kategori Lembaga <span class="text-red-500">*</span>
                  </label>
                  <select
                    id="kategori_lembaga"
                    bind:value={lembagaForm.kategori_lembaga}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                  >
                    <option value="">Pilih Kategori Lembaga</option>

                    <!-- Kategori 1: Lembaga Pendidikan & Pembinaan -->
                    <optgroup label="🎓 Lembaga Pendidikan & Pembinaan">
                      <option value="Pondok Pesantren">Pondok Pesantren</option>
                      <option value="Rumah Tahfidz/Rumah Qur'an">Rumah Tahfidz/Rumah Qur'an</option>
                      <option value="TPQ/TPA/Madin">TPQ/TPA/Madin</option>
                      <option value="Sekolah/Madrasah">Sekolah/Madrasah</option>
                    </optgroup>

                    <!-- Kategori 2: Komunitas & Dakwah Kemasyarakatan -->
                    <optgroup label="🕌 Komunitas & Dakwah Kemasyarakatan">
                      <option value="Masjid/Mushola/Majelis Taklim/Jamaah Masjid">Masjid/Mushola/Majelis Taklim/Jamaah Masjid</option>
                      <option value="Masyarakat/Jamaah Alfatihah">Masyarakat/Jamaah Alfatihah</option>
                      <option value="Organisasi/Paguyuban/Event Sosial/Komunitas">Organisasi/Paguyuban/Event Sosial/Komunitas</option>
                      <option value="Santri & Karyawan Alfatihah">Santri & Karyawan Alfatihah</option>
                    </optgroup>

                    <!-- Kategori 3: Lembaga Sosial & Pemerintahan -->
                    <optgroup label="🏛️ Lembaga Sosial & Pemerintahan">
                      <option value="Yayasan">Yayasan</option>
                      <option value="Panti Asuhan/Anak Yatim">Panti Asuhan/Anak Yatim</option>
                      <option value="RT/RW/Pemerintah Desa/Kecamatan">RT/RW/Pemerintah Desa/Kecamatan</option>
                      <option value="Lembaga Lainnya">Lembaga lainnya yang membutuhkan</option>
                    </optgroup>

                    <!-- Kategori 4: Penerima Manfaat Khusus -->
                    <optgroup label="🤲 Penerima Manfaat Khusus">
                      <option value="Muallaf">Muallaf</option>
                      <option value="Penerima Manfaat Khusus Lainnya">Penerima Manfaat Khusus Lainnya</option>
                    </optgroup>
                  </select>
                  <p class="text-xs text-gray-500 mt-1">Pilih kategori yang paling sesuai dengan lembaga</p>
                </div>
              </div>

              <!-- Address Form Component -->
              <div class="mt-6">
                <h5 class="text-sm font-semibold text-gray-700 mb-4">📍 Alamat Lengkap</h5>
                <AddressFormIndonesia bind:form={lembagaForm} errors={{}} />
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                <div>
                  <label for="sumber_info" class="block text-sm font-medium text-gray-700 mb-1">
                    Sumber Informasi
                  </label>
                  <select
                    id="sumber_info"
                    bind:value={lembagaForm.sumber_info}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  >
                    <option value="">Pilih sumber informasi</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Facebook">Facebook</option>
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Teman/Kenalan">Teman/Kenalan</option>
                    <option value="Website">Website</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>

                <div class="md:col-span-2">
                  <label for="urgensi_request" class="block text-sm font-medium text-gray-700 mb-1">
                    Ceritakan urgensi "Kenapa mengajukan permintaan Qur'an?" <span class="text-red-500">*</span>
                  </label>
                  <textarea
                    id="urgensi_request"
                    bind:value={lembagaForm.urgensi_request}
                    rows="5"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="Jelaskan secara detail mengapa lembaga Anda membutuhkan Al-Qur'an..."
                    required
                  ></textarea>
                </div>

                <div>
                  <label for="latitude" class="block text-sm font-medium text-gray-700 mb-1">
                    Latitude <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    step="any"
                    id="latitude"
                    bind:value={lembagaForm.latitude}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="-6.123456"
                    required
                  />
                </div>

                <div>
                  <label for="longitude" class="block text-sm font-medium text-gray-700 mb-1">
                    Longitude <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    step="any"
                    id="longitude"
                    bind:value={lembagaForm.longitude}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="106.789012"
                    required
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              on:click={() => showEditLembagaModal = false}
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              disabled={isLoading}
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={isLoading}
            >
              {isLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
{/if}

<!-- Edit Kontak Modal -->
{#if showEditKontakModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="edit-kontak-modal" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        on:click={() => showEditKontakModal = false}
        on:keydown={(e) => e.key === 'Escape' && (showEditKontakModal = false)}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <form on:submit|preventDefault={handleUpdateKontak}>
          <div class="flex items-start justify-between mb-6">
            <div class="flex items-center">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <HeroIcon name="pencil-square" class="h-6 w-6 text-blue-600" />
              </div>
              <div class="ml-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Edit Informasi Kontak
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  {mushafRequest.nama_lembaga} - {mushafRequest.no_request}
                </p>
              </div>
            </div>
          </div>

          <div class="max-h-[70vh] overflow-y-auto px-1">
            <!-- Pengurus 1 -->
            <div class="mb-6">
              <h4 class="text-md font-semibold text-gray-900 mb-4 pb-2 border-b">Pengurus 1</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label for="nama_pengurus_1" class="block text-sm font-medium text-gray-700 mb-1">
                    Nama <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="nama_pengurus_1"
                    bind:value={kontakForm.nama_pengurus_1}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                  />
                </div>

                <div>
                  <label for="jabatan_pengurus_1" class="block text-sm font-medium text-gray-700 mb-1">
                    Jabatan <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="jabatan_pengurus_1"
                    bind:value={kontakForm.jabatan_pengurus_1}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    required
                  />
                </div>

                <div>
                  <label for="whatsapp_pengurus_1" class="block text-sm font-medium text-gray-700 mb-1">
                    WhatsApp <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="whatsapp_pengurus_1"
                    bind:value={kontakForm.whatsapp_pengurus_1}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="628123456789"
                    required
                  />
                </div>
              </div>
            </div>

            <!-- Pengurus 2 -->
            <div class="mb-6">
              <h4 class="text-md font-semibold text-gray-900 mb-4 pb-2 border-b">Pengurus 2 (Opsional)</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label for="nama_pengurus_2" class="block text-sm font-medium text-gray-700 mb-1">
                    Nama
                  </label>
                  <input
                    type="text"
                    id="nama_pengurus_2"
                    bind:value={kontakForm.nama_pengurus_2}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  />
                </div>

                <div>
                  <label for="jabatan_pengurus_2" class="block text-sm font-medium text-gray-700 mb-1">
                    Jabatan
                  </label>
                  <input
                    type="text"
                    id="jabatan_pengurus_2"
                    bind:value={kontakForm.jabatan_pengurus_2}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  />
                </div>

                <div>
                  <label for="whatsapp_pengurus_2" class="block text-sm font-medium text-gray-700 mb-1">
                    WhatsApp
                  </label>
                  <input
                    type="text"
                    id="whatsapp_pengurus_2"
                    bind:value={kontakForm.whatsapp_pengurus_2}
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="628123456789"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              on:click={() => showEditKontakModal = false}
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              disabled={isLoading}
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={isLoading}
            >
              {isLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
{/if}

<!-- Edit Files Modal -->
{#if showEditFilesModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="edit-files-modal" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        on:click={() => showEditFilesModal = false}
        on:keydown={(e) => e.key === 'Escape' && (showEditFilesModal = false)}
        role="button"
        tabindex="0"
        aria-label="Close modal"
        transition:fade={{ duration: 200 }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <form on:submit|preventDefault={handleUpdateFiles}>
          <div class="flex items-start justify-between mb-6">
            <div class="flex items-center">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <HeroIcon name="pencil-square" class="h-6 w-6 text-blue-600" />
              </div>
              <div class="ml-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Edit File Lampiran
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  {mushafRequest.nama_lembaga} - {mushafRequest.no_request}
                </p>
              </div>
            </div>
          </div>

          <div class="max-h-[70vh] overflow-y-auto px-1">
            <div class="space-y-4">
              <!-- Foto Santri -->
              <div>
                <label for="foto_santri_file" class="block text-sm font-medium text-gray-700 mb-2">Foto Santri</label>
                {#if mushafRequest.foto_santri_path && !filesForm.delete_foto_santri}
                  <div class="flex items-center space-x-3 mb-2">
                    <span class="text-sm text-gray-600">File saat ini: {mushafRequest.foto_santri_path.split('/').pop()}</span>
                    <button
                      type="button"
                      on:click={() => filesForm.delete_foto_santri = true}
                      class="text-xs text-red-600 hover:text-red-800"
                    >
                      Hapus
                    </button>
                  </div>
                {/if}
                {#if filesForm.delete_foto_santri}
                  <p class="text-sm text-red-600 mb-2">File akan dihapus</p>
                {/if}
                <input
                  id="foto_santri_file"
                  type="file"
                  accept="image/*"
                  on:change={(e) => filesForm.foto_santri = e.target.files[0]}
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <p class="mt-1 text-xs text-gray-500">Max 2MB, format: JPG, PNG</p>
              </div>

              <!-- Foto Lembaga -->
              <div>
                <label for="foto_lembaga_file" class="block text-sm font-medium text-gray-700 mb-2">Foto Lembaga</label>
                {#if mushafRequest.foto_lembaga_path && !filesForm.delete_foto_lembaga}
                  <div class="flex items-center space-x-3 mb-2">
                    <span class="text-sm text-gray-600">File saat ini: {mushafRequest.foto_lembaga_path.split('/').pop()}</span>
                    <button
                      type="button"
                      on:click={() => filesForm.delete_foto_lembaga = true}
                      class="text-xs text-red-600 hover:text-red-800"
                    >
                      Hapus
                    </button>
                  </div>
                {/if}
                {#if filesForm.delete_foto_lembaga}
                  <p class="text-sm text-red-600 mb-2">File akan dihapus</p>
                {/if}
                <input
                  id="foto_lembaga_file"
                  type="file"
                  accept="image/*"
                  on:change={(e) => filesForm.foto_lembaga = e.target.files[0]}
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <p class="mt-1 text-xs text-gray-500">Max 2MB, format: JPG, PNG</p>
              </div>

              <!-- File Nama Santri -->
              <div>
                <label for="file_nama_santri_file" class="block text-sm font-medium text-gray-700 mb-2">File Nama Santri</label>
                {#if mushafRequest.file_nama_santri_path && !filesForm.delete_file_nama_santri}
                  <div class="flex items-center space-x-3 mb-2">
                    <span class="text-sm text-gray-600">File saat ini: {mushafRequest.file_nama_santri_path.split('/').pop()}</span>
                    <button
                      type="button"
                      on:click={() => filesForm.delete_file_nama_santri = true}
                      class="text-xs text-red-600 hover:text-red-800"
                    >
                      Hapus
                    </button>
                  </div>
                {/if}
                {#if filesForm.delete_file_nama_santri}
                  <p class="text-sm text-red-600 mb-2">File akan dihapus</p>
                {/if}
                <input
                  id="file_nama_santri_file"
                  type="file"
                  accept=".pdf,.xlsx,.xls,.doc,.docx"
                  on:change={(e) => filesForm.file_nama_santri = e.target.files[0]}
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <p class="mt-1 text-xs text-gray-500">Max 5MB, format: PDF, Excel, Word</p>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-3 pt-4 border-t">
            <button
              type="button"
              on:click={() => showEditFilesModal = false}
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              disabled={isLoading}
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={isLoading}
            >
              {isLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
{/if}

</AdminLayout>
