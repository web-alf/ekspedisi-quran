<script>
  import { onMount, onDestroy, createEventDispatcher, tick } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  export let width = 300;
  export let height = 300;
  export let facingMode = 'environment'; // 'user' for front camera, 'environment' for back
  export let fps = 10;
  export let qrbox = 250;
  export let aspectRatio = 1.0;
  export let loadingText = 'Menyalakan kamera...';
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
   * dirender.
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

  /**
   * Menerjemahkan kegagalan peramban menjadi kalimat yang berguna.
   *
   * Tanpa ini, semua kegagalan tampak sama di layar dan orang menebak-nebak.
   * Yang paling sering di HP adalah NotAllowedError — izin ditolak, biasanya
   * karena halaman dibuka lewat koneksi tidak aman atau izin pernah ditolak.
   */
  function terjemahkanGagal(err) {
    const nama = err?.name || '';

    if (nama === 'NotAllowedError' || nama === 'SecurityError') {
      return 'Izin kamera ditolak. Pastikan halaman dibuka lewat HTTPS, lalu izinkan akses kamera untuk situs ini dan coba lagi.';
    }
    if (nama === 'NotFoundError' || nama === 'OverconstrainedError') {
      return 'Kamera tidak ditemukan di perangkat ini.';
    }
    if (nama === 'NotReadableError' || nama === 'AbortError') {
      return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
    }

    return `Kamera tidak dapat dinyalakan: ${err?.message || err}`;
  }

  /**
   * Menyalakan kamera LANGSUNG, tanpa UI bawaan html5-qrcode.
   *
   * Dulu komponen ini memakai pemindai bawaan pustaka yang merender UI sendiri
   * berisi tautan "Request Camera Permissions" dan "Scan an Image File". Akibat
   * di HP: menekan tombol kamera TIDAK langsung membuka kamera, tetapi
   * memunculkan satu klik tambahan yang membingungkan — dan kalau tautannya
   * tidak tersentuh, kamera tidak pernah menyala sama sekali.
   *
   * Html5Qrcode.start() membuka kamera saat itu juga. Ditambah permintaan izin
   * eksplisit lebih dulu supaya kegagalan izin bisa dibedakan dari kegagalan
   * lain, dan supaya dialog izin muncul dari sentuhan pengguna (beberapa
   * peramban menolak permintaan yang tidak dipicu sentuhan).
   */
  async function mulaiScanner() {
    loading = true;
    error = null;

    try {
      // Import dinamis: pustaka pemindai tidak ikut di bundel awal halaman.
      const { Html5Qrcode } = await import('html5-qrcode');

      if (!(await tungguContainer())) {
        throw new Error('wadah pemindai tidak muncul di halaman');
      }

      // Izin diminta lebih dulu, dan hasilnya dipakai sebagai penentu:
      // kalau ini gagal, sebabnya hampir selalu izin/HTTPS — bukan hal lain.
      await pastikanIzinKamera();

      if (!camerasAvailable.length) {
        try {
          camerasAvailable = await Html5Qrcode.getCameras();
        } catch (err) {
          console.warn('Daftar kamera tidak terbaca:', err);
        }
      }

      const kameraTerpilih = pilihKamera();

      html5QrCode = new Html5Qrcode(ELEMENT_ID, false);

      await html5QrCode.start(
        kameraTerpilih,
        {
          fps: fps,
          qrbox: { width: qrbox, height: qrbox },
          aspectRatio: aspectRatio,
        },
        onScanSuccess,
        onScanError
      );

      isScanning = true;
      loading = false;

      dispatch('scannerReady');
    } catch (err) {
      console.error('Kamera gagal dinyalakan:', err);
      error = terjemahkanGagal(err);
      loading = false;
      // Sisa objek pemindai dibersihkan supaya percobaan berikutnya tidak
      // bertumpuk dengan yang gagal (kamera bisa ikut tertahan).
      await bersihkanPemindai();
    }
  }

  /**
   * Meminta izin kamera dan MELEPAS kameranya segera setelah diizinkan.
   *
   * Pelepasan itu penting: tanpa track.stop(), kamera tetap dikuasai permintaan
   * izin ini sehingga pemindai gagal menyala karena perangkat "sedang dipakai".
   */
  async function pastikanIzinKamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
      throw Object.assign(new Error('peramban tidak mendukung akses kamera'), {
        name: 'NotAllowedError',
      });
    }

    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode },
    });
    stream.getTracks().forEach((track) => track.stop());
  }

  /**
   * Kamera yang dipakai: yang terpilih lebih dulu, lalu id perangkat bila sudah
   * ada, terakhir facingMode (browser memilih sendiri kamera belakang).
   */
  function pilihKamera() {
    if (currentCameraId) {
      return currentCameraId;
    }

    const belakang = camerasAvailable.find((camera) =>
      (camera.label || '').toLowerCase().includes('back') ||
      (camera.label || '').toLowerCase().includes('rear')
    );

    if (belakang?.id) {
      currentCameraId = belakang.id;
      return belakang.id;
    }

    return { facingMode };
  }

  onMount(() => {
    mulaiScanner();
  });

  onDestroy(() => {
    bersihkanPemindai();
  });

  function onScanSuccess(decodedText, decodedResult) {
    dispatch('scanSuccess', {
      text: decodedText,
      result: decodedResult
    });
  }

  function onScanError() {
    // Kegagalan baca per-frame itu wajar (QR belum masuk kotak). Sengaja tidak
    // dispatcher supaya tidak membanjiri pendengar dengan ribuan pesan.
  }

  async function bersihkanPemindai() {
    try {
      if (html5QrCode) {
        await html5QrCode.stop();
        html5QrCode.clear();
        html5QrCode = null;
      }
    } catch (err) {
      // Sudah berhenti / belum pernah menyala — bukan masalah.
    } finally {
      isScanning = false;
    }
  }

  async function stopScanning() {
    await bersihkanPemindai();
    dispatch('scannerStopped');
  }

  async function mulaiUlangKamera() {
    await bersihkanPemindai();
    await mulaiScanner();
    dispatch('scannerStarted');
  }

  /**
   * Menukar ke kamera berikutnya. Dipakai di perangkat dengan kamera lebih dari
   * satu — dan berguna persis saat kamera yang terpilih tidak bisa menyala.
   */
  async function toggleCamera() {
    if (!camerasAvailable || camerasAvailable.length < 2) return;

    const currentIndex = camerasAvailable.findIndex((cam) => cam.id === currentCameraId);
    const nextIndex = (currentIndex + 1) % camerasAvailable.length;
    currentCameraId = camerasAvailable[nextIndex].id;

    await mulaiUlangKamera();
  }

  async function muatUlang() {
    await bersihkanPemindai();
    await mulaiScanner();
  }

  // Public methods
  export function stop() {
    return stopScanning();
  }

  export function start() {
    return mulaiScanner();
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
    error bila elemennya tidak ada, pemindai tidak pernah bisa dibuat.
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
        <button 
          class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg"
          on:click={muatUlang}
        >
          Coba Lagi
        </button>
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
            title="Ganti Kamera"
          >
            <HeroIcon name="arrow-path" class="w-5 h-5" />
          </button>
        </div>
      {/if}

      <!-- Scanner Controls -->
      <div class="flex justify-center mt-4 space-x-2">
        <button
          class="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg disabled:opacity-50"
          on:click={mulaiUlangKamera}
          disabled={isScanning}
        >
          Mulai Pindai
        </button>
        <button
          class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg disabled:opacity-50"
          on:click={stopScanning}
          disabled={!isScanning}
        >
          Hentikan
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
