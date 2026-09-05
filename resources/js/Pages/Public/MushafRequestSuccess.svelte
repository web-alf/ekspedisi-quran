<script>
  import PublicLayout from '@/Layouts/PublicLayout.svelte';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  import { onMount } from 'svelte';
  
  export let mushafRequest;
  export let pageTitle = 'Permintaan Berhasil Dikirim';
  export let settings = {};
  
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
  
  let countdown = 3;
  let countdownInterval;
  
  // Auto redirect to WhatsApp admin after 3 seconds
  onMount(() => {
    countdownInterval = setInterval(() => {
      countdown--;
      if (countdown <= 0) {
        clearInterval(countdownInterval);
        redirectToWhatsApp();
      }
    }, 1000);
    
    // Cleanup on component destroy
    return () => {
      if (countdownInterval) {
        clearInterval(countdownInterval);
      }
    };
  });
  
  function redirectToWhatsApp() {
    const phoneNumber = formatPhoneForWhatsApp(settings.contact_phone);
    const message = `Assalamu'alaikum, saya telah mengirim permintaan mushaf dengan nomor: ${mushafRequest.no_request}. Mohon konfirmasi lebih lanjut. Terima kasih.`;
    
    // Cek apakah ini mobile device
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    let waUrl;
    if (isMobile) {
      // Untuk mobile: gunakan wa.me dengan parameter text
      waUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
      // Di mobile, gunakan window.location untuk direct redirect
      window.location.href = waUrl;
    } else {
      // Untuk desktop: gunakan api.whatsapp.com untuk WhatsApp Web
      waUrl = `https://api.whatsapp.com/send/?phone=${phoneNumber}&text=${encodeURIComponent(message)}&type=phone_number&app_absent=0`;
      // Di desktop, buka di tab baru
      window.open(waUrl, '_blank');
    }
  }
  
  function cancelRedirect() {
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
    countdown = 0;
  }
  
  // Status helper functions untuk konsistensi dengan admin panel
  function getStatusText(status) {
    const statusMap = {
      'pending': 'Menunggu Review',
      'reviewed': 'Sedang Direview',
      'approved': 'Disetujui',
      'rejected': 'Ditolak',
      'processed': 'Sudah Diproses',
      'selesai': 'Selesai'
    };
    return statusMap[status] || status;
  }
  
  function getStatusIcon(status) {
    const iconMap = {
      'pending': '⏳',
      'reviewed': '👀',
      'approved': '✅',
      'rejected': '❌',
      'processed': '📦',
      'selesai': '🎉'
    };
    return iconMap[status] || '📋';
  }
  
  function getStatusClass(status) {
    const classMap = {
      'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
      'reviewed': 'bg-blue-100 text-blue-800 border-blue-200',
      'approved': 'bg-green-100 text-green-800 border-green-200',
      'rejected': 'bg-red-100 text-red-800 border-red-200',
      'processed': 'bg-purple-100 text-purple-800 border-purple-200',
      'selesai': 'bg-emerald-100 text-emerald-800 border-emerald-200'
    };
    return classMap[status] || 'bg-gray-100 text-gray-800 border-gray-200';
  }
</script>

<svelte:head>
  <title>{pageTitle} - Ekspedisi Qur'an</title>
  <meta name="description" content="Permintaan mushaf Al-Qur'an Anda telah berhasil dikirim dan akan diproses oleh tim kami" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<PublicLayout {settings}>
  <div class="font-cairo">
    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-white to-red-50 overflow-hidden pt-32 pb-20">
      <!-- Islamic Pattern Background -->
      <div class="absolute inset-0 opacity-30 islamic-geometric"></div>
      
      <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Success Message -->
        <div class="text-center mb-12">
          <div class="inline-flex items-center justify-center w-20 h-20 bg-green-500 rounded-full mb-6">
            <HeroIcon name="check-circle" class="w-12 h-12 text-white" />
          </div>
          <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
            <span class="block">Permintaan Berhasil</span>
            <span class="block text-[#eb3434]">Dikirim!</span>
          </h1>
          <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Terima kasih telah mengajukan permintaan mushaf Al-Qur'an. Tim kami akan segera memproses permintaan Anda.
          </p>
          
          <!-- WhatsApp Redirect Notification -->
          {#if countdown > 0}
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-8 max-w-md mx-auto">
              <div class="flex items-center justify-center mb-4">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mr-4">
                  <HeroIcon name="message-circle" class="w-6 h-6 text-white" />
                </div>
                <div class="text-center">
                  <p class="text-green-800 font-semibold mb-1">Konfirmasi via WhatsApp</p>
                  <p class="text-green-600 text-sm">Redirect otomatis dalam <span class="font-bold text-lg">{countdown}</span> detik</p>
                </div>
              </div>
              <div class="text-center">
                <button 
                  on:click={redirectToWhatsApp}
                  class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium mr-3 transition-colors"
                >
                  Hubungi Sekarang
                </button>
                <button 
                  on:click={cancelRedirect}
                  class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors"
                >
                  Batal
                </button>
              </div>
            </div>
          {:else if countdownInterval === null}
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-8 max-w-md mx-auto">
              <div class="text-center">
                <p class="text-blue-800 font-medium mb-2">💬 Butuh konfirmasi langsung?</p>
                <button 
                  on:click={redirectToWhatsApp}
                  class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium transition-colors"
                >
                  Hubungi Admin via WhatsApp
                </button>
              </div>
            </div>
          {/if}
        </div>

        <!-- Main Content Container -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
          <!-- Header dengan gradient -->
          <div class="bg-gradient-to-r from-green-500 to-green-600 px-8 py-8 text-white">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
              <div class="mb-4 md:mb-0">
                <h2 class="text-2xl md:text-3xl font-bold mb-2">📝 {mushafRequest.no_request}</h2>
                <p class="text-green-100 text-lg">
                  Permintaan dari <strong>{mushafRequest.nama_lembaga}</strong>
                </p>
              </div>
              <div class="text-right">
                <div class="inline-flex items-center px-6 py-3 bg-white/20 backdrop-blur rounded-xl border border-white/30">
                <span class="mr-3 text-2xl">{getStatusIcon(mushafRequest.status)}</span>
                <div class="text-left">
                <div class="text-sm text-green-100">Status Terkini</div>
                <div class="font-bold text-lg">{getStatusText(mushafRequest.status)}</div>
                </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Detail Content -->
          <div class="p-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
              <!-- Left Column - Detail Permintaan -->
              <div class="space-y-8">
                <div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <span class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3">
                      <span class="text-white text-sm">📋</span>
                    </span>
                    Detail Permintaan
                  </h3>
                  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="space-y-4">
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Nomor Permintaan:</span>
                        <span class="font-bold text-[#eb3434] text-lg">{mushafRequest.no_request}</span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Nama Lembaga:</span>
                        <span class="font-semibold text-gray-900">{mushafRequest.nama_lembaga}</span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Jumlah Mushaf:</span>
                        <span class="font-semibold text-gray-900">{mushafRequest.jumlah_mushaf} buah</span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Jumlah IQRA':</span>
                        <span class="font-semibold text-gray-900">{mushafRequest.jumlah_iqra} buah</span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Total Permintaan:</span>
                        <span class="font-bold text-green-600 text-lg">{(mushafRequest.jumlah_mushaf || 0) + (mushafRequest.jumlah_iqra || 0)} buah</span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Jenis yang Diminta:</span>
                        <span class="font-semibold text-gray-900">
                          {#if mushafRequest.jenis_mushaf_diminta && Array.isArray(mushafRequest.jenis_mushaf_diminta)}
                            {mushafRequest.jenis_mushaf_diminta.map(jenis => {
                              if (jenis === 'A5') return 'Al-Qur\'an A5';
                              if (jenis === 'A6') return 'Al-Qur\'an A6';
                              if (jenis === 'IQRA') return 'IQRA\'';
                              return jenis;
                            }).join(', ')}
                          {:else}
                            -
                          {/if}
                        </span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Status:</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {getStatusClass(mushafRequest.status)}">
                          {getStatusIcon(mushafRequest.status)} {getStatusText(mushafRequest.status)}
                        </span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3">
                        <span class="text-gray-600 font-medium">Tanggal Pengajuan:</span>
                        <span class="font-semibold text-gray-900">{new Date(mushafRequest.created_at).toLocaleDateString('id-ID', { 
                          year: 'numeric', 
                          month: 'long', 
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        })}</span>
                      </div>
                    </div>
                  </div>
                </div>

                {#if mushafRequest.alamat_lengkap}
                  <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                      <span class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3">
                        <span class="text-white text-sm">📍</span>
                      </span>
                      Alamat Lembaga
                    </h3>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                      <p class="text-gray-900 font-medium mb-4">{mushafRequest.alamat_lengkap}</p>
                      <div class="grid grid-cols-1 gap-4">
                        <!-- Pengurus 1 -->
                        <div class="bg-gray-50 rounded-lg p-4">
                          <div class="flex items-center mb-2">
                            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                              <HeroIcon name="user" class="w-5 h-5 text-white" />
                            </div>
                            <div>
                              <p class="font-semibold text-gray-900">{mushafRequest.nama_pengurus_1}</p>
                              <p class="text-sm text-gray-600">{mushafRequest.jabatan_pengurus_1}</p>
                            </div>
                          </div>
                          <div class="flex items-center text-sm text-gray-600 ml-13">
                            <HeroIcon name="phone" class="w-4 h-4 mr-2 text-green-500" />
                            <span>{mushafRequest.whatsapp_pengurus_1}</span>
                          </div>
                        </div>

                        <!-- Pengurus 2 -->
                        <div class="bg-gray-50 rounded-lg p-4">
                          <div class="flex items-center mb-2">
                            <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center mr-3">
                              <HeroIcon name="user" class="w-5 h-5 text-white" />
                            </div>
                            <div>
                              <p class="font-semibold text-gray-900">{mushafRequest.nama_pengurus_2}</p>
                              <p class="text-sm text-gray-600">{mushafRequest.jabatan_pengurus_2}</p>
                            </div>
                          </div>
                          <div class="flex items-center text-sm text-gray-600 ml-13">
                            <HeroIcon name="phone" class="w-4 h-4 mr-2 text-green-500" />
                            <span>{mushafRequest.whatsapp_pengurus_2}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                {/if}
              </div>

              <!-- Right Column - Next Steps -->
              <div class="space-y-8">
                <div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <span class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3">
                      <span class="text-white text-sm">🚀</span>
                    </span>
                    Langkah Selanjutnya
                  </h3>
                  <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                    <ol class="space-y-4">
                      <li class="flex items-start space-x-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">1</span>
                        <div>
                          <p class="font-medium text-blue-900">Review Permintaan</p>
                          <p class="text-blue-700 text-sm">Tim kami akan mereview permintaan Anda dalam 1-3 hari kerja</p>
                        </div>
                      </li>
                      <li class="flex items-start space-x-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">2</span>
                        <div>
                          <p class="font-medium text-blue-900">Konfirmasi WhatsApp</p>
                          <p class="text-blue-700 text-sm">Anda akan dihubungi melalui WhatsApp untuk konfirmasi</p>
                        </div>
                      </li>
                      <li class="flex items-start space-x-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">3</span>
                        <div>
                          <p class="font-medium text-blue-900">Pengiriman</p>
                          <p class="text-blue-700 text-sm">Jika disetujui, mushaf akan dikirim ke alamat yang tertera</p>
                        </div>
                      </li>
                      <li class="flex items-start space-x-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">4</span>
                        <div>
                          <p class="font-medium text-blue-900">Dokumentasi</p>
                          <p class="text-blue-700 text-sm">Jangan lupa untuk melakukan dokumentasi sesuai ketentuan</p>
                        </div>
                      </li>
                    </ol>
                  </div>
                </div>

                {#if mushafRequest.urgensi_request}
                  <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                      <span class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3">
                        <span class="text-white text-sm">💭</span>
                      </span>
                      Alasan Permintaan
                    </h3>
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                      <p class="text-gray-800 leading-relaxed">{mushafRequest.urgensi_request}</p>
                    </div>
                  </div>
                {/if}

                {#if mushafRequest.sumber_info}
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                      <span class="w-6 h-6 bg-gray-400 rounded-lg flex items-center justify-center mr-2">
                        <span class="text-white text-xs">ℹ️</span>
                      </span>
                      Sumber Informasi
                    </h3>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                      <p class="text-gray-700">Mengetahui Ekspedisi Qur'an dari: <strong>{mushafRequest.sumber_info}</strong></p>
                    </div>
                  </div>
                {/if}
              </div>
            </div>

            <!-- Important Notes -->
            <div class="mt-12 p-6 bg-yellow-50 border border-yellow-200 rounded-xl">
              <h3 class="font-bold text-yellow-800 mb-4 text-lg flex items-center">
                <span class="mr-3">⚠️</span>
                Catatan Penting
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <ul class="space-y-3 text-yellow-700">
                  <li class="flex items-start space-x-2">
                    <span class="text-yellow-600 mt-1 flex-shrink-0">•</span>
                    <span>Simpan nomor permintaan <strong>{mushafRequest.no_request}</strong> untuk referensi</span>
                  </li>
                  <li class="flex items-start space-x-2">
                    <span class="text-yellow-600 mt-1 flex-shrink-0">•</span>
                    <span>Pastikan nomor WhatsApp yang diberikan aktif</span>
                  </li>
                </ul>
                <ul class="space-y-3 text-yellow-700">
                  <li class="flex items-start space-x-2">
                    <span class="text-yellow-600 mt-1 flex-shrink-0">•</span>
                    <span>Siapkan tempat yang layak untuk menerima mushaf</span>
                  </li>
                  <li class="flex items-start space-x-2">
                    <span class="text-yellow-600 mt-1 flex-shrink-0">•</span>
                    <span>Wajib melakukan dokumentasi setelah menerima mushaf</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mt-12">
          <a 
            href="/mushaf-request" 
            class="inline-flex items-center justify-center px-8 py-4 bg-[#eb3434] hover:bg-red-600 text-white font-semibold rounded-xl transition-colors shadow-lg gap-2"
          >
            <HeroIcon name="document-text" class="w-5 h-5" />
            <span>Ajukan Permintaan Lain</span>
          </a>
          
          <a 
            href="/mushaf-tracking" 
            class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors shadow-lg gap-2"
          >
            <HeroIcon name="magnifying-glass" class="w-5 h-5" />
            <span>Cek Permintaan</span>
          </a>
          
          <a 
            href="/tracking" 
            class="inline-flex items-center justify-center px-8 py-4 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition-colors shadow-lg gap-2"
          >
            <HeroIcon name="cube" class="w-5 h-5" />
            <span>Cek Resi Pengiriman</span>
          </a>
          
          <a 
            href="/" 
            class="inline-flex items-center justify-center px-8 py-4 border-2 border-[#eb3434] text-[#eb3434] hover:bg-[#eb3434] hover:text-white font-semibold rounded-xl transition-colors gap-2"
          >
            <HeroIcon name="home" class="w-5 h-5" />
            <span>Kembali ke Beranda</span>
          </a>
        </div>
      </div>
    </section>

    <!-- Contact Section -->
    <section class="py-20 bg-[#eb3434] text-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold mb-6">Ada Pertanyaan?</h2>
        <p class="text-xl mb-8 opacity-90">
          Tim kami siap membantu Anda jika ada hal yang ingin ditanyakan tentang permintaan mushaf Anda.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a 
            href="https://wa.me/{formatPhoneForWhatsApp(settings.contact_phone)}" 
            target="_blank"
            class="inline-flex items-center justify-center px-8 py-4 bg-white text-[#eb3434] font-semibold rounded-lg hover:bg-gray-100 transform hover:scale-105 transition-all duration-300"
          >
            <span class="mr-2">📱</span>
            Hubungi WhatsApp
          </a>
          <a 
            href="mailto:info@ekspedisi-quran.org" 
            class="inline-flex items-center justify-center px-8 py-4 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-[#eb3434] transform hover:scale-105 transition-all duration-300"
          >
            <span class="mr-2">✉️</span>
            Kirim Email
          </a>
        </div>
        
        <div class="mt-12 text-center opacity-75">
          <p class="mb-2">Permintaan akan diproses dalam 1-7 hari kerja</p>
          <p>WhatsApp: <a href="https://wa.me/{formatPhoneForWhatsApp(settings.contact_phone)}" class="underline hover:no-underline">{settings.contact_phone || '021-1234-5678'}</a></p>
        </div>
      </div>
    </section>
  </div>
</PublicLayout>

<style>
  .font-cairo {
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
  
  /* Spacing untuk icon pengurus */
  .ml-13 {
    margin-left: 3.25rem; /* 52px */
  }
  
  /* Smooth scrolling */
  :global(html) {
    scroll-behavior: smooth;
  }
  
  * {
    scroll-behavior: smooth;
  }
  
  section {
    scroll-margin-top: 80px;
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
    .pt-16 {
      padding-top: 0 !important;
    }
  }
</style>
