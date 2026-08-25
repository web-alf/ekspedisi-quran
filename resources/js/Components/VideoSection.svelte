<script>
  import { onDestroy } from 'svelte';

  export let settings = {};
  export let videos = [];

  // Video configuration from settings
  $: videoEnabled = settings.landing_video_enabled === '1' || settings.landing_video_enabled === true;
  $: videoTitle = settings.landing_video_title || 'Video Ekspedisi';
  $: videoSubtitle = settings.landing_video_subtitle || 'Dokumentasi video perjalanan mushaf Al-Qur\'an';
  $: videoLayout = settings.landing_video_layout || 'grid';
  $: showCaptions = settings.landing_video_show_captions === '1' || settings.landing_video_show_captions === true;
  $: lightboxEnabled = settings.landing_video_lightbox !== '0';
  $: maxItems = parseInt(settings.landing_video_max_items) || 6;

  // Limit videos to max items
  $: displayVideos = videos.slice(0, maxItems);

  // Lightbox state
  let showLightbox = false;
  let lightboxIndex = 0;
  let currentVideoId = null;

  // Slider state
  let currentSlide = 0;
  let sliderInterval;

  // Extract YouTube video ID from URL
  function getYoutubeVideoId(url) {
    if (!url) return null;

    // youtu.be format
    const shortMatch = url.match(/youtu\.be\/([^?]+)/);
    if (shortMatch) return shortMatch[1];

    // youtube.com/watch?v= format
    const watchMatch = url.match(/[?&]v=([^&]+)/);
    if (watchMatch) return watchMatch[1];

    // youtube.com/embed/ format
    const embedMatch = url.match(/embed\/([^?]+)/);
    if (embedMatch) return embedMatch[1];

    return null;
  }

  // Get thumbnail URL from YouTube
  function getThumbnailUrl(url) {
    const videoId = getYoutubeVideoId(url);
    return videoId ? `https://img.youtube.com/vi/${videoId}/hqdefault.jpg` : null;
  }

  // Get embed URL
  function getEmbedUrl(url) {
    const videoId = getYoutubeVideoId(url);
    return videoId ? `https://www.youtube.com/embed/${videoId}?autoplay=1` : null;
  }

  function openLightbox(index) {
    if (!lightboxEnabled) return;
    lightboxIndex = index;
    currentVideoId = getYoutubeVideoId(displayVideos[index]?.video_url);
    showLightbox = true;
    document.body.style.overflow = 'hidden';

    // Stop slider when lightbox is open
    if (sliderInterval) clearInterval(sliderInterval);
  }

  function closeLightbox() {
    showLightbox = false;
    currentVideoId = null;
    document.body.style.overflow = '';
  }

  function nextLightbox() {
    lightboxIndex = (lightboxIndex + 1) % displayVideos.length;
    currentVideoId = getYoutubeVideoId(displayVideos[lightboxIndex]?.video_url);
  }

  function prevLightbox() {
    lightboxIndex = lightboxIndex === 0 ? displayVideos.length - 1 : lightboxIndex - 1;
    currentVideoId = getYoutubeVideoId(displayVideos[lightboxIndex]?.video_url);
  }

  function nextSlide() {
    currentSlide = (currentSlide + 1) % displayVideos.length;
  }

  function prevSlide() {
    currentSlide = currentSlide === 0 ? displayVideos.length - 1 : currentSlide - 1;
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

  // Auto-play functionality for slider
  $: if (videoLayout === 'slider' && displayVideos.length > 1) {
    if (sliderInterval) clearInterval(sliderInterval);
    sliderInterval = setInterval(nextSlide, 6000);
  } else if (sliderInterval) {
    clearInterval(sliderInterval);
  }

  // Cleanup interval on destroy
  onDestroy(() => {
    if (sliderInterval) clearInterval(sliderInterval);
  });
</script>

<svelte:window on:keydown={handleKeydown} />

{#if videoEnabled && displayVideos.length > 0}
  <section id="video" class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
          {videoTitle}
        </h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
          {videoSubtitle}
        </p>
      </div>

      <!-- Video Content -->
      {#if videoLayout === 'grid'}
        <!-- Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {#each displayVideos as video, index}
            <div class="group relative overflow-hidden rounded-xl shadow-lg bg-gray-900 hover:shadow-xl transition-shadow duration-300">
              <!-- Video Thumbnail with Play Button -->
              <button
                type="button"
                class="block w-full text-left relative"
                on:click={() => openLightbox(index)}
                aria-label="Play video"
              >
                <div class="aspect-w-16 aspect-h-9">
                  <img
                    src={video.thumbnail_url || getThumbnailUrl(video.video_url)}
                    alt={video.title}
                    class="w-full h-56 object-cover transition-transform duration-300 group-hover:scale-105"
                    loading="lazy"
                  />
                  <!-- Play Button Overlay -->
                  <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition-colors duration-300">
                    <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                      <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                      </svg>
                    </div>
                  </div>
                </div>
              </button>

              {#if showCaptions}
                <div class="p-4 bg-white">
                  <h3 class="font-semibold text-gray-900 line-clamp-2">{video.title}</h3>
                  {#if video.caption}
                    <p class="text-gray-600 text-sm mt-1 line-clamp-2">{video.caption}</p>
                  {/if}
                </div>
              {/if}
            </div>
          {/each}
        </div>

      {:else if videoLayout === 'slider'}
        <!-- Slider Layout -->
        <div class="relative max-w-4xl mx-auto">
          <div class="overflow-hidden rounded-xl shadow-2xl bg-gray-900">
            <div class="relative aspect-w-16 aspect-h-9 h-80 md:h-[450px]">
              {#each displayVideos as video, index}
                <div class="absolute inset-0 transition-opacity duration-500 {index === currentSlide ? 'opacity-100' : 'opacity-0'}">
                  <button
                    type="button"
                    class="block w-full h-full text-left relative"
                    on:click={() => openLightbox(index)}
                    aria-label="Play video"
                  >
                    <img
                      src={video.thumbnail_url || getThumbnailUrl(video.video_url)}
                      alt={video.title}
                      class="w-full h-full object-cover"
                    />
                    <!-- Play Button Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/40 transition-colors duration-300">
                      <div class="w-20 h-20 bg-red-600 rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform duration-300">
                        <svg class="w-10 h-10 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M8 5v14l11-7z"/>
                        </svg>
                      </div>
                    </div>
                    {#if showCaptions}
                      <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent">
                        <div class="absolute bottom-6 left-6 right-6">
                          <h3 class="text-white text-xl font-bold">{video.title}</h3>
                          {#if video.caption}
                            <p class="text-white/80 text-sm mt-1">{video.caption}</p>
                          {/if}
                        </div>
                      </div>
                    {/if}
                  </button>
                </div>
              {/each}
            </div>
          </div>

          <!-- Navigation Arrows -->
          {#if displayVideos.length > 1}
            <button
              on:click={prevSlide}
              class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-gray-800 p-3 rounded-full shadow-lg transition-all duration-200 hover:scale-110"
              aria-label="Previous video"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            <button
              on:click={nextSlide}
              class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/90 hover:bg-white text-gray-800 p-3 rounded-full shadow-lg transition-all duration-200 hover:scale-110"
              aria-label="Next video"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          {/if}

          <!-- Dots Indicator -->
          {#if displayVideos.length > 1}
            <div class="flex justify-center mt-6 space-x-2">
              {#each displayVideos as _, index}
                <button
                  on:click={() => goToSlide(index)}
                  class="w-3 h-3 rounded-full transition-all duration-200 {index === currentSlide ? 'bg-[#eb3434] scale-125' : 'bg-gray-300 hover:bg-gray-400'}"
                  aria-label="Go to slide {index + 1}"
                ></button>
              {/each}
            </div>
          {/if}
        </div>
      {/if}
    </div>
  </section>
{/if}

<!-- Lightbox Modal -->
{#if showLightbox && lightboxEnabled && displayVideos.length > 0}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/95" role="dialog" aria-modal="true">
    <div class="relative w-full max-w-5xl mx-4">
      <!-- Close Button -->
      <button
        on:click={closeLightbox}
        class="absolute -top-12 right-0 bg-white text-gray-800 rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
        aria-label="Close lightbox"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- Video Embed -->
      <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 16/9;">
        {#if currentVideoId}
          <iframe
            src={getEmbedUrl(displayVideos[lightboxIndex]?.video_url)}
            title={displayVideos[lightboxIndex]?.title}
            class="w-full h-full"
            style="aspect-ratio: 16/9; width: 100%; height: 100%; border: none;"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            loading="eager"
          ></iframe>
        {:else}
          <div class="flex items-center justify-center w-full h-full min-h-[300px]">
            <div class="text-white text-center">
              <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z"/>
                </svg>
              </div>
              <p>Loading video...</p>
            </div>
          </div>
        {/if}
      </div>

      <!-- Caption -->
      {#if showCaptions && displayVideos[lightboxIndex]?.title}
        <div class="mt-4 text-center">
          <h3 class="text-white text-xl font-bold">{displayVideos[lightboxIndex].title}</h3>
          {#if displayVideos[lightboxIndex]?.caption}
            <p class="text-white/80 text-sm mt-1">{displayVideos[lightboxIndex].caption}</p>
          {/if}
        </div>
      {/if}

      <!-- Navigation -->
      {#if displayVideos.length > 1}
        <button
          on:click={prevLightbox}
          class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 md:-translate-x-12 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200"
          aria-label="Previous video"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <button
          on:click={nextLightbox}
          class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 md:translate-x-12 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full transition-all duration-200"
          aria-label="Next video"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <!-- Video counter -->
        <div class="absolute -bottom-8 left-1/2 -translate-x-1/2 text-white text-sm">
          {lightboxIndex + 1} / {displayVideos.length}
        </div>
      {/if}
    </div>
  </div>
{/if}
