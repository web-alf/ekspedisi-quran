<script>
  /**
   * Penghitung karakter di bawah isian doa (batas DOA_MAKS_KARAKTER agar muat di label resi).
   * Doa lama yang lebih panjang diberi peringatan: tercetak terpotong sampai diperpendek.
   */
  import { DOA_MAKS_KARAKTER, panjangDoa, doaTerlaluPanjang } from '../utils/doa.js';

  export let teks = '';

  $: panjang = panjangDoa(teks);
  $: kelebihan = doaTerlaluPanjang(teks);
</script>

<div class="mt-1 flex items-start justify-between gap-3 text-xs">
  <p class={kelebihan ? 'text-red-600' : 'text-gray-500'}>
    {#if kelebihan}
      Lebih dari {DOA_MAKS_KARAKTER} karakter: doa ini tercetak terpotong di resi. Persingkat bila diubah.
    {:else}
      Maksimal {DOA_MAKS_KARAKTER} karakter agar muat di label resi.
    {/if}
  </p>
  <p class="flex-none tabular-nums {kelebihan ? 'text-red-600 font-medium' : 'text-gray-500'}">{panjang}/{DOA_MAKS_KARAKTER}</p>
</div>
