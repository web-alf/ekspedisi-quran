<script>
  /**
   * Jatah packing hari ini dari Manager Gudang ke SPV, dibanding yang sudah dibagi SPV
   * ke freelancer (30/10/2026).
   *
   * Hierarki penugasan: PJ memberi jatah ke SPV per gudang, SPV meneruskannya ke freelancer.
   * Dulu tidak ada yang menghubungkan keduanya — SPV harus mengingat sendiri berapa jatahnya
   * dan berapa yang sudah dibagi. Bentuknya mengikuti StatTile (border, label kapital kecil).
   *
   * Props: `jatah` dari RingkasanGudangService::jatahGudang(); `nama` = nama gudang, diisi
   * bila beberapa gudang tampil berdampingan (tampilan PJ); `besar` = ukuran kartu Dashboard
   * Gudang (01/11/2026), Monitor & Penugasan tetap ukuran biasa.
   */
  import PilTitik from './PilTitik.svelte';

  export let jatah = { target: 0, jenis: [], spv: [], dibagi: 0, sisa: 0, lebih: 0 };
  export let nama = '';
  export let besar = false;

  $: persenDibagi = jatah.target > 0 ? Math.min(100, Math.round((jatah.dibagi / jatah.target) * 100)) : 0;
  $: teksKecil = besar ? 'text-sm' : 'text-xs';
</script>

<div class="border border-gray-200 rounded-xl min-w-0 {besar ? 'p-4 sm:p-5' : 'p-3 sm:p-4'}">
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div class="min-w-0">
      <p class="font-semibold uppercase tracking-wide text-gray-500 {besar ? 'text-xs mb-1.5' : 'text-[10px] mb-1'}">
        Jatah dari Manager Gudang{#if nama} · {nama}{/if}
      </p>
      {#if jatah.target > 0}
        <p class="font-bold text-gray-900 leading-none {besar ? 'text-3xl sm:text-4xl' : 'text-2xl'}">
          {jatah.target}
          <span class="font-medium text-gray-400 {teksKecil}">mushaf</span>
        </p>
        <p class="text-gray-500 {besar ? 'text-sm mt-2' : 'text-xs mt-1.5'}">
          {jatah.jenis.length ? jatah.jenis.join(', ') : 'Semua jenis'}
          {#if jatah.spv.length} · untuk {jatah.spv.join(', ')}{/if}
        </p>
      {:else}
        <p class="font-semibold text-gray-900 {besar ? 'text-base' : 'text-sm'}">Belum ada jatah hari ini</p>
        <p class="text-gray-500 mt-1 {teksKecil}">Manager Gudang belum memberi target ke SPV gudang ini.</p>
      {/if}
    </div>

    {#if jatah.target > 0}
      {#if jatah.sisa > 0}
        <PilTitik warna="amber" {besar}>Sisa {jatah.sisa} belum dibagi</PilTitik>
      {:else if jatah.lebih > 0}
        <PilTitik warna="amber" {besar}>Lebih {jatah.lebih} dari jatah</PilTitik>
      {:else}
        <PilTitik warna="green" {besar}>Sudah terbagi semua</PilTitik>
      {/if}
    {/if}
  </div>

  {#if jatah.target > 0}
    <div class="mt-3 h-1.5 rounded-full bg-gray-100 overflow-hidden">
      <div class="h-full rounded-full {jatah.sisa === 0 ? 'bg-green-500' : 'bg-[#eb3434]'}" style="width: {persenDibagi}%"></div>
    </div>
  {/if}
  <p class="text-gray-500 {besar ? 'text-sm mt-2' : 'text-xs mt-1.5'}">
    Sudah dibagi ke freelancer: <b class="text-gray-900">{jatah.dibagi}</b>{#if jatah.target > 0} dari {jatah.target}{/if} mushaf
  </p>
</div>
