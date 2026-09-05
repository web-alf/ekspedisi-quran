<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  
  export let permission = {};
  export let categories = {};
  export let errors = {};
  
  let formData = {
    name: permission.name || ''
  };
  
  let processing = false;
  
  function submit() {
    processing = true;
    router.put(`/admin/permissions/${permission.id}`, formData, {
      onFinish: () => processing = false
    });
  }
  
  // Extract category from permission name
  $: currentCategory = permission.name ? permission.name.split('.')[0] : '';
</script>

<AdminLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <a 
        href="/admin/permissions"
        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors"
      >
        <HeroIcon name="chevron-left" class="w-4 h-4 mr-1" />
        Kembali
      </a>
      <h1 class="text-2xl font-bold text-gray-900">Edit Permission: {permission.name}</h1>
    </div>

    <form on:submit|preventDefault={submit} class="space-y-6">
      <!-- Basic Info -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Permission</h2>
        
        <div class="space-y-4">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              Nama Permission <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="name"
              bind:value={formData.name}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.name ? 'border-red-500' : ''}"
              placeholder="Contoh: users.create, wakif.read, etc..."
              required
            />
            {#if errors.name}
              <p class="mt-1 text-sm text-red-600">{errors.name}</p>
            {/if}
            <p class="mt-1 text-sm text-gray-500">
              Format: kategori.aksi (contoh: users.create, wakif.read, shipments.update)
            </p>
          </div>
          
          <div>
            <p class="block text-sm font-medium text-gray-700 mb-2">
              Kategori Saat Ini
            </p>
            <div class="px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
              {categories[currentCategory] || currentCategory}
            </div>
            <p class="mt-1 text-sm text-gray-500">
              Kategori akan otomatis berubah berdasarkan nama permission
            </p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-4">
        <a
          href="/admin/permissions"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
        >
          Batal
        </a>
        
        <button
          type="submit"
          disabled={processing}
          class="px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-[#d42c2c] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {processing ? 'Menyimpan...' : 'Update Permission'}
        </button>
      </div>
    </form>
  </div>
</AdminLayout>
