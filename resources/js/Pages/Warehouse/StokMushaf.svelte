<script>
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';

  // Fase 3: stok mushaf tanpa muwakif per gudang + dokumen transfer antar gudang.
  export let gudangList = [];
  export let jenisList = [];
  export let stokList = [];
  export let transferList = [];
  export let pergerakanList = [];
  export let bolehKelola = false;
  export let bolehTransfer = false;
  export let gudangSaya = null;
  export let tercakupGudang = false;

  let pesan = '';
  let galat = '';
  let sibuk = false;

  // Form entri stok
  let entriTipe = 'masuk';
  let entriGudang = '';
  let entriJenis = '';
  let entriJumlah = 1;
  let entriCatatan = '';

  // Form transfer
  let transferAsal = '';
  let transferTujuan = '';
  let transferJenis = '';
  let transferJumlah = 1;
  let transferCatatan = '';

  function kapasitasJenis(id) {
    const jenis = jenisList.find((item) => String(item.id) === String(id));

    return jenis ? Number(jenis.default_capacity) : 0;
  }

  function kirimJson(endpoint, body, sukses) {
    galat = '';
    pesan = '';
    sibuk = true;
    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(body),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          pesan = data.message;
          sukses?.();
        } else {
          galat = data.message || 'Gagal.';
        }
      })
      .catch(() => { galat = 'Gagal menghubungi server.'; })
      .finally(() => { sibuk = false; });
  }
</script>

<AdminLayout>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Stok Mushaf (Belum Ada Muwakif)</h1>
      <p class="text-sm text-gray-500 mt-1">
        Mushaf siap di gudang yang belum terkait donatur — per gudang, dihitung dalam dus (1 dus = 1 kerdus penuh jenis itu).
      </p>
    </div>

    {#if pesan}<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">{pesan}</div>{/if}
    {#if galat}<div class="rounded-lg border border-gray-200 bg-red-50 px-4 py-3 text-sm text-red-700">{galat}</div>{/if}

    <section class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="font-semibold text-gray-900 mb-3">Isi Stok</h2>
      {#if stokList.length > 0}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 text-left text-gray-600">
                <th class="py-2 pr-4">Gudang</th>
                <th class="py-2 pr-4">Jenis</th>
                <th class="py-2 pr-4 text-right">Eks</th>
                <th class="py-2 text-right">Jumlah dus</th>
              </tr>
            </thead>
            <tbody>
              {#each stokList as stok}
                <tr class="border-b border-gray-100">
                  <td class="py-2 pr-4 text-gray-800">{stok.gudang?.nama}</td>
                  <td class="py-2 pr-4 text-gray-800">{stok.jenisQuran?.nama_jenis}</td>
                  <td class="py-2 pr-4 text-right font-medium text-gray-900">{stok.eks} eks</td>
                  <td class="py-2 text-right text-gray-600">{stok.dus} dus{stok.sisaEks > 0 ? ` + ${stok.sisaEks}` : ''}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {:else}
        <p class="text-sm text-gray-500">Belum ada stok tercatat.</p>
      {/if}
    </section>

    {#if bolehKelola}
      <section class="bg-white rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Catat Pergerakan</h2>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Tipe</span>
            <select bind:value={entriTipe} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="masuk">Masuk</option>
              <option value="keluar">Keluar (muwakif dilampirkan)
              </option>
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Gudang
</span>
            <select bind:value={entriGudang} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Pilih gudang...</option>
              {#each gudangList as gudang}
                <option value={gudang.id}>{gudang.nama}</option>
              {/each}
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Jenis
</span>
            <select bind:value={entriJenis} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Pilih jenis...</option>
              {#each jenisList as jenis}
                <option value={jenis.id}>{jenis.kode_jenis}</option>
              {/each}
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Jumlah eks
</span>
            <input type="number" min="1" bind:value={entriJumlah} class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Catatan
</span>
            <input type="text" bind:value={entriCatatan} placeholder="mis. terima barang produksi" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          1 dus = {kapasitasJenis(entriJenis) || 0} eks{#if entriJenis && entriJumlah} · total {kapasitasJenis(entriJenis) * (Number(entriJumlah) || 0)} eks{/if}
        </p>
        <button
          type="button"
          disabled={!entriGudang || !entriJenis || sibuk}
          on:click={() => kirimJson('/admin/warehouse/stok/entri', { gudang_id: entriGudang, jenis_quran_id: entriJenis, tipe: entriTipe, jumlah_eks: Number(entriJumlah) || 0, catatan: entriCatatan }, () => { router.reload({ only: ['stokList', 'pergerakanList'] }); })}
          class="mt-3 px-4 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-700 disabled:opacity-50"
        >{sibuk ? 'Merekam...' : 'Rekam Pergerakan'}</button>
      </section>
    {/if}

    {#if bolehTransfer}
      <section class="bg-white rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Dokumen Transfer Antar Gudang</h2>
        <p class="text-xs text-gray-500 mb-3">Dibuat dulu; stok berpindah saat SPV gudang TUJUAN menerima (barang fisik sudah tiba).</p>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Dari
</span>
            <select bind:value={transferAsal} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Gudang asal...</option>
              {#each gudangList as gudang}
                <option value={gudang.id}>{gudang.nama}</option>
              {/each}
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Ke
</span>
            <select bind:value={transferTujuan} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Gudang tujuan...</option>
              {#each gudangList as gudang}
                <option value={gudang.id}>{gudang.nama}</option>
              {/each}
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Jenis
</span>
            <select bind:value={transferJenis} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Pilih jenis...</option>
              {#each jenisList as jenis}
                <option value={jenis.id}>{jenis.kode_jenis}</option>
              {/each}
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Jumlah eks
</span>
            <input type="number" min="1" bind:value={transferJumlah} class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Catatan
</span>
            <input type="text" bind:value={transferCatatan} class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
        </div>
        <button
          type="button"
          disabled={!transferAsal || !transferTujuan || !transferJenis || transferAsal === transferTujuan || sibuk}
          on:click={() => kirimJson('/admin/warehouse/stok/transfer', { gudang_asal_id: transferAsal, gudang_tujuan_id: transferTujuan, jenis_quran_id: transferJenis, jumlah_eks: Number(transferJumlah) || 0, catatan: transferCatatan }, () => { router.reload({ only: ['transferList'] }); })}
          class="mt-3 px-4 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-700 disabled:opacity-50"
        >Buat Dokumen Transfer</button>
      </section>
    {/if}

    <section class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="font-semibold text-gray-900 mb-3">Daftar Dokumen Transfer</h2>
      {#if transferList.length === 0}
        <p class="text-sm text-gray-500">Belum ada dokumen.</p>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 text-left text-gray-600">
                <th class="py-2 pr-4">Kode</th>
                <th class="py-2 pr-4">Dari → Ke</th>
                <th class="py-2 pr-4">Jenis</th>
                <th class="py-2 pr-4 text-right">Eks</th>
                <th class="py-2 pr-4">Status</th>
                <th class="py-2">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {#each transferList as transfer}
                <tr class="border-b border-gray-100">
                  <td class="py-2 pr-4 font-mono text-gray-800">{transfer.kode}</td>
                  <td class="py-2 pr-4 text-gray-800">{transfer.asal} → {transfer.tujuan}</td>
                  <td class="py-2 pr-4 text-gray-800">{transfer.jenis}</td>
                  <td class="py-2 pr-4 text-right text-gray-900">{transfer.jumlah_eks}</td>
                  <td class="py-2 pr-4">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {transfer.status === 'diterima' ? 'bg-gray-900 text-white' : transfer.status === 'dibatalkan' ? 'bg-gray-100 text-gray-500 line-through' : 'bg-gray-100 text-gray-800 border border-gray-200'}">{transfer.status}</span>
                  </td>
                  <td class="py-2 flex gap-2">
                    {#if bolehTransfer && transfer.status === 'diproses'}
                      <button
                        type="button"
                        on:click={() => kirimJson(`/admin/warehouse/stok/transfer/${transfer.id}/terima`, {}, () => { router.reload({ only: ['transferList', 'stokList', 'pergerakanList'] }); })}
                        class="px-3 py-1 border border-gray-300 rounded-lg text-xs text-gray-800 hover:bg-gray-100"
                      >Terima</button>
                      <button
                        type="button"
                        on:click={() => kirimJson(`/admin/warehouse/stok/transfer/${transfer.id}/batalkan`, {}, () => { router.reload({ only: ['transferList'] }); })}
                        class="px-3 py-1 rounded-lg text-xs text-gray-600 hover:text-gray-900"
                      >Batalkan</button>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </section>

    <section class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="font-semibold text-gray-900 mb-3">Pergerakan Terakhir</h2>
      {#if pergerakanList.length === 0}
        <p class="text-sm text-gray-500">Belum ada pergerakan.</p>
      {:else}
        <ul class="text-sm divide-y divide-gray-100">
          {#each pergerakanList as baris}
            <li class="py-2 flex flex-col sm:flex-row sm:justify-between text-gray-700">
            <span>
            <span class="font-medium">{baris.gudang}</span> · {baris.jenis} · {baris.tipe} · <span class="font-medium text-gray-900">{baris.jumlah_eks} eks</span>
            {#if baris.catatan}<span class="text-gray-500"> — {baris.catatan}</span>{/if}
            </span>
            <span class="text-xs text-gray-500">{baris.oleh} · {baris.waktu}</span>
            </li>
          {/each}
        </ul>
      {/if}
    </section>
  </div>
</AdminLayout>