<script>
  import { onDestroy } from 'svelte';
  
  export let settings = {};
  export let galleries = [];
  
  // Gallery configuration from settings
  $: galleryEnabled = settings.landing_gallery_enabled === '1' || settings.landing_gallery_enabled === true;
  $: galleryTitle = settings.landing_gallery_title || 'Galeri Ekspedisi';
  $: gallerySubtitle = settings.landing_gallery_subtitle || 'Dokumentasi perjalanan mushaf Al-Qur\'an ke berbagai pelosok Indonesia';
  $: galleryLayout = settings.landing_gallery_layout || 'hero';
  $: galleryAutoplay = settings.landing_gallery_autoplay === '1' || settings.landing_gallery_autoplay === true;
  $: showCaptions = settings.landing_gallery_show_captions === '1' || settings.landing_gallery_show_captions === true;
  $: lightboxEnabled = settings.landing_gallery_lightbox === '1' || settings.landing_gallery_lightbox === true;
  
  // Default gallery images from /public/images/galleries
  const defaultGalleryImages = [
    {
      image_url: '/images/galleries/distribusi 1.webp',
      title: 'Distribusi Mushaf 1',
      caption: 'Dokumentasi distribusi mushaf Al-Qur\'an ke berbagai daerah'
    },
    {
      image_url: '/images/galleries/distribusi 2.webp',
      title: 'Distribusi Mushaf 2',
      caption: 'Penyerahan mushaf Al-Qur\'an kepada penerima manfaat'
    },
    {
      image_url: '/images/galleries/distribusi 3.webp',
      title: 'Distribusi Mushaf 3',
      caption: 'Kegiatan ekspedisi mushaf Al-Qur\'an ke pelosok Indonesia'
    },
    {
      image_url: '/images/galleries/distribusi 4.webp',
      title: 'Distribusi Mushaf 4',
      caption: 'Pengiriman mushaf Al-Qur\'an untuk masjid dan pesantren'
    }
  ];

  // Use galleries from database, fallback to default images if empty
  $: galleryImages = (galleries && galleries.length > 0) ? galleries : defaultGalleryImages;
  
  
  
  // Lightbox state
  let showLightbox = false;
  let lightboxIndex = 0;
  
  // Slider state
  let currentSlide = 0;
  let sliderInterval;
  
  
  function openLightbox(index) {
    if (!lightboxEnabled) return;
    lightboxIndex = index;
    showLightbox = true;
    document.body.style.overflow = 'hidden';
  }
  
  function closeLightbox() {
    showLightbox = false;
    document.body.style.overflow = '';
  }
  
  function nextLightbox() {
    lightboxIndex = (lightboxIndex + 1) % galleryImages.length;
  }
  
  function prevLightbox() {
    lightboxIndex = lightboxIndex === 0 ? galleryImages.length - 1 : lightboxIndex - 1;
  }
  
  function nextSlide() {
    currentSlide = (currentSlide + 1) % galleryImages.length;
  }
  
  function prevSlide() {
    currentSlide = currentSlide === 0 ? galleryImages.length - 1 : currentSlide - 1;
  }
  
  function goToSlide(index) {
    currentSlide = index;
  }
  
  // Handle keyboard navigation in lightbox
  function handleKeydown(event) {
    if (!showLightbox) return;
    
    switch (event.key) {
      case 'Escape':
        closeLightbox();
        break;
      case 'ArrowLeft':
        prevLightbox();
        break;
      case 'ArrowRight':
        nextLightbox();
        break;
    }
  }
  

  // Auto-play functionality
  $: if ((galleryLayout === 'slider' || galleryLayout === 'hero') && galleryAutoplay && galleryImages.length > 1) {
    if (sliderInterval) clearInterval(sliderInterval);
    sliderInterval = setInterval(nextSlide, 5000);
  } else if (sliderInterval) {
    clearInterval(sliderInterval);
  }
  
  // Cleanup interval on destroy
  onDestroy(() => {
    if (sliderInterval) clearInterval(sliderInterval);
  });
</script>

<svelte:window on:keydown={handleKeydown} />

{#if galleryEnabled && galleryImages.length > 0}
  <section id="galeri" class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
          {galleryTitle}
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          {gallerySubtitle}
        </p>
      </div>
      
      <!-- Gallery Content -->
      {#if galleryLayout === 'grid'}
        <!-- Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {#each galleryImages as item, index}
            <div class="group relative overflow-hidden rounded-xl shadow-lg bg-white hover:shadow-xl transition-shadow duration-300">
              <div class="aspect-w-4 aspect-h-3">
                <button type="button" class="block w-full h-full text-left" on:click={() => openLightbox(index)} aria-label="Buka gambar dalam lightbox">
                  <img
                    src={item.image_url}
                    alt={item.caption}
                    class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-110"
                    loading="lazy"
                  />
                </button>
              </div>
              {#if showCaptions}
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <div class="absolute bottom-4 left-4 right-4">
                    <p class="text-white text-sm font-medium">{item.caption}</p>
                  </div>
                </div>
              {/if}
              <!-- Lightbox indicator -->
              {#if lightboxEnabled}
                <div class="absolute top-4 right-4 bg-black/50 text-white p-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                </div>
              {/if}
            </div>
          {/each}
        </div>
        
      {:else if galleryLayout === 'slider'}
        <!-- Slider Layout -->
        <div class="relative max-w-4xl mx-auto">
          <div class="overflow-hidden rounded-xl shadow-2xl">
            <div class="relative h-96 md:h-[500px]">
              {#each galleryImages as item, index}
                <div class="absolute inset-0 transition-opacity duration-500 {index === currentSlide ? 'opacity-100' : 'opacity-0'}">
                  <button type="button" class="block w-full h-full text-left" on:click={() => openLightbox(index)} aria-label="Buka gambar dalam lightbox">
                    <img
                      src={item.image_url}
                      alt={item.caption}
                      class="w-full h-full object-cover"
                    />
                  </button>
                  {#if showCaptions}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent">
                      <div class="absolute bottom-6 left-6 right-6">
                        <p class="text-white text-lg font-medium">{item.caption}</p>
                      </div>
                    </div>
                  {/if}
                  <!-- Lightbox indicator -->
                  {#if lightboxEnabled}
                    <div class="absolute top-6 right-6 bg-black/50 text-white p-3 rounded-full">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                    </div>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
          
          <!-- Navigation Arrows -->
          {#if galleryImages.length > 1}
            <button
              on:click={prevSlide}
              class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-gray-800 p-3 rounded-full shadow-lg transition-all duration-200 hover:scale-110"
              aria-label="Previous image"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            <button
              on:click={nextSlide}
              class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-gray-800 p-3 rounded-full shadow-lg transition-all duration-200 hover:scale-110"
              aria-label="Next image"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          {/if}
          
          <!-- Dots Indicator -->
          {#if galleryImages.length > 1}
            <div class="flex justify-center mt-6 space-x-2">
              {#each galleryImages as _, index}
                <button
                  on:click={() => goToSlide(index)}
                  class="w-3 h-3 rounded-full transition-all duration-200 {index === currentSlide ? 'bg-[#eb3434] scale-125' : 'bg-gray-300 hover:bg-gray-400'}"
                  aria-label="Go to slide {index + 1}"
                ></button>
              {/each}
            </div>
          {/if}
        </div>
        
      {:else if galleryLayout === 'hero'}
        <!-- Hero Layout with Main Image and Thumbnails -->
        <div class="max-w-6xl mx-auto">
          <!-- Main Image -->
          <div class="relative mb-6 overflow-hidden rounded-2xl shadow-2xl bg-black">
            <div class="relative h-96 md:h-[600px]">
              {#each galleryImages as item, index}
                <div class="absolute inset-0 transition-opacity duration-500 {index === currentSlide ? 'opacity-100' : 'opacity-0'}">
                  <button type="button" class="block w-full h-full text-left" on:click={() => openLightbox(index)} aria-label="Buka gambar dalam lightbox">
                    <img
                      src={item.image_url}
                      alt={item.title || item.caption}
                      class="w-full h-full object-cover"
                    />
                  </button>
                  {#if showCaptions && (item.title || item.caption)}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent">
                      <div class="absolute bottom-8 left-8 right-8">
                        <h3 class="text-white text-2xl md:text-3xl font-bold mb-2">{item.title}</h3>
                        {#if item.caption}
                          <p class="text-white/90 text-lg">{item.caption}</p>
                        {/if}
                      </div>
                    </div>
                  {/if}
                </div>
              {/each}
            </div>
            
            <!-- Navigation Arrows -->
            {#if galleryImages.length > 1}
              <button
                on:click={prevSlide}
                class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/10 hover:bg-white/20 text-white p-4 rounded-full backdrop-blur-sm transition-all duration-200 hover:scale-110"
                aria-label="Previous image"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>
              <button
                on:click={nextSlide}
                class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/10 hover:bg-white/20 text-white p-4 rounded-full backdrop-blur-sm transition-all duration-200 hover:scale-110"
                aria-label="Next image"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            {/if}
          </div>
          
          <!-- Thumbnail Row -->
          {#if galleryImages.length > 1}
            <div class="relative flex justify-center">
              <!-- Scrollable Container -->
              <div class="overflow-x-auto scrollbar-hide">
                <div class="flex gap-3 px-4 py-2 min-w-max justify-center">
                  {#each galleryImages as item, index}
                    <button
                      on:click={() => goToSlide(index)}
                      class="group relative overflow-hidden rounded-xl aspect-square bg-gray-200 hover:ring-4 hover:ring-[#eb3434]/50 transition-all duration-200 {index === currentSlide ? 'ring-4 ring-[#eb3434]' : ''} w-20 h-20 sm:w-24 sm:h-24 flex-shrink-0"
                    >
                      <img
                        src={item.image_url}
                        alt={item.title || item.caption}
                        class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-110"
                      />
                      <div class="absolute inset-0 bg-black/20 {index === currentSlide ? 'opacity-0' : 'opacity-0 group-hover:opacity-40'} transition-opacity duration-200"></div>
                      {#if index === currentSlide}
                        <div class="absolute inset-0 bg-gradient-to-t from-[#eb3434]/80 to-transparent opacity-60"></div>
                        <div class="absolute bottom-2 left-2 right-2">
                          <div class="w-2 h-2 bg-white rounded-full mx-auto"></div>
                        </div>
                      {/if}
                    </button>
                  {/each}
                </div>
              </div>
              
              <!-- Fade Edges -->
              <div class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-gray-50 to-transparent pointer-events-none"></div>
              <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-gray-50 to-transparent pointer-events-none"></div>
            </div>
          {/if}
        </div>
        
      {:else if galleryLayout === 'masonry'}
        <!-- Masonry Layout -->
        <div class="columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6">
          {#each galleryImages as item, index}
            <div class="break-inside-avoid group relative overflow-hidden rounded-xl shadow-lg bg-white hover:shadow-xl transition-shadow duration-300 mb-6">
              <button type="button" class="block w-full text-left" on:click={() => openLightbox(index)} aria-label="Buka gambar dalam lightbox">
                <img
                  src={item.image_url}
                  alt={item.caption}
                  class="w-full object-cover transition-transform duration-300 group-hover:scale-105"
                  loading="lazy"
                />
              </button>
              {#if showCaptions}
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <div class="absolute bottom-4 left-4 right-4">
                    <p class="text-white text-sm font-medium">{item.caption}</p>
                  </div>
                </div>
              {/if}
              <!-- Lightbox indicator -->
              {#if lightboxEnabled}
                <div class="absolute top-4 right-4 bg-black/50 text-white p-2 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                </div>
              {/if}
            </div>
          {/each}
        </div>
      {/if}
    </div>
  </section>
{/if}

<!-- Lightbox Modal -->
{#if showLightbox && lightboxEnabled && galleryImages.length > 0}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" role="button" tabindex="0" aria-label="Tutup lightbox" on:click={closeLightbox} on:keydown={(e) => e.key === 'Enter' || e.key === ' ' || e.key === 'Escape' ? closeLightbox() : null}>
    <div class="relative max-w-5xl max-h-full p-4" role="dialog" aria-modal="true" tabindex="-1">
      <!-- Close Button -->
      <button
        on:click={closeLightbox}
        class="absolute -top-2 -right-2 bg-white text-gray-800 rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
        aria-label="Close lightbox"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
      
      <!-- Image -->
      <img
        src={galleryImages[lightboxIndex]?.image_url}
        alt={galleryImages[lightboxIndex]?.caption}
        class="max-w-full max-h-[80vh] object-contain rounded-lg"
      />
      
      <!-- Caption -->
      {#if showCaptions && galleryImages[lightboxIndex]?.caption}
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-6 rounded-b-lg">
          <p class="text-white text-lg font-medium text-center">
            {galleryImages[lightboxIndex].caption}
          </p>
        </div>
      {/if}
      
      <!-- Navigation -->
      {#if galleryImages.length > 1}
        <button
          on:click={prevLightbox}
          class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200"
          aria-label="Previous image"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <button
          on:click={nextLightbox}
          class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200"
          aria-label="Next image"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
      {/if}
      
      <!-- Image counter -->
      <div class="absolute top-4 left-1/2 -translate-x-1/2 bg-black/50 text-white px-4 py-2 rounded-full text-sm">
        {lightboxIndex + 1} / {galleryImages.length}
      </div>
    </div>
  </div>
{/if}

<style>
  /* Hide scrollbar but keep functionality */
  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
    scroll-behavior: smooth;
  }
  
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
  
  /* Smooth scroll and touch gestures */
  .scrollbar-hide {
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
  }
  
  .scrollbar-hide button {
    scroll-snap-align: center;
  }
</style>
