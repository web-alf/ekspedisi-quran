<script>
  /**
   * PerPageSelector.svelte — pemilih "Baris per halaman" untuk tabel admin.
   *
   * Dipakai bersama oleh seluruh tabel supaya perilakunya seragam: lebar tetap,
   * tidak diperas oleh flex, dan angka tidak menumpuk ikon panah bawaan browser.
   * Tanpa `shrink-0` + lebar tetap, select di dalam wadah flex akan menyusut
   * sampai "20" bertabrakan dengan panahnya.
   *
   * Props:
   *   perPage  — nilai terpilih (bind:perPage dari induk)
   *   options  — daftar ukuran yang sah
   *   id       — id unik, dipakai label `for` (beberapa halaman punya >1 tabel)
   *   onchange — dipanggil setelah nilai berubah; induk yang mengurus navigasi
   */
  export let perPage = 20;
  export let options = [10, 20, 50, 100, 200];
  export let id = 'per_page';
  export let onchange = () => {};
</script>

<div class="flex items-center gap-2">
  <label for={id} class="text-sm text-gray-500 whitespace-nowrap">Baris per halaman</label>
  <select
    {id}
    bind:value={perPage}
    on:change={onchange}
    class="shrink-0 w-[4.75rem] pl-3 pr-8 py-1.5 text-sm border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
  >
    {#each options as opsi}
      <option value={opsi}>{opsi}</option>
    {/each}
  </select>
</div>
