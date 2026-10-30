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
   * bila beberapa gudang tampil berdampingan (tampilan PJ).
   */
  import PilTitik from './PilTitik.svelte';

  export let jatah = { target: 0, jenis: [], spv: [], dibagi: 0, sisa: 0, lebih: 0 };
  export let nama = '';

  $: persenDibagi = jatah.target > 0 ? Math.min(100, Math.round((jatah.dibagi / jatah.target) * 100)) : 0;
</script>

<div class="border border-gray-200 rounded-xl p-3 sm:p-4 min-w-0">
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div class="min-w-0">
      <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1">
        Jatah dari Manager Gudang{#if nama} · {nama}{/if}
      </p>
      {#if jatah.target > 0}
        <p class="text-2xl font-bold text-gray-900 leading-none">
          {jatah.target}
          <span class="text-xs font-medium text-gray-400">mushaf</span>
        </p>
        <p class="text-xs text-gray-500 mt-1.5">
          {jatah.jenis.length ? jatah.jenis.join(', ') : 'Semua jenis'}
          {#if jatah.spv.length} · untuk {jatah.spv.join(', ')}{/if}
        </p>
      {:else}
        <p class="text-sm font-semibold text-gray-900">Belum ada jatah hari ini</p>
        <p class="text-xs text-gray-500 mt-1">Manager Gudang belum memberi target ke SPV gudang ini.</p>
      {/if}
    </div>

    {#if jatah.target > 0}
      {#if jatah.sisa > 0}
        <PilTitik warna="amber">Sisa {jatah.sisa} belum dibagi</PilTitik>
      {:else if jatah.lebih > 0}
        <PilTitik warna="amber">Lebih {jatah.lebih} dari jatah</PilTitik>
      {:else}
        <PilTitik warna="green">Sudah terbagi semua</PilTitik>
      {/if}
    {/if}
  </div>

  {#if jatah.target > 0}
    <div class="mt-3 h-1.5 rounded-full bg-gray-100 overflow-hidden">
      <div class="h-full rounded-full {jatah.sisa === 0 ? 'bg-green-500' : 'bg-[#eb3434]'}" style="width: {persenDibagi}%"></div>
    </div>
  {/if}
  <p class="text-xs text-gray-500 mt-1.5">
    Sudah dibagi ke freelancer: <b class="text-gray-900">{jatah.dibagi}</b>{#if jatah.target > 0} dari {jatah.target}{/if} mushaf
  </p>
</div>
