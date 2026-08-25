<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  
  let form = {
    title: '',
    caption: '',
    image: null,
    sort_order: '',
    is_active: true
  };
  
  let errors = {};
  let processing = false;
  let imagePreview = null;
  
  function handleImageChange(event) {
    const file = event.target.files[0];
    if (file) {
      form.image = file;
      
      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        imagePreview = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  }
  
  function submit() {
    processing = true;
    errors = {};
    
    const formData = new FormData();
    formData.append('title', form.title);
    formData.append('caption', form.caption);
    if (form.image) {
      formData.append('image', form.image);
    }
    if (form.sort_order) {
      formData.append('sort_order', form.sort_order);
    }
    formData.append('is_active', form.is_active ? '1' : '0');
    
    router.post('/admin/galleries', formData, {
      onError: (errorBag) => {
        errors = errorBag;
        processing = false;
      },
      onSuccess: () => {
        processing = false;
      }
    });
  }
</script>

<AdminLayout>
  <div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center gap-4 mb-4">
        <a
          href="/admin/galleries"
          class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </a>
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Tambah Gallery</h1>
          <p class="mt-2 text-gray-600">Tambahkan gambar baru ke gallery</p>
        </div>
      </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <form on:submit|preventDefault={submit} class="p-6 space-y-6">
        
        <!-- Image Upload -->
        <div>
          <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
            Gambar *
          </label>
          <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
            <div class="space-y-1 text-center">
              {#if imagePreview}
                <div class="mb-4">
                  <img src={imagePreview} alt="Preview" class="mx-auto h-32 w-auto rounded-md" />
                </div>
              {:else}
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                  <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              {/if}
              <div class="flex text-sm text-gray-600">
                <label for="image" class="relative cursor-pointer bg-white rounded-md font-medium text-[#eb3434] hover:text-red-500">
                  <span>Upload gambar</span>
                  <input
                    id="image"
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    on:change={handleImageChange}
                    required
                  />
                </label>
                <p class="pl-1">atau drag and drop</p>
              </div>
              <p class="text-xs text-gray-500">PNG, JPG, GIF hingga 2MB</p>
            </div>
          </div>
          {#if errors.image}
            <p class="mt-1 text-sm text-red-600">{errors.image}</p>
          {/if}
        </div>

        <!-- Title -->
        <div>
          <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
            Judul *
          </label>
          <input
            type="text"
            id="title"
            bind:value={form.title}
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Masukkan judul gambar"
            required
          />
          {#if errors.title}
            <p class="mt-1 text-sm text-red-600">{errors.title}</p>
          {/if}
        </div>

        <!-- Caption -->
        <div>
          <label for="caption" class="block text-sm font-medium text-gray-700 mb-2">
            Caption
          </label>
          <textarea
            id="caption"
            bind:value={form.caption}
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Masukkan caption gambar (opsional)"
          ></textarea>
          {#if errors.caption}
            <p class="mt-1 text-sm text-red-600">{errors.caption}</p>
          {/if}
        </div>


        <!-- Sort Order -->
        <div>
          <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">
            Urutan
          </label>
          <input
            type="number"
            id="sort_order"
            bind:value={form.sort_order}
            min="0"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Kosongkan untuk otomatis"
          />
          {#if errors.sort_order}
            <p class="mt-1 text-sm text-red-600">{errors.sort_order}</p>
          {/if}
        </div>

        <!-- Status -->
        <div>
          <label class="flex items-center">
            <input
              type="checkbox"
              bind:checked={form.is_active}
              class="rounded border-gray-300 text-[#eb3434] shadow-sm focus:border-[#eb3434] focus:ring focus:ring-[#eb3434] focus:ring-opacity-50"
            />
            <span class="ml-2 text-sm text-gray-700">Aktif</span>
          </label>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
          <a
            href="/admin/galleries"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
          >
            Batal
          </a>
          <button
            type="submit"
            disabled={processing}
            class="px-4 py-2 text-sm font-medium text-white bg-[#eb3434] border border-transparent rounded-md shadow-sm hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {processing ? 'Menyimpan...' : 'Simpan Gallery'}
          </button>
        </div>
      </form>
    </div>
  </div>
</AdminLayout>