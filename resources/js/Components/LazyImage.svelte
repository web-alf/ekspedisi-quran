<script>
  import { onMount } from 'svelte';
  import { lazyload } from '@/utils/lazyload.js';
  
  export let src = '';
  export let alt = '';
  export let className = '';
  export let loading = 'lazy';
  export let placeholder = '/images/placeholder.webp';
  export let srcset = '';
  export let sizes = '';
  export let width = null;
  export let height = null;
  
  let imageElement;
  let loaded = false;
  let error = false;
  
  function handleLoad() {
    loaded = true;
  }
  
  function handleError() {
    error = true;
  }
</script>

<div class="lazy-image-container {className}">
  <img
    bind:this={imageElement}
    src={loaded ? src : placeholder}
    data-src={src}
    {alt}
    {srcset}
    {sizes}
    {width}
    {height}
    class="lazy-image {loaded ? 'loaded' : ''} {error ? 'error' : ''}"
    class:loading={!loaded && !error}
    on:load={handleLoad}
    on:error={handleError}
    use:lazyload={{
      src,
      srcset,
      options: { rootMargin: '50px' }
    }}
  />
  
  {#if !loaded && !error}
    <div class="lazy-loader">
      <div class="pulse-animation"></div>
    </div>
  {/if}
  
  {#if error}
    <div class="error-placeholder">
      <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <span class="text-sm text-gray-500 mt-2">Gambar gagal dimuat</span>
    </div>
  {/if}
</div>

<style>
  .lazy-image-container {
    position: relative;
    overflow: hidden;
  }
  
  .lazy-image {
    transition: opacity 0.3s ease-in-out;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  
  .lazy-image.loading {
    opacity: 0.3;
  }
  
  .lazy-image.loaded {
    opacity: 1;
  }
  
  .lazy-image.error {
    display: none;
  }
  
  .lazy-loader {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgb(243 244 246);
  }
  
  .pulse-animation {
    width: 2rem;
    height: 2rem;
    background-color: rgb(156 163 175);
    border-radius: 50%;
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  }
  
  .error-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: absolute;
    inset: 0;
    background-color: rgb(249 250 251);
  }
  
  @keyframes pulse {
    0%, 100% {
      opacity: 1;
    }
    50% {
      opacity: 0.5;
    }
  }
</style>