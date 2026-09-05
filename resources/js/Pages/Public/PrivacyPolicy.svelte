<script>
  import PublicLayout from '../../Layouts/PublicLayout.svelte';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  import { formatPageTitle, pageTitles, generateMetaDescription } from '@/utils/seo.js';
  
  export let settings = {};
  export let content = '';
  export let lastUpdated = '';

  // SEO data
  const seoTitle = formatPageTitle(pageTitles.privacyPolicy);
  const seoDescription = generateMetaDescription('privacyPolicy');
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
            <HeroIcon name="shield-check" class="w-8 h-8 text-white" />
          </div>
          <h1 class="text-4xl font-bold text-gray-900 mb-4">Kebijakan Privasi</h1>
          <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            Kami berkomitmen untuk melindungi privasi dan keamanan informasi pribadi Anda
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
              <HeroIcon name="home" class="w-4 h-4 mr-2" />
              Beranda
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <HeroIcon name="chevron-right" class="w-4 h-4 text-gray-400 mx-1" />
              <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Kebijakan Privasi</span>
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
              <a href="#informasi" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Informasi yang Dikumpulkan
              </a>
              <a href="#penggunaan" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Penggunaan Informasi
              </a>
              <a href="#berbagi" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Berbagi Informasi
              </a>
              <a href="#keamanan" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Keamanan Data
              </a>
              <a href="#hak" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Hak Anda
              </a>
              <a href="#kontak" class="text-[#eb3434] hover:text-red-700 text-sm font-medium">
                → Hubungi Kami
              </a>
            </div>
          </div>

          <!-- Main Content -->
          <div class="prose prose-lg max-w-none">
            <div class="legal-content">
              {@html content}
            </div>
          </div>

          <!-- Contact Section -->
          <div class="mt-12 pt-8 border-t border-gray-200">
            <div class="bg-[#eb3434]/5 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <HeroIcon name="question-mark-circle" class="w-5 h-5 text-[#eb3434] mr-2" />
                Ada Pertanyaan?
              </h3>
              <p class="text-gray-600 mb-4">
                Jika Anda memiliki pertanyaan tentang kebijakan privasi ini atau ingin menjalankan hak privasi Anda, jangan ragu untuk menghubungi kami.
              </p>
              <div class="flex flex-col sm:flex-row gap-4">
                <a 
                  href="mailto:{settings.contact_email || 'privacy@ekspedisiquran.com'}"
                  class="inline-flex items-center px-6 py-3 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors"
                >
                  <HeroIcon name="envelope" class="w-5 h-5 mr-2" />
                  Hubungi via Email
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
