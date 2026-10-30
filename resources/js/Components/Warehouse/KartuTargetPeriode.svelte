<script>
  /**
   * Satu target mingguan/bulanan gudang (30/10/2026): capaian, bar progres dengan penanda
   * laju, dan kotak Sisa / Sisa hari / Perlu per hari. Data dari TargetPeriodeService::ringkas().
   *
   * `tampilGudang` = sebut nama gudang di label (tampilan Manager Gudang, beberapa gudang).
   */
  import PilTitik from './PilTitik.svelte';
  import { statusLaju } from '../../utils/targetPeriode.js';

  export let target;
  export let tampilGudang = false;

  const angka = (n) => Number(n ?? 0).toLocaleString('id-ID');

  $: laju = statusLaju(target);
  $: posisiLaju = target.jumlah_hari > 0 ? Math.min(100, (target.hari_ke / target.jumlah_hari) * 100) : 0;
</script>

<div class="border border-gray-200 rounded-xl p-3 sm:p-4 min-w-0">
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div class="min-w-0">
      <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1">
        {target.tipe === 'bulanan' ? 'Bulanan' : 'Mingguan'} · {target.periode} · {target.jenis ?? 'Semua jenis'}{#if tampilGudang} · {target.gudang}{/if}
      </p>
      <p class="text-2xl font-bold text-gray-900 leading-none">
        {angka(target.dikemas)}
        <span class="text-xs font-medium text-gray-400">/ {angka(target.target)} mushaf</span>
      </p>
    </div>
    <PilTitik warna={laju.warna}>{laju.teks}</PilTitik>
  </div>

  <div class="relative mt-3">
    <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
      <div class="h-full rounded-full {target.persen >= 100 ? 'bg-green-500' : 'bg-[#eb3434]'}" style="width: {target.persen}%"></div>
    </div>
    {#if target.status === 'berjalan'}
      <div class="absolute -top-1 w-0.5 h-[18px] bg-gray-700 rounded" style="left: {posisiLaju}%" title="Laju seharusnya hari ini"></div>
    {/if}
  </div>
  <p class="text-xs text-gray-500 mt-2">
    {#if target.status === 'akan_datang'}
      Dimulai {new Date(`${target.tanggal_mulai}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })} · {target.jumlah_hari} hari
    {:else}
      {target.persen}% · hari ke-{target.hari_ke} dari {target.jumlah_hari}
      {#if target.status === 'berjalan'} · seharusnya ±{angka(target.laju)} hari ini (garis hitam){/if}
    {/if}
  </p>
  {#if target.catatan}
    <p class="text-xs text-gray-500 mt-1 italic">{target.catatan}</p>
  {/if}

  <div class="grid grid-cols-3 gap-2 mt-3">
    <div class="border border-gray-200 rounded-xl p-2.5 min-w-0">
      <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Sisa</p>
      <p class="text-lg font-bold text-gray-900">{angka(target.sisa)} <span class="text-xs font-medium text-gray-400">mushaf</span></p>
    </div>
    <div class="border border-gray-200 rounded-xl p-2.5 min-w-0">
      <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Sisa hari</p>
      <p class="text-lg font-bold text-gray-900">{target.sisa_hari} <span class="text-xs font-medium text-gray-400">hari</span></p>
    </div>
    <div class="border border-gray-200 rounded-xl p-2.5 min-w-0">
      <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Perlu / hari</p>
      <p class="text-lg font-bold text-gray-900">{angka(target.perlu_per_hari)} <span class="text-xs font-medium text-gray-400">mushaf</span></p>
    </div>
  </div>
</div>
