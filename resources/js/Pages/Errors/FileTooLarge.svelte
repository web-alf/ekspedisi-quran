<script>
  import PublicLayout from '@/Layouts/PublicLayout.svelte';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  
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
      <div class="bg-red-600 p-5 text-white flex items-center gap-2">
        <HeroIcon name="exclamation-triangle" class="w-6 h-6 text-white" />
        <h1 class="text-2xl font-bold">File Terlalu Besar</h1>
      </div>
      
      <div class="p-8">
        <div class="flex items-start mb-8">
          <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mr-5 flex-shrink-0">
            <HeroIcon name="exclamation-triangle" class="w-8 h-8 text-red-500" />
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
            <HeroIcon name="information-circle" class="w-5 h-5 mr-2" />
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
            class="px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 flex items-center justify-center gap-2"
          >
            <HeroIcon name="arrow-left" class="w-5 h-5" />
            Kembali
          </button>
          <a 
            href="/mushaf-request" 
            class="px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 flex items-center justify-center gap-2"
          >
            <HeroIcon name="plus" class="w-5 h-5" />
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
