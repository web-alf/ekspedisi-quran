<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import { showWarning } from '../../../stores/toast.js';
  import FlashMessage from '../../../Components/FlashMessage.svelte';

  export let errors = {};
  export const auth = {};
  export const flash = {};
  export const availableFields = {};
  export let defaultPositions;
 
   let formData = {
     name: '',
     description: '',
     template_file: null,
     field_positions: { ...defaultPositions },
     is_default: false
   };

  let previewImage = null;
  let imageInfo = null;
  let isDragging = false;
  let isSubmitting = false;

  function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
      formData.template_file = file;
      
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImage = e.target.result;
        
        const img = new Image();
        img.onload = () => {
          imageInfo = {
            width: img.naturalWidth,
            height: img.naturalHeight,
            size: (file.size / 1024 / 1024).toFixed(2) + ' MB'
          };
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  }

  function handleDragOver(event) {
    event.preventDefault();
    isDragging = true;
  }

  function handleDragLeave(event) {
    event.preventDefault();
    isDragging = false;
  }

  function handleDrop(event) {
    event.preventDefault();
    isDragging = false;
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
      const file = files[0];
      if (file.type.startsWith('image/')) {
        formData.template_file = file;
        handleFileUpload({ target: { files: [file] } });
      }
    }
  }

  function updateFieldPosition(fieldName, property, value) {
    formData.field_positions[fieldName] = {
      ...formData.field_positions[fieldName],
      [property]: value
    };
  }

  function handleSubmit() {
    if (isSubmitting) return;
    
    if (!formData.template_file) {
      showWarning('Template Belum Diupload', 'Silakan upload template PNG terlebih dahulu');
      return;
    }

    isSubmitting = true;

    const data = new FormData();
    data.append('name', formData.name);
    data.append('description', formData.description);
    data.append('template_file', formData.template_file);
    data.append('is_default', formData.is_default ? '1' : '0');

    Object.entries(formData.field_positions).forEach(([fieldName, position]) => {
      data.append(`field_positions[${fieldName}][x]`, position.x || 0);
      data.append(`field_positions[${fieldName}][y]`, position.y || 0);
      data.append(`field_positions[${fieldName}][font_size]`, position.font_size || 12);
      data.append(`field_positions[${fieldName}][color]`, position.color || '#000000');
    });

    router.post('/admin/certificate-templates', data, {
      onSuccess: () => {
        // Redirect handled by controller
      },
      onError: (errors) => {
        console.error('Upload failed:', errors);
        isSubmitting = false;
      },
      onFinish: () => {
        isSubmitting = false;
      }
    });
  }

  function resetForm() {
    formData = {
      name: '',
      description: '',
      template_file: null,
      field_positions: { ...defaultPositions },
      is_default: false
    };
    previewImage = null;
    imageInfo = null;
  }
</script>

<svelte:head>
  <title>Buat Template Sertifikat - Admin</title>
</svelte:head>

<AdminLayout>
<div class="px-6 py-8">
  <!-- Flash Messages -->
    <FlashMessage />

  <!-- Header -->
<div class="mb-6 lg:mb-8">
<div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
<div>
  <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Buat Template Sertifikat</h1>
    <p class="text-gray-600 mt-1">Upload template PNG dan atur posisi field dengan preview interaktif</p>
  </div>
<button
  on:click={() => router.visit('/admin/certificate-templates')}
    class="self-start lg:self-auto bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors flex items-center"
>
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
          Kembali
        </button>
      </div>
    </div>

    <!-- Form -->
    <form on:submit|preventDefault={handleSubmit} class="space-y-6">
      <!-- Enhanced Layout dengan Interactive Preview -->
      <div class="space-y-6 lg:space-y-8">
      
      <!-- Basic Info -->
      <div class="bg-white rounded-lg shadow-sm border p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Template</h3>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Template Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              Nama Template *
            </label>
            <input
              type="text"
              id="name"
              bind:value={formData.name}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.name ? 'border-red-500' : ''}"
              placeholder="Masukkan nama template..."
              required
            />
            {#if errors.name}
              <p class="mt-1 text-sm text-red-600">{errors.name}</p>
            {/if}
          </div>

          <!-- Template Settings -->
          <div>
            <label for="template-settings" class="block text-sm font-medium text-gray-700 mb-2">Pengaturan</label>
            <div class="flex items-center" id="template-settings">
              <input
                id="is_default"
                type="checkbox"
                bind:checked={formData.is_default}
                class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
              />
              <label for="is_default" class="ml-2 block text-sm text-gray-900">
                Jadikan sebagai template default
              </label>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div class="mt-6">
          <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
            Deskripsi
          </label>
          <textarea
            id="description"
            bind:value={formData.description}
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.description ? 'border-red-500' : ''}"
            placeholder="Deskripsi template..."
          ></textarea>
          {#if errors.description}
            <p class="mt-1 text-sm text-red-600">{errors.description}</p>
          {/if}
        </div>
      </div>

      <!-- File Upload -->
      <div class="bg-white rounded-lg shadow-sm border p-4 lg:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload Template</h3>
        
        <div
          class="border-2 border-dashed border-gray-300 rounded-lg p-6 lg:p-8 text-center transition-colors hover:border-[#eb3434] {errors.template_file ? 'border-red-500 bg-red-50' : ''}"
          class:border-[#eb3434]={isDragging}
          class:bg-red-50={isDragging}
          role="button"
          tabindex="0"
          on:dragover={handleDragOver}
          on:dragleave={handleDragLeave}
          on:drop={handleDrop}
          on:keydown={(e) => e.key === 'Enter' || e.key === ' ' ? document.querySelector('input[type="file"]')?.click() : null}
        >
          {#if formData.template_file}
            <div class="space-y-4">
              <svg class="w-16 h-16 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <p class="text-gray-900 font-medium truncate">{formData.template_file.name}</p>
                {#if imageInfo}
                  <p class="text-sm text-gray-600">
                    {imageInfo.width} × {imageInfo.height}px • {imageInfo.size}
                  </p>
                {/if}
              </div>
              <button
                type="button"
                on:click={() => { previewImage = null; formData.template_file = null; imageInfo = null; }}
                class="text-red-600 hover:text-red-800 text-sm font-medium"
              >
                Ganti File
              </button>
            </div>
          {:else}
            <div class="space-y-4">
              <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <div>
                <p class="text-gray-600 mb-2">Drag & drop template PNG di sini</p>
                <p class="text-sm text-gray-400 mb-4">atau</p>
                <label class="cursor-pointer">
                  <span class="px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors">
                    Pilih File
                  </span>
                  <input
                    type="file"
                    accept="image/png,image/jpg,image/jpeg"
                    on:change={handleFileUpload}
                    class="hidden"
                  />
                </label>
              </div>
              <p class="text-xs text-gray-400">PNG, JPG maksimal 10MB</p>
            </div>
          {/if}
        </div>
        
        {#if errors.template_file}
          <p class="mt-2 text-sm text-red-600">{errors.template_file}</p>
        {/if}
      </div>


      <!-- Form Actions -->
      <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
          <button
            type="button"
            on:click={resetForm}
            class="w-full lg:w-auto bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors flex items-center justify-center"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Reset Form
          </button>
          
          <div class="flex flex-col space-y-3 lg:flex-row lg:space-y-0 lg:space-x-3">
            <button
              type="button"
              on:click={() => router.visit('/admin/certificate-templates')}
              class="w-full lg:w-auto bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors flex items-center justify-center"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              Batal
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !formData.name || !formData.template_file}
              class="w-full lg:w-auto bg-[#eb3434] text-white px-6 py-2 rounded-lg hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center min-w-[140px]"
            >
              {#if isSubmitting}
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menyimpan...
              {:else}
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Template
              {/if}
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
</AdminLayout>
