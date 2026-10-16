<script>
  /**
   * Buat muatan baru: pilih kurir, tanggal, lalu centang resi yang ikut.
   *
   * Resi yang ditampilkan hanya yang SUDAH selesai packing dan belum masuk
   * muatan mana pun — menyiapkan muatan dari resi yang belum siap berarti
   * merencanakan mengantar barang yang belum ada.
   */
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { toast } from '../../../utils/notifications.js';

  export let kurirList = [];
  export let resiSiap = [];
  export const errors = {};

  let kurirId = '';
  let tanggal = new Date().toISOString().slice(0, 10);
  let namaMuatan = '';
  let catatan = '';
  let jumlahLembaga = '';
  let terpilih = new Set();
  let cariResi = '';
  let memproses = false;

  $: resiTersaring = cariResi.trim()
    ? resiSiap.filter((r) => {
        const q = cariResi.toLowerCase();
        return (
          (r.no_resi || '').toLowerCase().includes(q) ||
          (r.nama_penerima || '').toLowerCase().includes(q) ||
          (r.nama_lembaga || '').toLowerCase().includes(q) ||
          (r.alamat_tujuan || '').toLowerCase().includes(q)
        );
      })
    : resiSiap;

  $: totalMushafTerpilih = resiSiap
    .filter((r) => terpilih.has(r.id))
    .reduce((n, r) => n + (r.jumlah_quran || 0), 0);

  function toggle(id) {
    if (terpilih.has(id)) {
      terpilih.delete(id);
    } else {
      terpilih.add(id);
    }
    terpilih = terpilih;
  }

  function pilihSemuaTersaring() {
    resiTersaring.forEach((r) => terpilih.add(r.id));
    terpilih = terpilih;
  }

  function kosongkanPilihan() {
    terpilih = new Set();
  }

  function simpan() {
    if (!kurirId) {
      toast.error('Pilih kurir terlebih dahulu.');
      return;
    }
    if (terpilih.size === 0) {
      toast.error('Pilih minimal satu resi.');
      return;
    }

    memproses = true;

    router.post(
      '/admin/muatan',
      {
        kurir_id: kurirId,
        tanggal_muatan: tanggal,
        nama_muatan: namaMuatan || null,
        catatan: catatan || null,
        jumlah_lembaga: jumlahLembaga === '' ? null : Number(jumlahLembaga),
        pengiriman_ids: Array.from(terpilih),
      },
      {
        onError: (e) => {
          toast.error(Object.values(e)[0] || 'Gagal menyimpan muatan.');
          memproses = false;
        },
        onFinish: () => {
          memproses = false;
        },
      }
    );
  }
</script>

<svelte:head>
  <title>Buat Muatan - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <FlashMessage />

  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8">
      <div class="mb-6">
        <a href="/admin/muatan" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
          <HeroIcon name="chevron-left" class="w-4 h-4" />
          Kembali ke daftar muatan
        </a>
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">Buat Muatan</h2>
          <p class="text-sm text-gray-600">Kelompokkan resi yang akan diantar satu kurir dalam satu perjalanan.</p>
        </div>
      </div>

      <!-- Data muatan -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Data Muatan</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="kurir" class="block text-sm font-medium text-gray-700 mb-2">Kurir <span class="text-red-500">*</span></label>
            <select
              id="kurir"
              bind:value={kurirId}
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
            >
              <option value="">Pilih kurir...</option>
              {#each kurirList as k}
                <option value={k.id}>{k.name} — {k.email}</option>
              {/each}
            </select>
            {#if errors.kurir_id}<p class="text-xs text-red-600 mt-1">{errors.kurir_id}</p>{/if}
          </div>

          <div>
            <label for="tanggal" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Muatan <span class="text-red-500">*</span></label>
            <input
              id="tanggal"
              type="date"
              bind:value={tanggal}
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
            />
            {#if errors.tanggal_muatan}<p class="text-xs text-red-600 mt-1">{errors.tanggal_muatan}</p>{/if}
          </div>

          <div>
            <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">Nama Muatan (opsional)</label>
            <input
              id="nama"
              type="text"
              bind:value={namaMuatan}
              placeholder="mis. Rute Surabaya Selatan"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
            />
          </div>

          <div>
            <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">Catatan (opsional)</label>
            <input
              id="catatan"
              type="text"
              bind:value={catatan}
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
            />
          </div>

          <div>
            <label for="jumlah_lembaga" class="block text-sm font-medium text-gray-700 mb-2">
              Jumlah Lembaga (opsional)
            </label>
            <input
              id="jumlah_lembaga"
              type="number"
              min="1"
              bind:value={jumlahLembaga}
              placeholder="mis. 5"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
            />
            <p class="text-xs text-gray-500 mt-1">
              Berapa lembaga yang akan dikunjungi muatan ini. Boleh dikosongkan.
            </p>
            {#if errors.jumlah_lembaga}<p class="text-xs text-red-600 mt-1">{errors.jumlah_lembaga}</p>{/if}
          </div>
        </div>
      </div>

      <!-- Pilih resi -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h3 class="text-base font-semibold text-gray-900">Pilih Resi</h3>
            <p class="text-xs text-gray-500 mt-0.5">
              Hanya resi selesai packing yang belum masuk muatan lain. Terpilih:
              <span class="font-semibold text-gray-900">{terpilih.size}</span> resi ·
              <span class="font-semibold text-gray-900">{totalMushafTerpilih}</span> mushaf
            </p>
          </div>
          <div class="flex gap-2">
            <button
              on:click={pilihSemuaTersaring}
              class="px-3 py-2 rounded-lg text-xs font-medium text-gray-700 border border-gray-300 hover:bg-gray-50"
            >
              Pilih semua ({resiTersaring.length})
            </button>
            <button
              on:click={kosongkanPilihan}
              class="px-3 py-2 rounded-lg text-xs font-medium text-gray-700 border border-gray-300 hover:bg-gray-50"
            >
              Kosongkan
            </button>
          </div>
        </div>

        <div class="px-4 sm:px-6 py-3 border-b border-gray-100">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
              <HeroIcon name="magnifying-glass" class="h-4 w-4" />
            </div>
            <input
              type="text"
              bind:value={cariResi}
              placeholder="Cari no. resi, penerima, atau alamat..."
              class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
            />
          </div>
        </div>

        {#if resiSiap.length === 0}
          <div class="p-12 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
              <HeroIcon name="archive-box" class="w-7 h-7 text-gray-400" />
            </div>
            <h4 class="text-base font-semibold text-gray-900 mb-1">Belum ada resi siap diantar</h4>
            <p class="text-sm text-gray-500">
              Resi akan muncul di sini setelah selesai packing di gudang.
            </p>
          </div>
        {:else}
          <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50 sticky top-0">
                <tr>
                  <th scope="col" class="px-4 py-3 w-10"></th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No. Resi</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Penerima</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Alamat</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                  <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                {#each resiTersaring as r (r.id)}
                  <tr class="hover:bg-gray-50 cursor-pointer" on:click={() => toggle(r.id)}>
                    <td class="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={terpilih.has(r.id)}
                        on:click|stopPropagation={() => toggle(r.id)}
                        class="rounded border-gray-300 text-[#eb3434] focus:ring-[#eb3434]"
                      />
                    </td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{r.no_resi}</td>
                    <td class="px-4 py-3">
                      <div class="text-sm text-gray-900">{r.nama_penerima || '—'}</div>
                      {#if r.nama_lembaga}<div class="text-xs text-gray-500">{r.nama_lembaga}</div>{/if}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                      <div class="line-clamp-2">{r.alamat_tujuan || '—'}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{r.jumlah_quran}</td>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{r.status || '—'}</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>

          {#if resiTersaring.length === 0}
            <div class="p-8 text-center text-sm text-gray-500">Tidak ada resi yang cocok dengan pencarian.</div>
          {/if}
        {/if}
      </div>

      <div class="flex justify-end gap-2">
        <a
          href="/admin/muatan"
          class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
        >
          Batal
        </a>
        <button
          on:click={simpan}
          disabled={memproses || !kurirId || terpilih.size === 0}
          class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <HeroIcon name="plus" class="w-4 h-4" />
          {memproses ? 'Menyimpan...' : `Buat Muatan (${terpilih.size} resi)`}
        </button>
      </div>
    </div>
  </div>
</AdminLayout>
