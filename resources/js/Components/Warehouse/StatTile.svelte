<script>
  /**
   * Kotak satu angka di ringkasan gudang: label kecil, angka besar + satuan.
   * Dipakai kartu packing dan beban gudang, untuk SPV maupun Manager.
   * Slot default untuk pil / rincian di bawah angka.
   *
   * `besar` = ukuran kartu Dashboard Gudang (01/11/2026). Halaman lain yang memakai kotak
   * ini (Proses Packing, Performa Kerja) tetap ukuran biasa.
   */
  export let label;
  export let nilai;
  export let satuan = '';
  // true = angka ditandai amber (mis. ada staf belum mulai).
  export let peringatan = false;
  export let besar = false;
</script>

<div class="border border-gray-200 rounded-xl min-w-0 {besar ? 'p-4 sm:p-5 min-h-[7.5rem] sm:min-h-[8.5rem]' : 'p-3'}">
  <p class="font-semibold uppercase tracking-wide text-gray-500 {besar ? 'text-xs mb-1.5' : 'text-[10px] mb-1'}">{label}</p>
  <p class="font-bold {besar ? 'text-3xl sm:text-4xl' : 'text-2xl'} {peringatan ? 'text-amber-700' : 'text-gray-900'}">
    {nilai}
    {#if satuan}<span class="font-medium text-gray-400 {besar ? 'text-sm' : 'text-xs'}">{satuan}</span>{/if}
  </p>
  <slot />
</div>
