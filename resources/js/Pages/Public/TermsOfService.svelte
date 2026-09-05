<script>
  import PublicLayout from '../../Layouts/PublicLayout.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
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
          <div class="inline-flex items-center justify-center w-16 h-16 bg-[#eb3434] rounded-full mb-6 text-white">
            <HeroIcon name="document-text" class="w-8 h-8" />
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
            <a href="/" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-[#eb3434] gap-1.5">
              <HeroIcon name="home" class="w-4 h-4" />
              Beranda
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <HeroIcon name="chevron-right" class="w-4 h-4 text-gray-400" />
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
                <HeroIcon name="exclamation-triangle" class="w-6 h-6 text-amber-500" />
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
                <HeroIcon name="check-circle" class="w-5 h-5 text-green-600 mr-2" />
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
                  <HeroIcon name="book-open" class="w-5 h-5 mr-2" />
                  Ajukan Permohonan Mushaf
                </a>
                <a 
                  href="/tracking"
                  class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <HeroIcon name="magnifying-glass" class="w-5 h-5 mr-2" />
                  Lacak Pengiriman
                </a>
                <a 
                  href="/"
                  class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors"
                >
                  <HeroIcon name="home" class="w-5 h-5 mr-2" />
                  Kembali ke Beranda
                </a>
              </div>
            </div>
          </div>

          <!-- Contact Section -->
          <div class="mt-8">
            <div class="bg-gray-50 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <HeroIcon name="question-mark-circle" class="w-5 h-5 text-[#eb3434] mr-2" />
                Pertanyaan Hukum atau Kontrak?
              </h3>
              <p class="text-gray-600 mb-4">
                Untuk pertanyaan mengenai aspek legal dari syarat dan ketentuan ini, atau jika Anda memerlukan klarifikasi khusus, silakan hubungi tim legal kami.
              </p>
              <a 
                href="mailto:{settings.contact_email || 'legal@ekspedisiquran.com'}"
                class="inline-flex items-center text-[#eb3434] hover:text-red-700 font-medium"
              >
                <HeroIcon name="envelope" class="w-5 h-5 mr-2" />
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
