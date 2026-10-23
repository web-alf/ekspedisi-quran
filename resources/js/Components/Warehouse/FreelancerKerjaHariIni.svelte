<script>
  /**
   * Freelancer yang SUDAH mulai kerja hari ini, di kartu packing dashboard SPV dan
   * Manager. Yang ditugaskan tapi belum mulai sengaja tidak disebut namanya —
   * cukup terhitung di kotak "Staf belum mulai". Daftar lengkap per tanggal ada
   * di halaman Performa Kerja.
   */
  import HeroIcon from '../UI/HeroIcon.svelte';
  import PilTitik from './PilTitik.svelte';
  import { hasPermission } from '../../utils/permissions.js';

  /** @type {Array<{nama: string, selesai: number, target: number, kode?: string}>} */
  export let staf = [];

  $: bolehLihatPerforma = hasPermission('warehouse.performance.view');
</script>

<div class="mt-4 pt-4 border-t border-dashed border-gray-200">
  <div class="flex items-center justify-between gap-3 mb-2">
    <p class="text-xs font-medium text-gray-500">Freelancer yang kerja hari ini</p>
    {#if bolehLihatPerforma}
      <a href="/admin/warehouse/performance" class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:text-blue-800">
        Semua per tanggal
        <HeroIcon name="arrow-right" class="w-3.5 h-3.5" />
      </a>
    {/if}
  </div>
  {#if staf.length > 0}
    <div class="flex flex-wrap gap-2">
      {#each staf as s (s.nama + (s.kode ?? ''))}
        <PilTitik warna={s.target > 0 && s.selesai >= s.target ? 'green' : 'indigo'}>
          {s.nama}{s.kode ? ' · ' + s.kode : ''} · {s.selesai}/{s.target}
        </PilTitik>
      {/each}
    </div>
  {:else}
    <p class="text-sm text-gray-500">Belum ada freelancer yang mulai kerja hari ini.</p>
  {/if}
</div>
