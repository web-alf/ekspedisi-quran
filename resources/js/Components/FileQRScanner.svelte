<script>
  import { createEventDispatcher } from 'svelte';
  import { toast } from '../utils/notifications.js';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  const dispatch = createEventDispatcher();
  
  let fileInput;
  let processing = false;
  let dragActive = false;
  
  async function handleFileUpload(file) {
    if (processing) return;
    
    if (!file || !file.type.startsWith('image/')) {
      toast.error('Hanya file gambar yang diperbolehkan');
      return;
    }
    
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
      toast.error('File terlalu besar (maksimal 10MB)');
      return;
    }
    
    processing = true;
    
    try {
      // Import QR code library dynamically to reduce initial bundle size
      const { default: QrScanner } = await import('qr-scanner');
      
      const result = await QrScanner.scanImage(file, {
        returnDetailedScanResult: false,
        highlightScanRegion: false,
        highlightCodeOutline: false
      });
      
      dispatch('scan', {
        decodedText: result,
        source: 'file'
      });
      
      toast.success('QR code berhasil dibaca dari file');
      
    } catch (error) {
      console.error('File QR scan error:', error);
      toast.error('Gagal membaca QR code dari file. Pastikan gambar jelas dan mengandung QR code.');
    } finally {
      processing = false;
      
      // Reset file input
      if (fileInput) {
        fileInput.value = '';
      }
    }
  }
  
  function handleFileInput(event) {
    const file = event.target.files[0];
    if (file) {
      handleFileUpload(file);
    }
  }
  
  function handleDrop(event) {
    event.preventDefault();
    dragActive = false;
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
      handleFileUpload(files[0]);
    }
  }
  
  function handleDragOver(event) {
    event.preventDefault();
  }
  
  function handleDragEnter() {
    dragActive = true;
  }
  
  function handleDragLeave() {
    dragActive = false;
  }
</script>

<div class="bg-white rounded-lg border-2 border-dashed border-gray-300 p-6">
  <div class="text-center">
    <HeroIcon name="photo" class="mx-auto h-12 w-12 text-gray-400" />
    
    <div class="mt-4">
      <h3 class="text-sm font-medium text-gray-900">Scan QR dari File Gambar</h3>
      <p class="text-sm text-gray-600 mt-1">Upload foto yang berisi QR code untuk di-scan</p>
    </div>
    
    <!-- File drop zone -->
    <div 
      class="mt-4 border-2 border-dashed rounded-lg p-4 transition-colors {dragActive ? 'border-blue-400 bg-blue-50' : 'border-gray-300'}"
      on:drop={handleDrop}
      on:dragover={handleDragOver}
      on:dragenter={handleDragEnter}
      on:dragleave={handleDragLeave}
      role="button"
      tabindex="0"
      aria-label="Drop file here or click to upload"
    >
      {#if processing}
        <div class="flex items-center justify-center">
          <svg class="animate-spin h-5 w-5 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-sm text-blue-600">Memproses gambar...</span>
        </div>
      {:else}
        <div class="space-y-2">
          <p class="text-sm text-gray-600">
            {dragActive ? 'Lepaskan file di sini' : 'Seret file ke sini atau'}
          </p>
          
          <input
            bind:this={fileInput}
            type="file"
            accept="image/*"
            on:change={handleFileInput}
            class="hidden"
            disabled={processing}
          />
          
          <button
            on:click={() => fileInput?.click()}
            disabled={processing}
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
          >
            <HeroIcon name="plus" class="mr-2 h-4 w-4" />
            Pilih File Gambar
          </button>
        </div>
      {/if}
    </div>
    
    <div class="mt-3 text-xs text-gray-500">
      <p>Format yang didukung: JPG, PNG, GIF, WEBP</p>
      <p>Maksimal ukuran file: 10MB</p>
      <p class="mt-1 text-orange-600">💡 Pastikan QR code terlihat jelas dalam gambar</p>
    </div>
  </div>
</div>

<style>
  [role="button"]:focus {
    outline: 2px solid #3B82F6;
    outline-offset: 2px;
  }
</style>