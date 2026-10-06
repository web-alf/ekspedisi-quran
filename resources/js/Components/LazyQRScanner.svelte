<script>
  import { onMount, onDestroy, createEventDispatcher, tick } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  export let width = 300;
  export let height = 300;
  export let facingMode = 'environment'; // 'user' for front camera, 'environment' for back
  export let fps = 10;
  export let qrbox = 250;
  export let aspectRatio = 1.0;
  export let loadingText = 'Loading camera...';
  export let showToggleCamera = true;

  const ELEMENT_ID = 'qr-scanner-container';

  const dispatch = createEventDispatcher();
  
  let scannerContainer;
  let html5QrCode;
  let isScanning = false;
  let loading = true;
  let error = null;
  let camerasAvailable = [];
  let currentCameraId = null;

  /**
   * Menunggu #qr-scanner-container benar-benar ada di DOM.
   *
   * html5-qrcode MELEMPAR error dari konstruktornya bila elemen tujuan belum ada
   * ("HTML Element with id=... not found"). Komponen ini dipasang di dalam blok
   * {#if} (tombol "Nyalakan Kamera"), jadi saat onMount berjalan elemennya belum
   * dirender. Dulu akibatnya blok {:else} yang memuat container itu tidak pernah
   * dieksekusi, sehingga kamera GAGAL SELALU — dan pesannya keliru menuduh izin
   * kamera, padahal tidak ada permintaan izin yang pernah terjadi.
   */
  async function tungguContainer(percobaan = 20) {
    for (let i = 0; i < percobaan; i++) {
      if (typeof document !== 'undefined' && document.getElementById(ELEMENT_ID)) {
        return true;
      }
      // tick() menunggu Svelte menyelesaikan flush, termasuk blok {#if} yang
      // sedang memasang komponen ini.
      await tick();
      await new Promise((resolve) => setTimeout(resolve, 50));
    }

    return typeof document !== 'undefined' && !!document.getElementById(ELEMENT_ID);
  }

  async function mulaiScanner() {
    try {
      // Import dinamis: pustaka pemindai tidak ikut di bundel awal halaman.
      const { Html5QrcodeScanner, Html5Qrcode } = await import('html5-qrcode');

      if (!(await tungguContainer())) {
        throw new Error('wadah pemindai tidak muncul di halaman');
      }

      // Daftar kamera hanya untuk pemilihan awal. Bila izin belum diberikan,
      // browser menyembunyikan label dan ini tetap boleh gagal diam-diam —
      // html5-qrcode yang akan meminta izinnya sendiri saat render.
      try {
        const cameras = await Html5Qrcode.getCameras();
        camerasAvailable = cameras;

        const preferredCamera = cameras.find(camera =>
          camera.label.toLowerCase().includes(facingMode) ||
          camera.label.toLowerCase().includes('back')
        ) || cameras[0];

        currentCameraId = preferredCamera?.id;
      } catch (err) {
        console.warn('Could not get cameras:', err);
      }

      const config = {
        fps: fps,
        qrbox: { width: qrbox, height: qrbox },
        aspectRatio: aspectRatio,
        showTorchButtonIfSupported: true,
        showZoomSliderIfSupported: true,
        defaultZoomValueIfSupported: 2,
      };

      html5QrCode = new Html5QrcodeScanner(ELEMENT_ID, config, false);
      
      html5QrCode.render(onScanSuccess, onScanError);
      
      loading = false;
      isScanning = true;

      dispatch('scannerReady');
    } catch (err) {
      console.error('Failed to initialize QR scanner:', err);
      // Sebab aslinya ikut ditampilkan. Pesan lama selalu menuduh izin kamera,
      // sehingga kegagalan lain (mis. wadah yang belum dirender) menyesatkan dan
      // membuat orang mengejar setelan izin yang sebenarnya tidak pernah diminta.
      error = `Kamera tidak dapat dinyalakan: ${err?.message || err}`;
      loading = false;
    }
  }

  onMount(() => {
    mulaiScanner();
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

  /**
   * Kegagalan yang benar-benar soal izin kamera. Dipakai agar tombol "Minta Izin"
   * hanya muncul ketika memang itu masalahnya — dulu blok ini selalu muncul untuk
   * SETIAP kegagalan, sehingga orang mengutak-atik izin padahal sebabnya lain.
   */
  $: menyinggungIzin = /izin|permission|NotAllowed|denied/i.test(error || '');

  async function muatUlang() {
    await tick();
    loading = true;
    error = null;
    await mulaiScanner();
  }

  function requestCameraPermission() {
    if (!navigator.mediaDevices?.getUserMedia) {
      error = 'Peramban ini tidak mendukung akses kamera.';
      return;
    }

    navigator.mediaDevices.getUserMedia({ video: true })
      .then((stream) => {
        // Hentikan segera: permintaan ini hanya untuk memicu dialog izin, bukan
        // untuk memakai kamera. Tanpa ini, kamera tertahan dan pemindai justru
        // gagal karena perangkat sedang dipakai.
        stream.getTracks().forEach((track) => track.stop());
        muatUlang();
      })
      .catch((err) => {
        if (err?.name === 'NotAllowedError') {
          error = 'Izin kamera ditolak. Aktifkan akses kamera di setelan peramban, lalu coba lagi.';
        } else if (err?.name === 'NotFoundError') {
          error = 'Kamera tidak ditemukan di perangkat ini.';
        } else if (err?.name === 'NotReadableError') {
          error = 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
        } else {
          error = `Tidak dapat mengakses kamera: ${err?.message || err}`;
        }
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
  <!--
    Wadah pemindai HARUS selalu ada di DOM, bukan di dalam cabang {:else}.

    Svelte hanya merender SATU cabang dari if/else-if/else. Dulu wadah ini berada
    di cabang {:else}, yang mensyaratkan loading=false — sementara loading baru
    dijadikan false SETELAH pemindai berhasil dibuat. Urutan itu saling
    mengunci: wadah tidak pernah dirender, dan karena html5-qrcode melempar
    error bila elemennya tidak ada, pemindai tidak pernah bisa dibuat. Kamera
    gagal permanen, dengan pesan yang salah menuduh izin kamera.
  -->
  <div id="qr-scanner-container" bind:this={scannerContainer}></div>

  {#if loading}
    <div class="flex items-center justify-center p-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300" style="width: {width}px; height: {height}px">
      <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
        <p class="text-sm text-gray-600">{loadingText}</p>
      </div>
    </div>
  {/if}

  {#if error}
    <div class="flex items-center justify-center p-8 bg-red-50 rounded-lg border-2 border-red-300" style="width: {width}px; height: {height}px">
      <div class="text-center">
        <div class="text-red-400 mb-2">
          <HeroIcon name="video-camera" class="w-8 h-8 mx-auto" />
        </div>
        <p class="text-sm text-red-700 mb-3">{error}</p>
        <div class="flex flex-col sm:flex-row gap-2 justify-center">
          <button 
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg"
            on:click={muatUlang}
          >
            Coba Lagi
          </button>
          {#if menyinggungIzin}
            <button 
              class="px-4 py-2 text-sm border border-red-300 text-red-700 hover:bg-red-100 rounded-lg"
              on:click={requestCameraPermission}
            >
              Minta Izin Kamera
            </button>
          {/if}
        </div>
      </div>
    </div>
  {/if}

  {#if !loading && !error}
    <div class="relative">
      <!-- Control Buttons -->
      {#if showToggleCamera && camerasAvailable.length > 1}
        <div class="absolute top-4 right-4 z-10">
          <button
            class="bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-70 transition-all"
            on:click={toggleCamera}
            title="Switch Camera"
          >
            <HeroIcon name="arrow-path" class="w-5 h-5" />
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