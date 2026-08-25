<script>
  import { router } from '@inertiajs/svelte';
  import PublicLayout from '@/Layouts/PublicLayout.svelte';
  import AddressFormIndonesia from '@/Components/AddressFormIndonesia.svelte';
  import { onMount } from 'svelte';
  import { toast } from '../../utils/notifications.js';
  import { formatPageTitle, pageTitles, generateMetaDescription } from '@/utils/seo.js';
  
  // Omit unused page props to reduce build warnings
  export let errors = {};

  // SEO data
  const seoTitle = formatPageTitle(pageTitles.publicMushafRequest);
  const seoDescription = generateMetaDescription('mushafRequest');
  export let settings = {};
  export let old = {};
  
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
  
  // Helper function to create optimal WhatsApp URL
  function createWhatsAppUrl(phone, message = '') {
    const formattedPhone = formatPhoneForWhatsApp(phone);
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (message) {
      // With message
      if (isMobile) {
        return `https://wa.me/${formattedPhone}?text=${encodeURIComponent(message)}`;
      } else {
        return `https://api.whatsapp.com/send/?phone=${formattedPhone}&text=${encodeURIComponent(message)}&type=phone_number&app_absent=0`;
      }
    } else {
      // Without message - simple contact
      return `https://wa.me/${formattedPhone}`;
    }
  }
  
  let form = {
    nama_lembaga: old.nama_lembaga || '',
    kategori_lembaga: old.kategori_lembaga || '',
    // Address fields - detailed with API IDs
    provinsi: old.provinsi || '',
    provinsi_id: old.provinsi_id || '',
    kota_kabupaten: old.kota_kabupaten || '',
    kota_kabupaten_id: old.kota_kabupaten_id || '',
    kecamatan: old.kecamatan || '',
    kecamatan_id: old.kecamatan_id || '',
    kelurahan_desa: old.kelurahan_desa || '',
    kelurahan_desa_id: old.kelurahan_desa_id || '',
    kode_pos: old.kode_pos || '',
    alamat_detail: old.alamat_detail || '',
    latitude: old.latitude || '',
    longitude: old.longitude || '',
    // Legacy field for backward compatibility
    alamat_lengkap: old.alamat_lengkap || '',
    
    nama_pengurus_1: old.nama_pengurus_1 || '',
    jabatan_pengurus_1: old.jabatan_pengurus_1 || '',
    whatsapp_pengurus_1: old.whatsapp_pengurus_1 || '',
    nama_pengurus_2: old.nama_pengurus_2 || '',
    jabatan_pengurus_2: old.jabatan_pengurus_2 || '',
    whatsapp_pengurus_2: old.whatsapp_pengurus_2 || '',
    urgensi_request: old.urgensi_request || '',
    jumlah_mushaf: old.jumlah_mushaf || 0,
    jumlah_mushaf_a5: old.jumlah_mushaf_a5 || 0,
    jumlah_mushaf_a6: old.jumlah_mushaf_a6 || 0,
    jumlah_iqra: old.jumlah_iqra || 0,
    jenis_mushaf_diminta: old.jenis_mushaf_diminta || [],
    sumber_info: old.sumber_info || '',
    foto_santri: null,
    foto_lembaga: null,
    file_nama_santri: null
  };
  
  let loading = false;
  let agreementChecked = false;
  let fotoSantriPreview = null;
  let fotoLembagaPreview = null;
  let fileNamaSantriPreview = null;
  let gettingLocation = false;
  let coordinatesDetected = false;
  
  // Validation states
  let validationErrors = {};
  let showValidation = false;
  
  // Handle form submission errors - especially 413 Payload Too Large
  function handleFormSubmitError() {
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
      return originalFetch.apply(this, args).then(response => {
        // If response status is 413 (Payload Too Large)
        if (response.status === 413) {
          // Reset loading state
          loading = false;
          
          // Set validation errors for all file fields
          validationErrors.foto_santri = 'Ukuran file terlalu besar. Maksimal 2MB.';
          validationErrors.foto_lembaga = 'Ukuran file terlalu besar. Maksimal 2MB.';
          validationErrors.file_nama_santri = 'Ukuran file terlalu besar. Maksimal 2MB.';
          showValidation = true;
          
          // Create a custom error to scroll to the top of the form
          validationErrors.error = 'Total ukuran file melebihi batas maksimum 6MB. Silakan kompres file Anda.';
          
          // Scroll to first error field
          setTimeout(() => {
            const firstErrorField = document.querySelector('.border-red-500');
            if (firstErrorField) {
              firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }, 100);
          
          // Create a clone to avoid 'body used already' error but still returning original response
          return response.clone();
        }
        return response;
      });
    };
  }
  
  // Initialize error handler when component mounts
  onMount(() => {
    handleFormSubmitError();
  });
  
  // Function to validate form before submit
  function validateForm() {
    validationErrors = {};
    
    // Validate required fields
    if (!form.nama_lembaga?.trim()) {
      validationErrors.nama_lembaga = 'Nama lembaga wajib diisi';
    }
    
    if (!form.kategori_lembaga) {
      validationErrors.kategori_lembaga = 'Kategori lembaga wajib dipilih';
    }
    
    if (!form.latitude) {
      validationErrors.latitude = 'Koordinat latitude wajib diisi';
    }
    
    if (!form.longitude) {
      validationErrors.longitude = 'Koordinat longitude wajib diisi';
    }
    
    if (!form.alamat_detail?.trim()) {
      validationErrors.alamat_detail = 'Alamat detail wajib diisi';
    }
    
    if (!form.nama_pengurus_1?.trim()) {
      validationErrors.nama_pengurus_1 = 'Nama pengurus 1 wajib diisi';
    }
    
    if (!form.jabatan_pengurus_1?.trim()) {
      validationErrors.jabatan_pengurus_1 = 'Jabatan pengurus 1 wajib diisi';
    }
    
    if (!form.whatsapp_pengurus_1?.trim()) {
      validationErrors.whatsapp_pengurus_1 = 'WhatsApp pengurus 1 wajib diisi';
    } else if (!/^(\+62|62|0)8[1-9][0-9]{6,9}$/.test(form.whatsapp_pengurus_1)) {
      validationErrors.whatsapp_pengurus_1 = 'Format WhatsApp tidak valid (contoh: 08123456789)';
    }
    
    if (!form.nama_pengurus_2?.trim()) {
      validationErrors.nama_pengurus_2 = 'Nama pengurus 2 wajib diisi';
    }
    
    if (!form.jabatan_pengurus_2?.trim()) {
      validationErrors.jabatan_pengurus_2 = 'Jabatan pengurus 2 wajib diisi';
    }
    
    if (!form.whatsapp_pengurus_2?.trim()) {
      validationErrors.whatsapp_pengurus_2 = 'WhatsApp pengurus 2 wajib diisi';
    } else if (!/^(\+62|62|0)8[1-9][0-9]{6,9}$/.test(form.whatsapp_pengurus_2)) {
      validationErrors.whatsapp_pengurus_2 = 'Format WhatsApp tidak valid (contoh: 08123456789)';
    }
    
    if (!form.urgensi_request?.trim()) {
      validationErrors.urgensi_request = 'Urgensi permintaan wajib diisi';
    }
    
    // Validate jenis mushaf dan jumlah
    if (!form.jenis_mushaf_diminta || form.jenis_mushaf_diminta.length === 0) {
      validationErrors.jenis_mushaf_diminta = 'Pilih minimal 1 jenis mushaf';
    } else {
      // Check konsistensi jenis dan jumlah
      if (form.jenis_mushaf_diminta.includes('A5') && (!form.jumlah_mushaf_a5 || form.jumlah_mushaf_a5 <= 0)) {
        validationErrors.jumlah_mushaf_a5 = 'Jumlah Mushaf A5 harus diisi jika dipilih';
      }
      if (form.jenis_mushaf_diminta.includes('A6') && (!form.jumlah_mushaf_a6 || form.jumlah_mushaf_a6 <= 0)) {
        validationErrors.jumlah_mushaf_a6 = 'Jumlah Mushaf A6 harus diisi jika dipilih';
      }
      if (form.jenis_mushaf_diminta.includes('IQRA') && (!form.jumlah_iqra || form.jumlah_iqra <= 0)) {
        validationErrors.jumlah_iqra = 'Jumlah IQRA harus diisi jika dipilih';
      }
    }
    
    if (!form.sumber_info) {
      validationErrors.sumber_info = 'Sumber informasi wajib dipilih';
    }
    
    // Validate file uploads
    if (!form.foto_santri) {
      validationErrors.foto_santri = 'Foto santri wajib diupload';
    }
    
    if (!form.foto_lembaga) {
      validationErrors.foto_lembaga = 'Foto lembaga wajib diupload';
    }
    
    if (!form.file_nama_santri) {
      validationErrors.file_nama_santri = 'File nama santri wajib diupload';
    }
    
    if (!agreementChecked) {
      validationErrors.agreement = 'Anda harus menyetujui ketentuan yang berlaku';
    }
    
    return Object.keys(validationErrors).length === 0;
  }
  
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
  
  function handleJenisMushafChange(event) {
    const value = event.target.value;
    const checked = event.target.checked;
    
    if (checked) {
      form.jenis_mushaf_diminta = [...form.jenis_mushaf_diminta, value];
      // Set default value 1 untuk field jumlah yang baru muncul
      if (value === 'A5' && !form.jumlah_mushaf_a5) {
        form.jumlah_mushaf_a5 = 1;
      } else if (value === 'A6' && !form.jumlah_mushaf_a6) {
        form.jumlah_mushaf_a6 = 1;
      } else if (value === 'IQRA' && !form.jumlah_iqra) {
        form.jumlah_iqra = 1;
      }
    } else {
      form.jenis_mushaf_diminta = form.jenis_mushaf_diminta.filter(item => item !== value);
      // Reset jumlah ke 0 ketika jenis mushaf di-uncheck
      if (value === 'A5') {
        form.jumlah_mushaf_a5 = 0;
      } else if (value === 'A6') {
        form.jumlah_mushaf_a6 = 0;
      } else if (value === 'IQRA') {
        form.jumlah_iqra = 0;
      }
    }
  }
  
  function handleFileChange(event, type) {
    const file = event.target.files[0];
    
    if (!file) {
      form[type] = null;
      // Hapus error jika file dihapus
      if (validationErrors[type]) {
        delete validationErrors[type];
        validationErrors = {...validationErrors}; // Trigger reactivity
      }
      return;
    }
    
    // Validasi format file
    const allowedFormats = {
      'foto_santri': ['jpg', 'jpeg', 'png', 'webp'],
      'foto_lembaga': ['jpg', 'jpeg', 'png', 'webp'],
      'file_nama_santri': ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx']
    };
    
    const fileExtension = file.name.split('.').pop().toLowerCase();
    if (!allowedFormats[type].includes(fileExtension)) {
      const formatLabels = {
        'foto_santri': 'JPG, JPEG, PNG, atau WEBP',
        'foto_lembaga': 'JPG, JPEG, PNG, atau WEBP',
        'file_nama_santri': 'JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, atau XLSX'
      };
      
      validationErrors[type] = `Format file tidak didukung. Hanya menerima format: ${formatLabels[type]}`;
      validationErrors = {...validationErrors}; // Trigger reactivity
      event.target.value = ''; // Reset input
      return;
    }
    
    // Validasi ukuran file (2MB = 2097152 bytes)
    const maxSize = 2097152; // 2MB
    
    if (file.size > maxSize) {
      validationErrors[type] = `Ukuran file terlalu besar: ${formatFileSize(file.size)}. Maksimal 2MB.`;
      validationErrors = {...validationErrors}; // Trigger reactivity
      event.target.value = ''; // Reset input
      return;
    }
    
    // Hapus error jika file valid
    if (validationErrors[type]) {
      delete validationErrors[type];
      validationErrors = {...validationErrors}; // Trigger reactivity
    }
    
    form[type] = file;
    
    // Preview untuk gambar
    if ((type === 'foto_santri' || type === 'foto_lembaga' || type === 'file_nama_santri') && file && ['jpg', 'jpeg', 'png', 'webp'].includes(fileExtension)) {
      const reader = new FileReader();
      reader.onload = (e) => {
        if (type === 'foto_santri') {
          fotoSantriPreview = e.target.result;
        } else if (type === 'foto_lembaga') {
          fotoLembagaPreview = e.target.result;
        } else if (type === 'file_nama_santri') {
          fileNamaSantriPreview = e.target.result;
        }
      };
      reader.readAsDataURL(file);
    }
  }
  
  // Auto-generate alamat lengkap ketika komponen alamat berubah
  function updateAlamatLengkap() {
    const components = [
      form.alamat_detail,
      form.kelurahan_desa,
      form.kecamatan,
      form.kota_kabupaten,
      form.provinsi,
      form.kode_pos
    ].filter(Boolean);
    
    form.alamat_lengkap = components.join(', ');
  }
  
  // Watch for address field changes
  $: {
    if (form.provinsi || form.kota_kabupaten || form.kecamatan || form.kelurahan_desa || form.alamat_detail || form.kode_pos) {
      updateAlamatLengkap();
    }
  }
  
  // Get current location using GPS
  function getCurrentLocation() {
    if (!navigator.geolocation) {
      toast.warning('Geolocation tidak didukung oleh browser ini.');
      return;
    }
    
    gettingLocation = true;
    
    navigator.geolocation.getCurrentPosition(
      (position) => {
        form.latitude = position.coords.latitude.toFixed(8);
        form.longitude = position.coords.longitude.toFixed(8);
        coordinatesDetected = true;
        gettingLocation = false;
      },
      (error) => {
        gettingLocation = false;
        let errorMessage = 'Gagal mendapatkan lokasi: ';
        switch(error.code) {
          case error.PERMISSION_DENIED:
            errorMessage += 'Permintaan akses lokasi ditolak.';
            break;
          case error.POSITION_UNAVAILABLE:
            errorMessage += 'Informasi lokasi tidak tersedia.';
            break;
          case error.TIMEOUT:
            errorMessage += 'Timeout dalam mendapatkan lokasi.';
            break;
          default:
            errorMessage += 'Error tidak dikenal.';
            break;
        }
        toast.error(errorMessage);
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000
      }
    );
  }
  
  // Reset coordinates
  function resetCoordinates() {
    form.latitude = '';
    form.longitude = '';
    coordinatesDetected = false;
  }
  
  function submitForm() {
    showValidation = true;
    
    if (!validateForm()) {
      // Scroll to first error
      const firstErrorField = document.querySelector('.border-red-500');
      if (firstErrorField) {
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }
    
    if (!agreementChecked) {
      toast.warning('Anda harus menyetujui ketentuan terlebih dahulu');
      return;
    }
    
    loading = true;
    
    const formData = new FormData();
    
    Object.keys(form).forEach(key => {
      if (key === 'jenis_mushaf_diminta') {
        form[key].forEach(item => {
          formData.append('jenis_mushaf_diminta[]', item);
        });
      } else if (form[key] !== null && form[key] !== undefined && form[key] !== '') {
        formData.append(key, form[key]);
      }
    });
    
    router.post('/mushaf-request', formData, {
      onFinish: () => {
        loading = false;
      },
      preserveState: true
    });
  }
  
  $: totalMushaf = parseInt(form.jumlah_mushaf_a5 || 0) + parseInt(form.jumlah_mushaf_a6 || 0) + parseInt(form.jumlah_iqra || 0);
  $: form.jumlah_mushaf = parseInt(form.jumlah_mushaf_a5 || 0) + parseInt(form.jumlah_mushaf_a6 || 0); // Legacy field for backend compatibility
  $: hasCoordinates = form.latitude && form.longitude;
</script>

<svelte:head>
  <title>{seoTitle}</title>
  <meta name="description" content="{seoDescription}">
  
  <!-- Open Graph / Facebook -->
  <meta property="og:title" content="{seoTitle}">
  <meta property="og:description" content="{seoDescription}">
  
  <!-- Twitter -->
  <meta property="twitter:title" content="{seoTitle}">
  <meta property="twitter:description" content="{seoDescription}">
</svelte:head>

<PublicLayout {settings}>
  <div class="bg-gradient-to-br from-white to-red-50">
    <!-- Hero Section -->
    <section class="relative flex items-center justify-center overflow-hidden pt-32 pb-20">
      <!-- Islamic Pattern Background -->
      <div class="absolute inset-0 opacity-30 islamic-geometric"></div>
      
      <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Main Content -->
        <div class="text-center mb-12">
          <div class="inline-flex items-center justify-center w-20 h-20 bg-[#eb3434] rounded-full mb-6">
            <span class="text-white text-3xl">📖</span>
          </div>
          <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
            <span class="block">Formulir Permintaan</span>
            <span class="block text-[#eb3434]">Mushaf Al-Qur'an</span>
          </h1>
          <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Ajukan permintaan mushaf Al-Qur'an gratis untuk lembaga Anda
          </p>
        </div>

        <!-- Form Container -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
          <!-- Ketentuan Permintaan -->
          <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8">
            <h2 class="text-xl font-bold text-yellow-800 mb-4">⚠️ Ketentuan Permintaan Al-Qur'an</h2>
            <ol class="list-decimal list-inside space-y-3 text-yellow-800">
              <li>Pihak Ekspedisi Qur'an disebut sebagai Pihak Pertama sementara pihak yang meminta Al-Qur'an adalah pihak kedua.</li>
              <li>Pihak kedua wajib menaati ketentuan yang sudah ditetapkan oleh pihak pertama.</li>
              <li><strong>Ketentuan yang wajib dilakukan oleh pihak kedua:</strong>
                <ul class="list-disc list-inside ml-4 mt-2 space-y-1">
                  <li>Wajib mencantumkan alamat yang jelas agar bisa diantar ke tempat tujuan</li>
                  <li>Pihak kedua diwajibkan untuk mengirimkan dokumentasi</li>
                </ul>
              </li>
              <li><strong>Berkenan untuk didokumentasikan</strong> berupa foto dan video untuk laporan distribusi ke donatur serta akan dipublikasikan di sosial media.</li>
              <li class="text-red-600 font-semibold">⚠️ Kami akan melakukan <u>blacklist</u> bagi yayasan yang sudah menerima Al-Qur'an namun tidak menaati ketentuan yang berlaku.</li>
            </ol>
          </div>

          <!-- Form -->
          <form on:submit|preventDefault={submitForm} class="space-y-8">
            
            <!-- Validation Summary Error -->
            {#if showValidation && validationErrors.error}
              <div class="bg-red-50 border-2 border-red-300 rounded-xl p-6 mb-8">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <h3 class="text-lg font-bold text-red-800 mb-2">Kesalahan: {validationErrors.error}</h3>
                    <p class="text-red-700 mb-4">Mohon periksa ukuran file yang Anda unggah. Server menolak permintaan karena ukuran file terlalu besar.</p>
                    
                    <div class="bg-white p-3 rounded-lg border border-red-200">
                      <h4 class="font-medium text-red-800 mb-2">Tips mengatasi masalah ukuran file:</h4>
                      <ul class="list-disc list-inside space-y-1 text-gray-700 text-sm">
                        <li>Kompres file gambar menggunakan layanan online seperti TinyPNG, CompressJPEG, dll.</li>
                        <li>Kurangi resolusi/dimensi gambar jika terlalu besar</li>
                        <li>Simpan dokumen dengan format yang lebih efisien (PDF terkompresi)</li>
                        <li>Pastikan total ukuran file tidak melebihi 10MB</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            {/if}
            
            <!-- Error Summary -->
            {#if showValidation && Object.keys(validationErrors).length > 0}
              <div class="bg-red-50 border-2 border-red-200 rounded-xl p-6">
                <div class="flex items-center mb-4">
                  <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-white text-sm font-bold">!</span>
                  </div>
                  <h3 class="text-red-800 font-bold text-lg">❌ Terdapat {Object.keys(validationErrors).length} kesalahan yang perlu diperbaiki:</h3>
                </div>
                <ul class="space-y-2 text-sm">
                  {#each Object.entries(validationErrors) as [field, message]}
                    <li class="flex items-start">
                      <span class="text-red-600 mr-2">•</span>
                      <span class="text-red-700 font-medium">{message}</span>
                    </li>
                  {/each}
                </ul>
                <p class="text-red-600 text-sm mt-4 font-medium">📍 Silakan scroll ke bawah untuk memperbaiki field yang ditandai merah.</p>
              </div>
            {/if}
            <!-- Data Lembaga -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-6">📖 Data Lembaga</h3>
              
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <label for="nama-lembaga" class="block text-sm font-medium text-gray-700 mb-2">Nama Lembaga *</label>
                  <input
                    id="nama-lembaga"
                    type="text"
                    bind:value={form.nama_lembaga}
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(errors.nama_lembaga || validationErrors.nama_lembaga) ? 'border-red-500' : ''}"
                    placeholder="Contoh: Pondok Pesantren Al-Barokah"
                    required
                  />
                  {#if errors.nama_lembaga || validationErrors.nama_lembaga}
                    <p class="text-red-500 text-sm mt-1">{errors.nama_lembaga || validationErrors.nama_lembaga}</p>
                  {/if}
                </div>
                
                <div>
                  <label for="kategori-lembaga" class="block text-sm font-medium text-gray-700 mb-2">Kategori Lembaga *</label>
                  <select
                    id="kategori-lembaga"
                    bind:value={form.kategori_lembaga}
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(errors.kategori_lembaga || validationErrors.kategori_lembaga) ? 'border-red-500' : ''}"
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
                  {#if errors.kategori_lembaga || validationErrors.kategori_lembaga}
                    <p class="text-red-500 text-sm mt-1">{errors.kategori_lembaga || validationErrors.kategori_lembaga}</p>
                  {/if}
                  <p class="text-sm text-gray-500 mt-1">Pilih kategori yang paling sesuai dengan lembaga Anda</p>
                </div>
              </div>
            </div>

            <!-- Alamat Detail Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-6">📍 Alamat Lengkap Lembaga</h3>
              
              <!-- Address Form Component -->
              <AddressFormIndonesia bind:form={form} {errors} />

              <!-- Koordinat GPS -->
              <div class="mt-6">
                <h4 class="text-md font-semibold text-gray-700 mb-3">🗺️ Koordinat Lokasi *</h4>
                <p class="text-gray-600 text-sm mb-4">
                  Koordinat GPS diperlukan untuk memudahkan proses pengiriman dan visualisasi peta distribusi Al-Qur'an.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div>
                    <label for="latitude" class="block text-sm font-medium text-gray-700 mb-2">Latitude *</label>
                    <input
                      id="latitude"
                      type="number"
                      step="any"
                      bind:value={form.latitude}
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(errors.latitude || validationErrors.latitude) ? 'border-red-500' : ''}"
                      placeholder="-6.200000"
                      readonly={gettingLocation}
                      required
                    />
                    {#if errors.latitude || validationErrors.latitude}
                      <p class="text-red-500 text-sm mt-1">{errors.latitude || validationErrors.latitude}</p>
                    {/if}
                  </div>

                  <div>
                    <label for="longitude" class="block text-sm font-medium text-gray-700 mb-2">Longitude *</label>
                    <input
                      id="longitude"
                      type="number"
                      step="any"
                      bind:value={form.longitude}
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(errors.longitude || validationErrors.longitude) ? 'border-red-500' : ''}"
                      placeholder="106.816666"
                      readonly={gettingLocation}
                      required
                    />
                    {#if errors.longitude || validationErrors.longitude}
                      <p class="text-red-500 text-sm mt-1">{errors.longitude || validationErrors.longitude}</p>
                    {/if}
                  </div>
                </div>

                <div class="flex flex-wrap gap-3">
                  <button
                    type="button"
                    on:click={getCurrentLocation}
                    disabled={gettingLocation}
                    class="flex items-center px-4 py-3 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                  >
                    {#if gettingLocation}
                      <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                      Mendapatkan Lokasi...
                    {:else}
                      📍 Dapatkan Lokasi Saat Ini
                    {/if}
                  </button>

                  {#if hasCoordinates}
                    <button
                      type="button"
                      on:click={resetCoordinates}
                      class="flex items-center px-4 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors font-medium"
                    >
                      🗑️ Reset Koordinat
                    </button>
                  {/if}
                </div>

                {#if coordinatesDetected}
                  <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-green-800 font-medium">✅ Koordinat berhasil didapatkan!</p>
                    <p class="text-green-700 text-sm mt-1">Lat: {form.latitude}, Lng: {form.longitude}</p>
                  </div>
                {/if}

                {#if !hasCoordinates}
                  <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-800 font-medium">⚠️ Koordinat lokasi diperlukan</p>
                    <p class="text-yellow-700 text-sm mt-1">Klik tombol "Dapatkan Lokasi Saat Ini" untuk mengisi koordinat secara otomatis, atau isi manual jika mengetahui koordinat lokasi.</p>
                    <p class="text-yellow-600 text-xs mt-2">
                      <strong>Tips:</strong> Jika tidak bisa menggunakan GPS, Anda bisa mencari koordinat lembaga di 
                      <a href="https://www.google.com/maps" target="_blank" class="underline hover:no-underline">Google Maps</a> 
                      dengan cara klik kanan pada lokasi lembaga, lalu pilih koordinat yang muncul.
                    </p>
                  </div>
                {/if}
              </div>

              <!-- Preview Alamat Lengkap -->
              {#if form.alamat_lengkap}
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-4">
                  <h4 class="text-md font-semibold text-gray-800 mb-2">📋 Preview Alamat Lengkap:</h4>
                  <p class="text-gray-700">{form.alamat_lengkap}</p>
                </div>
              {/if}
            </div>

            <!-- Pengurus 1 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-6">👤 Pengurus 1</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label for="nama-pengurus-1" class="block text-sm font-medium text-gray-700 mb-2">Nama Pengurus 1 *</label>
                  <input id="nama-pengurus-1" type="text" bind:value={form.nama_pengurus_1} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.nama_pengurus_1 ? 'border-red-500' : ''}" placeholder="Nama lengkap pengurus" required />
                  {#if errors.nama_pengurus_1}<p class="text-red-500 text-sm mt-1">{errors.nama_pengurus_1}</p>{/if}
                </div>
                
                <div>
                  <label for="jabatan-pengurus-1" class="block text-sm font-medium text-gray-700 mb-2">Jabatan Pengurus 1 *</label>
                  <input id="jabatan-pengurus-1" type="text" bind:value={form.jabatan_pengurus_1} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.jabatan_pengurus_1 ? 'border-red-500' : ''}" placeholder="Contoh: Ketua, Direktur, Pengasuh" required />
                  {#if errors.jabatan_pengurus_1}<p class="text-red-500 text-sm mt-1">{errors.jabatan_pengurus_1}</p>{/if}
                </div>
                
                <div>
                  <label for="whatsapp-pengurus-1" class="block text-sm font-medium text-gray-700 mb-2">Nomor WhatsApp Pengurus 1 *</label>
                  <input id="whatsapp-pengurus-1" type="tel" bind:value={form.whatsapp_pengurus_1} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.whatsapp_pengurus_1 ? 'border-red-500' : ''}" placeholder="08xxxxxxxxxx" required />
                  {#if errors.whatsapp_pengurus_1}<p class="text-red-500 text-sm mt-1">{errors.whatsapp_pengurus_1}</p>{/if}
                </div>
              </div>
            </div>

            <!-- Pengurus 2 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-6">👥 Pengurus 2</h3>
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label for="nama-pengurus-2" class="block text-sm font-medium text-gray-700 mb-2">Nama Pengurus 2 *</label>
                  <input id="nama-pengurus-2" type="text" bind:value={form.nama_pengurus_2} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.nama_pengurus_2 ? 'border-red-500' : ''}" placeholder="Nama lengkap pengurus" required />
                  {#if errors.nama_pengurus_2}<p class="text-red-500 text-sm mt-1">{errors.nama_pengurus_2}</p>{/if}
                </div>
                
                <div>
                  <label for="jabatan-pengurus-2" class="block text-sm font-medium text-gray-700 mb-2">Jabatan Pengurus 2 *</label>
                  <input id="jabatan-pengurus-2" type="text" bind:value={form.jabatan_pengurus_2} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.jabatan_pengurus_2 ? 'border-red-500' : ''}" placeholder="Contoh: Sekretaris, Wakil Direktur" required />
                  {#if errors.jabatan_pengurus_2}<p class="text-red-500 text-sm mt-1">{errors.jabatan_pengurus_2}</p>{/if}
                </div>
                
                <div>
                  <label for="whatsapp-pengurus-2" class="block text-sm font-medium text-gray-700 mb-2">Nomor WhatsApp Pengurus 2 *</label>
                  <input id="whatsapp-pengurus-2" type="tel" bind:value={form.whatsapp_pengurus_2} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.whatsapp_pengurus_2 ? 'border-red-500' : ''}" placeholder="08xxxxxxxxxx" required />
                  {#if errors.whatsapp_pengurus_2}<p class="text-red-500 text-sm mt-1">{errors.whatsapp_pengurus_2}</p>{/if}
                </div>
              </div>
            </div>

            <!-- Detail Permintaan -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-6">📝 Detail Permintaan</h3>
              
              <div class="space-y-6">
                <div>
                  <label for="urgensi-request" class="block text-sm font-medium text-gray-700 mb-2">Ceritakan urgensi "Kenapa mengajukan permintaan Qur'an?" *</label>
                  <textarea id="urgensi-request" bind:value={form.urgensi_request} rows="5" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.urgensi_request ? 'border-red-500' : ''}" placeholder="Jelaskan secara detail mengapa lembaga Anda membutuhkan Al-Qur'an..." required></textarea>
                  {#if errors.urgensi_request}<p class="text-red-500 text-sm mt-1">{errors.urgensi_request}</p>{/if}
                </div>
                
                <div>
                  <div class="block text-sm font-medium text-gray-700 mb-3">Permintaan Jenis Mushaf * <span class="text-gray-500">(Pilih minimal 1)</span></div>
                  <div class="space-y-4">
                    <!-- Mushaf Al-Qur'an A5 -->
                    <div class="border border-gray-200 rounded-lg p-4">
                      <label class="flex items-center cursor-pointer">
                        <input 
                          type="checkbox" 
                          value="A5" 
                          on:change={handleJenisMushafChange} 
                          class="w-5 h-5 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434]" 
                        />
                        <span class="ml-3 text-gray-700 font-medium">📖 Mushaf Al-Qur'an A5</span>
                      </label>
                      
                      {#if form.jenis_mushaf_diminta.includes('A5')}
                        <div class="mt-3 ml-8">
                          <label for="jumlah-mushaf-a5" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Permintaan Mushaf A5 *</label>
                          <input 
                            id="jumlah-mushaf-a5"
                            type="number" 
                            bind:value={form.jumlah_mushaf_a5} 
                            min="1" 
                            max="1000" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.jumlah_mushaf_a5 ? 'border-red-500' : ''}" 
                            placeholder="Jumlah Mushaf A5" 
                            required 
                          />
                          {#if errors.jumlah_mushaf_a5}<p class="text-red-500 text-sm mt-1">{errors.jumlah_mushaf_a5}</p>{/if}
                        </div>
                      {/if}
                    </div>
                    
                    <!-- Mushaf Al-Qur'an A6 -->
                    <div class="border border-gray-200 rounded-lg p-4">
                      <label class="flex items-center cursor-pointer">
                        <input 
                          type="checkbox" 
                          value="A6" 
                          on:change={handleJenisMushafChange} 
                          class="w-5 h-5 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434]" 
                        />
                        <span class="ml-3 text-gray-700 font-medium">📘 Mushaf Al-Qur'an A6</span>
                      </label>
                      
                      {#if form.jenis_mushaf_diminta.includes('A6')}
                        <div class="mt-3 ml-8">
                          <label for="jumlah-mushaf-a6" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Permintaan Mushaf A6 *</label>
                          <input 
                            id="jumlah-mushaf-a6"
                            type="number" 
                            bind:value={form.jumlah_mushaf_a6} 
                            min="1" 
                            max="1000" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.jumlah_mushaf_a6 ? 'border-red-500' : ''}" 
                            placeholder="Jumlah Mushaf A6" 
                            required 
                          />
                          {#if errors.jumlah_mushaf_a6}<p class="text-red-500 text-sm mt-1">{errors.jumlah_mushaf_a6}</p>{/if}
                        </div>
                      {/if}
                    </div>
                    
                    <!-- IQRA' -->
                    <div class="border border-gray-200 rounded-lg p-4">
                      <label class="flex items-center cursor-pointer">
                        <input 
                          type="checkbox" 
                          value="IQRA" 
                          on:change={handleJenisMushafChange} 
                          class="w-5 h-5 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434]" 
                        />
                        <span class="ml-3 text-gray-700 font-medium">📚 IQRA'</span>
                      </label>
                      
                      {#if form.jenis_mushaf_diminta.includes('IQRA')}
                        <div class="mt-3 ml-8">
                          <label for="jumlah-iqra" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Permintaan IQRA' *</label>
                          <input 
                            id="jumlah-iqra"
                            type="number" 
                            bind:value={form.jumlah_iqra} 
                            min="1" 
                            max="1000" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.jumlah_iqra ? 'border-red-500' : ''}" 
                            placeholder="Jumlah IQRA'" 
                            required 
                          />
                          {#if errors.jumlah_iqra}<p class="text-red-500 text-sm mt-1">{errors.jumlah_iqra}</p>{/if}
                        </div>
                      {/if}
                    </div>
                  </div>
                  {#if errors.jenis_mushaf_diminta}<p class="text-red-500 text-sm mt-1">{errors.jenis_mushaf_diminta}</p>{/if}
                </div>
                
                {#if totalMushaf > 0}
                  <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="text-green-800 font-semibold mb-2">📊 Ringkasan Permintaan</h4>
                    <div class="space-y-1 text-sm">
                      {#if form.jumlah_mushaf_a5 > 0}
                        <p class="text-green-700">• Mushaf Al-Qur'an A5: <span class="font-semibold">{form.jumlah_mushaf_a5}</span> buah</p>
                      {/if}
                      {#if form.jumlah_mushaf_a6 > 0}
                        <p class="text-green-700">• Mushaf Al-Qur'an A6: <span class="font-semibold">{form.jumlah_mushaf_a6}</span> buah</p>
                      {/if}
                      {#if form.jumlah_iqra > 0}
                        <p class="text-green-700">• IQRA': <span class="font-semibold">{form.jumlah_iqra}</span> buah</p>
                      {/if}
                      <hr class="border-green-300 my-2">
                      <p class="text-green-800 font-bold text-lg">Total: <span class="text-xl">{totalMushaf}</span> buah</p>
                    </div>
                  </div>
                {/if}
                
                <div>
                  <label for="sumber-info" class="block text-sm font-medium text-gray-700 mb-2">Darimana anda mendapatkan info Ekspedisi Qur'an ini? *</label>
                  <select id="sumber-info" bind:value={form.sumber_info} class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.sumber_info ? 'border-red-500' : ''}" required>
                    <option value="">Pilih sumber informasi</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Facebook">Facebook</option>
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Teman/Kenalan">Teman/Kenalan</option>
                    <option value="Website">Website</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                  {#if errors.sumber_info}<p class="text-red-500 text-sm mt-1">{errors.sumber_info}</p>{/if}
                </div>
              </div>
            </div>

            <!-- Upload Files -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-6">📤 Upload Dokumen Pendukung</h3>
              
              <div class="space-y-6">
                <div>
                  <label for="foto-santri" class="block text-sm font-medium text-gray-700 mb-2">Foto Santri (max 2 MB) *</label>
                  <div class="border-2 border-dashed {(errors.foto_santri || validationErrors.foto_santri) ? 'border-red-500 bg-red-50' : 'border-gray-300'} rounded-lg p-6 hover:border-[#eb3434] transition-colors">
                    <input 
                      type="file" 
                      id="foto-santri"
                      accept="image/jpeg,image/jpg,image/png,image/webp" 
                      on:change={(e) => handleFileChange(e, 'foto_santri')} 
                      class="w-full {(errors.foto_santri || validationErrors.foto_santri) ? 'border-red-500 bg-red-50' : ''}" 
                      required 
                    />
                    <p class="text-sm text-gray-500 mt-2">Format: JPG, JPEG, PNG, WEBP | Ukuran: Maksimal 2MB</p>
                    {#if errors.foto_santri || validationErrors.foto_santri}
                      <div class="mt-3 bg-red-50 border border-red-300 rounded-lg p-4 animate-pulse">
                        <div class="flex items-start">
                          <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                          </div>
                          <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                              ❌ {errors.foto_santri || validationErrors.foto_santri}
                            </p>
                            {#if validationErrors.foto_santri && validationErrors.foto_santri.includes('terlalu besar')}
                              <div class="mt-2">
                                <p class="text-xs font-medium text-red-700 mb-1">💡 Tips mengatasi:</p>
                                <ul class="text-xs text-red-600 space-y-1">
                                  <li>• Gunakan TinyPNG.com atau CompressJPEG.com</li>
                                  <li>• Pilih foto dengan resolusi lebih kecil</li>
                                  <li>• Crop foto jika terlalu besar</li>
                                </ul>
                              </div>
                            {/if}
                          </div>
                        </div>
                      </div>
                    {/if}
                    {#if fotoSantriPreview}
                      <div class="mt-4">
                        <img src={fotoSantriPreview} alt="Preview foto santri" class="w-32 h-32 object-cover rounded-lg shadow-md" />
                        <p class="text-sm text-gray-500 mt-2">Preview foto santri</p>
                      </div>
                    {/if}
                    {#if form.foto_santri}
                      <div class="mt-3 bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-green-800 font-medium flex items-center">
                          <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                          </svg>
                          ✅ {form.foto_santri.name}
                        </p>
                        <p class="text-xs text-green-600 mt-1">Ukuran: {formatFileSize(form.foto_santri.size)}</p>
                      </div>
                    {/if}
                  </div>
                </div>
                
                <div>
                  <label for="foto-lembaga" class="block text-sm font-medium text-gray-700 mb-2">Foto Lembaga/Pondok Pesantren Penerima (Max 2 MB) *</label>
                  <div class="border-2 border-dashed {(errors.foto_lembaga || validationErrors.foto_lembaga) ? 'border-red-500 bg-red-50' : 'border-gray-300'} rounded-lg p-6 hover:border-[#eb3434] transition-colors">
                    <input 
                      type="file" 
                      id="foto-lembaga"
                      accept="image/jpeg,image/jpg,image/png,image/webp" 
                      on:change={(e) => handleFileChange(e, 'foto_lembaga')} 
                      class="w-full {(errors.foto_lembaga || validationErrors.foto_lembaga) ? 'border-red-500 bg-red-50' : ''}" 
                      required 
                    />
                    <p class="text-sm text-gray-500 mt-2">Format: JPG, JPEG, PNG, WEBP | Ukuran: Maksimal 2MB</p>
                    {#if errors.foto_lembaga || validationErrors.foto_lembaga}
                      <div class="mt-3 bg-red-50 border border-red-300 rounded-lg p-4 animate-pulse">
                        <div class="flex items-start">
                          <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                          </div>
                          <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                              ❌ {errors.foto_lembaga || validationErrors.foto_lembaga}
                            </p>
                            {#if validationErrors.foto_lembaga && validationErrors.foto_lembaga.includes('terlalu besar')}
                              <div class="mt-2">
                                <p class="text-xs font-medium text-red-700 mb-1">💡 Tips mengatasi:</p>
                                <ul class="text-xs text-red-600 space-y-1">
                                  <li>• Gunakan TinyPNG.com atau CompressJPEG.com</li>
                                  <li>• Pilih foto dengan resolusi lebih kecil</li>
                                  <li>• Crop foto jika terlalu besar</li>
                                </ul>
                              </div>
                            {/if}
                          </div>
                        </div>
                      </div>
                    {/if}
                    {#if fotoLembagaPreview}
                      <div class="mt-4">
                        <img src={fotoLembagaPreview} alt="Preview foto lembaga" class="w-32 h-32 object-cover rounded-lg shadow-md" />
                        <p class="text-sm text-gray-500 mt-2">Preview foto lembaga</p>
                      </div>
                    {/if}
                    {#if form.foto_lembaga}
                      <div class="mt-3 bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-green-800 font-medium flex items-center">
                          <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                          </svg>
                          ✅ {form.foto_lembaga.name}
                        </p>
                        <p class="text-xs text-green-600 mt-1">Ukuran: {formatFileSize(form.foto_lembaga.size)}</p>
                      </div>
                    {/if}
                  </div>
                </div>
                
                <div>
                  <label for="file-nama-santri" class="block text-sm font-medium text-gray-700 mb-2">File Nama Santri (Max 2 MB) *</label>
                  <div class="border-2 border-dashed {(errors.file_nama_santri || validationErrors.file_nama_santri) ? 'border-red-500 bg-red-50' : 'border-gray-300'} rounded-lg p-6 hover:border-[#eb3434] transition-colors">
                    <input 
                      type="file" 
                      id="file-nama-santri"
                      accept="image/jpeg,image/jpg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx" 
                      on:change={(e) => handleFileChange(e, 'file_nama_santri')} 
                      class="w-full {(errors.file_nama_santri || validationErrors.file_nama_santri) ? 'border-red-500 bg-red-50' : ''}" 
                      required 
                    />
                    <p class="text-sm text-gray-500 mt-2">Format: JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX | Ukuran: Maksimal 2MB</p>
                    {#if errors.file_nama_santri || validationErrors.file_nama_santri}
                      <div class="mt-3 bg-red-50 border border-red-300 rounded-lg p-4 animate-pulse">
                        <div class="flex items-start">
                          <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                          </div>
                          <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                              ❌ {errors.file_nama_santri || validationErrors.file_nama_santri}
                            </p>
                            {#if validationErrors.file_nama_santri && validationErrors.file_nama_santri.includes('terlalu besar')}
                              <div class="mt-2">
                                <p class="text-xs font-medium text-red-700 mb-1">💡 Tips mengatasi:</p>
                                <ul class="text-xs text-red-600 space-y-1">
                                  <li>• Gunakan TinyPNG.com, CompressJPEG.com untuk gambar</li>
                                  <li>• Gunakan SmallPDF.com untuk kompres PDF</li>
                                  <li>• Simpan dokumen tanpa gambar/foto</li>
                                  <li>• Buat file baru dengan data yang sama</li>
                                </ul>
                              </div>
                            {/if}
                          </div>
                        </div>
                      </div>
                    {/if}
                    {#if fileNamaSantriPreview}
                      <div class="mt-4">
                        <img src={fileNamaSantriPreview} alt="Preview file nama santri" class="w-32 h-32 object-cover rounded-lg shadow-md" />
                        <p class="text-sm text-gray-500 mt-2">Preview file nama santri</p>
                      </div>
                    {/if}
                    {#if form.file_nama_santri}
                      <div class="mt-3 bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-green-800 font-medium flex items-center">
                          <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                          </svg>
                          ✅ {form.file_nama_santri.name}
                        </p>
                        <p class="text-xs text-green-600 mt-1">Ukuran: {formatFileSize(form.file_nama_santri.size)}</p>
                      </div>
                    {/if}
                  </div>
                </div>
              </div>
            </div>

            <!-- Agreement -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-6 {validationErrors.agreement ? 'border-red-500 bg-red-50' : ''}">
              <label class="flex items-start cursor-pointer">
                <input type="checkbox" bind:checked={agreementChecked} class="w-6 h-6 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434] mt-1 mr-4" required />
                <span class="{validationErrors.agreement ? 'text-red-800' : 'text-blue-800'} text-lg leading-relaxed">
                  <strong>Saya menyetujui</strong> semua ketentuan yang telah ditetapkan dan bersedia melakukan dokumentasi sesuai dengan persyaratan yang berlaku. 
                  Saya memahami bahwa jika tidak mematuhi ketentuan, lembaga kami akan di-blacklist.
                </span>
              </label>
              {#if validationErrors.agreement}
                <p class="text-red-500 text-sm mt-2 font-medium">{validationErrors.agreement}</p>
              {/if}
            </div>

            <!-- Error General -->
            {#if errors.error}
              <div class="bg-red-50 border-2 border-red-200 rounded-xl p-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center mr-3 mt-1">
                    <span class="text-white text-sm font-bold">!</span>
                  </div>
                  <div>
                    <p class="text-red-600 font-semibold text-lg mb-2">{errors.error}</p>
                    {#if errors.error.includes('Ukuran file melebihi batas')}
                      <div class="mt-3 bg-white rounded-lg p-4 border border-red-200">
                        <h4 class="font-medium text-red-800 mb-2">Tips untuk memperkecil ukuran file:</h4>
                        <ul class="list-disc list-inside space-y-1 text-gray-700 text-sm">
                          <li>Gunakan layanan kompres gambar online seperti TinyPNG, CompressJPEG, dll.</li>
                          <li>Kurangi resolusi/dimensi gambar jika terlalu besar</li>
                          <li>Untuk file dokumen, simpan sebagai versi lebih ringan atau gunakan fitur "Compress PDF" jika tersedia</li>
                          <li>Pastikan tidak ada konten yang tidak perlu dalam file dokumen</li>
                        </ul>
                      </div>
                    {/if}
                  </div>
                </div>
              </div>
            {/if}

            <!-- Submit Button -->
            <div class="text-center pt-8">
              <button
                type="submit"
                disabled={loading || !agreementChecked}
                class="px-12 py-5 bg-[#eb3434] text-white text-xl font-bold rounded-2xl hover:bg-red-600 transform hover:scale-105 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none shadow-xl flex items-center mx-auto"
              >
                {#if loading}
                  <div class="animate-spin w-6 h-6 border-2 border-white border-t-transparent rounded-full mr-4"></div>
                  Mengirim Permintaan...
                {:else}
                  <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                  </svg>
                  Kirim Permintaan Mushaf
                {/if}
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <!-- Call to Action -->
    <section class="py-20 bg-[#eb3434] text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold mb-6">Butuh Bantuan?</h2>
        <p class="text-xl mb-8 opacity-90">
          Tim kami siap membantu Anda dalam proses pengajuan permintaan mushaf. 
          Jangan ragu untuk menghubungi kami jika ada pertanyaan.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a 
            href="https://wa.me/{formatPhoneForWhatsApp(settings.contact_phone)}" 
            target="_blank"
            class="px-8 py-4 bg-white text-[#eb3434] font-semibold rounded-lg hover:bg-gray-100 transform hover:scale-105 transition-all duration-300 flex items-center justify-center"
          >
            <span class="mr-2">📱</span>
            Hubungi WhatsApp
          </a>
          <a 
            href="/tracking" 
            class="px-8 py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-[#eb3434] transform hover:scale-105 transition-all duration-300 flex items-center justify-center"
          >
            <span class="mr-2">🔍</span>
            Cek Status Pengiriman
          </a>
        </div>
        
        <div class="mt-12 text-center opacity-75">
          <p class="mb-2">Permintaan akan diproses dalam 1-7 hari kerja</p>
          <p>Email: <a href="mailto:info@ekspedisi-quran.org" class="underline hover:no-underline">info@ekspedisi-quran.org</a></p>
        </div>
      </div>
    </section>
  </div>
</PublicLayout>

<style>
  :global(.font-cairo) {
    font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
  }
  
  .islamic-geometric {
    background-image:
      repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.08) 4px, rgba(235, 52, 52, 0.08) 6px),
      repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.06) 4px, rgba(235, 52, 52, 0.06) 6px),
      radial-gradient(circle at 25% 25%, rgba(235, 52, 52, 0.1) 1px, transparent 1px),
      radial-gradient(circle at 75% 75%, rgba(235, 52, 52, 0.1) 1px, transparent 1px);
    background-size: 12px 12px, 12px 12px, 8px 8px, 8px 8px;
  }
  
  /* Custom file input styling */
  input[type="file"] {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
  }
  
  input[type="file"]:hover {
    border-color: #eb3434;
  }
  
  input[type="file"]:focus {
    outline: none;
    border-color: #eb3434;
    box-shadow: 0 0 0 4px rgba(235, 52, 52, 0.1);
  }
  
  /* Smooth scrolling */
  :global(html) {
    scroll-behavior: smooth;
  }
  
  section {
    scroll-margin-top: 80px;
  }
</style>
