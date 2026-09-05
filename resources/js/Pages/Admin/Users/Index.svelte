<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { can } from '../../../utils/permissions.js';
  
  // Props from Inertia
  export let users = { data: [], total: 0, from: 0, to: 0, last_page: 1, prev_page_url: null, next_page_url: null };
  export let filters = {};
  export let roles = {};
  export let auth = {};
  export const errors = {};
  export const flash = {};
  
  // Initialize filter values to prevent undefined errors
  $: if (!filters.search) filters.search = '';
  $: if (!filters.role) filters.role = '';
  $: if (filters.status === undefined) filters.status = '';
  
  let search = filters.search || '';
  let roleFilter = filters.role || '';
  let statusFilter = filters.status !== undefined ? filters.status : '';
  let showDeleteModal = false;
  let userToDelete = null;
  let deleteErrorMessage = null;
  
  // Check if user is current logged in user
  function isCurrentUser(user) {
    return user.id === auth.user?.id;
  }
  
  // Permission checks
  $: canCreate = can.users.create();
  $: canUpdate = can.users.update();
  $: canDelete = can.users.delete();
  $: canToggle = can.users.toggle();
  
  // Debounce search
  let searchTimeout;
  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }
  
  function applyFilters() {
    const params = {};
    if (search) params.search = search;
    if (roleFilter) params.role = roleFilter;
    if (statusFilter !== '') params.status = statusFilter;
    
    router.get('/admin/users', params, {
      preserveState: true,
      preserveScroll: true
    });
  }
  
  function resetFilters() {
    search = '';
    roleFilter = '';
    statusFilter = '';
    router.get('/admin/users');
  }
  
  function handleCreate() {
    router.visit('/admin/users/create');
  }
  
  function handleEdit(userId) {
    router.visit(`/admin/users/${userId}/edit`);
  }
  
  function confirmDelete(user) {
    // Prevent deleting current user
    if (isCurrentUser(user)) {
      showWarning('Tidak Dapat Menghapus', 'Anda tidak bisa menghapus akun sendiri.');
      return;
    }
    
    userToDelete = user;
    showDeleteModal = true;
  }
  
  function handleDelete() {
    if (userToDelete) {
      // Reset error message
      deleteErrorMessage = null;

      router.delete(`/admin/users/${userToDelete.id}`, {
        onSuccess: () => {
          showDeleteModal = false;
          userToDelete = null;
          deleteErrorMessage = null;
        },
        onError: (errors) => {
          // Show error message in modal instead of closing it
          if (errors.error) {
            deleteErrorMessage = errors.error;
          } else {
            deleteErrorMessage = 'Terjadi kesalahan saat menghapus user.';
          }
        }
      });
    }
  }

  function cancelDelete() {
    showDeleteModal = false;
    userToDelete = null;
    deleteErrorMessage = null;
  }
  
  function toggleStatus(user) {
    // Prevent toggling current user status
    if (isCurrentUser(user) && user.is_active) {
      showWarning('Tidak Dapat Menonaktifkan', 'Anda tidak bisa menonaktifkan akun sendiri.');
      return;
    }
    
    router.patch(`/admin/users/${user.id}/toggle`, {
      is_active: !user.is_active
    }, {
      preserveState: true,
      preserveScroll: true,
      onError: (errors) => {
        console.error('Toggle status error:', errors);
      }
    });
  }
  
  function getRoleDisplay(role) {
    return roles[role] || role;
  }
  
  function getRoleBadgeColor(role) {
    const colors = {
      'super-admin': 'bg-red-100 text-red-800',
      'customer-service': 'bg-blue-100 text-blue-800',
      'warehouse': 'bg-green-100 text-green-800',
      'supervisor': 'bg-purple-100 text-purple-800',
      'courier': 'bg-yellow-100 text-yellow-800'
    };
    return colors[role] || 'bg-gray-100 text-gray-800';
  }
  
  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }
</script>

<svelte:head>
  <title>Manajemen User - Ekspedisi Qur'an</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<AdminLayout>
  <!-- Flash Messages -->
  <FlashMessage />
  
  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8">
      <!-- Page Header -->
      <div class="mb-6 sm:mb-8">
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
            <div>
              <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1 sm:mb-2">Manajemen User</h2>
              <p class="text-sm sm:text-base text-gray-600">Kelola pengguna sistem Ekspedisi Qur'an</p>
            </div>
            {#if canCreate}
            <button 
              on:click={handleCreate}
              class="w-full sm:w-auto bg-[#eb3434] hover:bg-red-600 text-white px-4 py-2 sm:py-3 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1.5"
            >
              <HeroIcon name="plus" class="w-4 h-4" />
              <span class="hidden sm:inline">Tambah User</span>
              <span class="sm:hidden">Tambah</span>
            </button>
            {/if}
          </div>
        </div>
      </div>
    
      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
        <div class="space-y-4">
          <!-- Search -->
          <div>
            <label for="user-search" class="block text-sm font-medium text-gray-700 mb-2">Cari User</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <HeroIcon name="magnifying-glass" class="h-4 w-4 sm:h-5 sm:w-5" />
              </div>
              <input
                id="user-search"
                type="text"
                bind:value={search}
                on:input={handleSearch}
                placeholder="Nama atau email..."
                class="w-full pl-9 sm:pl-10 pr-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              />
            </div>
          </div>
          
          <!-- Filters Row -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            <!-- Role Filter -->
            <div>
              <label for="role-filter" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
              <select
                id="role-filter"
                bind:value={roleFilter}
                on:change={applyFilters}
                class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              >
                <option value="">Semua Role</option>
                {#each Object.entries(roles) as [value, label]}
                  <option {value}>{label}</option>
                {/each}
              </select>
            </div>
            
            <!-- Status Filter -->
            <div>
              <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <select
                id="status-filter"
                bind:value={statusFilter}
                on:change={applyFilters}
                class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              >
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
              </select>
            </div>
            
            <!-- Reset Button -->
            <div class="flex items-end">
              <button
                on:click={resetFilters}
                class="w-full px-4 py-2 sm:py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1.5"
              >
                <HeroIcon name="arrow-path" class="w-4 h-4" />
                <span class="hidden sm:inline">Reset Filter</span>
                <span class="sm:hidden">Reset</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Desktop Table -->
      <div class="hidden lg:block bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Daftar User</h3>
          <p class="text-sm text-gray-500">Total: {users.total} user</p>
        </div>
    
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#if users.data && users.data.length > 0}
                {#each users.data as user}
                  <tr class="hover:bg-gray-50">
                    <!-- User Info -->
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="w-10 h-10 bg-[#eb3434] rounded-full flex items-center justify-center mr-3">
                          <span class="text-sm font-medium text-white">{user.name.charAt(0)}</span>
                        </div>
                        <div>
                          <div class="text-sm font-medium text-gray-900">{user.name}</div>
                          <div class="text-sm text-gray-500">{user.email}</div>
                        </div>
                      </div>
                    </td>
                    
                    <!-- Role -->
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {getRoleBadgeColor(user.spatie_role)}">
                        {user.role_display || 'No Role'}
                      </span>
                    </td>
                    
                    <!-- Status -->
                    <td class="px-6 py-4 whitespace-nowrap">
                      {#if isCurrentUser(user)}
                        <!-- Current user cannot toggle their own status -->
                        <div class="relative inline-flex h-6 w-11 items-center rounded-full bg-[#eb3434] opacity-50 cursor-not-allowed" title="Anda tidak bisa mengubah status akun sendiri">
                          <span class="inline-block h-4 w-4 transform rounded-full bg-white translate-x-6"></span>
                        </div>
                        <span class="ml-2 text-sm text-gray-600">Aktif (Anda)</span>
                      {:else if canToggle}
                        <button
                          on:click={() => toggleStatus(user)}
                          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:ring-offset-2 {user.is_active ? 'bg-[#eb3434]' : 'bg-gray-200'}"
                          title="Klik untuk mengubah status"
                        >
                          <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {user.is_active ? 'translate-x-6' : 'translate-x-1'}"></span>
                        </button>
                        <span class="ml-2 text-sm text-gray-600">
                          {user.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                      {:else}
                        <span class="ml-2 text-sm text-gray-600">
                          {user.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                      {/if}
                    </td>
                    
                    <!-- Created Date -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(user.created_at)}
                    </td>
                    
                    <!-- Actions -->
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div class="flex justify-end space-x-2">
                        {#if canUpdate}
                        <button
                          on:click={() => handleEdit(user.id)}
                          class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-50 rounded transition-colors"
                          title="Edit"
                        >
                          <HeroIcon name="pencil-square" class="w-4 h-4" />
                        </button>
                        {/if}
                        
                        {#if canDelete && !isCurrentUser(user)}
                          <button
                            on:click={() => confirmDelete(user)}
                            class="text-red-600 hover:text-red-900 p-1 hover:bg-red-50 rounded transition-colors"
                            title="Hapus"
                          >
                            <HeroIcon name="trash" class="w-4 h-4" />
                          </button>
                        {:else if isCurrentUser(user)}
                          <!-- Show disabled delete button for current user with tooltip -->
                          <button
                            disabled
                            class="text-gray-400 p-1 rounded cursor-not-allowed opacity-50"
                            title="Anda tidak bisa menghapus akun sendiri"
                          >
                            <HeroIcon name="trash" class="w-4 h-4" />
                          </button>
                        {/if}
                      </div>
                    </td>
                  </tr>
                {/each}
              {:else}
                <tr>
                  <td colspan="5" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                      <HeroIcon name="users" class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                      <p class="text-lg font-medium">Tidak ada user ditemukan</p>
                      <p class="text-sm">Coba ubah filter pencarian atau tambah user baru</p>
                    </div>
                  </td>
                </tr>
              {/if}
            </tbody>
          </table>
        </div>
    
        <!-- Pagination -->
        {#if users.last_page > 1 && users.data && users.data.length > 0}
          <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-500">
                Menampilkan {users.from} - {users.to} dari {users.total} hasil
              </div>
              <div class="flex space-x-1">
                <!-- Previous -->
                {#if users.prev_page_url}
                  <button
                    on:click={() => router.visit(users.prev_page_url, { preserveScroll: true })}
                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md"
                  >
                    ← Sebelumnya
                  </button>
                {/if}
                
                <!-- Next -->
                {#if users.next_page_url}
                  <button
                    on:click={() => router.visit(users.next_page_url, { preserveScroll: true })}
                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md"
                  >
                    Selanjutnya →
                  </button>
                {/if}
              </div>
            </div>
          </div>
        {/if}
      </div>

      <!-- Mobile Cards -->
      <div class="lg:hidden space-y-3 sm:space-y-4">
        {#if users.data && users.data.length > 0}
          {#each users.data as user}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4">
              <!-- Card Header -->
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center flex-1 min-w-0">
                  <div class="w-10 h-10 sm:w-12 sm:h-12 bg-[#eb3434] rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                    <span class="text-sm sm:text-base font-medium text-white">{user.name.charAt(0)}</span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <h3 class="text-sm sm:text-base font-medium text-gray-900 truncate">{user.name}</h3>
                    <p class="text-xs sm:text-sm text-gray-600 truncate">{user.email}</p>
                    {#if isCurrentUser(user)}
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                        Akun Anda
                      </span>
                    {/if}
                  </div>
                </div>
                <span class="inline-flex items-center px-2 py-1 sm:px-2.5 sm:py-0.5 rounded-full text-xs font-medium {getRoleBadgeColor(user.role)} ml-2 flex-shrink-0">
                  {getRoleDisplay(user.role)}
                </span>
              </div>
              
              <!-- Card Content -->
              <div class="space-y-3 mb-4">
                <div class="flex items-center justify-between">
                  <span class="text-xs sm:text-sm text-gray-500">Status:</span>
                  <div class="flex items-center">
                    {#if isCurrentUser(user)}
                      <div class="relative inline-flex h-5 w-9 sm:h-6 sm:w-11 items-center rounded-full bg-[#eb3434] opacity-50 cursor-not-allowed">
                        <span class="inline-block h-3 w-3 sm:h-4 sm:w-4 transform rounded-full bg-white translate-x-5 sm:translate-x-6"></span>
                      </div>
                      <span class="ml-2 text-xs sm:text-sm text-gray-600">Aktif</span>
                    {:else if canToggle}
                      <button
                        on:click={() => toggleStatus(user)}
                        class="relative inline-flex h-5 w-9 sm:h-6 sm:w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:ring-offset-2 {user.is_active ? 'bg-[#eb3434]' : 'bg-gray-200'}"
                      >
                        <span class="inline-block h-3 w-3 sm:h-4 sm:w-4 transform rounded-full bg-white transition-transform {user.is_active ? 'translate-x-5 sm:translate-x-6' : 'translate-x-1'}"></span>
                      </button>
                      <span class="ml-2 text-xs sm:text-sm text-gray-600">
                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    {:else}
                      <span class="ml-2 text-xs sm:text-sm text-gray-600">
                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    {/if}
                  </div>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-xs sm:text-sm text-gray-500">Dibuat:</span>
                  <span class="text-xs sm:text-sm text-gray-900">{formatDate(user.created_at)}</span>
                </div>
              </div>
              
              <!-- Card Actions -->
              <div class="flex gap-2">
                {#if canUpdate}
                <button
                  on:click={() => handleEdit(user.id)}
                  class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-md text-xs sm:text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors gap-1"
                >
                  <HeroIcon name="pencil-square" class="w-3.5 h-3.5" />
                  <span>Edit</span>
                </button>
                {/if}
                
                {#if canDelete && !isCurrentUser(user)}
                  <button
                    on:click={() => confirmDelete(user)}
                    class="inline-flex items-center justify-center px-3 py-2 rounded-md text-xs sm:text-sm font-medium text-red-600 bg-white border border-red-200 hover:bg-red-50 transition-colors"
                  >
                    <HeroIcon name="trash" class="w-3.5 h-3.5" />
                    <span class="sr-only">Hapus</span>
                  </button>
                {:else if isCurrentUser(user)}
                  <button
                    disabled
                    class="inline-flex items-center justify-center px-3 py-2 rounded-md text-xs sm:text-sm font-medium text-gray-400 bg-gray-50 border border-gray-200 cursor-not-allowed opacity-50"
                    title="Anda tidak bisa menghapus akun sendiri"
                  >
                    <HeroIcon name="trash" class="w-3.5 h-3.5" />
                    <span class="sr-only">Hapus</span>
                  </button>
                {/if}
              </div>
            </div>
          {/each}
        {:else}
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8 text-center">
            <HeroIcon name="users" class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-3 sm:mb-4 text-gray-300" />
            <h3 class="text-sm sm:text-base font-medium text-gray-900 mb-2">Tidak ada user ditemukan</h3>
            <p class="text-xs sm:text-sm text-gray-500">Coba ubah filter pencarian atau tambah user baru</p>
          </div>
        {/if}
        
        <!-- Mobile Pagination -->
        {#if users.last_page > 1 && users.data && users.data.length > 0}
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
              <div class="text-xs sm:text-sm text-gray-500 text-center sm:text-left">
                Menampilkan {users.from} - {users.to} dari {users.total} hasil
              </div>
              <div class="flex justify-center space-x-2">
                <!-- Previous -->
                {#if users.prev_page_url}
                  <button
                    on:click={() => router.visit(users.prev_page_url, { preserveScroll: true })}
                    class="px-3 py-2 text-xs sm:text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300"
                  >
                    ← Sebelumnya
                  </button>
                {/if}
                
                <!-- Next -->
                {#if users.next_page_url}
                  <button
                    on:click={() => router.visit(users.next_page_url, { preserveScroll: true })}
                    class="px-3 py-2 text-xs sm:text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md border border-gray-300"
                  >
                    Selanjutnya →
                  </button>
                {/if}
              </div>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>

<!-- Delete Confirmation Modal -->
{#if showDeleteModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <button 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
        on:click={cancelDelete}
        transition:fade={{ duration: 200 }}
      ></button>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 mx-4"
        transition:scale={{ duration: 200, start: 0.95 }}
      >
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-red-100 sm:mx-0">
            <HeroIcon name="exclamation-triangle" class="h-5 w-5 sm:h-6 sm:w-6 text-red-600" />
          </div>

          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
            <h3 class="text-base sm:text-lg leading-6 font-medium text-gray-900" id="modal-title">
              Hapus User
            </h3>
            <div class="mt-2">
              {#if deleteErrorMessage}
                <!-- Error Message -->
                <div class="rounded-md bg-red-50 p-3 sm:p-4 mb-3" transition:fade={{ duration: 200 }}>
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <HeroIcon name="x-circle" class="h-5 w-5 text-red-400" />
                    </div>
                    <div class="ml-3">
                      <h3 class="text-sm font-medium text-red-800">
                        Tidak dapat menghapus user
                      </h3>
                      <div class="mt-2 text-sm text-red-700">
                        <p>{deleteErrorMessage}</p>
                      </div>
                    </div>
                  </div>
                </div>
              {:else}
                <!-- Confirmation Message -->
                <p class="text-sm text-gray-500">
                  Apakah Anda yakin ingin menghapus user <strong class="text-gray-900">{userToDelete?.name}</strong>?
                  Tindakan ini tidak dapat dibatalkan.
                </p>
              {/if}
            </div>
          </div>
        </div>

        <div class="mt-5 sm:mt-4 flex flex-col-reverse sm:flex-row-reverse gap-3 sm:gap-0">
          {#if deleteErrorMessage}
            <!-- Only show close button when there's an error -->
            <button
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm sm:text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:w-auto transition-colors duration-200"
              on:click={cancelDelete}
            >
              Tutup
            </button>
          {:else}
            <!-- Show delete and cancel buttons when no error -->
            <button
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-sm sm:text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto transition-colors duration-200"
              on:click={handleDelete}
            >
              Hapus
            </button>

            <button
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm sm:text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] sm:w-auto transition-colors duration-200"
              on:click={cancelDelete}
            >
              Batal
            </button>
          {/if}
        </div>
      </div>
    </div>
  </div>
{/if}
