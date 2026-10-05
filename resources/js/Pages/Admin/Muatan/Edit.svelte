<script>
  /**
   * Ubah data muatan (kurir, tanggal, nama, catatan).
   *
   * Daftar resi sengaja TIDAK bisa diubah dari halaman ini: isi muatan dikelola
   * di halaman detail lewat pemindaian, supaya tiap perubahan resi meninggalkan
   * jejak siapa yang memuatnya dan kapan.
   */
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { toast } from '../../../utils/notifications.js';

  export let muatan = {};
  export let kurirList = [];
  export const errors = {};

  let kurirId = muatan.kurir_id || '';
  let tanggal = muatan.tanggal_muatan || '';
  let namaMuatan = muatan.nama_muatan || '';
  let catatan = muatan.catatan || '';
  let memproses = false;

  function simpan() {
    if (!kurirId) {
      toast.error('Pilih kurir terlebih dahulu.');
      return;
    }

    memproses = true;

    router.put(
      `/admin/muatan/${muatan.id}`,
      {
        kurir_id: kurirId,
        tanggal_muatan: tanggal,
        nama_muatan: namaMuatan || null,
        catatan: catatan || null,
      },
      {
        onError: () => {
          toast.error('Gagal menyimpan perubahan.');
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
  <title>Ubah {muatan.kode_muatan} - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <FlashMessage />

  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8 max-w-3xl">
      <div class="mb-6">
        <a
          href={`/admin/muatan/${muatan.id}`}
          class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4"
        >
          <HeroIcon name="chevron-left" class="w-4 h-4" />
          Kembali ke detail muatan
        </a>
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">Ubah Muatan</h2>
          <p class="text-sm text-gray-600 font-mono">{muatan.kode_muatan}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
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
        </div>

        <div class="flex justify-end gap-2 mt-6 pt-6 border-t border-gray-100">
          <a
            href={`/admin/muatan/${muatan.id}`}
            class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors"
          >
            Batal
          </a>
          <button
            on:click={simpan}
            disabled={memproses || !kurirId}
            class="px-5 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {memproses ? 'Menyimpan...' : 'Simpan Perubahan'}
          </button>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>
