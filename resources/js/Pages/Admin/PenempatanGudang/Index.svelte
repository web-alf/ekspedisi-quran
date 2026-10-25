<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';

  // Penempatan Gudang (16/10/2026): akun SPV/staf → Semarang atau Boyolali.
  // Gudang akun menentukan lokasi mushaf yang ia kerjakan BERIKUTNYA; lokasi mushaf yang
  // sudah tercatat tidak ikut pindah (itu lewat Perpindahan Mushaf).
  export let akun = [];
  export let gudangList = [];

  let filter = 'semua';
  let kataKunci = '';
  let sedangSimpan = null;

  $: hitung = {
    semua: akun.length,
    belum: akun.filter((a) => !a.gudang_id).length,
    ...Object.fromEntries(gudangList.map((g) => [g.id, akun.filter((a) => a.gudang_id === g.id).length])),
  };

  $: tampil = akun.filter((a) => {
    if (filter === 'belum' && a.gudang_id) return false;
    if (filter !== 'semua' && filter !== 'belum' && a.gudang_id !== filter) return false;
    const q = kataKunci.trim().toLowerCase();
    return !q || a.nama.toLowerCase().includes(q) || (a.email || '').toLowerCase().includes(q);
  });

  function pindahkan(orang, nilai) {
    const gudangId = nilai === '' ? null : Number(nilai);
    if (gudangId === orang.gudang_id) return;

    sedangSimpan = orang.id;
    router.patch(`/admin/penempatan-gudang/${orang.id}`, { gudang_id: gudangId }, {
      preserveScroll: true,
      onFinish: () => { sedangSimpan = null; },
    });
  }

  const kelasTab = (aktif) =>
    `px-3 py-1.5 rounded-md text-sm whitespace-nowrap ${aktif ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`;
</script>

<AdminLayout>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Penempatan Gudang</h1>
      <p class="text-sm text-gray-500 mt-1">
        Tentukan SPV dan staf bekerja di gudang mana. Mushaf yang mereka proses sesudahnya otomatis
        tercatat di gudang itu. Lokasi mushaf yang sudah tercatat tidak ikut pindah — gunakan
        Perpindahan Mushaf untuk itu.
      </p>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
      <div class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg overflow-x-auto max-w-full" role="tablist">
        <button type="button" class={kelasTab(filter === 'semua')} on:click={() => (filter = 'semua')}>Semua ({hitung.semua})</button>
        {#each gudangList as g (g.id)}
          <button type="button" class={kelasTab(filter === g.id)} on:click={() => (filter = g.id)}>{g.nama} ({hitung[g.id] || 0})</button>
        {/each}
        <button type="button" class={kelasTab(filter === 'belum')} on:click={() => (filter = 'belum')}>Belum ditempatkan ({hitung.belum})</button>
      </div>
      <input type="search" bind:value={kataKunci} placeholder="Cari nama atau email"
        class="w-full sm:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm" />
    </div>

    <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      {#if tampil.length === 0}
        <p class="px-4 py-10 text-center text-sm text-gray-500">Tidak ada akun pada pilihan ini.</p>
      {:else}
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Peran</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sm:w-56">Gudang</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each tampil as orang (orang.id)}
                <tr class="hover:bg-gray-50" data-akun={orang.id}>
                  <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">{orang.nama}</div>
                    <!-- Di ponsel peran ditaruh di bawah nama supaya kolom gudang tidak terjepit. -->
                    <div class="text-xs text-gray-500 sm:hidden">{orang.peran}</div>
                    <div class="text-xs text-gray-500 break-all">
                      {orang.email}{orang.aktif ? '' : ' · nonaktif'}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell text-sm text-gray-700">
                    <span class="inline-flex items-center gap-1.5">
                      <span class="w-1.5 h-1.5 rounded-full {['bg-gray-900', 'bg-gray-600', 'bg-gray-300'][orang.urutan_peran] || 'bg-gray-300'}"></span>
                      {orang.peran}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <select
                      value={orang.gudang_id ?? ''}
                      on:change={(e) => pindahkan(orang, e.currentTarget.value)}
                      disabled={sedangSimpan === orang.id}
                      aria-label="Gudang {orang.nama}"
                      class="w-full min-w-[9.5rem] px-3 py-1.5 border border-gray-300 rounded-lg text-sm disabled:opacity-50 {orang.gudang_id ? '' : 'text-gray-500'}"
                    >
                      <!-- SPV wajib bergudang (PenempatanGudangRequest): opsi kosong
                           dinonaktifkan untuk SPV yang sudah ditempatkan. -->
                      <option value="" disabled={orang.adalah_spv && orang.gudang_id}>Belum ditempatkan</option>
                      {#each gudangList as g (g.id)}
                        <option value={g.id}>{g.nama}</option>
                      {/each}
                    </select>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </section>
  </div>
</AdminLayout>
