<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  
  export let role = {};
  export let permissions = {};
  export let categories = {};
  export let errors = {};
  
  let formData = {
    name: role.name || '',
    display_name: role.display_name || '',
    permissions: role.permissions || []
  };
  
  let processing = false;
  
  function submit() {
    processing = true;
    router.put(`/admin/roles/${role.id}`, formData, {
      onFinish: () => processing = false
    });
  }
  
  function togglePermission(permissionName) {
    const index = formData.permissions.indexOf(permissionName);
    if (index > -1) {
      formData.permissions.splice(index, 1);
    } else {
      formData.permissions.push(permissionName);
    }
    formData.permissions = [...formData.permissions];
  }

  function toggleCategoryPermissions(categoryPermissions) {
    const categoryNames = categoryPermissions.map(p => p.name);
    const allSelected = categoryNames.every(name => formData.permissions.includes(name));

    if (allSelected) {
      // Remove all category permissions
      formData.permissions = formData.permissions.filter(name => !categoryNames.includes(name));
    } else {
      // Add all category permissions
      categoryNames.forEach(name => {
        if (!formData.permissions.includes(name)) {
          formData.permissions.push(name);
        }
      });
    }
    formData.permissions = [...formData.permissions];
  }
</script>

<AdminLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <a 
        href="/admin/roles"
        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Kembali
      </a>
      <h1 class="text-2xl font-bold text-gray-900">Edit Role: {role.display_name || role.name}</h1>
    </div>

    {#if role.is_system_role}
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
      <div class="flex">
        <svg class="h-5 w-5 text-amber-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div>
          <h3 class="text-sm font-medium text-amber-800">Role Sistem</h3>
          <p class="text-sm text-amber-700 mt-1">Ini adalah role sistem. Perubahan pada role ini harus dilakukan dengan hati-hati.</p>
        </div>
      </div>
    </div>
    {/if}

    <form on:submit|preventDefault={submit} class="space-y-6">
      <!-- Basic Info -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Role</h2>

        <div class="space-y-4">
          <div>
            <label for="display_name" class="block text-sm font-medium text-gray-700 mb-2">
              Display Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="display_name"
              bind:value={formData.display_name}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.display_name ? 'border-red-500' : ''}"
              placeholder="Contoh: Super Admin, Staff Gudang"
              required
            />
            {#if errors.display_name}
              <p class="mt-1 text-sm text-red-600">{errors.display_name}</p>
            {/if}
            <p class="mt-1 text-sm text-gray-500">Nama yang akan ditampilkan di UI</p>
          </div>

          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              Role Name (Slug) <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="name"
              bind:value={formData.name}
              disabled={role.is_system_role}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.name ? 'border-red-500' : ''} {role.is_system_role ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''}"
              placeholder="Contoh: super-admin, staff-gudang"
              required
            />
            {#if errors.name}
              <p class="mt-1 text-sm text-red-600">{errors.name}</p>
            {/if}
            {#if role.is_system_role}
              <p class="mt-1 text-sm text-amber-600">Role name tidak dapat diubah untuk role sistem</p>
            {:else}
              <p class="mt-1 text-sm text-gray-500">Format: lowercase, gunakan dash (-) untuk spasi</p>
            {/if}
          </div>

          {#if role.users_count > 0}
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p class="text-sm text-blue-800">
              <strong>{role.users_count}</strong> user menggunakan role ini
            </p>
          </div>
          {/if}
        </div>
      </div>

      <!-- Permissions -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Permissions</h2>
        
        <div class="space-y-6">
          {#each Object.entries(permissions) as [category, categoryPermissions]}
            <div class="border border-gray-200 rounded-lg p-4">
              <div class="flex items-center justify-between mb-3">
                <h3 class="font-medium text-gray-900">
                  {categories[category] || category}
                </h3>
                <button
                  type="button"
                  on:click={() => toggleCategoryPermissions(categoryPermissions)}
                  class="text-sm text-[#eb3434] hover:text-[#d42c2c] font-medium"
                >
                  {categoryPermissions.every(p => formData.permissions.includes(p.name)) ? 'Unselect All' : 'Select All'}
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {#each categoryPermissions as permission}
                  <label class="flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={formData.permissions.includes(permission.name)}
                      on:change={() => togglePermission(permission.name)}
                      class="rounded border-gray-300 text-[#eb3434] focus:ring-[#eb3434] mr-2"
                    />
                    <span class="text-sm text-gray-700">
                      {permission.name}
                    </span>
                  </label>
                {/each}
              </div>
            </div>
          {/each}
        </div>
        
        {#if errors.permissions}
          <p class="mt-2 text-sm text-red-600">{errors.permissions}</p>
        {/if}
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <a
          href="/admin/roles"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
        >
          Batal
        </a>
        
        <button
          type="submit"
          disabled={processing}
          class="px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-[#d42c2c] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {processing ? 'Menyimpan...' : 'Update Role'}
        </button>
      </div>
    </form>
  </div>
</AdminLayout>