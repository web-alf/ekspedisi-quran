<script>
  /**
   * Kartu saldo stok satu jenis × varian. Dipakai dashboard SPV (satu gudang)
   * dan Manager (dua gudang, dengan `gudang` diisi nama gudangnya).
   */
  export let baris;
  export let gudang = '';

  // Padanan dus yang BENER: "6 dus", "5 dus + 10 eks", atau "18 eks". Dulu hilang kata
  // "dus" karena number + string tanpa menyebut satuannya.
  function padananStok(b) {
    if (b.dus > 0 && b.sisa_eks > 0) return b.dus + ' dus + ' + b.sisa_eks + ' eks';
    if (b.dus > 0) return b.dus + ' dus';
    if (b.eks > 0) return b.eks + ' eks';

    return 'Pesan produksi dulu';
  }
</script>

<div class="rounded-xl border p-4 {baris.status === 'habis' ? 'border-amber-200 bg-amber-50' : 'border-gray-200'}">
  <div class="flex items-center gap-1.5 mb-2 flex-wrap">
    <span class="text-xs font-bold text-gray-900">{baris.kode_jenis}</span>
    {#if baris.varian}
      <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700">{baris.varian}</span>
    {/if}
    {#if baris.status !== 'normal'}
      <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
        {baris.status === 'habis' ? 'Stok habis' : 'Stok rendah'}
      </span>
    {/if}
  </div>
  <p class="text-2xl font-bold text-gray-900">{baris.eks} <span class="text-xs font-medium text-gray-400">eks</span></p>
  <p class="text-xs text-gray-500">{padananStok(baris)}</p>
  {#if gudang}
    <p class="text-xs text-gray-400 mt-2">{gudang}</p>
  {/if}
</div>
