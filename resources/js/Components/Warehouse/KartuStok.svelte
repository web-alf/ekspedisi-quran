<script>
  /**
   * Kartu saldo stok satu jenis × varian. Dipakai dashboard SPV (satu gudang)
   * dan Manager (dua gudang, dengan `gudang` diisi nama gudangnya).
   *
   * `besar` = ukuran kartu Dashboard Gudang (01/11/2026); menu Stok Mushaf tetap ukuran biasa.
   */
  export let baris;
  export let gudang = '';
  export let besar = false;

  // Padanan dus yang BENER: "6 dus", "5 dus + 10 eks", atau "18 eks". Dulu hilang kata
  // "dus" karena number + string tanpa menyebut satuannya.
  function padananStok(b) {
    if (b.dus > 0 && b.sisa_eks > 0) return b.dus + ' dus + ' + b.sisa_eks + ' eks';
    if (b.dus > 0) return b.dus + ' dus';
    if (b.eks > 0) return b.eks + ' eks';

    return 'Pesan produksi dulu';
  }
</script>

<div class="rounded-xl border {besar ? 'p-4 sm:p-5 min-h-[7.5rem] sm:min-h-[8.5rem]' : 'p-4'} {baris.status === 'habis' ? 'border-amber-200 bg-amber-50' : 'border-gray-200'}">
  <div class="flex items-center gap-1.5 mb-2 flex-wrap">
    <span class="font-bold text-gray-900 {besar ? 'text-sm' : 'text-xs'}">{baris.kode_jenis}</span>
    {#if baris.varian}
      <span class="inline-flex px-2 py-0.5 rounded-full font-semibold bg-gray-100 text-gray-700 {besar ? 'text-xs' : 'text-[11px]'}">{baris.varian}</span>
    {/if}
    {#if baris.status !== 'normal'}
      <span class="inline-flex px-2 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-800 {besar ? 'text-xs' : 'text-[11px]'}">
        {baris.status === 'habis' ? 'Stok habis' : 'Stok rendah'}
      </span>
    {/if}
  </div>
  <p class="font-bold text-gray-900 {besar ? 'text-3xl sm:text-4xl' : 'text-2xl'}">{baris.eks} <span class="font-medium text-gray-400 {besar ? 'text-sm' : 'text-xs'}">eks</span></p>
  <p class="text-gray-500 {besar ? 'text-sm' : 'text-xs'}">{padananStok(baris)}</p>
  {#if gudang}
    <p class="text-gray-400 mt-2 {besar ? 'text-sm' : 'text-xs'}">{gudang}</p>
  {/if}
</div>
