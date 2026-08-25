<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';

  export let video;

  let form = {
    title: video.title,
    video_url: video.video_url,
    caption: video.caption || '',
    category: video.category || '',
    sort_order: video.sort_order || '',
    is_active: video.is_active
  };

  let errors = {};
  let processing = false;

  // Extract YouTube video ID from URL
  function getYoutubeVideoId(url) {
    if (!url) return null;
    const shortMatch = url.match(/youtu\.be\/([^?]+)/);
    if (shortMatch) return shortMatch[1];
    const watchMatch = url.match(/[?&]v=([^&]+)/);
    if (watchMatch) return watchMatch[1];
    const embedMatch = url.match(/embed\/([^?]+)/);
    if (embedMatch) return embedMatch[1];
    return null;
  }

  // Get thumbnail URL from YouTube
  function getThumbnailUrl(url) {
    const videoId = getYoutubeVideoId(url);
    return videoId ? `https://img.youtube.com/vi/${videoId}/hqdefault.jpg` : null;
  }

  // Preview thumbnail
  $: videoPreview = getThumbnailUrl(form.video_url);

  function submit() {
    processing = true;
    errors = {};

    router.put(`/admin/videos/${video.id}`, form, {
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
          href="/admin/videos"
          class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </a>
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Edit Video</h1>
          <p class="mt-2 text-gray-600">Edit video: {video.title}</p>
        </div>
      </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <form on:submit|preventDefault={submit} class="p-6 space-y-6">

        <!-- Video URL -->
        <div>
          <label for="video_url" class="block text-sm font-medium text-gray-700 mb-2">
            URL YouTube *
          </label>
          <input
            type="url"
            id="video_url"
            bind:value={form.video_url}
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="https://www.youtube.com/watch?v=... atau https://youtu.be/..."
            required
          />
          {#if errors.video_url}
            <p class="mt-1 text-sm text-red-600">{errors.video_url}</p>
          {/if}
          <p class="mt-1 text-xs text-gray-500">
            Contoh: https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP
          </p>
        </div>

        <!-- Video Preview -->
        {#if videoPreview}
          <div>
            <p class="block text-sm font-medium text-gray-700 mb-2">
              Preview Thumbnail
            </p>
            <div class="relative max-w-md">
              <img
                src={videoPreview}
                alt="Video Preview"
                class="w-full rounded-lg shadow-md"
              />
              <div class="absolute inset-0 flex items-center justify-center bg-black/20 rounded-lg">
                <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center shadow-lg">
                  <svg class="w-6 h-6 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                  </svg>
                </div>
              </div>
            </div>
          </div>
        {/if}

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
            placeholder="Masukkan judul video"
            required
          />
          {#if errors.title}
            <p class="mt-1 text-sm text-red-600">{errors.title}</p>
          {/if}
        </div>

        <!-- Caption -->
        <div>
          <label for="caption" class="block text-sm font-medium text-gray-700 mb-2">
            Caption / Deskripsi
          </label>
          <textarea
            id="caption"
            bind:value={form.caption}
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Masukkan deskripsi video (opsional)"
          ></textarea>
          {#if errors.caption}
            <p class="mt-1 text-sm text-red-600">{errors.caption}</p>
          {/if}
        </div>

        <!-- Category -->
        <div>
          <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
            Kategori
          </label>
          <input
            type="text"
            id="category"
            bind:value={form.category}
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Kategori video (opsional)"
          />
          {#if errors.category}
            <p class="mt-1 text-sm text-red-600">{errors.category}</p>
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
            href="/admin/videos"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
          >
            Batal
          </a>
          <button
            type="submit"
            disabled={processing}
            class="px-4 py-2 text-sm font-medium text-white bg-[#eb3434] border border-transparent rounded-md shadow-sm hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {processing ? 'Menyimpan...' : 'Update Video'}
          </button>
        </div>
      </form>
    </div>
  </div>
</AdminLayout>
