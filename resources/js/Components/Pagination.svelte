<script>
  import { router } from '@inertiajs/svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  import PerPageSelector from './PerPageSelector.svelte';
  
  export let data = {};
  export let additionalParams = {};
  // Ukuran halaman yang sedang dipakai (dikirim controller sebagai prop `perPage`),
  // supaya pemilihnya menampilkan nilai yang benar dan ikut terbawa saat pindah
  // halaman. Halaman yang tidak mengirimnya tetap jalan dengan bawaan 20.
  export let perPage = 20;
  export let perPageOptions = [10, 20, 50, 100, 200];

  $: pagination = {
    current_page: data.current_page || 1,
    last_page: data.last_page || 1,
    per_page: data.per_page || perPage,
    total: data.total || 0,
    from: data.from || 0,
    to: data.to || 0,
    links: data.links || []
  };

  // Ganti ukuran halaman: kembali ke halaman 1 supaya pengguna tidak terlempar ke
  // halaman yang melewati batas, dan supaya filternya tetap terbawa.
  function gantiPerHalaman() {
    const params = { ...additionalParams, per_page: perPage, page: 1 };
    router.get(window.location.pathname, params, {
      preserveState: true,
      preserveScroll: true
    });
  }
  
  function goToPage(url) {
    if (url) {
      // Selalu bawa ukuran halaman yang dipilih, kalau tidak pilihannya hilang
      // begitu pengguna menekan halaman berikutnya.
      const urlObj = new URL(url);
      const params = { ...Object.fromEntries(urlObj.searchParams), ...additionalParams, per_page: perPage };
      
      router.get(urlObj.pathname, params, {
        preserveState: true,
        preserveScroll: true
      });
    }
  }
</script>

{#if pagination.total > 0}
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
      <div class="text-sm text-gray-700">
        Showing <span class="font-medium">{pagination.from}</span> to <span class="font-medium">{pagination.to}</span> of <span class="font-medium">{pagination.total}</span> results
      </div>
      <!-- Pemilih selalu tampil walau hasilnya cuma satu halaman: pengguna tetap
           berhak memperbesar/memperkecil jumlah baris yang ingin dilihat. -->
      <PerPageSelector bind:perPage options={perPageOptions} id="per_page_bersama" onchange={gantiPerHalaman} />
    </div>
    {#if pagination.last_page > 1}

    <!-- Pagination links -->
    <div class="flex items-center space-x-2">
      {#each pagination.links as link}
        {#if link.label.includes('Previous') || link.label.includes('&laquo;')}
          <button
            on:click={() => goToPage(link.url)}
            disabled={!link.url}
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed gap-1"
          >
            <HeroIcon name="chevron-left" class="w-4 h-4" />
            <span>Previous</span>
          </button>
        {:else if link.label.includes('Next') || link.label.includes('&raquo;')}
          <button
            on:click={() => goToPage(link.url)}
            disabled={!link.url}
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed gap-1"
          >
            <span>Next</span>
            <HeroIcon name="chevron-right" class="w-4 h-4" />
          </button>
        {:else}
          <button
            on:click={() => goToPage(link.url)}
            disabled={!link.url}
            class={`px-3 py-2 text-sm font-medium border rounded-md ${
              link.active 
                ? 'text-white bg-blue-600 border-blue-600' 
                : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'
            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            {link.label}
          </button>
        {/if}
      {/each}
    </div>
    {/if}
  </div>
{/if}