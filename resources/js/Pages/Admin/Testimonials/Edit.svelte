<script>
  import { router, useForm } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  
  export let testimonial = {};
  export let errors = {};
  export const auth = {};
  export const flash = {};
  
  const form = useForm({
    name: testimonial.name || '',
    location: testimonial.location || '',
    quote: testimonial.quote || '',
    image: null,
    is_active: testimonial.is_active ? true : false,
    _method: 'PUT'
  });
  
  let imagePreview = testimonial.image_url || null;
  let fileInput;
  
  function handleImageChange(event) {
    const file = event.target.files[0];
    if (file) {
      $form.image = file;
      
      const reader = new FileReader();
      reader.onload = (e) => {
        imagePreview = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  }
  
  function removeImage() {
    $form.image = null;
    imagePreview = null;
    if (fileInput) fileInput.value = '';
  }
  
  function handleSubmit() {
    $form.post(`/admin/testimonials/${testimonial.id}`, {
      forceFormData: true,
      preserveState: false
    });
  }
</script>

<AdminLayout>
  <div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center gap-4 mb-4">
        <a 
          href="/admin/testimonials"
          class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
          title="Kembali"
        >
          <HeroIcon name="chevron-left" class="w-5 h-5" />
        </a>
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Edit Testimonial</h1>
          <p class="mt-2 text-gray-600">Edit informasi testimonial</p>
        </div>
      </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <form on:submit|preventDefault={handleSubmit} class="p-6">
        <div class="space-y-6">
          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              Nama <span class="text-red-500">*</span>
            </label>
            <input
              id="name"
              type="text"
              bind:value={$form.name}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434] {errors.name ? 'border-red-500' : ''}"
              placeholder="Masukkan nama"
              required
            />
            {#if errors.name}
              <p class="mt-1 text-sm text-red-600">{errors.name}</p>
            {/if}
          </div>

          <!-- Location -->
          <div>
            <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
              Lokasi <span class="text-red-500">*</span>
            </label>
            <input
              id="location"
              type="text"
              bind:value={$form.location}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434] {errors.location ? 'border-red-500' : ''}"
              placeholder="Masukkan lokasi"
              required
            />
            {#if errors.location}
              <p class="mt-1 text-sm text-red-600">{errors.location}</p>
            {/if}
          </div>

          <!-- Quote -->
          <div>
            <label for="quote" class="block text-sm font-medium text-gray-700 mb-2">
              Testimonial <span class="text-red-500">*</span>
            </label>
            <textarea
              id="quote"
              bind:value={$form.quote}
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434] resize-none {errors.quote ? 'border-red-500' : ''}"
              placeholder="Masukkan testimonial..."
              required
            ></textarea>
            {#if errors.quote}
              <p class="mt-1 text-sm text-red-600">{errors.quote}</p>
            {/if}
          </div>

          <!-- Current Image -->
          {#if testimonial.image_url}
            <div>
              <div class="block text-sm font-medium text-gray-700 mb-2">
                Foto Saat Ini
              </div>
              <img 
                src={testimonial.image_url} 
                alt={testimonial.name}
                class="w-24 h-24 rounded-full object-cover"
              />
            </div>
          {/if}

          <!-- Image -->
          <div>
            <label for="foto-profil-edit" class="block text-sm font-medium text-gray-700 mb-2">
              {testimonial.image_url ? 'Ganti Foto Profil' : 'Foto Profil'}
            </label>
            
            {#if imagePreview && imagePreview !== testimonial.image_url}
              <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">Preview foto baru:</p>
                <img 
                  src={imagePreview} 
                  alt="Preview" 
                  class="w-24 h-24 rounded-full object-cover"
                />
                <button
                  type="button"
                  on:click={removeImage}
                  class="mt-2 text-sm text-red-600 hover:text-red-800"
                >
                  Batalkan perubahan foto
                </button>
              </div>
            {/if}
            
            <input
              id="foto-profil-edit"
              type="file"
              bind:this={fileInput}
              on:change={handleImageChange}
              accept="image/*"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434] {errors.image ? 'border-red-500' : ''}"
            />
            <p class="mt-1 text-sm text-gray-500">Format: JPG, PNG, GIF. Maksimal 2MB. Kosongkan jika tidak ingin mengubah foto.</p>
            {#if errors.image}
              <p class="mt-1 text-sm text-red-600">{errors.image}</p>
            {/if}
          </div>

          <!-- Status -->
          <div>
            <div class="flex items-center">
              <input
                id="is_active"
                type="checkbox"
                bind:checked={$form.is_active}
                class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
              />
              <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                Aktif (tampilkan di halaman utama)
              </label>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
          <a
            href="/admin/testimonials"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434]"
          >
            Batal
          </a>
          <button
            type="submit"
            disabled={$form.processing}
            class="px-4 py-2 text-sm font-medium text-white bg-[#eb3434] border border-transparent rounded-md shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {$form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
          </button>
        </div>
      </form>
    </div>
  </div>
</AdminLayout>