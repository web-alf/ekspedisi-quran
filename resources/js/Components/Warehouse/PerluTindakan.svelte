<script>
  /**
   * Isi bagian "Perlu Tindakan" di Dashboard Gudang: dokumen perpindahan yang menunggu
   * diterima (mushaf ber-resi DAN stok tanpa muwakif) + kerdus penuh yang menunggu dimuat,
   * per gudang. SPV mengirim satu gudang, Manager dua — tampilan dan tautannya sama.
   *
   * Dokumen transfer stok dulu hanya terlihat di halaman Stok Mushaf, jadi bisa menggantung
   * tanpa pernah muncul di dashboard (26/10/2026). Sekarang keduanya ada di sini.
   *
   * Tautan hanya tampil bila halamannya memang boleh dibuka: tautan yang pasti 403 lebih
   * buruk daripada tidak ada tautan sama sekali.
   */
  import HeroIcon from '../UI/HeroIcon.svelte';
  import { hasPermission } from '../../utils/permissions.js';

  /** @type {Array<{kode: string, asal: string, tujuan?: string, jumlah_resi: number, jumlah_eks: number, dibuat_pada?: string}>} */
  export let transfer = [];
  /** @type {Array<{kode: string, asal: string, tujuan?: string, jenis: string, varian: string, jumlah_eks: number, dibuat_pada?: string}>} */
  export let transferStok = [];
  /** @type {Array<{id: number|string, nama: string, kerdus_menunggu_muat: number}>} */
  export let muat = [];
  // false untuk SPV: semua dokumen pasti menuju gudangnya sendiri.
  export let tampilTujuan = false;
  // Manager: staf yang belum mulai, per gudang. Kosong untuk SPV (kartu packing-nya sudah menyebut).
  /** @type {Array<{nama: string, jumlah: number}>} */
  export let stafBelumMulai = [];
  // Manager: jenis yang habis di satu gudang padahal gudang lain masih punya.
  /** @type {Array<{label: string, gudang: string, sumber: string, eksSumber: number}>} */
  export let stokHabis = [];

  $: bolehBukaTransfer = hasPermission('mushaf.transfer');
  $: bolehBukaTransferStok = hasPermission('stok.transfer');
  $: bolehBukaMuatan = hasPermission('muatan.read');
  $: bolehBukaPenugasan = hasPermission('supervisor.warehouse.monitor');
  $: totalBelumMulai = stafBelumMulai.reduce((n, g) => n + g.jumlah, 0);
</script>

{#if totalBelumMulai > 0}
  <div class="border border-gray-200 rounded-xl p-4 mb-3 flex flex-col sm:flex-row sm:items-center gap-4">
    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 flex-none">
      <HeroIcon name="users" class="w-5 h-5" />
    </div>
    <div class="flex-1 min-w-0">
      <p class="font-semibold text-gray-900">{totalBelumMulai} staf belum mulai kerja</p>
      <p class="text-sm text-gray-600 mt-0.5">
        {stafBelumMulai.filter((g) => g.jumlah > 0).map((g) => g.nama + ' ' + g.jumlah).join(' · ')}
      </p>
    </div>
    {#if bolehBukaPenugasan}
      <a href="/admin/supervisor/warehouse-monitor" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 text-center flex-none">
        Buka Penugasan
      </a>
    {/if}
  </div>
{/if}

{#each stokHabis as s (s.label + s.gudang)}
  <div class="border border-amber-200 bg-amber-50 rounded-xl p-4 mb-3 flex flex-col sm:flex-row sm:items-center gap-4">
    <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center text-amber-600 flex-none">
      <HeroIcon name="exclamation-triangle" class="w-5 h-5" />
    </div>
    <div class="flex-1 min-w-0">
      <p class="font-semibold text-gray-900">Stok {s.label} habis di {s.gudang}</p>
      <p class="text-sm text-gray-600 mt-0.5">{s.sumber} masih punya {s.eksSumber} eks — bisa dipindahkan.</p>
    </div>
    {#if bolehBukaTransferStok}
      <a href="/admin/transfer-mushaf" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 text-center flex-none">
        Buat Transfer Stok
      </a>
    {/if}
  </div>
{/each}

{#if transfer.length === 0 && transferStok.length === 0}
  <div class="rounded-xl border border-dashed border-gray-300 p-4 flex items-center gap-3 mb-3">
    <HeroIcon name="check-circle" class="w-5 h-5 text-green-500 flex-none" />
    <p class="text-sm text-gray-600">
      Tidak ada kiriman antar gudang yang menunggu.{#if totalBelumMulai === 0 && stokHabis.length === 0} Semua beres.{/if}
    </p>
  </div>
{:else}
  {#each transfer as t (t.kode)}
    <div class="border border-gray-200 rounded-xl p-4 mb-3 flex flex-col sm:flex-row sm:items-center gap-4">
      <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 flex-none">
        <HeroIcon name="arrow-down-tray" class="w-5 h-5" />
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-gray-900">Dokumen {t.kode} menunggu diterima</p>
        <p class="text-sm text-gray-600 mt-0.5">
          {#if tampilTujuan}
            {t.asal} → <b class="text-gray-900">{t.tujuan}</b>
          {:else}
            Dari {t.asal}
          {/if}
          · {t.jumlah_resi} resi ({t.jumlah_eks} mushaf)
        </p>
        <p class="text-xs text-gray-400 mt-1">
          {#if t.dibuat_pada}Dibuat {t.dibuat_pada} — {/if}lokasi mushaf berpindah saat gudang tujuan menekan Terima.
        </p>
      </div>
      {#if bolehBukaTransfer}
        <a href="/admin/transfer-mushaf" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 text-center flex-none">
          Buka Perpindahan
        </a>
      {/if}
    </div>
  {/each}

  {#each transferStok as t (t.kode)}
    <div class="border border-gray-200 rounded-xl p-4 mb-3 flex flex-col sm:flex-row sm:items-center gap-4">
      <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 flex-none">
        <HeroIcon name="cube" class="w-5 h-5" />
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-gray-900">Dokumen {t.kode} menunggu diterima</p>
        <p class="text-sm text-gray-600 mt-0.5">
          {#if tampilTujuan}
            {t.asal} → <b class="text-gray-900">{t.tujuan}</b>
          {:else}
            Dari {t.asal}
          {/if}
          · {t.jenis} ({t.varian}) · {t.jumlah_eks} eks stok
        </p>
        <p class="text-xs text-gray-400 mt-1">
          {#if t.dibuat_pada}Dibuat {t.dibuat_pada} — {/if}stok berpindah saat gudang tujuan menekan Terima di tab "Stok tanpa muwakif".
        </p>
      </div>
      {#if bolehBukaTransferStok}
        <a href="/admin/transfer-mushaf" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 text-center flex-none">
          Buka Perpindahan
        </a>
      {/if}
    </div>
  {/each}
{/if}

<div class="grid grid-cols-1 {muat.length > 1 ? 'sm:grid-cols-2' : ''} gap-3">
  {#each muat as g (g.id)}
    <div class="rounded-xl border border-dashed border-gray-300 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
      <HeroIcon name="truck" class="w-5 h-5 text-indigo-600 flex-none" />
      <p class="text-sm text-gray-600 flex-1">
        <b class="text-gray-900">{g.kerdus_menunggu_muat} kerdus</b> penuh menunggu dimuat
        {#if muat.length > 1}<span class="text-gray-500">— {g.nama}</span>{/if}
      </p>
      {#if bolehBukaMuatan}
        <a href="/admin/muatan" class="inline-flex items-center gap-1 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg flex-none">
          Lihat daftar
          <HeroIcon name="arrow-right" class="w-4 h-4" />
        </a>
      {/if}
    </div>
  {/each}
</div>
