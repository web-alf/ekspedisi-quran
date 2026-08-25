<script>
  import PublicLayout from '@/Layouts/PublicLayout.svelte';
  
  export let mushafRequest = null;
  export let no_request;
  export const statusHistory = [];
  export let quantityComparison = null;
  export const auth = {};
  export const errors = {};
  export const flash = {};
  export let settings = {};
  
  // Helper function to format phone number for WhatsApp/Tel
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
  
  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
  // Status helper functions - sinkron dengan admin panel
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
  
  function getJenisMushafList(jenisArray) {
    if (!jenisArray || !Array.isArray(jenisArray)) return '-';
    
    return jenisArray.map(jenis => {
      if (jenis === 'A5') return 'Al-Qur\'an A5';
      if (jenis === 'A6') return 'Al-Qur\'an A6';
      if (jenis === 'IQRA') return 'IQRA\'';
      return jenis;
    }).join(', ');
  }
</script>

<svelte:head>
  <title>Status Permintaan {no_request} - Ekspedisi Quran</title>
  <meta name="description" content="Detail status permintaan mushaf Al-Qur'an dengan nomor {no_request}" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<PublicLayout {settings}>
  <div class="font-cairo min-h-screen bg-gradient-to-br from-white to-red-50 pt-32 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      {#if mushafRequest}
        <!-- Success - Show Request Details -->
        
        <!-- Header Status Card -->
        <div class="bg-gradient-to-br from-white to-red-50 rounded-3xl shadow-2xl border border-gray-100 overflow-hidden mb-8">
          <!-- Header dengan gradient -->
          <div class="bg-gradient-to-r from-[#eb3434] to-red-600 px-8 py-8 text-white">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
              <div class="mb-4 md:mb-0">
                <h1 class="text-3xl md:text-4xl font-bold mb-2">📋 {mushafRequest.no_request}</h1>
                <p class="text-red-100 text-lg">
                  Permintaan Mushaf dari <strong>{mushafRequest.nama_lembaga}</strong>
                </p>
              </div>
              <div class="text-right">
                <div class="inline-flex items-center px-6 py-3 bg-white/20 backdrop-blur rounded-xl border border-white/30">
                  <span class="mr-3 text-2xl">{getStatusIcon(mushafRequest.status)}</span>
                  <div class="text-left">
                    <div class="text-sm text-red-100">Status Terkini</div>
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
                  <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <span class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3">
                      <span class="text-white text-sm">📋</span>
                    </span>
                    Detail Permintaan
                  </h2>
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
                      
                      <!-- Quantity Comparison Section -->
                      {#if quantityComparison && quantityComparison.has_change}
                        <div class="py-3 border-b border-gray-100">
                          <p class="text-gray-600 font-medium mb-3">Perbandingan Jumlah:</p>
                          <div class="bg-blue-50 rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                              <div>
                                <p class="text-xs text-gray-600 mb-2 font-medium">Yang Diajukan</p>
                                <div class="space-y-1">
                                  <p class="text-gray-700">A5: <span class="font-semibold">{quantityComparison.requested.mushaf_a5}</span></p>
                                  <p class="text-gray-700">A6: <span class="font-semibold">{quantityComparison.requested.mushaf_a6}</span></p>
                                  <p class="text-gray-700">IQRA: <span class="font-semibold">{quantityComparison.requested.iqra}</span></p>
                                  <p class="font-bold text-gray-900 pt-1 border-t border-gray-300">Total: {quantityComparison.requested.total}</p>
                                </div>
                              </div>
                              <div>
                                <p class="text-xs text-green-600 mb-2 font-medium">✓ Yang Disetujui</p>
                                <div class="space-y-1">
                                  <p class="text-green-700">A5: <span class="font-semibold">{quantityComparison.approved.mushaf_a5}</span></p>
                                  <p class="text-green-700">A6: <span class="font-semibold">{quantityComparison.approved.mushaf_a6}</span></p>
                                  <p class="text-green-700">IQRA: <span class="font-semibold">{quantityComparison.approved.iqra}</span></p>
                                  <p class="font-bold text-green-900 pt-1 border-t border-green-300">Total: {quantityComparison.approved.total}</p>
                                </div>
                              </div>
                            </div>

                            {#if quantityComparison.change_percentage !== 0}
                              <div class="bg-yellow-50 border border-yellow-200 rounded p-2 text-xs">
                                <p class="text-yellow-800">
                                  <strong>Perbedaan:</strong> {quantityComparison.change_percentage > 0 ? '+' : ''}{quantityComparison.change_percentage}%
                                  ({quantityComparison.approved.total - quantityComparison.requested.total > 0 ? '+' : ''}{quantityComparison.approved.total - quantityComparison.requested.total} buah)
                                </p>
                              </div>
                            {/if}

                            {#if quantityComparison.catatan_perubahan}
                              <div class="bg-white border border-blue-200 rounded p-2 text-xs">
                                <p class="font-medium text-blue-900 mb-1">Catatan:</p>
                                <p class="text-blue-800">{quantityComparison.catatan_perubahan}</p>
                              </div>
                            {/if}
                          </div>
                        </div>
                      {:else}
                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                          <span class="text-gray-600 font-medium">Jumlah Mushaf:</span>
                          <span class="font-semibold text-gray-900">{mushafRequest.jumlah_mushaf || 0} buah</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                          <span class="text-gray-600 font-medium">Jumlah IQRA':</span>
                          <span class="font-semibold text-gray-900">{mushafRequest.jumlah_iqra || 0} buah</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                          <span class="text-gray-600 font-medium">Total Permintaan:</span>
                          <span class="font-bold text-green-600 text-lg">{(mushafRequest.jumlah_mushaf || 0) + (mushafRequest.jumlah_iqra || 0)} buah</span>
                        </div>
                      {/if}
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Jenis yang Diminta:</span>
                        <span class="font-semibold text-gray-900">{getJenisMushafList(mushafRequest.jenis_mushaf_diminta)}</span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3 border-b border-gray-100">
                        <span class="text-gray-600 font-medium">Status:</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {getStatusClass(mushafRequest.status)}">
                          {getStatusIcon(mushafRequest.status)} {getStatusText(mushafRequest.status)}
                        </span>
                      </div>
                      
                      <div class="flex justify-between items-center py-3">
                        <span class="text-gray-600 font-medium">Tanggal Pengajuan:</span>
                        <span class="font-semibold text-gray-900">{formatDate(mushafRequest.created_at)}</span>
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
                      Alamat Tujuan
                    </h3>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                      <p class="text-gray-900 font-medium mb-4">{mushafRequest.alamat_lengkap}</p>
                      <div class="grid grid-cols-1 gap-3">
                        <div class="flex items-center text-sm text-gray-600">
                          <span class="w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-xs">👤</span>
                          </span>
                          <span><strong>Pengurus 1:</strong> {mushafRequest.nama_pengurus_1} ({mushafRequest.jabatan_pengurus_1})</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                          <span class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-xs">📱</span>
                          </span>
                          <span>{mushafRequest.whatsapp_pengurus_1}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                {/if}
              </div>

              <!-- Right Column - Status Timeline -->
              <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                  <span class="w-8 h-8 bg-[#eb3434] rounded-lg flex items-center justify-center mr-3">
                    <span class="text-white text-sm">📈</span>
                  </span>
                  Riwayat Status
                </h2>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                  <div class="space-y-6">
                    <!-- Current Status -->
                    <div class="flex items-start space-x-4 relative">
                      <div class="flex-shrink-0 w-12 h-12 bg-[#eb3434] rounded-full flex items-center justify-center shadow-lg">
                        <span class="text-white text-lg">{getStatusIcon(mushafRequest.status)}</span>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex flex-col space-y-2">
                          <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {getStatusClass(mushafRequest.status)}">
                              {getStatusText(mushafRequest.status)}
                            </span>
                            <span class="text-xs text-gray-500 font-medium">Status Saat Ini</span>
                          </div>
                          {#if mushafRequest.catatan_admin}
                            <p class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3">
                              <strong>Catatan Admin:</strong> {mushafRequest.catatan_admin}
                            </p>
                          {/if}
                        </div>
                      </div>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-gray-200 pt-6">
                      <h4 class="text-sm font-medium text-gray-500 mb-4">Timeline Permintaan</h4>
                      
                      <!-- Request Created -->
                      <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                          <span class="text-white text-sm">📝</span>
                        </div>
                        <div class="flex-1 min-w-0">
                          <div class="flex flex-col space-y-1">
                            <span class="text-sm font-medium text-gray-900">Permintaan Dibuat</span>
                            <span class="text-xs text-gray-500">{formatDate(mushafRequest.created_at)}</span>
                            <span class="text-xs text-green-600 font-medium">✓ Selesai</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {#if mushafRequest.urgensi_request}
              <div class="mt-8 p-6 bg-blue-50 border border-blue-200 rounded-xl">
                <h3 class="font-bold text-blue-900 mb-3 flex items-center">
                  <span class="mr-2">💭</span>
                  Alasan Permintaan
                </h3>
                <p class="text-blue-800">{mushafRequest.urgensi_request}</p>
              </div>
            {/if}
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="/mushaf-tracking" class="inline-flex items-center justify-center px-8 py-4 bg-[#eb3434] hover:bg-red-600 text-white font-semibold rounded-xl transition-colors shadow-lg">
            <span class="mr-2">🔍</span>
            Cek Permintaan Lain
          </a>
          <button onclick="window.print()" class="inline-flex items-center justify-center px-8 py-4 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-xl transition-colors shadow-lg">
            <span class="mr-2">🖨️</span>
            Cetak Halaman
          </button>
          <a href="/mushaf-request" class="inline-flex items-center justify-center px-8 py-4 border-2 border-[#eb3434] text-[#eb3434] hover:bg-[#eb3434] hover:text-white font-semibold rounded-xl transition-colors">
            <span class="mr-2">📝</span>
            Ajukan Permintaan Baru
          </a>
        </div>
      {:else}
        <!-- Not Found -->
        <div class="text-center py-20">
          <div class="inline-flex items-center justify-center w-24 h-24 bg-red-100 rounded-full mb-8">
            <span class="text-red-500 text-4xl">❌</span>
          </div>
          <h1 class="text-4xl font-bold text-gray-900 mb-4">
            Nomor Permintaan Tidak Ditemukan
          </h1>
          <p class="text-xl text-gray-600 mb-8">
            Nomor permintaan <strong class="text-[#eb3434]">"{no_request}"</strong> tidak ditemukan dalam sistem kami.
          </p>
          
          <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-8 max-w-lg mx-auto mb-12">
            <h2 class="font-bold text-yellow-800 mb-4 text-lg">💡 Tips Pencarian:</h2>
            <ul class="text-yellow-700 space-y-3 text-left">
              <li class="flex items-start space-x-2">
                <span class="text-yellow-600 mt-1">•</span>
                <span>Pastikan format nomor benar: <code class="bg-yellow-200 px-2 py-1 rounded text-sm">REQ-YYYY-XXXXX</code></span>
              </li>
              <li class="flex items-start space-x-2">
                <span class="text-yellow-600 mt-1">•</span>
                <span>Periksa kembali nomor yang dimasukkan</span>
              </li>
              <li class="flex items-start space-x-2">
                <span class="text-yellow-600 mt-1">•</span>
                <span>Nomor permintaan mungkin belum diproses (tunggu 1-2 jam)</span>
              </li>
            </ul>
          </div>
          
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/mushaf-tracking" class="inline-flex items-center justify-center px-8 py-4 bg-[#eb3434] hover:bg-red-600 text-white font-semibold rounded-xl transition-colors shadow-lg">
              <span class="mr-2">🔍</span>
              Coba Lagi
            </a>
            <a href="tel:+{formatPhoneForWhatsApp(settings.contact_phone)}" class="inline-flex items-center justify-center px-8 py-4 border-2 border-[#eb3434] text-[#eb3434] hover:bg-[#eb3434] hover:text-white font-semibold rounded-xl transition-colors">
              <span class="mr-2">📞</span>
              Hubungi CS
            </a>
          </div>
        </div>
      {/if}
    </div>
  </div>
</PublicLayout>

<style>
  .font-cairo {
    font-family: 'Cairo', 'Noto Sans Arabic', sans-serif;
  }
</style>
