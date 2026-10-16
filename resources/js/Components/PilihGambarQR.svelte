<script>
  import { createEventDispatcher } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  import { bacaQrDariGambar, PESAN_TANPA_QR } from '../utils/qrGambar.js';

  export let label = 'Pilih gambar QR';
  export let keterangan = 'Dari galeri HP atau berkas di komputer';
  /** Pembaca gambar; bisa diganti di tes supaya tidak memuat pustaka pemindai. */
  export let bacaGambar = bacaQrDariGambar;

  const dispatch = createEventDispatcher();

  let inputBerkas;
  let memproses = false;
  let pesanGagal = null;

  /**
   * Hasil gambar dikirim lewat event yang SAMA dengan hasil kamera
   * (`scanSuccess` + `detail.text`), jadi halaman pemakai tidak perlu tahu
   * dari mana teks QR berasal.
   */
  async function pilihBerkas(event) {
    const berkas = event.currentTarget.files?.[0];
    // Dikosongkan supaya gambar yang sama bisa dipilih ulang setelah gagal.
    event.currentTarget.value = '';

    if (!berkas || memproses) {
      return;
    }

    memproses = true;
    pesanGagal = null;

    try {
      const teks = await bacaGambar(berkas);
      dispatch('scanSuccess', { text: teks, result: null, sumber: 'gambar' });
    } catch (err) {
      pesanGagal = err?.message || PESAN_TANPA_QR;
    } finally {
      memproses = false;
    }
  }
</script>

<div class="w-full">
  <input
    bind:this={inputBerkas}
    type="file"
    accept="image/*"
    class="hidden"
    data-testid="input-gambar-qr"
    on:change={pilihBerkas}
  />

  <button
    type="button"
    class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
    disabled={memproses}
    on:click={() => inputBerkas?.click()}
  >
    {#if memproses}
      <span class="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600"></span>
      Membaca gambar...
    {:else}
      <HeroIcon name="photo" class="w-4 h-4" />
      {label}
    {/if}
  </button>

  {#if keterangan}
    <p class="mt-1 text-xs text-gray-500 text-center">{keterangan}</p>
  {/if}

  {#if pesanGagal}
    <p class="mt-2 text-sm text-red-600 text-center" role="alert">{pesanGagal}</p>
  {/if}
</div>
