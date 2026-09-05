<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { onMount } from 'svelte';
  import { showSuccess, showError } from '../../../stores/toast.js';

  export let template = {};
  export let availableFields = {};
  export let imageInfo = {};
  export let currentHijriDate = 'Semarang, [Tanggal Hijriah Saat Ini]';
  export let errors = {};

  let showDeleteModal = false;
  let localFieldPositions = { ...(template.field_positions || {}) };
  let templateImageElement;
  let scale = 1;
  let hasUnsavedChanges = false;
  let isSaving = false;
  
  // Edit mode state
  let editMode = false;
  let editFormData = {
    name: template.name || '',
    description: template.description || '',
    template_file: null,
    is_active: template.is_active || false,
    is_default: template.is_default || false
  };
  let previewImage = null;
  let isDragging = false;
  let isSubmitting = false;
  let hasNewFile = false;

  // Reactive statement to sync localFieldPositions when template changes
  $: if (template?.field_positions) {
    localFieldPositions = { ...template.field_positions };
  }

  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'long', 
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function formatFileSize(bytes) {
    if (!bytes) return 'N/A';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  }

  function setAsDefault() {
    if (template?.id) {
      router.patch(`/admin/certificate-templates/${template.id}/set-default`);
    }
  }

  function toggleStatus() {
    if (template?.id) {
      router.patch(`/admin/certificate-templates/${template.id}/toggle-status`);
    }
  }

  function toggleEditMode() {
    editMode = !editMode;
    if (editMode) {
      // Reset form data when entering edit mode
      editFormData = {
        name: template.name || '',
        description: template.description || '',
        template_file: null,
        is_active: template.is_active || false,
        is_default: template.is_default || false
      };
      hasNewFile = false;
      // Set initial preview from existing template
      if (template.id) {
        previewImage = `/admin/certificate-templates/${template.id}/preview`;
      }
    }
  }

  function confirmDelete() {
    showDeleteModal = true;
  }

  function handleDelete() {
    if (template?.id) {
      router.delete(`/admin/certificate-templates/${template.id}`, {
        onSuccess: () => {
          router.visit('/admin/certificate-templates');
        }
      });
    }
  }

  function calculateScale() {
    if (templateImageElement && template?.width) {
      scale = templateImageElement.clientWidth / template.width;
    }
  }
  
  onMount(() => {
    const handleResize = () => calculateScale();
    window.addEventListener('resize', handleResize);
    
    return () => {
      window.removeEventListener('resize', handleResize);
      clearTimeout(window.saveTimeout);
    };
  });

  function updateFieldPositionRealTime(fieldKey, property, value) {
    localFieldPositions[fieldKey] = {
      ...localFieldPositions[fieldKey],
      [property]: value
    };
    
    localFieldPositions = { ...localFieldPositions };
    hasUnsavedChanges = true;
  }

  async function saveAllFieldPositions() {
    if (!hasUnsavedChanges) return;
    
    isSaving = true;
    try {
      // Use Inertia router instead of fetch for better session handling
      router.post(`/admin/certificate-templates/${template.id}/update-all-positions`, {
        field_positions: localFieldPositions
      }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          // Update template with fresh data from server
          if (page.props?.template?.field_positions) {
            template.field_positions = page.props.template.field_positions;
          }
          hasUnsavedChanges = false;
          showSuccess('Berhasil Disimpan!', 'Posisi field template sertifikat telah diperbarui dengan baik.');
        },
        onError: (errors) => {
          console.error('Failed to save field positions:', errors);
          showError('Gagal Menyimpan!', 'Terjadi kesalahan saat menyimpan posisi field. Silakan coba lagi atau periksa koneksi internet Anda.');
        },
        onFinish: () => {
          isSaving = false;
        }
      });
    } catch (error) {
      console.error('Failed to save field positions:', error);
      showError('Gagal Menyimpan!', 'Terjadi kesalahan saat menyimpan posisi field. Silakan coba lagi atau periksa koneksi internet Anda.');
      isSaving = false;
    }
  }

  // File upload functions for edit mode
  function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
      editFormData.template_file = file;
      hasNewFile = true;
      
      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImage = e.target.result;
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
        editFormData.template_file = file;
        handleFileUpload({ target: { files: [file] } });
      }
    }
  }

  function removeCurrentFile() {
    editFormData.template_file = null;
    hasNewFile = false;
    
    // Reset to original template preview
    if (template.id) {
      previewImage = `/admin/certificate-templates/${template.id}/preview`;
    }
  }

  function handleEditSubmit() {
    if (isSubmitting) return;

    isSubmitting = true;

    const data = new FormData();
    data.append('_method', 'PUT');
    data.append('name', editFormData.name);
    data.append('description', editFormData.description);
    
    if (editFormData.template_file) {
      data.append('template_file', editFormData.template_file);
    }
    
    // Send field positions
    Object.entries(localFieldPositions).forEach(([fieldName, position]) => {
      if (position) {
        data.append(`field_positions[${fieldName}][x]`, position.x || 0);
        data.append(`field_positions[${fieldName}][y]`, position.y || 0);
        data.append(`field_positions[${fieldName}][font_size]`, position.font_size || 12);
        data.append(`field_positions[${fieldName}][color]`, position.color || '#000000');
      }
    });
    
    data.append('is_active', editFormData.is_active ? '1' : '0');
    data.append('is_default', editFormData.is_default ? '1' : '0');

    router.post(`/admin/certificate-templates/${template.id}`, data, {
      onSuccess: (page) => {
        // Update template with fresh data
        if (page.props?.template) {
          template = page.props.template;
          localFieldPositions = { ...template.field_positions };
        }
        editMode = false;
        hasUnsavedChanges = false;
        showSuccess('Berhasil Disimpan!', 'Template sertifikat telah diperbarui dengan baik.');
      },
      onError: (errors) => {
        console.error('Update failed:', errors);
        showError('Gagal Menyimpan!', 'Terjadi kesalahan saat menyimpan template. Silakan coba lagi.');
      },
      onFinish: () => {
        isSubmitting = false;
      }
    });
  }
</script>

<svelte:head>
  <title>{editMode ? 'Edit' : 'Detail'} Template - {template?.name || 'Certificate Template'}</title>
</svelte:head>

<AdminLayout>
  <div class="px-6 py-8">
    <!-- Header -->
    <div class="mb-6 lg:mb-8">
      <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
        <div>
          <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-2">
            {#if editMode}
              Edit Template: {template?.name || 'Certificate Template'}
            {:else}
              {template?.name || 'Certificate Template'}
            {/if}
          </h2>
          <p class="text-gray-600">
            {#if editMode}
              Update template PNG dan pengaturan posisi field dengan preview real-time
            {:else}
              {template?.description || 'Detail template sertifikat dengan preview interaktif'}
            {/if}
          </p>
        </div>
        <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:gap-3">
          <button
            on:click={() => router.visit('/admin/certificate-templates')}
            class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center"
          >
            <HeroIcon name="arrow-left" class="w-4 h-4 mr-2" />
            Kembali
          </button>
          
          {#if editMode}
            <button
              on:click={() => editMode = false}
              class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center"
            >
              <HeroIcon name="x-mark" class="w-4 h-4 mr-2" />
              Batal Edit
            </button>
            <button
              on:click={handleEditSubmit}
              disabled={isSubmitting || !editFormData.name}
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center"
            >
              {#if isSubmitting}
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menyimpan...
              {:else}
                <HeroIcon name="check" class="w-4 h-4 mr-2" />
                Simpan Perubahan
              {/if}
            </button>
          {:else}
            <button
              on:click={toggleEditMode}
              class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center justify-center"
            >
              <HeroIcon name="pencil-square" class="w-4 h-4 mr-2" />
              Edit Template
            </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Two Column Layout: Preview + Sidebar -->
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 lg:gap-8">
      <!-- Left Column: Template Preview (3/4 width on XL screens) -->
      <div class="xl:col-span-3 space-y-6">
        <!-- Template Image with Real-time Preview -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
          <div class="p-4 border-b bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <HeroIcon name="eye" class="w-5 h-5 mr-2 text-[#eb3434]" />
                Preview Template
              </h3>
              <div class="text-sm text-gray-500">
                {template.width} × {template.height}px
              </div>
            </div>
          </div>
          <div class="p-4 lg:p-6 bg-gray-50">
            <div class="relative bg-white rounded-lg shadow-inner overflow-hidden inline-block border-2 border-gray-200" style="position: relative; z-index: 0;">
              <img 
                bind:this={templateImageElement}
                src={editMode && hasNewFile ? previewImage : `/admin/certificate-templates/${template.id}/preview`} 
                alt="{template.name} Template"
                class="block max-w-full h-auto"
                style="max-height: 600px;"
                on:load={() => calculateScale()}
                on:error={(e) => {
                  e.target.style.display = 'none';
                  e.target.nextElementSibling.style.display = 'flex';
                }}
              />
              
              <div class="hidden items-center justify-center h-64 text-gray-400">
                <div class="text-center">
                  <HeroIcon name="photo" class="w-16 h-16 mx-auto mb-4" />
                  <p>Template tidak dapat dimuat</p>
                </div>
              </div>
              
              <!-- Field Overlays with Sample Data -->
              {#if templateImageElement && scale > 0}
                {#each Object.entries(availableFields) as [fieldKey, fieldLabel]}
                  {@const position = localFieldPositions[fieldKey]}
                  {@const sampleData = {
                    wakif_name: 'Bapak Ahmad Sulaiman',
                    hijri_date: currentHijriDate
                  }}
                  
                  {#if position}
                    <div
                      class="absolute pointer-events-none select-none"
                      style="
                        left: {position.x * scale}px;
                        top: {position.y * scale}px;
                        font-size: {Math.max(8, position.font_size * scale)}px;
                        color: {position.color};
                        font-family: 'Times New Roman', Times, serif;
                        font-weight: bold;
                        z-index: 2;
                        transform: translate(-50%, -50%);
                        text-align: center;
                        white-space: nowrap;
                        text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
                      "
                    >
                      {sampleData[fieldKey]}
                    </div>
                    
                    <!-- Position Indicator -->
                    <div
                      class="absolute w-2 h-2 bg-[#eb3434] rounded-full border-2 border-white shadow-lg pointer-events-none"
                      style="
                        left: {position.x * scale - 4}px;
                        top: {position.y * scale - 4}px;
                        z-index: 3;
                      "
                      title="{fieldLabel}: {position.x}, {position.y}"
                    ></div>
                  {/if}
                {/each}
              {/if}
            </div>
            
            <!-- Legend -->
            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
              <h4 class="text-sm font-medium text-blue-900 mb-2">🎯 Legend:</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-blue-800">
                {#each Object.entries(availableFields) as [fieldKey, fieldLabel], index}
                  <div class="flex items-center">
                    <div class="w-3 h-3 bg-[#eb3434] rounded-full mr-2"></div>
                    <span>{fieldLabel}</span>
                  </div>
                {/each}
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Form Sections (Only visible in edit mode) -->
        {#if editMode}
          <!-- Template Info Edit -->
          <div class="bg-white rounded-lg shadow-sm border p-4 lg:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <HeroIcon name="information-circle" class="w-5 h-5 mr-2 text-[#eb3434]" />
              Edit Informasi Template
            </h3>
            
            <div class="space-y-4">
              <div>
                <label for="template-name" class="block text-sm font-medium text-gray-700 mb-2">Nama Template</label>
                <input
                  type="text"
                  id="template-name"
                  bind:value={editFormData.name}
                  placeholder="Contoh: Template Sertifikat Resmi"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-[#eb3434] focus:border-[#eb3434] {errors.name ? 'border-red-500' : ''}"
                  required
                />
                {#if errors.name}
                  <p class="mt-1 text-sm text-red-600">{errors.name}</p>
                {/if}
              </div>

              <div>
                <label for="template-description" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                <textarea
                  id="template-description"
                  bind:value={editFormData.description}
                  placeholder="Deskripsi template..."
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-[#eb3434] focus:border-[#eb3434] {errors.description ? 'border-red-500' : ''}"
                ></textarea>
                {#if errors.description}
                  <p class="mt-1 text-sm text-red-600">{errors.description}</p>
                {/if}
              </div>

              <div class="flex flex-col space-y-3 sm:flex-row sm:items-center sm:space-y-0 sm:space-x-4">
                <label class="flex items-center">
                  <input
                    type="checkbox"
                    bind:checked={editFormData.is_active}
                    class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
                  />
                  <span class="ml-2 text-sm font-medium text-gray-700">Template aktif</span>
                </label>

                <label class="flex items-center">
                  <input
                    type="checkbox"
                    bind:checked={editFormData.is_default}
                    class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
                  />
                  <span class="ml-2 text-sm font-medium text-gray-700">Set sebagai default</span>
                </label>
              </div>
            </div>
          </div>

          <!-- File Upload Edit -->
          <div class="bg-white rounded-lg shadow-sm border p-4 lg:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <HeroIcon name="photo" class="w-5 h-5 mr-2 text-[#eb3434]" />
              Update Template PNG
            </h3>
            <p class="text-sm text-gray-600 mb-4">Upload file baru jika ingin mengganti template</p>
            
            <div 
              class="border-2 border-dashed border-gray-300 rounded-lg p-6 lg:p-8 text-center transition-colors hover:border-[#eb3434] {errors.template_file ? 'border-red-500 bg-red-50' : ''}"
              class:border-[#eb3434]={isDragging}
              class:bg-red-50={isDragging}
              on:dragover={handleDragOver}
              on:dragleave={handleDragLeave}
              on:drop={handleDrop}
              role="button"
              aria-label="Area unggah template sertifikat, dukungan drag and drop"
              tabindex="0"
            >
              {#if previewImage}
                <div class="space-y-4">
                  <img src={previewImage} alt="Preview" class="max-w-full max-h-48 mx-auto rounded-lg border" />
                  <div class="text-sm text-gray-600">
                    <p>Dimensi: {template.width} x {template.height} px</p>
                    {#if hasNewFile}
                      <p class="text-blue-600 font-medium">✓ File baru dipilih</p>
                    {:else}
                      <p class="text-gray-500">File saat ini</p>
                    {/if}
                  </div>
                  <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:gap-2 sm:justify-center">
                    <label class="cursor-pointer px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-colors">
                      Ganti File
                      <input
                        type="file"
                        accept="image/png,image/jpg,image/jpeg"
                        on:change={handleFileUpload}
                        class="hidden"
                      />
                    </label>
                    {#if hasNewFile}
                      <button
                        type="button"
                        on:click={removeCurrentFile}
                        class="px-4 py-2 text-red-600 hover:text-red-800 text-sm border border-red-300 rounded-lg hover:bg-red-50 transition-colors"
                      >
                        Batalkan Perubahan
                      </button>
                    {/if}
                  </div>
                </div>
              {:else}
                <div class="space-y-4">
                  <HeroIcon name="photo" class="w-16 h-16 mx-auto text-gray-400" />
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
        {/if}

        <!-- Interactive Field Editor -->
        <div class="bg-white rounded-lg shadow-sm border">
          <div class="p-4 border-b bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                  <HeroIcon name="adjustments-horizontal" class="w-5 h-5 mr-2 text-[#eb3434]" />
                  Live Field Positioning
                </h3>
                <p class="text-sm text-gray-600 mt-1">Ubah posisi field dan klik tombol Save untuk menyimpan</p>
              </div>
              
              <!-- Save Button -->
              <button
                on:click={saveAllFieldPositions}
                disabled={!hasUnsavedChanges || isSaving}
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
              >
                {#if isSaving}
                  <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Saving...
                {:else}
                  <HeroIcon name="check" class="w-4 h-4" />
                  Save Changes
                  {#if hasUnsavedChanges}
                    <span class="ml-1 bg-red-500 text-white text-xs rounded-full w-2 h-2"></span>
                  {/if}
                {/if}
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="space-y-6">
              {#each Object.entries(availableFields) as [fieldKey, fieldLabel]}
                {@const position = localFieldPositions[fieldKey] || { x: 100, y: 100, font_size: 14, color: '#000000' }}
                
                <div class="border-2 rounded-lg p-4 bg-gradient-to-r from-gray-50 to-white hover:border-[#eb3434]/30 transition-colors">
                  <div class="flex items-center mb-4">
                    <div class="w-8 h-8 bg-[#eb3434] rounded-full flex items-center justify-center mr-3">
                      <span class="text-white font-bold text-sm">{Object.keys(availableFields).indexOf(fieldKey) + 1}</span>
                    </div>
                    <h4 class="font-medium text-gray-800">{fieldLabel}</h4>
                    <div class="ml-auto text-xs bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                      {fieldKey === 'wakif_name' ? 'Bapak Ahmad Sulaiman' : currentHijriDate}
                    </div>
                  </div>

                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                      <label for={`pos-x-${fieldKey}`} class="block text-sm font-medium text-gray-700 mb-2">X Position</label>
                      <input
                        type="number"
                        id={`pos-x-${fieldKey}`}
                        value={position.x}
                        on:input={(e) => updateFieldPositionRealTime(fieldKey, 'x', parseInt(e.target.value) || 0)}
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-center"
                      />
                    </div>

                    <div>
                      <label for={`pos-y-${fieldKey}`} class="block text-sm font-medium text-gray-700 mb-2">Y Position</label>
                      <input
                        type="number"
                        id={`pos-y-${fieldKey}`}
                        value={position.y}
                        on:input={(e) => updateFieldPositionRealTime(fieldKey, 'y', parseInt(e.target.value) || 0)}
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-center"
                      />
                    </div>

                    <div>
                      <label for={`font-size-${fieldKey}`} class="block text-sm font-medium text-gray-700 mb-2">Font Size</label>
                      <input
                        type="number"
                        id={`font-size-${fieldKey}`}
                        min="1"
                        value={position.font_size}
                        on:input={(e) => updateFieldPositionRealTime(fieldKey, 'font_size', parseInt(e.target.value) || 14)}
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-center"
                      />
                    </div>

                    <div>
                      <label for={`color-${fieldKey}`} class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                      <div class="flex items-center gap-2">
                        <input
                          type="color"
                          id={`color-${fieldKey}`}
                          value={position.color}
                          on:input={(e) => updateFieldPositionRealTime(fieldKey, 'color', e.target.value)}
                          class="w-12 h-10 border border-gray-300 rounded-lg cursor-pointer"
                        />
                        <span class="text-xs text-gray-500 font-mono">
                          {position.color}
                        </span>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Quick Preset Buttons -->
                  <div class="mt-4 pt-3 border-t border-gray-200">
                    <div class="flex flex-wrap gap-2">
                      <button
                        type="button"
                        on:click={() => {
                          updateFieldPositionRealTime(fieldKey, 'x', 100);
                          updateFieldPositionRealTime(fieldKey, 'y', 100);
                        }}
                        class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                      >
                        Top Left
                      </button>
                      <button
                        type="button"
                        on:click={() => {
                          updateFieldPositionRealTime(fieldKey, 'x', (template?.width || 2000) / 2);
                          updateFieldPositionRealTime(fieldKey, 'y', 100);
                        }}
                        class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                      >
                        Top Center
                      </button>
                      <button
                        type="button"
                        on:click={() => {
                          updateFieldPositionRealTime(fieldKey, 'x', (template?.width || 2000) / 2);
                          updateFieldPositionRealTime(fieldKey, 'y', (template?.height || 1400) / 2);
                        }}
                        class="px-3 py-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg transition-colors"
                      >
                        Center
                      </button>
                    </div>
                  </div>
                </div>
              {/each}
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Template Info & Actions (1/4 width on XL screens) -->
      <div class="xl:col-span-1 space-y-6">
        {#if editMode}
          <!-- Edit Mode Info -->
          <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6 sticky top-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
              <HeroIcon name="pencil-square" class="w-5 h-5 mr-2" />
              Mode Edit
            </h3>
            
            <div class="space-y-3 text-sm text-blue-800">
              <div class="flex items-start">
                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                <span>Edit informasi template dan upload file baru</span>
              </div>
              <div class="flex items-start">
                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                <span>Atur posisi field dengan live preview</span>
              </div>
              <div class="flex items-start">
                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                <span>Klik "Simpan Perubahan" untuk menyimpan</span>
              </div>
              <div class="flex items-start">
                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                <span>Klik "Batal Edit" untuk membatalkan</span>
              </div>
            </div>
          </div>
        {:else}
          <!-- Status & Quick Actions -->
          <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <HeroIcon name="check-circle" class="w-5 h-5 mr-2 text-[#eb3434]" />
              Status
            </h3>
            
            <div class="space-y-4">
              <div class="flex flex-wrap gap-2">
                {#if template.is_default}
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                    <HeroIcon name="star" class="w-4 h-4 mr-1" />
                    Default
                  </span>
                {/if}
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {template.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                  <div class="w-2 h-2 rounded-full mr-2 {template.is_active ? 'bg-green-400' : 'bg-red-400'}"></div>
                  {template.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
              </div>

              <div class="space-y-3">
                {#if !template.is_default}
                  <button
                    on:click={setAsDefault}
                    class="w-full px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center justify-center"
                  >
                    <HeroIcon name="star" class="w-4 h-4 mr-2" />
                    Set sebagai Default
                  </button>
                {/if}
                
                <button
                  on:click={toggleStatus}
                  class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center"
                >
                  <HeroIcon name="arrow-path" class="w-4 h-4 mr-2" />
                  {template.is_active ? 'Nonaktifkan' : 'Aktifkan'} Template
                </button>
                
                <button
                  on:click={confirmDelete}
                  class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center"
                >
                  <HeroIcon name="trash" class="w-4 h-4 mr-2" />
                  Hapus Template
                </button>
              </div>
            </div>
          </div>
        {/if}

        <!-- Template Information -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <HeroIcon name="information-circle" class="w-5 h-5 mr-2 text-[#eb3434]" />
            Info Template
          </h3>
          
          <div class="space-y-4">
            <div class="bg-gray-50 rounded-lg p-4">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="text-center">
                  <div class="text-2xl font-bold text-gray-900">{template.width}</div>
                  <div class="text-gray-600">Width (px)</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-gray-900">{template.height}</div>
                  <div class="text-gray-600">Height (px)</div>
                </div>
              </div>
            </div>
            
            <div class="space-y-3 text-sm">
              {#if imageInfo?.size}
                <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                  <span class="text-gray-600 flex items-center">
                    <HeroIcon name="document-text" class="w-4 h-4 mr-2" />
                    Ukuran File:
                  </span>
                  <span class="font-medium">{formatFileSize(imageInfo?.size)}</span>
                </div>
              {/if}
              
              <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                <span class="text-gray-600 flex items-center">
                  <HeroIcon name="calendar-days" class="w-4 h-4 mr-2" />
                  Dibuat:
                </span>
                <span class="font-medium">{formatDate(template.created_at)}</span>
              </div>
              
              <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                <span class="text-gray-600 flex items-center">
                  <HeroIcon name="user" class="w-4 h-4 mr-2" />
                  Pembuat:
                </span>
                <span class="font-medium">{template.creator?.name || 'System'}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Tips & Help -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6">
          <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
            <HeroIcon name="sparkles" class="w-5 h-5 mr-2" />
            Tips & Bantuan
          </h3>
          
          <div class="space-y-3 text-sm text-blue-800">
            <div class="flex items-start">
              <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
              <span>Gunakan editor di sebelah kiri untuk mengatur posisi field secara real-time</span>
            </div>
            <div class="flex items-start">
              <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
              <span>Titik merah pada preview menunjukkan posisi tepat field</span>
            </div>
            <div class="flex items-start">
              <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
              <span>Perubahan akan otomatis tersimpan ke database</span>
            </div>
            <div class="flex items-start">
              <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
              <span>Gunakan tombol preset untuk positioning cepat</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>

<!-- Delete Confirmation Modal -->
{#if showDeleteModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
           class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
           role="button"
           tabindex="0"
           aria-label="Tutup modal hapus"
           on:click={() => showDeleteModal = false}
           on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showDeleteModal = false; } }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
            <HeroIcon name="exclamation-triangle" class="h-6 w-6 text-red-600" />
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Template</h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Apakah Anda yakin ingin menghapus template "{template.name}"? Tindakan ini tidak dapat dibatalkan dan akan menghapus file template.
              </p>
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            on:click={handleDelete}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Hapus Template
          </button>
          <button
            type="button"
            on:click={() => showDeleteModal = false}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<FlashMessage />
