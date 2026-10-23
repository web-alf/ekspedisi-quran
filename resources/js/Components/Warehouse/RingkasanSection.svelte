<script>
  /**
   * Kerangka satu bagian ringkasan di Dashboard Gudang (SPV & Manager).
   *
   * Dashboard SPV dan Manager dulu menulis kerangka ini masing-masing, lalu
   * hanya satu yang dirombak — hasilnya dua gaya dalam satu halaman. Semua
   * bagian ringkasan memakai komponen ini supaya kotak ikon, judul, dan jarak
   * tetap sama.
   *
   * Slot `kanan` untuk angka besar / tab di sisi kanan judul.
   */
  import HeroIcon from '../UI/HeroIcon.svelte';

  export let icon;
  export let judul;
  export let keterangan = '';
  // indigo = packing/performa, amber = perlu tindakan, blue = beban, green = stok
  export let warna = 'indigo';

  const WARNA_IKON = {
    indigo: 'bg-indigo-50 text-indigo-600',
    amber: 'bg-amber-50 text-amber-600',
    blue: 'bg-blue-50 text-blue-600',
    green: 'bg-green-50 text-green-600',
  };
</script>

<section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
  <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-none {WARNA_IKON[warna] ?? WARNA_IKON.indigo}">
        <HeroIcon name={icon} class="w-4 h-4" />
      </div>
      <div class="min-w-0">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">{judul}</h2>
        {#if keterangan}
          <p class="text-xs sm:text-sm text-gray-500">{keterangan}</p>
        {/if}
      </div>
    </div>
    <slot name="kanan" />
  </div>

  <slot />
</section>
