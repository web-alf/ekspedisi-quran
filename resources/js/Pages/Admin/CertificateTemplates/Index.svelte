<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
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
            class="px-4 py-2 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors flex items-center justify-center gap-2"
          >
            <HeroIcon name="plus" class="w-4 h-4" />
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
            <HeroIcon name="document-text" class="w-5 h-5 lg:w-6 lg:h-6 text-blue-600" />
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
            <HeroIcon name="check" class="w-5 h-5 lg:w-6 lg:h-6 text-green-600" />
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
            <HeroIcon name="star" class="w-5 h-5 lg:w-6 lg:h-6 text-yellow-600" />
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
                  <HeroIcon name="photo" class="w-16 h-16" />
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
                    class="w-full px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                    title="Lihat Detail & Edit Template"
                  >
                    <HeroIcon name="pencil-square" class="w-4 h-4" />
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
                    <HeroIcon name="chevron-down" class="w-4 h-4 ml-1 transform transition-transform {activeDropdown === template.id ? 'rotate-180' : ''}" />
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
                          <div class="flex items-center gap-2">
                            <HeroIcon name="star" class="w-4 h-4 text-yellow-600" />
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
                        <div class="flex items-center gap-2">
                          <HeroIcon name="arrow-path" class="w-4 h-4 {template.is_active ? 'text-red-600' : 'text-green-600'}" />
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
                        <div class="flex items-center gap-2">
                          <HeroIcon name="trash" class="w-4 h-4" />
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
                  <HeroIcon name="document-text" class="w-4 h-4 text-gray-400" />
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
                        <HeroIcon name="chevron-left" class="w-4 h-4 mr-1" />
                        <span class="hidden sm:inline">Sebelumnya</span>
                      {:else if isLast}
                        <span class="hidden sm:inline">Selanjutnya</span>
                        <HeroIcon name="chevron-right" class="w-4 h-4 ml-1" />
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
              <div class="inline-flex items-center px-3 py-1 bg-white rounded-full border border-gray-200 text-xs text-gray-600 gap-1">
                <HeroIcon name="information-circle" class="w-3.5 h-3.5 text-[#eb3434]" />
                Halaman {templates.current_page} dari {templates.last_page}
              </div>
            </div>
          </div>
        {/if}
      {:else}
        <div class="p-8 lg:p-12 text-center">
          <HeroIcon name="document-text" class="w-16 h-16 mx-auto text-gray-300 mb-4" />
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
            <HeroIcon name="exclamation-triangle" class="h-6 w-6 text-red-600" />
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
