<script>
  /**
   * Data calon permintaan mushaf yang sudah DISETUJUI — sudut pandang kurir.
   *
   * Ini daftar TUJUAN yang sudah lolos persetujuan dan belum terkirim: bahan bagi
   * kurir untuk tahu ke mana barang akan dibawa, dan bagi role distribusi untuk
   * menyiapkan muatan.
   *
   * Hanya status `approved` yang tampil. Kurir sengaja TIDAK diberi tombol
   * menyetujui/menolak — persetujuan adalah wewenang customer-service.
   */
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import Pagination from '../../../Components/Pagination.svelte';

  export let permintaan = { data: [], current_page: 1, last_page: 1, total: 0 };
  export let filters = {};
  export let kategoriList = [];
  export let provinsiList = [];
  export let ringkasan = { total_permintaan: 0, total_mushaf: 0, belum_ada_resi: 0 };
  export let perPage = 20;
  export let perPageOptions = [10, 20, 50, 100, 200];

  let search = filters.search || '';
  let kategori = filters.kategori || '';
  let provinsi = filters.provinsi || '';
  let searchTimeout;

  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
  }

  function applyFilters() {
    const params = { page: 1 };
    if (search) params.search = search;
    if (kategori) params.kategori = kategori;
    if (provinsi) params.provinsi = provinsi;

    router.get('/admin/kurir/permintaan-disetujui', params, { preserveState: true, preserveScroll: true });
  }

  function resetFilters() {
    search = '';
    kategori = '';
    provinsi = '';
    // Ukuran halaman tidak direset: preferensi tampilan, bukan filter.
    router.get('/admin/kurir/permintaan-disetujui', { per_page: perPage }, { preserveState: true, preserveScroll: true });
  }

  function formatTanggal(t) {
    if (!t) return '-';
    return new Date(t).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function mapsUrl(r) {
    if (r.link_gmaps) return r.link_gmaps;
    if (r.latitude && r.longitude) {
      return `https://www.openstreetmap.org/?mlat=${r.latitude}&mlon=${r.longitude}#map=15/${r.latitude}/${r.longitude}`;
    }
    return null;
  }
</script>

<svelte:head>
  <title>Permintaan Disetujui - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8">
      <div class="mb-6 sm:mb-8">
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1 sm:mb-2">Permintaan Mushaf Disetujui</h2>
          <p class="text-sm sm:text-base text-gray-600">
            Daftar tujuan yang sudah disetujui dan siap diantar. Hanya untuk melihat — persetujuan adalah
            wewenang Customer Service.
          </p>

          <div class="mt-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Permintaan Disetujui</div>
              <div class="text-xl font-bold text-gray-900">{ringkasan.total_permintaan}</div>
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Total Mushaf</div>
              <div class="text-xl font-bold text-gray-900">{ringkasan.total_mushaf}</div>
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Belum Ada Resi</div>
              <div class="text-xl font-bold text-gray-900">{ringkasan.belum_ada_resi}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
        <div class="space-y-4">
          <div>
            <label for="permintaan-search" class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <HeroIcon name="magnifying-glass" class="h-4 w-4 sm:h-5 sm:w-5" />
              </div>
              <input
                id="permintaan-search"
                type="text"
                bind:value={search}
                on:input={handleSearch}
                placeholder="Nama lembaga, pemohon, no. request, atau kota..."
                class="w-full pl-9 sm:pl-10 pr-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            <div>
              <label for="kategori-filter" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
              <select
                id="kategori-filter"
                bind:value={kategori}
                on:change={applyFilters}
                class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              >
                <option value="">Semua Kategori</option>
                {#each kategoriList as k}
                  <option value={k}>{k}</option>
                {/each}
              </select>
            </div>

            <div>
              <label for="provinsi-filter" class="block text-sm font-medium text-gray-700 mb-2">Provinsi</label>
              <select
                id="provinsi-filter"
                bind:value={provinsi}
                on:change={applyFilters}
                class="w-full px-3 py-2 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm sm:text-base"
              >
                <option value="">Semua Provinsi</option>
                {#each provinsiList as p}
                  <option value={p}>{p}</option>
                {/each}
              </select>
            </div>

            <div class="flex items-end">
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

      <!-- Tabel -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {#if permintaan.data.length === 0}
          <div class="p-12 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
              <HeroIcon name="badge-check" class="w-7 h-7 text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">Tidak ada permintaan disetujui</h3>
            <p class="text-sm text-gray-500">
              Permintaan akan muncul di sini setelah disetujui Customer Service.
            </p>
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No. Request</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lembaga</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Wilayah</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jumlah Disetujui</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Disetujui</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Resi</th>
                  <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Lokasi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                {#each permintaan.data as r (r.id)}
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                      <div class="text-sm font-mono font-medium text-gray-900">{r.no_request}</div>
                      {#if r.kategori_lembaga}
                        <div class="text-xs text-gray-500">{r.kategori_lembaga}</div>
                      {/if}
                    </td>
                    <td class="px-4 py-3">
                      <div class="text-sm text-gray-900">{r.nama_lembaga || '—'}</div>
                      {#if r.nama_pengurus}
                        <div class="text-xs text-gray-500">{r.nama_pengurus}</div>
                      {/if}
                      {#if r.no_hp}
                        <div class="text-xs text-gray-500">{r.no_hp}</div>
                      {/if}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                      <div class="line-clamp-2">
                        {[r.kelurahan_desa, r.kecamatan, r.kota_kabupaten, r.provinsi].filter(Boolean).join(', ') || '—'}
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                      <span class="font-semibold text-gray-900">{r.jumlah_disetujui}</span>
                      {#if r.rincian}
                        <div class="text-xs text-gray-500">
                          A5 {r.rincian.a5 || 0} · A6 {r.rincian.a6 || 0} · Iqra {r.rincian.iqra || 0}
                        </div>
                      {/if}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{formatTanggal(r.approved_at)}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      {#if r.pengiriman}
                        <div class="text-sm font-mono text-gray-900">{r.pengiriman.no_resi}</div>
                        <div class="text-xs text-gray-500">{r.pengiriman.status || '—'}</div>
                      {:else}
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 border border-gray-200">
                          Belum ada resi
                        </span>
                      {/if}
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                      {#if mapsUrl(r)}
                        <a
                          href={mapsUrl(r)}
                          target="_blank"
                          rel="noopener noreferrer"
                          class="inline-flex items-center gap-1 text-sm font-medium text-[#eb3434] hover:text-red-700"
                        >
                          <HeroIcon name="map-pin" class="w-4 h-4" />
                          Peta
                        </a>
                      {:else}
                        <span class="text-xs text-gray-400">—</span>
                      {/if}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>

          <div class="px-4 py-3 border-t border-gray-100">
            <Pagination data={permintaan} {perPage} perPageOptions={perPageOptions} />
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
