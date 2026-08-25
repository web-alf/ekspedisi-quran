<script>
  import PublicLayout from '../../Layouts/PublicLayout.svelte';
  import { formatPageTitle, pageTitles, generateMetaDescription } from '@/utils/seo.js';
  
  export let settings = {};
  export let content = '';
  export let lastUpdated = '';
  
  // SEO data
  const seoTitle = formatPageTitle(pageTitles.termsOfService);
  const seoDescription = generateMetaDescription('termsOfService');
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
  <div class="min-h-screen bg-gray-50 py-12 pt-32">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
        <div class="px-8 py-12 text-center">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-[#eb3434] rounded-full mb-6">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v11a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2zM13 5h2a2 2 0 012 2v11a2 2 0 01-2 2h-2a2 2 0 01-2-2V7a2 2 0 012-2zM9 12h6"/>
            </svg>
          </div>
          <h1 class="text-4xl font-bold text-gray-900 mb-4">Syarat dan Ketentuan</h1>
          <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            Panduan lengkap untuk menggunakan layanan distribusi mushaf Al-Qur'an gratis kami
          </p>
          {#if lastUpdated}
          <p class="text-sm text-gray-500 mt-4">
            Terakhir diperbarui: {lastUpdated}
          </p>
          {/if}
        </div>
      </div>

      <!-- Breadcrumb -->
      <nav class="flex mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
          <li class="inline-flex items-center">
            <a href="/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-[#eb3434]">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
              </svg>
              Beranda
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
              </svg>
              <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Syarat dan Ketentuan</span>
            </div>
          </li>
        </ol>
      </nav>

      <!-- Content -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-8 py-12">
          <!-- Quick Navigation -->
          <div class="bg-gray-50 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Navigasi Cepat</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <a href="#layanan" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Tentang Layanan
              </a>
              <a href="#eligibilitas" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Eligibilitas
              </a>
              <a href="#permohonan" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Proses Permohonan
              </a>
              <a href="#pengiriman" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Ketentuan Pengiriman
              </a>
              <a href="#kewajiban" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Kewajiban Penerima
              </a>
              <a href="#larangan" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Larangan
              </a>
            </div>
          </div>

          <!-- Important Notice -->
          <div class="bg-amber-50 border-l-4 border-amber-400 p-6 mb-8">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-amber-800">Penting untuk Dibaca</h3>
                <div class="mt-2 text-sm text-amber-700">
                  <p>Dengan menggunakan layanan kami, Anda menyetujui untuk terikat oleh seluruh syarat dan ketentuan yang tercantum di halaman ini. Harap baca dengan seksama sebelum mengajukan permohonan.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Main Content -->
          <div class="prose prose-lg max-w-none">
            <div class="legal-content">
              {@html content}
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="mt-12 pt-8 border-t border-gray-200">
            <div class="bg-green-50 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Siap Untuk Memulai?
              </h3>
              <p class="text-gray-600 mb-6">
                Jika Anda telah membaca dan menyetujui syarat dan ketentuan di atas, Anda dapat langsung mengajukan permohonan mushaf Al-Qur'an untuk lembaga Anda.
              </p>
              <div class="flex flex-col sm:flex-row gap-4">
                <a 
                  href="/mushaf-request"
                  class="inline-flex items-center px-6 py-3 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors"
                >
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                  Ajukan Permohonan Mushaf
                </a>
                <a 
                  href="/tracking"
                  class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                  Lacak Pengiriman
                </a>
                <a 
                  href="/"
                  class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors"
                >
                  <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                  </svg>
                  Kembali ke Beranda
                </a>
              </div>
            </div>
          </div>

          <!-- Contact Section -->
          <div class="mt-8">
            <div class="bg-gray-50 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 text-[#eb3434] mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Pertanyaan Hukum atau Kontrak?
              </h3>
              <p class="text-gray-600 mb-4">
                Untuk pertanyaan mengenai aspek legal dari syarat dan ketentuan ini, atau jika Anda memerlukan klarifikasi khusus, silakan hubungi tim legal kami.
              </p>
              <a 
                href="mailto:{settings.contact_email || 'legal@ekspedisiquran.com'}"
                class="inline-flex items-center text-[#eb3434] hover:text-red-700 font-medium"
              >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Hubungi Tim Legal
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</PublicLayout>

<style>
  :global(.legal-content h2) {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-top: 2rem;
    margin-bottom: 1rem;
  }
  
  :global(.legal-content h2:first-child) {
    margin-top: 0;
  }
  
  :global(.legal-content h3) {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
  }
  
  :global(.legal-content p) {
    color: #4b5563;
    margin-bottom: 1rem;
    line-height: 1.625;
  }
  
  :global(.legal-content ul) {
    list-style-type: disc;
    list-style-position: inside;
    margin-bottom: 1rem;
  }
  
  :global(.legal-content li) {
    color: #4b5563;
    line-height: 1.625;
    margin-bottom: 0.5rem;
  }
  
  :global(.legal-content strong) {
    font-weight: 600;
    color: #111827;
  }
  
  :global(.legal-content a) {
    color: #eb3434;
    text-decoration: underline;
  }
  
  :global(.legal-content a:hover) {
    color: #dc2626;
  }

  /* Smooth scrolling for anchor links */
  :global(html) {
    scroll-behavior: smooth;
  }
</style>
