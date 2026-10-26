<script>
  /**
   * Freelancer yang SUDAH mulai kerja hari ini, di kartu packing dashboard SPV dan
   * Manager. Susunannya SENGAJA sama dengan daftar freelancer di halaman Performa
   * Kerja (nama + pil status, jam mulai/selesai + jumlah kerdus, lalu selesai/target
   * dengan bar progress) supaya mata tidak perlu menyesuaikan diri antarhalaman
   * (keputusan pemilik sistem, 26/10/2026 — sebelumnya hanya pil nama · selesai/target).
   *
   * Yang ditugaskan tapi belum mulai sengaja tidak disebut namanya — cukup terhitung
   * di kotak "Staf belum mulai". Daftar lengkap per tanggal ada di halaman Performa Kerja.
   */
  import HeroIcon from '../UI/HeroIcon.svelte';
  import PilTitik from './PilTitik.svelte';
  import { hasPermission } from '../../utils/permissions.js';

  /** @type {Array<{id?: number, nama: string, peran?: string, selesai: number, target: number, kode?: string, status?: string, persen?: number, jam_mulai?: string|null, jam_selesai?: string|null, kerdus?: number}>} */
  export let staf = [];

  $: bolehLihatPerforma = hasPermission('warehouse.performance.view');

  /** Selesai = hijau, sedang kerja = indigo — sama dengan halaman Performa Kerja. */
  function tampilanStatus(status) {
    return status === 'selesai'
      ? { label: 'Selesai', warna: 'green' }
      : { label: 'Sedang kerja', warna: 'indigo' };
  }

  function persen(s) {
    if (typeof s.persen === 'number') {
      return s.persen;
    }

    return s.target > 0 ? Math.round((s.selesai / s.target) * 100) : 0;
  }
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
    <ul class="divide-y divide-gray-100 border border-gray-200 rounded-xl">
      {#each staf as s (s.id ?? s.nama + (s.kode ?? ''))}
        <li class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-semibold text-gray-900 truncate">{s.nama}</p>
              {#if s.kode}
                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold tracking-wide border border-gray-200 text-gray-500">{s.kode}</span>
              {/if}
              {#if s.peran && s.peran !== 'Freelancer Gudang'}
                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold border border-gray-200 text-gray-600 bg-gray-50">{s.peran}</span>
              {/if}
              <PilTitik warna={tampilanStatus(s.status).warna}>{tampilanStatus(s.status).label}</PilTitik>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              {#if s.jam_mulai}
                Mulai {s.jam_mulai}{s.jam_selesai ? ' · selesai ' + s.jam_selesai : ''} · {s.kerdus ?? 0} kerdus
              {:else}
                Tidak ada pemindaian
              {/if}
            </p>
          </div>
          <div class="sm:w-56 flex-none">
            <div class="flex items-baseline justify-between text-sm mb-1">
              <span><b class="text-gray-900">{s.selesai}</b><span class="text-gray-500"> / {s.target} mushaf</span></span>
              <span class="text-xs font-semibold text-gray-500">{persen(s)}%</span>
            </div>
            <div class="h-1.5 rounded-full bg-gray-200 overflow-hidden">
              <div class="h-full rounded-full {s.status === 'selesai' ? 'bg-green-500' : 'bg-indigo-500'}" style="width: {Math.min(100, persen(s))}%"></div>
            </div>
          </div>
        </li>
      {/each}
    </ul>
  {:else}
    <p class="text-sm text-gray-500">Belum ada freelancer yang mulai kerja hari ini.</p>
  {/if}
</div>
