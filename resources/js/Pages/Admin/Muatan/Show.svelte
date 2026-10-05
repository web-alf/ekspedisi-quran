<script>
  /**
   * Detail muatan — halaman kerja kurir dan role distribusi.
   *
   * Perbedaan wewenang yang terlihat di sini:
   *   - Kurir: memindai barang masuk muatan, memindahkan status PERJALANAN.
   *   - Role distribusi (dan super-admin): tombol "Selesaikan Distribusi".
   *
   * Tombolnya disembunyikan menurut prop `dapatMenyelesaikan` dari server, tapi
   * server tetap menolak bila kurir memanggil endpoint-nya langsung. Sembunyikan
   * tombol saja BUKAN pengamanan.
   */
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import ConfirmDialog from '../../../Components/ConfirmDialog.svelte';
  import { toast } from '../../../utils/notifications.js';
  import axios from 'axios';

  export let muatan = {};
  export let items = [];
  export let statusPerjalanan = [];
  export let statusSelesai = null;
  export let dapatMengubah = false;
  export let dapatMenghapus = false;
  export let dapatMemindai = false;
  export let dapatMenyelesaikan = false;
  export let alasanTidakBolehSelesai = null;
  export const flash = {};

  let noResi = '';
  let memindai = false;
  let statusTerpilih = '';
  let catatanStatus = '';
  let memprosesStatus = false;
  let showSelesaikan = false;
  let memprosesSelesai = false;

  $: bolehMenyelesaikan = dapatMenyelesaikan && muatan.total_resi > 0 && !muatan.selesai;
  $: slugSelesai = statusSelesai?.slug || 'diterima';

  async function pindaiResi() {
    if (!noResi.trim() || memindai) return;

    memindai = true;
    try {
      const { data } = await axios.post(`/admin/muatan/${muatan.id}/pindai`, { no_resi: noResi.trim() });

      if (data.success) {
        toast.success(data.message);
        noResi = '';
        muatUlang();
      } else {
        toast.error(data.message);
      }
    } catch (err) {
      toast.error(err.response?.data?.message || 'Gagal memindai resi.');
    } finally {
      memindai = false;
    }
  }

  async function ubahStatusPerjalanan() {
    if (!statusTerpilih || memprosesStatus) return;

    memprosesStatus = true;
    try {
      const { data } = await axios.post(`/admin/muatan/${muatan.id}/status-perjalanan`, {
        status_id: statusTerpilih,
        catatan: catatanStatus || null,
      });

      if (data.success) {
        toast.success(data.message);
        statusTerpilih = '';
        catatanStatus = '';
        muatUlang();
      } else {
        toast.error(data.message);
      }
    } catch (err) {
      toast.error(err.response?.data?.message || 'Gagal mengubah status.');
    } finally {
      memprosesStatus = false;
    }
  }

  async function selesaikanDistribusi() {
    memprosesSelesai = true;
    try {
      const { data } = await axios.post(`/admin/muatan/${muatan.id}/selesaikan`, {
        catatan: 'Distribusi diselesaikan',
      });

      if (data.success) {
        toast.success(data.message);
        showSelesaikan = false;
        muatUlang();
      } else {
        toast.error(data.message);
        showSelesaikan = false;
      }
    } catch (err) {
      // 403 dari server = kurir mencoba menyelesaikan. Pesannya ditampilkan apa
      // adanya supaya jelas kenapa ditolak, bukan "terjadi kesalahan".
      toast.error(err.response?.data?.message || 'Gagal menyelesaikan distribusi.');
      showSelesaikan = false;
    } finally {
      memprosesSelesai = false;
    }
  }

  function keluarkanResi(item) {
    router.delete(`/admin/muatan/${muatan.id}/item/${item.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Resi dikeluarkan dari muatan.'),
    });
  }

  function hapusMuatan() {
    router.delete(`/admin/muatan/${muatan.id}`, {
      onSuccess: () => toast.success('Muatan dihapus.'),
    });
  }

  function muatUlang() {
    router.reload({ only: ['muatan', 'items'] });
  }

  function formatTanggal(t) {
    if (!t) return '-';
    return new Date(t).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  function warnaStatus(warna) {
    const map = {
      yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      blue: 'bg-blue-100 text-blue-800 border-blue-200',
      green: 'bg-green-100 text-green-800 border-green-200',
      red: 'bg-red-100 text-red-800 border-red-200',
      purple: 'bg-purple-100 text-purple-800 border-purple-200',
      gray: 'bg-gray-100 text-gray-700 border-gray-200',
      emerald: 'bg-emerald-100 text-emerald-800 border-emerald-200',
      orange: 'bg-orange-100 text-orange-800 border-orange-200',
    };
    // Netral abu bila warna tidak dikenal — bukan kartu berwarna.
    return map[warna] || 'bg-gray-100 text-gray-700 border-gray-200';
  }
</script>

<svelte:head>
  <title>{muatan.kode_muatan} - Muatan & Distribusi</title>
</svelte:head>

<AdminLayout>
  <FlashMessage />

  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8">
      <!-- Header -->
      <div class="mb-6">
        <a href="/admin/muatan" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
          <HeroIcon name="chevron-left" class="w-4 h-4" />
          Kembali ke daftar muatan
        </a>

        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
              <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 font-mono">{muatan.kode_muatan}</h2>
                {#if muatan.selesai}
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                    <HeroIcon name="badge-check" class="w-3.5 h-3.5" />
                    Distribusi Selesai
                  </span>
                {:else}
                  <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                    Berjalan
                  </span>
                {/if}
              </div>
              {#if muatan.nama_muatan}
                <p class="text-sm text-gray-600 mt-1">{muatan.nama_muatan}</p>
              {/if}
              <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                <span>Tanggal: <span class="font-medium text-gray-900">{formatTanggal(muatan.tanggal_muatan)}</span></span>
                <span>Kurir: <span class="font-medium text-gray-900">{muatan.kurir ? muatan.kurir.name : '—'}</span></span>
                <span>Dibuat oleh: <span class="font-medium text-gray-900">{muatan.pembuat || '—'}</span></span>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
              {#if dapatMengubah}
                <a
                  href={`/admin/muatan/${muatan.id}/ubah`}
                  class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
                >
                  <HeroIcon name="pencil" class="w-4 h-4" />
                  Ubah
                </a>
              {/if}
              {#if dapatMenghapus}
                <button
                  on:click={hapusMuatan}
                  class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-red-700 border border-red-200 hover:bg-red-50 transition-colors"
                >
                  <HeroIcon name="trash" class="w-4 h-4" />
                  Hapus
                </button>
              {/if}
            </div>
          </div>

          <!-- Ringkasan angka -->
          <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Total Resi</div>
              <div class="text-xl font-bold text-gray-900">{muatan.total_resi}</div>
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Total Mushaf</div>
              <div class="text-xl font-bold text-gray-900">{muatan.total_mushaf}</div>
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Sudah Diterima</div>
              <div class="text-xl font-bold text-gray-900">{muatan.sebaran_status?.[slugSelesai] || 0}</div>
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-3">
              <div class="text-xs text-gray-500 mb-1">Belum Diterima</div>
              <div class="text-xl font-bold text-gray-900">
                {muatan.total_resi - (muatan.sebaran_status?.[slugSelesai] || 0)}
              </div>
            </div>
          </div>

          <!-- Sebaran status -->
          {#if Object.keys(muatan.sebaran_status || {}).length > 0}
            <div class="mt-4 flex flex-wrap gap-2">
              {#each Object.entries(muatan.sebaran_status) as [slug, jumlah]}
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs bg-gray-100 text-gray-700 border border-gray-200">
                  {slug}
                  <span class="font-semibold">{jumlah}</span>
                </span>
              {/each}
            </div>
          {/if}
        </div>
      </div>

      <!-- Aksi distribusi -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Distribusi</h3>

        {#if muatan.selesai}
          <p class="text-sm text-gray-600">
            Seluruh {muatan.total_resi} resi sudah diterima penerima. Distribusi muatan ini selesai.
          </p>
        {:else if dapatMenyelesaikan}
          <p class="text-sm text-gray-600 mb-4">
            Tekan tombol di bawah bila seluruh resi sudah benar-benar diterima penerima. Status SEMUA resi
            akan berubah menjadi "{statusSelesai?.nama || 'Diterima'}" dan tidak dapat dikembalikan.
          </p>
          <button
            on:click={() => (showSelesaikan = true)}
            disabled={!bolehMenyelesaikan}
            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <HeroIcon name="badge-check" class="w-4 h-4" />
            Selesaikan Distribusi
          </button>
          {#if muatan.total_resi === 0}
            <p class="text-xs text-gray-500 mt-2">Muatan masih kosong — belum ada resi yang bisa diselesaikan.</p>
          {/if}
        {:else}
          <!-- Kurir: dijelaskan, bukan sekadar tombolnya hilang tanpa alasan -->
          <div class="flex items-start gap-2.5 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3">
            <HeroIcon name="information-circle" class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
            <span>{alasanTidakBolehSelesai || 'Anda tidak berwenang menyelesaikan distribusi.'}</span>
          </div>
        {/if}

        {#if dapatMemindai && !muatan.selesai}
          <div class="mt-6 pt-6 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-900 mb-1">Pindahkan Status Perjalanan</h4>
            <p class="text-xs text-gray-500 mb-3">
              Berlaku untuk seluruh resi dalam muatan ini. Status "Diterima" tidak tersedia di sini.
            </p>
            <div class="flex flex-col sm:flex-row gap-2">
              <select
                bind:value={statusTerpilih}
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              >
                <option value="">Pilih status perjalanan...</option>
                {#each statusPerjalanan as s}
                  <option value={s.id}>{s.nama}</option>
                {/each}
              </select>
              <input
                type="text"
                bind:value={catatanStatus}
                placeholder="Catatan (opsional)"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              />
              <button
                on:click={ubahStatusPerjalanan}
                disabled={!statusTerpilih || memprosesStatus}
                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
              >
                {memprosesStatus ? 'Memproses...' : 'Terapkan'}
              </button>
            </div>
          </div>
        {/if}
      </div>

      <!-- Pindai resi masuk muatan -->
      {#if dapatMemindai && !muatan.selesai}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
          <h3 class="text-base font-semibold text-gray-900 mb-1">Pindai Resi Masuk Muatan</h3>
          <p class="text-sm text-gray-600 mb-4">
            Pindai QR resi, atau ketik nomor resinya langsung.
          </p>
          <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <HeroIcon name="qr-code" class="h-4 w-4" />
              </div>
              <input
                type="text"
                bind:value={noResi}
                on:keydown={(e) => e.key === 'Enter' && pindaiResi()}
                placeholder="EQ-2026-00001"
                class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
              />
            </div>
            <button
              on:click={pindaiResi}
              disabled={!noResi.trim() || memindai}
              class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
            >
              {memindai ? 'Memindai...' : 'Pindai'}
            </button>
          </div>
        </div>
      {/if}

      <!-- Daftar resi -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-100">
          <h3 class="text-base font-semibold text-gray-900">Daftar Resi ({items.length})</h3>
        </div>

        {#if items.length === 0}
          <div class="p-12 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
              <HeroIcon name="archive-box" class="w-7 h-7 text-gray-400" />
            </div>
            <h4 class="text-base font-semibold text-gray-900 mb-1">Belum ada resi</h4>
            <p class="text-sm text-gray-500">Pindai resi untuk memasukkannya ke muatan ini.</p>
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No. Resi</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Penerima</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Alamat</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                  {#if dapatMenghapus}
                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                  {/if}
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                {#each items as item (item.id)}
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-400">{item.urutan ?? '-'}</td>
                    <td class="px-4 py-3">
                      <div class="text-sm font-mono font-medium text-gray-900">{item.pengiriman?.no_resi || '—'}</div>
                      {#if item.pengiriman?.jenis}
                        <div class="text-xs text-gray-500">{item.pengiriman.jenis}</div>
                      {/if}
                    </td>
                    <td class="px-4 py-3">
                      <div class="text-sm text-gray-900">{item.pengiriman?.nama_penerima || '—'}</div>
                      {#if item.pengiriman?.nama_lembaga}
                        <div class="text-xs text-gray-500">{item.pengiriman.nama_lembaga}</div>
                      {/if}
                      {#if item.pengiriman?.no_hp_penerima}
                        <div class="text-xs text-gray-500">{item.pengiriman.no_hp_penerima}</div>
                      {/if}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                      <div class="line-clamp-2">{item.pengiriman?.alamat_tujuan || '—'}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{item.pengiriman?.jumlah_quran ?? 0}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      {#if item.pengiriman?.status}
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {warnaStatus(item.pengiriman.status.warna)}">
                          {item.pengiriman.status.nama}
                        </span>
                      {:else}
                        <span class="text-xs text-gray-400">—</span>
                      {/if}
                    </td>
                    {#if dapatMenghapus}
                      <td class="px-4 py-3 text-right">
                        <button
                          on:click={() => keluarkanResi(item)}
                          class="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-700"
                        >
                          <HeroIcon name="trash" class="w-4 h-4" />
                          Keluarkan
                        </button>
                      </td>
                    {/if}
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>

<ConfirmDialog
  bind:show={showSelesaikan}
  title="Selesaikan Distribusi"
  message="Semua resi dalam muatan ini akan ditandai &quot;Diterima&quot;. Tindakan ini tidak dapat dibatalkan."
  type="warning"
  confirmText={memprosesSelesai ? 'Memproses...' : 'Ya, Selesaikan'}
  cancelText="Batal"
  on:confirm={selesaikanDistribusi}
/>
