<script>
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';

  // Fase 3: stok mushaf tanpa muwakif per gudang + dokumen transfer antar gudang.
  // Temuan lapangan 19/10/2026: A5/A6 punya varian ber-terjemahan dan tanpa, jadi
  // setiap pergerakan stok WAJIB menyebut variannya (dua tumpukan terpisah).
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

  // Form entri stok. Varian sengaja dimulai kosong supaya benar-benar harus dipilih.
  let entriTipe = 'masuk';
  let entriGudang = '';
  let entriJenis = '';
  let entriTerjemahan = '';
  let entriJumlah = 1;
  let entriCatatan = '';

  // Form transfer
  let transferAsal = '';
  let transferTujuan = '';
  let transferJenis = '';
  let transferTerjemahan = '';
  let transferJumlah = 1;
  let transferCatatan = '';

  function kapasitasJenis(id) {
    const jenis = jenisList.find((item) => String(item.id) === String(id));

    return jenis ? Number(jenis.default_capacity) : 0;
  }

  function namaJenis(id) {
    const jenis = jenisList.find((item) => String(item.id) === String(id));

    return jenis ? jenis.kode_jenis : '';
  }

  // Padanan dus dari jumlah EKS yang diketik: 1 dus = kapasitas jenis (A5 20, A6 40, IQRO 160).
  function padananDus(jumlah, id) {
    const kapasitas = kapasitasJenis(id);
    const eks = Number(jumlah) || 0;

    if (!kapasitas || eks <= 0) {
      return '';
    }

    const dus = Math.floor(eks / kapasitas);
    const sisa = eks % kapasitas;
    const bagian = [];

    if (dus > 0) {
      bagian.push(`${dus} dus`);
    }

    if (sisa > 0) {
      bagian.push(`${sisa} eks`);
    }

    return bagian.length ? bagian.join(' + ') : '0 eks';
  }

  function kirimJson(endpoint, body, sukses) {
    galat = '';
    pesan = '';
    sibuk = true;

    const meta = document.querySelector('meta[name="csrf-token"]');

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Dua header ini WAJIB. Tanpa `Accept: application/json`, Laravel menganggap
        // permintaan ini kunjungan halaman biasa: galat validasi dijawab REDIRECT (302),
        // bukan JSON 422 — browser menerima halaman HTML, `res.json()` gagal, dan layar
        // hanya menampilkan "Gagal menghubungi server." tanpa menyebut apa yang salah.
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': meta ? meta.content : '',
      },
      body: JSON.stringify(body),
    })
      .then(async (res) => {
        const teks = await res.text();
        let data = null;

        try {
          data = JSON.parse(teks);
        } catch {
          data = null;
        }

        if (data && data.success) {
          pesan = data.message;
          sukses?.();

          return;
        }

        const rincian = data && data.errors ? Object.values(data.errors).flat().join(' ') : '';

        galat = rincian || (data && data.message) || `Gagal (kode ${res.status}). Muat ulang halaman lalu coba lagi.`;
      })
      .catch(() => { galat = 'Gagal menghubungi server. Periksa koneksi lalu coba lagi.'; })
      .finally(() => { sibuk = false; });
  }
</script>

<AdminLayout>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Stok Mushaf (Belum Ada Muwakif)</h1>
      <p class="text-sm text-gray-500 mt-1">
        Mushaf siap di gudang yang belum terkait donatur — per gudang, per varian (terjemahan/tanpa terjemahan).
        Satuan dasar eks; 1 dus = 1 kerdus penuh jenis itu (A5 20 eks, A6 40, IQRO 160).
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
                <th class="py-2 pr-4">Terjemahan</th>
                <th class="py-2 pr-4 text-right">Eks</th>
                <th class="py-2 text-right">Jumlah dus</th>
              </tr>
            </thead>
            <tbody>
              {#each stokList as stok}
                <tr class="border-b border-gray-100">
                  <td class="py-2 pr-4 text-gray-800">{stok.gudang}</td>
                  <td class="py-2 pr-4 text-gray-800">{stok.jenis}</td>
                  <td class="py-2 pr-4 text-gray-800">{stok.terjemahan}</td>
                  <td class="py-2 pr-4 text-right font-medium text-gray-900">{stok.eks} eks</td>
                  <td class="py-2 text-right text-gray-600">{stok.dus} dus{stok.sisaEks > 0 ? ` + ${stok.sisaEks} eks` : ''}</td>
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
        <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 items-end">
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
            <span class="block text-gray-700 mb-1">Terjemahan <span class="text-gray-500">*</span>
</span>
            <select bind:value={entriTerjemahan} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Pilih varian...</option>
              <option value={1}>Ada terjemahan</option>
              <option value={0}>Tanpa terjemahan</option>
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Jumlah eks
</span>
            <input type="number" min="1" max="500" bind:value={entriJumlah} class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Catatan
</span>
            <input type="text" bind:value={entriCatatan} placeholder="mis. terima barang produksi" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          1 dus {namaJenis(entriJenis) || 'jenis ini'} = {kapasitasJenis(entriJenis) || 0} eks{#if entriJenis && entriJumlah} · {Number(entriJumlah) || 0} eks = {padananDus(entriJumlah, entriJenis)}{/if}
          {#if entriTipe === 'keluar'} · pengurangan hanya berlaku untuk varian yang dipilih{/if}
        </p>
        <button
          type="button"
          disabled={!entriGudang || !entriJenis || entriTerjemahan === '' || sibuk}
          on:click={() => kirimJson('/admin/warehouse/stok/entri', { gudang_id: entriGudang, jenis_quran_id: entriJenis, terjemahan: Number(entriTerjemahan), tipe: entriTipe, jumlah_eks: Number(entriJumlah) || 0, catatan: entriCatatan }, () => { router.reload({ only: ['stokList', 'pergerakanList'] }); })}
          class="mt-3 px-4 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-700 disabled:opacity-50"
        >{sibuk ? 'Merekam...' : 'Rekam Pergerakan'}</button>
      </section>
    {/if}

    {#if bolehTransfer}
      <section class="bg-white rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Dokumen Transfer Antar Gudang</h2>
        <p class="text-xs text-gray-500 mb-3">Dibuat dulu; stok berpindah saat SPV gudang TUJUAN menerima (barang fisik sudah tiba). Varian harus sama dari asal ke tujuan.</p>
        <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 items-end">
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
            <span class="block text-gray-700 mb-1">Terjemahan <span class="text-gray-500">*</span>
</span>
            <select bind:value={transferTerjemahan} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Pilih varian...</option>
              <option value={1}>Ada terjemahan</option>
              <option value={0}>Tanpa terjemahan</option>
            </select>
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Jumlah eks
</span>
            <input type="number" min="1" max="500" bind:value={transferJumlah} class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Catatan
</span>
            <input type="text" bind:value={transferCatatan} class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          {#if transferJenis && transferJumlah}{Number(transferJumlah) || 0} eks = {padananDus(transferJumlah, transferJenis)}{/if}
        </p>
        <button
          type="button"
          disabled={!transferAsal || !transferTujuan || !transferJenis || transferTerjemahan === '' || transferAsal === transferTujuan || sibuk}
          on:click={() => kirimJson('/admin/warehouse/stok/transfer', { gudang_asal_id: transferAsal, gudang_tujuan_id: transferTujuan, jenis_quran_id: transferJenis, terjemahan: Number(transferTerjemahan), jumlah_eks: Number(transferJumlah) || 0, catatan: transferCatatan }, () => { router.reload({ only: ['transferList'] }); })}
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
                <th class="py-2 pr-4">Terjemahan</th>
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
                  <td class="py-2 pr-4 text-gray-800">{transfer.terjemahan}</td>
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
            <span class="font-medium">{baris.gudang}</span> · {baris.jenis} · {baris.terjemahan} · {baris.tipe} · <span class="font-medium text-gray-900">{baris.jumlah_eks} eks</span>
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
