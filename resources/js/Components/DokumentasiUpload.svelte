<script>
    /**
     * Reusable Documentation Upload Component
     * Supports both camera capture and file upload
     */
    import CameraCapture from './CameraCapture.svelte';
    import { createEventDispatcher } from 'svelte';
    import HeroIcon from './UI/HeroIcon.svelte';

    export let maxPhotos = 5;
    export let label = 'Dokumentasi (Foto)';
    export let showToggle = true;
    export let defaultMode = 'upload'; // 'upload' or 'camera'
    export let allowModeSwitch = true;

    const dispatch = createEventDispatcher();

    let useCameraMode = defaultMode === 'camera';
    let capturedPhotos = [];
    let uploadedFiles = [];
    let previewImages = [];

    // Toggle between camera and upload mode
    function toggleMode() {
        if (!allowModeSwitch) return;

        useCameraMode = !useCameraMode;

        // Clear data when switching modes
        if (useCameraMode) {
            uploadedFiles = [];
            previewImages = [];
        } else {
            capturedPhotos = [];
        }

        // Notify parent
        dispatch('modeChanged', { mode: useCameraMode ? 'camera' : 'upload' });
    }

    // Handle camera capture
    function handleCameraCapture(photos) {
        capturedPhotos = photos;
        dispatch('photosChanged', {
            mode: 'camera',
            photos: capturedPhotos,
            files: convertPhotosToFiles()
        });
    }

    // Handle file upload
    function handleFileChange(event) {
        const files = event.target.files;
        uploadedFiles = Array.from(files);

        // Clear existing preview images
        previewImages = [];

        // Create preview images
        uploadedFiles.forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImages = [...previewImages, e.target.result];
            };
            reader.readAsDataURL(file);
        });

        // Notify parent
        dispatch('photosChanged', {
            mode: 'upload',
            files: uploadedFiles
        });
    }

    // Convert base64 photos to File objects
    function convertPhotosToFiles() {
        return capturedPhotos.map((photo, index) => {
            const filename = `camera_${Date.now()}_${index}.jpg`;
            return base64ToFile(photo.dataUrl, filename);
        });
    }

    // Convert base64 to File object
    export function base64ToFile(dataUrl, filename) {
        const arr = dataUrl.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);

        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }

        return new File([u8arr], filename, { type: mime });
    }

    // Public method to get files (can be called from parent)
    export function getFiles() {
        if (useCameraMode) {
            return convertPhotosToFiles();
        } else {
            return uploadedFiles;
        }
    }

    // Public method to get photo count
    export function getPhotoCount() {
        return useCameraMode ? capturedPhotos.length : uploadedFiles.length;
    }

    // Public method to clear all photos
    export function clearAll() {
        capturedPhotos = [];
        uploadedFiles = [];
        previewImages = [];
        dispatch('cleared');
    }
</script>

<div class="dokumentasi-upload-component">
    <!-- Header with mode toggle -->
    <div class="flex items-center justify-between mb-2">
        <span class="block text-sm font-medium text-gray-700">
            {label}
        </span>
        {#if showToggle && allowModeSwitch}
            <button
                type="button"
                on:click={toggleMode}
                class="inline-flex items-center gap-1.5 text-xs px-3 py-1 rounded-full {useCameraMode ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-gray-100 text-gray-700 border border-gray-300'} font-medium transition hover:shadow"
            >
                {#if useCameraMode}
                    <HeroIcon name="camera" class="w-3.5 h-3.5" />
                    <span>Mode Kamera</span>
                {:else}
                    <HeroIcon name="arrow-up-tray" class="w-3.5 h-3.5" />
                    <span>Mode Upload</span>
                {/if}
            </button>
        {/if}
    </div>

    {#if useCameraMode}
        <!-- Camera Capture Mode -->
        <CameraCapture
            maxPhotos={maxPhotos}
            bind:capturedPhotos={capturedPhotos}
            onCapture={handleCameraCapture}
            showPreview={true}
            allowFileUpload={false}
        />
    {:else}
        <!-- File Upload Mode -->
        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition">
            <div class="space-y-1 text-center">
                <HeroIcon name="photo" class="mx-auto h-12 w-12 text-gray-400" />
                <div class="flex text-sm text-gray-600 justify-center">
                    <label for="file-upload-docs" class="relative cursor-pointer bg-white rounded-md font-medium text-red-600 hover:text-red-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-red-500">
                        <span>Upload file</span>
                        <input
                            id="file-upload-docs"
                            name="dokumentasi[]"
                            type="file"
                            multiple
                            class="sr-only"
                            on:change={handleFileChange}
                            accept="image/*"
                            disabled={uploadedFiles.length >= maxPhotos}
                        >
                    </label>
                    <p class="pl-1">atau drag and drop</p>
                </div>
                <p class="text-xs text-gray-500">
                    PNG, JPG, GIF up to 10MB (Max {maxPhotos} foto)
                </p>
            </div>
        </div>

        <!-- Preview uploaded images -->
        {#if previewImages.length > 0}
            <div class="mt-3 grid grid-cols-3 gap-3">
                {#each previewImages as image, index}
                    <div class="relative group">
                        <img src={image} alt="Preview {index}" class="h-24 w-full object-cover rounded-md border border-gray-200">
                        <div class="absolute bottom-1 left-1 right-1 bg-black/50 text-white text-xs px-2 py-1 rounded text-center opacity-0 group-hover:opacity-100 transition">
                            {uploadedFiles[index]?.name || `Foto ${index + 1}`}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    {/if}

    <!-- Photo count indicator -->
    {#if (useCameraMode && capturedPhotos.length > 0) || (!useCameraMode && uploadedFiles.length > 0)}
        <div class="mt-2 text-xs text-gray-600 flex items-center gap-1.5">
            <HeroIcon name="check-circle" class="w-4 h-4 text-green-600" />
            <span class="font-medium">
                {useCameraMode ? capturedPhotos.length : uploadedFiles.length}/{maxPhotos} foto dipilih
            </span>
        </div>
    {/if}
</div>

<style>
    .dokumentasi-upload-component {
        width: 100%;
    }
</style>
