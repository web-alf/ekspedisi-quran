<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  
  export let categories = {};
  export let errors = {};
  
  let formData = {
    name: '',
    category: ''
  };
  
  let processing = false;
  
  function submit() {
    processing = true;
    router.post('/admin/permissions', formData, {
      onFinish: () => processing = false
    });
  }
</script>

<AdminLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <a 
        href="/admin/permissions"
        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Kembali
      </a>
      <h1 class="text-2xl font-bold text-gray-900">Tambah Permission Baru</h1>
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
            <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
              Kategori <span class="text-red-500">*</span>
            </label>
            <select
              id="category"
              bind:value={formData.category}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.category ? 'border-red-500' : ''}"
              required
            >
              <option value="">Pilih Kategori</option>
              {#each Object.entries(categories) as [key, name]}
                <option value={key}>{name}</option>
              {/each}
            </select>
            {#if errors.category}
              <p class="mt-1 text-sm text-red-600">{errors.category}</p>
            {/if}
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
          {processing ? 'Menyimpan...' : 'Simpan Permission'}
        </button>
      </div>
    </form>
  </div>
</AdminLayout>