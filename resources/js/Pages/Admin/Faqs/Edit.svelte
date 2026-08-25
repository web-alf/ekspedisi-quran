<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  
  export let faq;
  
  let form = {
    question: faq.question,
    answer: faq.answer,
    category: faq.category,
    sort_order: faq.sort_order || '',
    is_active: faq.is_active
  };
  
  let errors = {};
  let processing = false;
  
  function submit() {
    processing = true;
    errors = {};
    
    router.put(`/admin/faqs/${faq.id}`, form, {
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
          href="/admin/faqs"
          class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </a>
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Edit FAQ</h1>
          <p class="mt-2 text-gray-600">Edit FAQ: {faq.question}</p>
        </div>
      </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <form on:submit|preventDefault={submit} class="p-6 space-y-6">
        
        <!-- Question -->
        <div>
          <label for="question" class="block text-sm font-medium text-gray-700 mb-2">
            Pertanyaan *
          </label>
          <input
            type="text"
            id="question"
            bind:value={form.question}
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Masukkan pertanyaan"
            required
          />
          {#if errors.question}
            <p class="mt-1 text-sm text-red-600">{errors.question}</p>
          {/if}
        </div>

        <!-- Answer -->
        <div>
          <label for="answer" class="block text-sm font-medium text-gray-700 mb-2">
            Jawaban *
          </label>
          <textarea
            id="answer"
            bind:value={form.answer}
            rows="6"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            placeholder="Masukkan jawaban lengkap"
            required
          ></textarea>
          <p class="mt-1 text-xs text-gray-500">Gunakan enter untuk membuat paragraf baru</p>
          {#if errors.answer}
            <p class="mt-1 text-sm text-red-600">{errors.answer}</p>
          {/if}
        </div>

        <!-- Category -->
        <div>
          <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
            Kategori *
          </label>
          <select
            id="category"
            bind:value={form.category}
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#eb3434] focus:border-[#eb3434]"
            required
          >
            <option value="general">Umum</option>
            <option value="donation">Donasi</option>
            <option value="distribution">Distribusi</option>
            <option value="tracking">Pelacakan</option>
            <option value="technical">Teknis</option>
          </select>
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
            href="/admin/faqs"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
          >
            Batal
          </a>
          <button
            type="submit"
            disabled={processing}
            class="px-4 py-2 text-sm font-medium text-white bg-[#eb3434] border border-transparent rounded-md shadow-sm hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {processing ? 'Menyimpan...' : 'Update FAQ'}
          </button>
        </div>
      </form>
    </div>
  </div>
</AdminLayout>