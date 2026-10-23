<script>
  /**
   * Kotak satu angka di ringkasan gudang: label kecil, angka besar + satuan.
   * Dipakai kartu packing dan beban gudang, untuk SPV maupun Manager.
   * Slot default untuk pil / rincian di bawah angka.
   */
  export let label;
  export let nilai;
  export let satuan = '';
  // true = angka ditandai amber (mis. ada staf belum mulai).
  export let peringatan = false;
</script>

<div class="border border-gray-200 rounded-xl p-3 min-w-0">
  <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1">{label}</p>
  <p class="text-2xl font-bold {peringatan ? 'text-amber-700' : 'text-gray-900'}">
    {nilai}
    {#if satuan}<span class="text-xs font-medium text-gray-400">{satuan}</span>{/if}
  </p>
  <slot />
</div>
