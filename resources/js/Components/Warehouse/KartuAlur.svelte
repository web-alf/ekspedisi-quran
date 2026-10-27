<script>
  /**
   * Satu kotak angka di dashboard gudang (keputusan pemilik 27/10/2026).
   *
   * Bentuknya SENGAJA sama dengan StatTile — border, radius, label kapital kecil, angka
   * besar + satuan — supaya seksi "Stok" tidak menjadi gaya ketiga di halaman yang sama.
   * Bedanya cuma satu: label boleh dua baris, jadi tingginya dikunci agar semua ANGKA
   * tetap sejajar antar kartu.
   */
  import HeroIcon from '../UI/HeroIcon.svelte';

  export let label;
  export let nilai;
  export let satuan = 'resi';
  // Keterangan pendek di bawah angka (mis. "1 kerdus tersegel"). Kosong = tidak dirender.
  export let keterangan = '';
  // biasa = tahap alur; cadangan = stok tanpa resi (garis putus, bukan tahap alur);
  // siap = tahap akhir di gudang (siap dikirim).
  export let nada = 'biasa';
  // Nama ikon HeroIcon di samping label (dipakai kartu Alur Mushaf Manager). Kosong = tanpa ikon.
  export let ikon = '';

  const NADA = {
    biasa: 'border-gray-200',
    cadangan: 'border-dashed border-gray-400',
    siap: 'border-green-300 bg-green-50',
  };
</script>

<div class="border {NADA[nada] ?? NADA.biasa} rounded-xl p-3 min-w-0 flex flex-col">
  <div class="flex items-start gap-1.5 mb-1 min-h-[26px]">
    {#if ikon}
      <HeroIcon name={ikon} class="w-4 h-4 flex-none {nada === 'siap' ? 'text-green-600' : 'text-gray-500'}" />
    {/if}
    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 pt-px">{label}</p>
  </div>
  <p class="text-2xl font-bold text-gray-900 leading-none">
    {nilai}
    {#if satuan}<span class="text-xs font-medium text-gray-400">{satuan}</span>{/if}
  </p>
  {#if keterangan}
    <p class="text-xs text-gray-500 mt-1.5">{keterangan}</p>
  {/if}
</div>
