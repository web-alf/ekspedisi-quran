<script>
  import PublicLayout from '@/Layouts/PublicLayout.svelte';
  
  export let maxFileSize = '2MB';
  export let maxPostSize = '10MB';
  export let limits = {
    foto: '2MB',
    dokumen: '5MB',
    total: '10MB'
  };
</script>

<PublicLayout>
  <div class="min-h-screen pt-32 pb-16 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
      <div class="bg-red-600 p-5 text-white">
        <h1 class="text-2xl font-bold">⚠️ File Terlalu Besar</h1>
      </div>
      
      <div class="p-8">
        <div class="flex items-start mb-8">
          <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mr-5 flex-shrink-0">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-3">Ukuran file melebihi batas maksimum</h2>
            <p class="text-gray-600 text-lg">
              File yang Anda coba upload terlalu besar. Server tidak dapat memproses file dengan ukuran tersebut.
            </p>
          </div>
        </div>
        
        <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mb-8">
          <h3 class="font-semibold text-gray-800 mb-3 text-lg">Batas ukuran file yang diizinkan:</h3>
          <ul class="list-disc list-inside space-y-2 text-gray-600">
            <li>Ukuran file maksimum: <strong>{maxFileSize}</strong></li>
            <li>Ukuran total upload maksimum: <strong>{maxPostSize}</strong></li>
            <li>Batas ukuran untuk foto: <strong>{limits.foto}</strong></li>
            <li>Batas ukuran untuk dokumen: <strong>{limits.dokumen}</strong></li>
            <li>Batas ukuran total seluruh file: <strong>{limits.total}</strong></li>
          </ul>
        </div>
        
        <div class="bg-yellow-50 p-6 rounded-lg border border-yellow-200 mb-8">
          <h3 class="font-semibold text-yellow-800 mb-3 text-lg">Tips untuk mengatasi masalah ini:</h3>
          <ul class="list-disc list-inside space-y-2 text-yellow-700">
            <li>Kompres file gambar menggunakan layanan online seperti 
              <a href="https://tinypng.com/" target="_blank" rel="noopener" class="text-blue-600 hover:underline">TinyPNG</a>, 
              <a href="https://compressjpeg.com/" target="_blank" rel="noopener" class="text-blue-600 hover:underline">CompressJPEG</a>, dll.
            </li>
            <li>Kurangi resolusi/dimensi gambar jika terlalu besar</li>
            <li>Untuk file dokumen, simpan sebagai versi lebih ringan atau gunakan fitur "Compress PDF" jika tersedia</li>
            <li>Pastikan tidak ada konten yang tidak perlu dalam file dokumen</li>
            <li>Gunakan format file yang lebih efisien (JPEG untuk foto, PDF terkompresi untuk dokumen)</li>
          </ul>
        </div>
        
        <div class="bg-blue-50 p-6 rounded-lg border border-blue-200 mb-8">
          <h3 class="font-semibold text-blue-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd"></path>
            </svg>
            Mengapa ada batasan ukuran file?
          </h3>
          <p class="text-blue-700">
            Batas ukuran file ditetapkan untuk memastikan server dapat menangani semua permintaan dengan baik dan
            mencegah masalah kinerja. File yang lebih kecil juga membantu mempercepat proses upload dan menghemat bandwidth.
          </p>
        </div>
        
        <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
          <button 
            on:click={() => window.history.back()} 
            class="px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 flex items-center justify-center"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali
          </button>
          <a 
            href="/mushaf-request" 
            class="px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 flex items-center justify-center"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Coba Lagi dengan File Baru
          </a>
        </div>
      </div>
    </div>
  </div>
</PublicLayout>

<style>
  :global(body) {
    background-color: #FEF2F2; /* bg-red-50 */
  }
</style>
