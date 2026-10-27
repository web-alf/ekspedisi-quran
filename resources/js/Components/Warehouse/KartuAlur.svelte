<script>
  /**
   * Satu kotak angka di dashboard gudang (keputusan pemilik 27/10/2026).
   *
   * Bentuknya SENGAJA sama dengan StatTile — border, radius, label kapital kecil, angka
   * besar + satuan — supaya seksi "Stok" tidak menjadi gaya ketiga di halaman yang sama.
   * Bedanya cuma satu: label boleh dua baris, jadi tingginya dikunci agar semua ANGKA
   * tetap sejajar antar kartu.
   */
  export let label;
  export let nilai;
  export let satuan = 'resi';
  // Keterangan pendek di bawah angka (mis. "1 kerdus tersegel"). Kosong = tidak dirender.
  export let keterangan = '';
  // biasa = tahap alur; cadangan = stok tanpa resi (garis putus, bukan tahap alur);
  // siap = tahap akhir di gudang (siap dikirim).
  export let nada = 'biasa';

  const NADA = {
    biasa: 'border-gray-200',
    cadangan: 'border-dashed border-gray-400',
    siap: 'border-green-300 bg-green-50',
  };
</script>

<div class="border {NADA[nada] ?? NADA.biasa} rounded-xl p-3 min-w-0 flex flex-col">
  <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1 min-h-[26px]">{label}</p>
  <p class="text-2xl font-bold text-gray-900 leading-none">
    {nilai}
    {#if satuan}<span class="text-xs font-medium text-gray-400">{satuan}</span>{/if}
  </p>
  {#if keterangan}
    <p class="text-xs text-gray-500 mt-1.5">{keterangan}</p>
  {/if}
</div>
