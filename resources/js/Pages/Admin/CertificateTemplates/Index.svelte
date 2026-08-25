<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import { can } from '../../../utils/permissions.js';
  import { onMount } from 'svelte';

  // Props from controller
  export let templates = { data: [] };
  export const filters = {};
  export let stats = {};
  export const auth = {};
  export const errors = {};
  export const flash = {};
  export const availableFields = {};
  export const defaultPositions = {};
  export const settings = {};

  let selectedItems = [];
  let showDeleteModal = false;
  let templateToDelete = null;
  let activeDropdown = null;
  
  // Permission checks
  $: canCreate = can.templates.create();
  $: canRead = can.templates.read();
  $: canUpdate = can.templates.update();
  $: canDelete = can.templates.delete();
  $: canSetDefault = can.templates.setDefault();
  $: canToggle = can.templates.toggle();

  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  function confirmDelete(template) {
    templateToDelete = template;
    showDeleteModal = true;
  }

  // Close dropdown when clicking outside
  onMount(() => {
    function handleClickOutside(event) {
      if (activeDropdown) {
        const dropdownContainer = event.target.closest('.relative');
        if (!dropdownContainer) {
          activeDropdown = null;
        }
      }
    }
    
    document.addEventListener('click', handleClickOutside);
    
    return () => {
      document.removeEventListener('click', handleClickOutside);
    };
  });

  function handleDelete() {
    if (templateToDelete) {
      router.delete(`/admin/certificate-templates/${templateToDelete.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          showDeleteModal = false;
          templateToDelete = null;
          // Force reload to refresh data while preserving pagination
          setTimeout(() => {
            router.reload({
              preserveState: false,
              preserveScroll: true
            });
          }, 100);
        }
      });
    }
  }

  function setAsDefault(template) {
    router.patch(`/admin/certificate-templates/${template.id}/set-default`, {}, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        // Force reload to refresh data while preserving pagination
        setTimeout(() => {
          router.reload({
            preserveState: false,
            preserveScroll: true
          });
        }, 100);
      }
    });
  }

  function toggleStatus(template) {
    router.patch(`/admin/certificate-templates/${template.id}/toggle-status`, {}, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        // Force reload to refresh data while preserving pagination
        setTimeout(() => {
          router.reload({
            preserveState: false,
            preserveScroll: true
          });
        }, 100);
      }
    });
  }
</script>

<svelte:head>
  <title>Template Sertifikat - Admin</title>
</svelte:head>

<AdminLayout>
  <!-- Header -->
  <div class="mb-6 lg:mb-8">
    <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
        <div>
          <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-2">Template Sertifikat</h2>
          <p class="text-gray-600">Kelola template sertifikat dengan positioning field dinamis</p>
        </div>
        <div class="flex gap-3">
          {#if canCreate}
          <button
            on:click={() => router.visit('/admin/certificate-templates/create')}
            class="px-4 py-2 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors flex items-center justify-center"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            <span class="hidden sm:inline">Buat Template</span>
            <span class="sm:hidden">Buat</span>
          </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mb-6">
      <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
        <div class="flex items-center">
          <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 lg:w-6 lg:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <div class="ml-3 lg:ml-4 min-w-0 flex-1">
            <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Total Template</p>
            <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.total || 0}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
        <div class="flex items-center">
          <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 lg:w-6 lg:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <div class="ml-3 lg:ml-4 min-w-0 flex-1">
            <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Template Aktif</p>
            <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.active || 0}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
        <div class="flex items-center">
          <div class="w-10 h-10 lg:w-12 lg:h-12 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 lg:w-6 lg:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
          </div>
          <div class="ml-3 lg:ml-4 min-w-0 flex-1">
            <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Template Default</p>
            <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.default || 0}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Templates Grid -->
    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100">
      <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Daftar Template</h3>
      </div>

      {#if templates.data.length > 0}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-6 p-4 lg:p-6">
          {#each templates.data as template}
            <div class="border border-gray-200 rounded-lg hover:shadow-md hover:border-[#eb3434]/30 transition-all duration-200">
              <!-- Template Preview -->
              <div class="aspect-video bg-gray-100 flex items-center justify-center relative rounded-t-lg overflow-hidden">
                <img 
                  src="/admin/certificate-templates/{template.id}/preview" 
                  alt={template.name}
                  class="max-w-full max-h-full object-contain"
                  on:error={(e) => {
                    e.target.style.display = 'none';
                    e.target.nextElementSibling.style.display = 'flex';
                  }}
                />
                <div class="hidden items-center justify-center text-gray-400">
                  <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                
                <!-- Status Badges -->
                <div class="absolute top-2 left-2 flex gap-2">
                  {#if template.is_default}
                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">
                      Default
                    </span>
                  {/if}
                  <span class="px-2 py-1 text-xs font-medium rounded-full {template.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                    {template.is_active ? 'Aktif' : 'Nonaktif'}
                  </span>
                </div>
              </div>

              <!-- Template Info -->
              <div class="p-4">
                <h4 class="font-semibold text-gray-900 mb-1 truncate">{template.name}</h4>
                {#if template.description}
                  <p class="text-sm text-gray-600 mb-3 line-clamp-2">{template.description}</p>
                {/if}
                
                <div class="text-xs text-gray-500 mb-4">
                  <p>Dimensi: {template.width} x {template.height}px</p>
                  <p>Dibuat: {formatDate(template.created_at)}</p>
                  <p class="truncate">Oleh: {template.creator?.name || 'System'}</p>
                </div>

                <!-- Primary Actions -->
                <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:gap-2">
                  {#if canRead}
                  <button
                    on:click={() => router.visit(`/admin/certificate-templates/${template.id}`)}
                    class="w-full px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center"
                    title="Lihat Detail & Edit Template"
                  >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Kelola Template
                  </button>
                  {/if}
                </div>
                
                <!-- Secondary Actions - Responsive Dropdown -->
                <div class="relative mt-3 z-50">
                  <button 
                    type="button" 
                    class="w-full px-3 py-2 text-sm text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition-colors flex items-center justify-center"
                    on:click|stopPropagation={() => {
                      // Toggle dropdown untuk template ini
                      if (activeDropdown === template.id) {
                        activeDropdown = null;
                      } else {
                        activeDropdown = template.id;
                      }
                    }}
                  >
                    Aksi Lainnya
                    <svg class="w-4 h-4 ml-1 transform transition-transform {activeDropdown === template.id ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </button>
                  
                  {#if activeDropdown === template.id}
                    <div class="dropdown-menu absolute left-0 right-0 z-[9999] w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden" style="min-width: 200px;">
                      {#if !template.is_default}
                        <button
                          on:click={() => {
                            setAsDefault(template);
                            activeDropdown = null;
                          }}
                          class="w-full px-4 py-3 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors block border-b border-gray-100"
                        >
                          <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            Set sebagai Default
                          </div>
                        </button>
                      {/if}
                      
                      <button
                        on:click={() => {
                          toggleStatus(template);
                          activeDropdown = null;
                        }}
                        class="w-full px-4 py-3 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors block border-b border-gray-100"
                      >
                        <div class="flex items-center">
                          <svg class="w-4 h-4 mr-2 {template.is_active ? 'text-red-600' : 'text-green-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                          </svg>
                          {template.is_active ? 'Nonaktifkan' : 'Aktifkan'} Template
                        </div>
                      </button>
                      
                      <button
                        on:click={() => {
                          confirmDelete(template);
                          activeDropdown = null;
                        }}
                        class="w-full px-4 py-3 text-sm text-left text-red-600 hover:bg-red-50 transition-colors block"
                      >
                        <div class="flex items-center">
                          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                          </svg>
                          Hapus Template
                        </div>
                      </button>
                    </div>
                  {/if}
                </div>
              </div>
            </div>
          {/each}
        </div>

        <!-- Enhanced Pagination -->
        {#if templates.links}
          <div class="px-4 lg:px-6 py-6 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
              <!-- Results Info -->
              <div class="text-sm text-gray-600 text-center lg:text-left order-2 lg:order-1">
                <div class="flex items-center justify-center lg:justify-start space-x-2">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <span>
                    Menampilkan <span class="font-semibold text-[#eb3434]">{templates.from}</span> - <span class="font-semibold text-[#eb3434]">{templates.to}</span> 
                    dari <span class="font-semibold text-[#eb3434]">{templates.total}</span> template
                  </span>
                </div>
              </div>
              
              <!-- Pagination Controls -->
              <div class="flex justify-center lg:justify-end order-1 lg:order-2">
                <nav class="inline-flex items-center space-x-1" aria-label="Pagination">
                  {#each templates.links as link, index}
                    {@const isFirst = index === 0}
                    {@const isLast = index === templates.links.length - 1}
                    {@const isNumber = !isNaN(parseInt(link.label))}
                    
                    <button
                      class="relative inline-flex items-center justify-center transition-all duration-200 font-medium
                             {link.active 
                               ? 'bg-[#eb3434] text-white shadow-lg shadow-red-500/25 scale-105' 
                               : link.url 
                                 ? 'bg-white text-gray-700 hover:bg-[#eb3434] hover:text-white hover:shadow-md hover:scale-105 border border-gray-300' 
                                 : 'bg-gray-100 text-gray-400 cursor-not-allowed'} 
                             {isFirst || isLast 
                               ? 'px-3 py-2 rounded-lg text-sm' 
                               : isNumber 
                                 ? 'w-10 h-10 rounded-lg text-sm' 
                                 : 'px-2 py-2 rounded-lg text-xs'}"
                      disabled={!link.url}
                      on:click={() => link.url && router.visit(link.url, {
                        preserveState: true,
                        preserveScroll: true
                      })}
                      title={isFirst ? 'Halaman sebelumnya' : isLast ? 'Halaman selanjutnya' : `Halaman ${link.label}`}
                    >
                      {#if isFirst}
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <span class="hidden sm:inline">Sebelumnya</span>
                      {:else if isLast}
                        <span class="hidden sm:inline">Selanjutnya</span>
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                      {:else}
                        {@html link.label}
                      {/if}
                    </button>
                  {/each}
                </nav>
              </div>
            </div>
            
            <!-- Additional Pagination Info for Mobile -->
            <div class="mt-4 text-center lg:hidden">
              <div class="inline-flex items-center px-3 py-1 bg-white rounded-full border border-gray-200 text-xs text-gray-600">
                <svg class="w-3 h-3 mr-1 text-[#eb3434]" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                Halaman {templates.current_page} dari {templates.last_page}
              </div>
            </div>
          </div>
        {/if}
      {:else}
        <div class="p-8 lg:p-12 text-center">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada template</h3>
          <p class="text-gray-600 mb-4">Mulai dengan membuat template sertifikat pertama Anda</p>
          {#if canCreate}
          <button
            on:click={() => router.visit('/admin/certificate-templates/create')}
            class="px-4 py-2 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors"
          >
            Buat Template Pertama
          </button>
          {/if}
        </div>
      {/if}
    </div>
</AdminLayout>

<!-- Delete Confirmation Modal -->
{#if showDeleteModal && templateToDelete}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" role="button" tabindex="-1" on:click={() => showDeleteModal = false} on:keydown={(e) => e.key === 'Enter' || e.key === ' ' ? showDeleteModal = false : null}></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Template</h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Apakah Anda yakin ingin menghapus template "{templateToDelete.name}"? Tindakan ini tidak dapat dibatalkan.
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
            Hapus
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

<style>
  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
</style>
