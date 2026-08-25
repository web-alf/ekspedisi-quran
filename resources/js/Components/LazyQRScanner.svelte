<script>
  import { onMount, onDestroy, createEventDispatcher } from 'svelte';
  
  export let width = 300;
  export let height = 300;
  export let facingMode = 'environment'; // 'user' for front camera, 'environment' for back
  export let fps = 10;
  export let qrbox = 250;
  export let aspectRatio = 1.0;
  export let loadingText = 'Loading camera...';
  export let showToggleCamera = true;
  
  const dispatch = createEventDispatcher();
  
  let scannerContainer;
  let html5QrCode;
  let isScanning = false;
  let loading = true;
  let error = null;
  let camerasAvailable = [];
  let currentCameraId = null;

  onMount(async () => {
    try {
      // Dynamically import HTML5 QrCode
      const { Html5QrcodeScanner, Html5Qrcode } = await import('html5-qrcode');
      
      // Get available cameras
      try {
        const cameras = await Html5Qrcode.getCameras();
        camerasAvailable = cameras;
        
        // Select preferred camera based on facingMode prop
        const preferredCamera = cameras.find(camera =>
          camera.label.toLowerCase().includes(facingMode) ||
          camera.label.toLowerCase().includes('back')
        ) || cameras[0];
        
        currentCameraId = preferredCamera?.id;
      } catch (err) {
        console.warn('Could not get cameras:', err);
      }

      // Initialize scanner
      const config = {
        fps: fps,
        qrbox: { width: qrbox, height: qrbox },
        aspectRatio: aspectRatio,
        showTorchButtonIfSupported: true,
        showZoomSliderIfSupported: true,
        defaultZoomValueIfSupported: 2,
      };

      html5QrCode = new Html5QrcodeScanner('qr-scanner-container', config, false);
      
      html5QrCode.render(onScanSuccess, onScanError);
      
      loading = false;
      isScanning = true;

      dispatch('scannerReady');
    } catch (err) {
      console.error('Failed to initialize QR scanner:', err);
      error = 'Failed to initialize camera. Please check camera permissions.';
      loading = false;
    }
  });

  onDestroy(() => {
    stopScanning();
  });

  function onScanSuccess(decodedText, decodedResult) {
    dispatch('scanSuccess', {
      text: decodedText,
      result: decodedResult
    });
  }

  function onScanError(error) {
    // Don't dispatch every scan error as they're frequent
    // Only dispatch if it's a critical error
    if (error.includes('Camera')) {
      dispatch('scanError', { error });
    }
  }

  function stopScanning() {
    if (html5QrCode && isScanning) {
      try {
        html5QrCode.clear();
        isScanning = false;
        dispatch('scannerStopped');
      } catch (err) {
        console.error('Error stopping scanner:', err);
      }
    }
  }

  function startScanning() {
    if (html5QrCode && !isScanning) {
      try {
        const config = {
          fps: fps,
          qrbox: { width: qrbox, height: qrbox },
          aspectRatio: aspectRatio
        };

        html5QrCode.render(onScanSuccess, onScanError);
        isScanning = true;
        dispatch('scannerStarted');
      } catch (err) {
        console.error('Error starting scanner:', err);
        error = 'Failed to start scanner';
      }
    }
  }

  async function toggleCamera() {
    if (!camerasAvailable || camerasAvailable.length < 2) return;

    try {
      stopScanning();
      
      // Find next camera
      const currentIndex = camerasAvailable.findIndex(cam => cam.id === currentCameraId);
      const nextIndex = (currentIndex + 1) % camerasAvailable.length;
      currentCameraId = camerasAvailable[nextIndex].id;

      // Restart with new camera
      setTimeout(() => {
        startScanning();
      }, 500);
    } catch (err) {
      console.error('Error switching camera:', err);
    }
  }

  function requestCameraPermission() {
    // Try to access camera to trigger permission request
    navigator.mediaDevices.getUserMedia({ video: true })
      .then(() => {
        // Permission granted, try to initialize again
        window.location.reload();
      })
      .catch((err) => {
        error = 'Camera permission denied. Please enable camera access in browser settings.';
      });
  }

  // Public methods
  export function stop() {
    stopScanning();
  }

  export function start() {
    startScanning();
  }

  export function isCurrentlyScanning() {
    return isScanning;
  }
</script>

<div class="w-full max-w-md mx-auto">
  {#if loading}
    <div class="flex items-center justify-center p-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300" style="width: {width}px; height: {height}px">
      <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
        <p class="text-sm text-gray-600">{loadingText}</p>
      </div>
    </div>
  {:else if error}
    <div class="flex items-center justify-center p-8 bg-red-50 rounded-lg border-2 border-red-300" style="width: {width}px; height: {height}px">
      <div class="text-center">
        <div class="text-red-400 mb-2">
          <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
        </div>
        <p class="text-sm text-red-700 mb-3">{error}</p>
        {#if error.includes('permission')}
          <button 
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg"
            on:click={requestCameraPermission}
          >
            Grant Camera Permission
          </button>
        {:else}
          <button 
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg"
            on:click={() => window.location.reload()}
          >
            Retry
          </button>
        {/if}
      </div>
    </div>
  {:else}
    <div class="relative">
      <!-- QR Scanner Container -->
      <div id="qr-scanner-container" bind:this={scannerContainer}></div>
      
      <!-- Control Buttons -->
      {#if showToggleCamera && camerasAvailable.length > 1}
        <div class="absolute top-4 right-4 z-10">
          <button
            class="bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-70 transition-all"
            on:click={toggleCamera}
            title="Switch Camera"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </button>
        </div>
      {/if}

      <!-- Scanner Controls -->
      <div class="flex justify-center mt-4 space-x-2">
        <button
          class="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg disabled:opacity-50"
          on:click={startScanning}
          disabled={isScanning}
        >
          Start Scan
        </button>
        <button
          class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg disabled:opacity-50"
          on:click={stopScanning}
          disabled={!isScanning}
        >
          Stop Scan
        </button>
      </div>
    </div>
  {/if}
</div>

<style>
  /* Override some default html5-qrcode styles */
  :global(#qr-scanner-container img) {
    border-radius: 8px;
  }
  
  :global(#qr-scanner-container video) {
    border-radius: 8px;
  }

  :global(#qr-scanner-container) {
    border-radius: 8px;
    overflow: hidden;
  }
</style>