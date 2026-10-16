<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  
  export let permissions = {};
  export let categories = {};
  export let errors = {};
  
  let formData = {
    name: '',
    display_name: '',
    permissions: []
  };
  
  let processing = false;
  
  function submit() {
    processing = true;
    router.post('/admin/roles', formData, {
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
        <HeroIcon name="chevron-left" class="w-4 h-4 mr-1" />
        Kembali
      </a>
      <h1 class="text-2xl font-bold text-gray-900">Tambah Role Baru</h1>
    </div>

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
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.name ? 'border-red-500' : ''}"
              placeholder="Contoh: super-admin, staff-gudang"
              required
            />
            {#if errors.name}
              <p class="mt-1 text-sm text-red-600">{errors.name}</p>
            {/if}
            <p class="mt-1 text-sm text-gray-500">Format: lowercase, gunakan dash (-) untuk spasi</p>
          </div>
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
          {processing ? 'Menyimpan...' : 'Simpan Role'}
        </button>
      </div>
    </form>
  </div>
</AdminLayout>