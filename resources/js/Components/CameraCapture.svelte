<script>
    import { onMount, onDestroy } from 'svelte';
    import { toast } from '../utils/notifications.js';

    export let onCapture = null; // Callback function when photo is captured
    export let maxPhotos = 5; // Maximum number of photos
    export let capturedPhotos = []; // Array of captured photos (base64)
    export let showPreview = true; // Show preview of captured photos
    export let allowFileUpload = true; // Allow file upload as alternative

    let videoElement;
    let canvasElement;
    let stream = null;
    let isCameraActive = false;
    let facingMode = 'environment'; // 'user' for front camera, 'environment' for back camera
    let isCapturing = false;
    let error = null;

    // Check if device has camera (synchronously for testability)
    let hasCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

    onMount(async () => {
        console.log('[CameraCapture] Component mounted');
        console.log('[CameraCapture] Checking getUserMedia support...');

        if (hasCamera) {
            console.log('[CameraCapture] ✅ getUserMedia supported, hasCamera =', hasCamera);
        } else {
            console.error('[CameraCapture] ❌ getUserMedia NOT supported');
            console.log('[CameraCapture] navigator.mediaDevices:', navigator.mediaDevices);
        }
    });

    async function startCamera() {
        console.log('[CameraCapture] startCamera() called');
        console.log('[CameraCapture] hasCamera =', hasCamera);

        if (!hasCamera) {
            console.error('[CameraCapture] ❌ Cannot start camera: hasCamera is false');
            toast.error('Kamera tidak tersedia di perangkat ini');
            return;
        }

        try {
            error = null;
            isCapturing = true;
            console.log('[CameraCapture] isCapturing set to true');

            // Stop existing stream if any
            if (stream) {
                stopCamera();
            }

            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            };

            // Add timeout for getUserMedia (30 seconds)
            console.log('[CameraCapture] Requesting camera with constraints:', constraints);
            const timeoutPromise = new Promise((_, reject) =>
                setTimeout(() => reject(new Error('Camera access timeout')), 30000)
            );

            // Race between getUserMedia and timeout
            stream = await Promise.race([
                navigator.mediaDevices.getUserMedia(constraints),
                timeoutPromise
            ]);

            console.log('[CameraCapture] ✅ Camera stream obtained, stream ID:', stream?.id);
            console.log('[CameraCapture] videoElement exists:', !!videoElement);

            if (videoElement) {
                videoElement.srcObject = stream;
                await videoElement.play();
                isCameraActive = true;
                isCapturing = false;
                console.log('[CameraCapture] ✅ Video playing, isCameraActive =', isCameraActive);
                toast.success('Kamera berhasil diaktifkan!');
            } else {
                console.error('[CameraCapture] ❌ videoElement is null!');
            }
        } catch (err) {
            console.error('Error accessing camera:', err);

            // Specific error messages
            let errorMessage = 'Tidak dapat mengakses kamera';

            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                errorMessage = '❌ Izin kamera ditolak. Klik icon 🔒 di address bar untuk mengizinkan kamera.';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                errorMessage = '❌ Kamera tidak ditemukan. Pastikan perangkat memiliki kamera.';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                errorMessage = '❌ Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut terlebih dahulu.';
            } else if (err.message === 'Camera access timeout') {
                errorMessage = '⏱️ Waktu akses kamera habis. Silakan coba lagi.';
            } else if (err.name === 'OverconstrainedError') {
                errorMessage = '⚠️ Spesifikasi kamera tidak didukung. Coba ganti kamera.';
            } else {
                errorMessage = `❌ Error: ${err.message || 'Unknown error'}`;
            }

            error = errorMessage;
            toast.error(errorMessage);
            isCapturing = false;
            isCameraActive = false;
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        isCameraActive = false;
        if (videoElement) {
            videoElement.srcObject = null;
        }
    }

    function switchCamera() {
        facingMode = facingMode === 'user' ? 'environment' : 'user';
        if (isCameraActive) {
            startCamera();
        }
    }

    async function capturePhoto() {
        if (!videoElement || !canvasElement || !isCameraActive) {
            toast.error('Kamera belum aktif');
            return;
        }

        if (capturedPhotos.length >= maxPhotos) {
            toast.error(`Maksimal ${maxPhotos} foto`);
            return;
        }

        try {
            // Set canvas size to match video
            canvasElement.width = videoElement.videoWidth;
            canvasElement.height = videoElement.videoHeight;

            // Draw video frame to canvas
            const context = canvasElement.getContext('2d');
            context.drawImage(videoElement, 0, 0);

            // Convert canvas to blob with compression
            const dataUrl = canvasElement.toDataURL('image/jpeg', 0.8); // 80% quality

            // Add to captured photos
            capturedPhotos = [...capturedPhotos, {
                dataUrl: dataUrl,
                timestamp: new Date().toISOString(),
                id: Date.now() + Math.random()
            }];

            // Play capture sound (optional)
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIGWi78OScTAoNUKbh8LdjHAU1k9fyz38wByp8w/HY');
            audio.volume = 0.3;
            audio.play().catch(() => {}); // Ignore errors if sound fails

            toast.success('Foto berhasil diambil!');

            // Call callback if provided
            if (onCapture) {
                onCapture(capturedPhotos);
            }

        } catch (err) {
            console.error('Error capturing photo:', err);
            toast.error('Gagal mengambil foto');
        }
    }

    function removePhoto(photoId) {
        capturedPhotos = capturedPhotos.filter(photo => photo.id !== photoId);

        // Call callback to update parent
        if (onCapture) {
            onCapture(capturedPhotos);
        }

        toast.success('Foto dihapus');
    }

    function handleFileUpload(event) {
        const files = event.target.files;

        if (!files || files.length === 0) return;

        const remainingSlots = maxPhotos - capturedPhotos.length;
        if (remainingSlots <= 0) {
            toast.error(`Maksimal ${maxPhotos} foto`);
            return;
        }

        const filesToProcess = Array.from(files).slice(0, remainingSlots);

        filesToProcess.forEach(file => {
            if (!file.type.startsWith('image/')) {
                toast.error(`${file.name} bukan file gambar`);
                return;
            }

            if (file.size > 10 * 1024 * 1024) { // 10MB
                toast.error(`${file.name} terlalu besar (max 10MB)`);
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                // Compress image before adding
                compressImage(e.target.result, (compressedDataUrl) => {
                    capturedPhotos = [...capturedPhotos, {
                        dataUrl: compressedDataUrl,
                        timestamp: new Date().toISOString(),
                        id: Date.now() + Math.random(),
                        fileName: file.name
                    }];

                    if (onCapture) {
                        onCapture(capturedPhotos);
                    }
                });
            };
            reader.readAsDataURL(file);
        });

        // Reset input
        event.target.value = '';
    }

    function compressImage(dataUrl, callback) {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            let width = img.width;
            let height = img.height;

            // Resize if too large
            const maxWidth = 1920;
            const maxHeight = 1080;

            if (width > maxWidth || height > maxHeight) {
                if (width > height) {
                    height = (height / width) * maxWidth;
                    width = maxWidth;
                } else {
                    width = (width / height) * maxHeight;
                    height = maxHeight;
                }
            }

            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            callback(canvas.toDataURL('image/jpeg', 0.8));
        };
        img.src = dataUrl;
    }

    onDestroy(() => {
        stopCamera();
    });
</script>

<svelte:window on:beforeunload={stopCamera} />

<div class="camera-capture-container">
    <!-- Camera Controls -->
    <div class="space-y-4">
        {#if error}
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm">{error}</span>
                </div>
            </div>
        {/if}

        <!-- Camera View (Always rendered, but hidden when not active) -->
        <div class="relative bg-black rounded-lg overflow-hidden shadow-lg" style="display: {isCameraActive ? 'block' : 'none'}">
            <video
                bind:this={videoElement}
                autoplay
                playsinline
                muted
                class="w-full h-auto"
                style="max-height: 60vh; object-fit: contain;"
                aria-label="Camera preview for capturing documentation photos"
            >
                <track kind="captions" />
            </video>

            <!-- Camera Overlay -->
            {#if isCameraActive}
                <div class="absolute top-0 left-0 right-0 p-4 bg-gradient-to-b from-black/50 to-transparent">
                    <div class="flex items-center justify-between text-white">
                        <span class="text-sm font-medium">
                            📸 {capturedPhotos.length}/{maxPhotos} Foto
                        </span>
                        <button
                            type="button"
                            on:click={switchCamera}
                            class="p-2 rounded-full bg-white/20 hover:bg-white/30 transition"
                            title="Ganti kamera"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Capture Button -->
                <div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black/50 to-transparent">
                    <div class="flex items-center justify-center gap-4">
                        <button
                            type="button"
                            on:click={stopCamera}
                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition shadow-lg"
                        >
                            ✕ Tutup
                        </button>
                        <button
                            type="button"
                            on:click={capturePhoto}
                            disabled={capturedPhotos.length >= maxPhotos}
                            class="w-16 h-16 bg-white rounded-full shadow-lg flex items-center justify-center hover:scale-105 active:scale-95 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <div class="w-14 h-14 bg-white border-4 border-gray-800 rounded-full"></div>
                        </button>
                        <div class="w-24"></div> <!-- Spacer for centering -->
                    </div>
                </div>
            {/if}
        </div>

        <!-- Camera Control Buttons -->
        {#if !isCameraActive}
            <!-- Camera Not Active - Show Buttons -->
            <div class="flex flex-col sm:flex-row gap-3">
                {#if hasCamera}
                    <button
                        type="button"
                        on:click={startCamera}
                        disabled={isCapturing || capturedPhotos.length >= maxPhotos}
                        class="flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-medium transition flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {isCapturing ? 'Membuka Kamera...' : 'Buka Kamera'}
                    </button>
                {/if}

                {#if allowFileUpload}
                    <label class="flex-1 px-4 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition cursor-pointer flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Pilih dari Galeri
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            on:change={handleFileUpload}
                            class="hidden"
                            disabled={capturedPhotos.length >= maxPhotos}
                        />
                    </label>
                {/if}
            </div>

            {#if !hasCamera}
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg text-sm">
                    ⚠️ Kamera tidak tersedia di perangkat ini. Silakan gunakan opsi upload file.
                </div>
            {/if}
        {/if}

        <!-- Hidden canvas for capturing -->
        <canvas bind:this={canvasElement} class="hidden"></canvas>

        <!-- Preview Captured Photos -->
        {#if showPreview && capturedPhotos.length > 0}
            <div class="space-y-2">
                <h4 class="font-medium text-gray-700">Foto Dokumentasi ({capturedPhotos.length}/{maxPhotos})</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    {#each capturedPhotos as photo (photo.id)}
                        <div class="relative group">
                            <img
                                src={photo.dataUrl}
                                alt="Captured at {photo.timestamp}"
                                class="w-full h-32 object-cover rounded-lg shadow border border-gray-200"
                            />
                            <button
                                type="button"
                                on:click={() => removePhoto(photo.id)}
                                class="absolute top-1 right-1 p-1.5 bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition"
                                title="Hapus foto"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                            {#if photo.fileName}
                                <div class="absolute bottom-1 left-1 right-1 bg-black/50 text-white text-xs px-2 py-1 rounded truncate">
                                    {photo.fileName}
                                </div>
                            {:else}
                                <div class="absolute bottom-1 left-1 right-1 bg-black/50 text-white text-xs px-2 py-1 rounded text-center">
                                    📸 Camera
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
    </div>
</div>

<style>
    .camera-capture-container {
        width: 100%;
    }

    video {
        display: block;
    }
</style>
