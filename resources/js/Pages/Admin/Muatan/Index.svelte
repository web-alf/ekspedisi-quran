<script>
  /**
   * Manajemen Muatan & Distribusi.
   *
   * Muatan = sekumpulan resi yang diantar satu kurir dalam satu perjalanan.
   *
   * Halaman ini dipakai DUA peran dengan sudut pandang berbeda, dan itu memang
   * disengaja — bukan dua halaman kembar:
   *   - Kurir melihat muatan miliknya sendiri (server yang menyaring, lihat prop
   *     `hanyaMiliknya`).
   *   - Role distribusi, supervisor, manager, dan super-admin melihat semua untuk
   *     memantau.
   *
   * Status perjalanan dibaca dari status resi di dalamnya, jadi tidak ada kolom
   * "status muatan" yang bisa berbohong.
   */
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import Pagination from '../../../Components/Pagination.svelte';

  export let muatan = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 };
  export let kurirList = [];
  export let filters = {};
  export let dapatMembuat = false;
  export let hanyaMiliknya = false;
  // Nama prop HARUS `perPage` — itu yang di-bind komponen PerPageSelector.
  export let perPage = 15;
  export let perPageOptions = [10, 15, 25, 50, 100];
  export const flash = {};

  let search = filters.search || '';
  let kurirFilter = filters.kurir_id || '';
  let tanggalFilter = filters.tanggal || '';
  let searchTimeout;

  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
  }

  function applyFilters() {
    const params = { page: 1 };
    if (search) params.search = search;
    if (kurirFilter) params.kurir_id = kurirFilter;
    if (tanggalFilter) params.tanggal = tanggalFilter;

    router.get('/admin/muatan', params, { preserveState: true, preserveScroll: true });
  }

  function resetFilters() {
    search = '';
    kurirFilter = '';
    tanggalFilter = '';
    // Ukuran halaman TIDAK direset: itu preferensi tampilan, bukan filter.
    router.get('/admin/muatan', { per_page: perPage }, { preserveState: true, preserveScroll: true });
  }

  function statusRingkas(sebaran) {
    return Object.entries(sebaran || {})
      .sort((a, b) => b[1] - a[1])
      .slice(0, 3);
  }

  function formatTanggal(t) {
    if (!t) return '-';
    return new Date(t).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  }
</script>

<svelte:head>
  <title>Muatan & Distribusi - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <FlashMessage />

  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8">
      <div class="mb-6 sm:mb-8">
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1 sm:mb-2">
                {hanyaMiliknya ? 'Muatan Saya' : 'Manajemen Muatan & Distribusi'}
              </h2>
              <p class="text-sm sm:text-base text-gray-600">
                {hanyaMiliknya
                  ? 'Daftar muatan yang Anda bawa. Tekan "Selesaikan Distribusi" hanya bila seluruh resi sudah benar-benar diterima penerima.'
                  : 'Kumpulan resi yang diantar satu kurir dalam satu perjalanan, beserta status distribusinya.'}
              </p>
            </div>
            {#if dapatMembuat}
              <a
                href="/admin/muatan/buat"
                class="w-full sm:w-auto bg-[#eb3434] hover:bg-red-600 text-white px-4 py-2 sm:py-3 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1.5"
              >
                <HeroIcon name="plus" class="w-4 h-4" />
                <span>Buat Muatan</span>
              </a>
            {/if}
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
        <div class="space-y-4">
          <div>
            <label for="muatan-search" class="block text-sm font-medium text-gray-700 mb-2">Cari Muatan</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <HeroIcon name="magnifying-glass" class="h-4 w-4 sm:h-5 sm:w-5" />
              </div>
              <input
                id="muatan-search"
                type="text"
                bind:value={search}
                on:input={handleSearch}
                placeholder="Kode muatan atau nama..."
                class="w-full pl-9 sm:pl-10 pr-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            {#if !hanyaMiliknya}
              <div>
                <label for="kurir-filter" class="block text-sm font-medium text-gray-700 mb-2">Kurir</label>
                <select
                  id="kurir-filter"
                  bind:value={kurirFilter}
                  on:change={applyFilters}
                  class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
                >
                  <option value="">Semua Kurir</option>
                  {#each kurirList as k}
                    <option value={k.id}>{k.name}</option>
                  {/each}
                </select>
              </div>
            {/if}

            <div>
              <label for="tanggal-filter" class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
              <input
                id="tanggal-filter"
                type="date"
                bind:value={tanggalFilter}
                on:change={applyFilters}
                class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              />
            </div>

            <div class="flex items-end gap-2">
              <button
                on:click={resetFilters}
                class="px-4 py-2 sm:py-3 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
              >
                Reset
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {#if muatan.data.length === 0}
          <div class="p-12 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
              <HeroIcon name="truck" class="w-7 h-7 text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">Belum ada muatan</h3>
            <p class="text-sm text-gray-500">
              {hanyaMiliknya
                ? 'Belum ada muatan yang ditugaskan kepada Anda.'
                : 'Buat muatan untuk mulai mengelompokkan resi per perjalanan.'}
            </p>
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kode</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                  {#if !hanyaMiliknya}
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kurir</th>
                  {/if}
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Isi</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Resi</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Distribusi</th>
                  <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                {#each muatan.data as m (m.id)}
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                      <div class="text-sm font-mono font-medium text-gray-900">{m.kode_muatan}</div>
                      {#if m.nama_muatan}
                        <div class="text-xs text-gray-500">{m.nama_muatan}</div>
                      {/if}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{formatTanggal(m.tanggal_muatan)}</td>
                    {#if !hanyaMiliknya}
                      <td class="px-4 py-3 text-sm text-gray-700">
                        {m.kurir ? m.kurir.name : '—'}
                      </td>
                    {/if}
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                      <span class="font-medium">{m.total_resi}</span> resi
                      <span class="text-gray-400">·</span>
                      <span class="font-medium">{m.total_mushaf}</span> mushaf
                    </td>
                    <td class="px-4 py-3">
                      <div class="flex flex-wrap gap-1">
                        {#each statusRingkas(m.sebaran_status) as [slug, jumlah]}
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 border border-gray-200">
                            {slug}
                            <span class="font-semibold">{jumlah}</span>
                          </span>
                        {/each}
                        {#if m.total_resi === 0}
                          <span class="text-xs text-gray-400">Kosong</span>
                        {/if}
                      </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      {#if m.selesai}
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                          <HeroIcon name="badge-check" class="w-3.5 h-3.5" />
                          Selesai
                        </span>
                      {:else}
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                          Berjalan
                        </span>
                      {/if}
                    </td>
                    <td class="px-4 py-3 text-right">
                      <a
                        href={`/admin/muatan/${m.id}`}
                        class="inline-flex items-center gap-1 text-sm font-medium text-[#eb3434] hover:text-red-700"
                      >
                        Detail
                        <HeroIcon name="chevron-right" class="w-4 h-4" />
                      </a>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 border-t border-gray-100">
            <Pagination data={muatan} {perPage} perPageOptions={perPageOptions} />
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
