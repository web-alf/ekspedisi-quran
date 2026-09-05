<script>
  import { router, page } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { can } from '../../../utils/permissions.js';
  
  export let roles = { data: [] };
  export let filters = {};
  
  
  let search = filters.search || '';
  let showDeleteModal = false;
  let selectedRole = null;
  
  // Permission checks
  $: canCreate = can.roles.create();
  $: canUpdate = can.roles.update();
  $: canDelete = can.roles.delete();
  
  function searchRoles() {
    router.get('/admin/roles', { search }, {
      preserveState: true,
      replace: true
    });
  }
  
  function clearSearch() {
    search = '';
    router.get('/admin/roles', {}, {
      preserveState: true,
      replace: true
    });
  }
  
  function confirmDelete(role) {
    selectedRole = role;
    showDeleteModal = true;
  }
  
  function deleteRole() {
    if (selectedRole) {
      router.delete(`/admin/roles/${selectedRole.id}`, {
        onSuccess: () => {
          showDeleteModal = false;
          selectedRole = null;
        }
      });
    }
  }
  
  function cancelDelete() {
    showDeleteModal = false;
    selectedRole = null;
  }
</script>

<AdminLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Kelola Role</h1>
        <p class="text-gray-600">Kelola role dan permissions untuk user</p>
      </div>
      
{#if canCreate}
      <a 
        href="/admin/roles/create"
        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#eb3434] hover:bg-[#d42c2c] transition-colors"
      >
        <HeroIcon name="plus" class="w-4 h-4 mr-2" />
        Tambah Role
      </a>
      {/if}
    </div>

    <!-- Search & Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <div class="relative">
            <input
              type="text"
              bind:value={search}
              on:keydown={(e) => e.key === 'Enter' && searchRoles()}
              placeholder="Cari role..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
            />
            <HeroIcon name="magnifying-glass" class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
          </div>
        </div>
        
        <div class="flex gap-2">
          <button
            on:click={searchRoles}
            class="px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-[#d42c2c] transition-colors"
          >
            Cari
          </button>
          
          {#if search}
            <button
              on:click={clearSearch}
              class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
            >
              Reset
            </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Roles Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Display Name
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Role Name (Slug)
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Permissions
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Users Count
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Created At
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            {#each roles.data as role}
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="text-sm font-medium text-gray-900">
                      {role.display_name || role.name}
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-500 font-mono">
                    {role.name}
                  </div>
                </td>

                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900">
                    {role.permissions ? role.permissions.length : 0} permissions
                  </div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    {role.users_count || 0} users
                  </div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-500">
                    {new Date(role.created_at).toLocaleDateString('id-ID')}
                  </div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex items-center justify-end gap-2">
                    {#if canUpdate}
                    <a
                      href={`/admin/roles/${role.id}/edit`}
                      class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-[#eb3434] bg-[#eb3434]/10 hover:bg-[#eb3434]/20 transition-colors"
                    >
                      Edit
                    </a>
                    {/if}
                    
                    {#if canDelete}
                    <button
                      on:click={() => confirmDelete(role)}
                      class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-red-600 bg-red-100 hover:bg-red-200 transition-colors"
                    >
                      Delete
                    </button>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
      
      {#if roles.data.length === 0}
        <div class="text-center py-8">
          <p class="text-gray-500">Tidak ada role ditemukan.</p>
        </div>
      {/if}
    </div>

    <!-- Pagination -->
    {#if roles.links && roles.links.length > 3}
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-700">
          Showing {roles.from} to {roles.to} of {roles.total} results
        </div>
        
        <div class="flex items-center gap-2">
          {#each roles.links as link}
            {#if link.url}
              <a
                href={link.url}
                class="px-3 py-2 text-sm rounded-lg {link.active ? 'bg-[#eb3434] text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} border border-gray-300 transition-colors"
              >
                {@html link.label}
              </a>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-500">
                {@html link.label}
              </span>
            {/if}
          {/each}
        </div>
      </div>
    {/if}
  </div>
</AdminLayout>

<!-- Delete Confirmation Modal -->
{#if showDeleteModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        role="button"
        tabindex="0"
        aria-label="Tutup modal"
        on:click={cancelDelete}
        on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cancelDelete(); } }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
              <HeroIcon name="exclamation-triangle" class="h-6 w-6 text-red-600" />
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Hapus Role
              </h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500">
                  Apakah Anda yakin ingin menghapus role "<strong>{selectedRole?.name}</strong>"? 
                  Tindakan ini tidak dapat dibatalkan.
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            on:click={deleteRole}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
          >
            Hapus
          </button>
          <button
            type="button"
            on:click={cancelDelete}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}
