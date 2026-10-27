<script>
  /**
   * Satu kotak angka di dashboard gudang (keputusan pemilik 27/10/2026).
   *
   * Bentuknya SENGAJA sama dengan StatTile — border, radius, label kapital kecil, angka
   * besar + satuan — supaya kartu "Alur Mushaf" tidak menjadi gaya ketiga di halaman yang
   * sama. Bedanya cuma satu: label boleh dua baris, jadi tingginya dikunci agar semua
   * ANGKA tetap sejajar antar kartu.
   */
  export let label;
  export let nilai;
  export let satuan = 'resi';
</script>

<div class="border border-gray-200 rounded-xl p-3 min-w-0 flex flex-col">
  <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1 min-h-[26px]">{label}</p>
  <p class="text-2xl font-bold text-gray-900 leading-none">
    {nilai}
    {#if satuan}<span class="text-xs font-medium text-gray-400">{satuan}</span>{/if}
  </p>
</div>
