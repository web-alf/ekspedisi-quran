<script>
  /**
   * Kerangka satu bagian ringkasan di Dashboard Gudang (SPV & Manager).
   *
   * Dashboard SPV dan Manager dulu menulis kerangka ini masing-masing, lalu
   * hanya satu yang dirombak — hasilnya dua gaya dalam satu halaman. Semua
   * bagian ringkasan memakai komponen ini supaya kotak ikon, judul, dan jarak
   * tetap sama.
   *
   * 29/10/2026: kotak ikon mengikuti kartu dashboard super-admin — 40 px, aksen
   * merah merek untuk semua seksi (dulu warna berbeda per seksi, 32 px).
   *
   * Slot `kanan` untuk angka besar / tab di sisi kanan judul.
   */
  import HeroIcon from '../UI/HeroIcon.svelte';

  export let icon;
  export let judul;
  export let keterangan = '';
</script>

<section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
  <div class="flex items-start justify-between gap-4 flex-wrap mb-4 sm:mb-6">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-10 h-10 rounded-lg bg-red-50 text-[#eb3434] flex items-center justify-center flex-none">
        <HeroIcon name={icon} class="w-6 h-6" />
      </div>
      <div class="min-w-0">
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">{judul}</h2>
        {#if keterangan}
          <p class="text-xs sm:text-sm text-gray-500">{keterangan}</p>
        {/if}
      </div>
    </div>
    <slot name="kanan" />
  </div>

  <slot />
</section>
