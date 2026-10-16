<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  import { inertia, page } from '@inertiajs/svelte';

  export let gallery;

  function goBack() {
    window.history.back();
  }
</script>

<AdminLayout>
  <svelte:fragment slot="header">
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <button 
          on:click={goBack}
          class="text-gray-600 hover:text-gray-900 transition-colors"
        >
          <HeroIcon name="arrow-left" class="w-6 h-6" />
        </button>
        <div>
          <h2 class="text-xl font-semibold text-gray-800">Detail Gallery</h2>
          <p class="text-sm text-gray-600">Lihat detail item gallery</p>
        </div>
      </div>
      <div class="flex space-x-3">
        <a
          href={`/admin/galleries/${gallery.id}/edit`}
          use:inertia
          class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors"
        >
          Edit
        </a>
        <a
          href="/admin/galleries"
          use:inertia
          class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
        >
          Kembali ke List
        </a>
      </div>
    </div>
  </svelte:fragment>

  <div class="p-6">
    <!-- Gallery Image -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
      <div class="text-center">
        <img 
          src={`/storage/${gallery.image}`}
          alt={gallery.title}
          class="max-w-full max-h-96 mx-auto rounded-lg shadow-md object-contain"
        />
      </div>
    </div>

    <!-- Gallery Details -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Gallery</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <p class="block text-sm font-medium text-gray-700 mb-1">Judul</p>
          <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">{gallery.title}</p>
        </div>

        <div>
          <p class="block text-sm font-medium text-gray-700 mb-1">Kategori</p>
          <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">{gallery.category}</p>
        </div>

        <div class="md:col-span-2">
          <p class="block text-sm font-medium text-gray-700 mb-1">Caption</p>
          <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">{gallery.caption || 'Tidak ada caption'}</p>
        </div>

        <div>
          <p class="block text-sm font-medium text-gray-700 mb-1">Urutan</p>
          <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">{gallery.sort_order || 0}</p>
        </div>

        <div>
          <p class="block text-sm font-medium text-gray-700 mb-1">Status</p>
          <div class="bg-gray-50 p-3 rounded-lg">
            <span class="px-3 py-1 rounded-full text-sm font-medium {gallery.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
              {gallery.is_active ? 'Aktif' : 'Tidak Aktif'}
            </span>
          </div>
        </div>

        <div>
          <p class="block text-sm font-medium text-gray-700 mb-1">Dibuat</p>
          <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">
            {new Date(gallery.created_at).toLocaleDateString('id-ID', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit'
            })}
          </p>
        </div>

        <div>
          <p class="block text-sm font-medium text-gray-700 mb-1">Diperbarui</p>
          <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">
            {new Date(gallery.updated_at).toLocaleDateString('id-ID', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit'
            })}
          </p>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>
