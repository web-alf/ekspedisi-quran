<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';

  // LOKASI MUSHAF (2026-10-15): memindahkan MUSHAAF BER-RESI antar gudang.
  // Bedanya dari Stok Mushaf: yang dipindah di sini mushaf wakaf yang sudah punya resi &
  // tujuan kirim, jadi satuannya RESI — bukan eks tanpa muwakif.
  export let gudangList = [];
  export let transferList = [];
  export let bolehTransfer = false;
  export let gudangSaya = null;
  export let tercakupGudang = false;
  export let peranSuperAdmin = false;

  let pesan = '';
  let galat = '';
  let sibuk = false;

  // Form dokumen baru
  let tujuan = '';
  let asalManual = '';
  let catatan = '';
  let kataKunci = '';
  let hasilCari = [];
  let terpilih = [];

  const namaGudang = (id) => gudangList.find((g) => String(g.id) === String(id))?.nama || '-';

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

  function cari() {
    if (kataKunci.trim().length < 3) {
      galat = 'Ketik minimal 3 huruf (nomor resi, nama lembaga, atau nama penerima).';

      return;
    }

    galat = '';
    fetch(`/admin/transfer-mushaf/cari?q=${encodeURIComponent(kataKunci.trim())}`, {
      headers: { Accept: 'application/json' },
    })
      .then((res) => res.json())
      .then((data) => { hasilCari = data.data || []; })
      .catch(() => { galat = 'Gagal mencari resi.'; });
  }

  function pilih(resi) {
    if (!resi.boleh || terpilih.some((r) => r.id === resi.id)) return;
    terpilih = [...terpilih, resi];
  }

  function buang(id) {
    terpilih = terpilih.filter((r) => r.id !== id);
  }

  function buat() {
    if (!tujuan) { galat = 'Pilih gudang tujuan lebih dulu.'; return; }
    if (terpilih.length === 0) { galat = 'Pilih minimal satu resi.'; return; }

    kirimJson('/admin/transfer-mushaf', {
      gudang_tujuan_id: Number(tujuan),
      gudang_asal_id: asalManual ? Number(asalManual) : undefined,
      pengiriman_ids: terpilih.map((r) => r.id),
      catatan: catatan || null,
    }, () => {
      terpilih = [];
      hasilCari = [];
      kataKunci = '';
      catatan = '';
      router.reload({ only: ['transferList'] });
    });
  }

  function terima(dokumen) {
    kirimJson(`/admin/transfer-mushaf/${dokumen.id}/terima`, {}, () => router.reload({ only: ['transferList'] }));
  }

  function batalkan(dokumen) {
    kirimJson(`/admin/transfer-mushaf/${dokumen.id}/batalkan`, {}, () => router.reload({ only: ['transferList'] }));
  }

  const bolehTerimaDokumen = (dokumen) =>
    dokumen.status === 'diproses' && (peranSuperAdmin || (gudangSaya && Number(gudangSaya) === Number(dokumen.tujuan_id)));

  const bolehBatalkanDokumen = (dokumen) =>
    dokumen.status === 'diproses' && (peranSuperAdmin || (gudangSaya && Number(gudangSaya) === Number(dokumen.asal_id)));

  const labelStatus = (status) =>
    ({ diproses: 'Menunggu diterima', diterima: 'Diterima', dibatalkan: 'Dibatalkan' }[status] || status);
</script>

<AdminLayout>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Perpindahan Mushaf Antar Gudang</h1>
      <p class="text-sm text-gray-500 mt-1">
        Mushaf yang sudah punya resi (wakaf) dipindahkan sebagai RESI, bukan sebagai eks.
        Lokasi mushaf baru berpindah saat gudang tujuan menekan Terima — selama dokumen masih
        berjalan, mushafnya masih tercatat di gudang asal.
      </p>
    </div>

    {#if pesan}<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">{pesan}</div>{/if}
    {#if galat}<div class="rounded-lg border border-gray-200 bg-red-50 px-4 py-3 text-sm text-red-700">{galat}</div>{/if}

    {#if bolehTransfer}
      <section class="bg-white rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Dokumen Perpindahan Baru</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Dari gudang</span>
            {#if gudangSaya}
              <input type="text" value={namaGudang(gudangSaya)} disabled
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600" />
            {:else}
              <select bind:value={asalManual} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">Pilih gudang asal...</option>
                {#each gudangList as gudang}
                  <option value={gudang.id}>{gudang.nama}</option>
                {/each}
              </select>
            {/if}
          </label>

          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Ke gudang</span>
            <select bind:value={tujuan} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
              <option value="">Pilih gudang tujuan...</option>
              {#each gudangList as gudang}
                {#if String(gudang.id) !== String(gudangSaya)}
                  <option value={gudang.id}>{gudang.nama}</option>
                {/if}
              {/each}
            </select>
          </label>

          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Catatan</span>
            <input type="text" bind:value={catatan} placeholder="mis. permintaan SPV Boyolali"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </label>
        </div>

        <div class="mt-4">
          <label class="block text-sm">
            <span class="block text-gray-700 mb-1">Cari mushaf (nomor resi / lembaga / penerima)</span>
            <div class="flex gap-2">
              <input type="text" bind:value={kataKunci} on:keydown={(e) => e.key === 'Enter' && cari()}
                placeholder="mis. EQ-2026-21529"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg" />
              <button type="button" on:click={cari}
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                <HeroIcon name="magnifying-glass" class="w-4 h-4" />
                Cari
              </button>
            </div>
          </label>

          {#if hasilCari.length > 0}
            <div class="mt-3 overflow-x-auto border border-gray-200 rounded-lg">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200 text-left text-gray-600 bg-gray-50">
                    <th class="py-2 px-3">No. Resi</th>
                    <th class="py-2 px-3">Jenis</th>
                    <th class="py-2 px-3 text-right">Eks</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3">Gudang sekarang</th>
                    <th class="py-2 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {#each hasilCari as resi}
                    <tr class="border-b border-gray-100">
                      <td class="py-2 px-3 font-medium text-gray-900">{resi.no_resi}</td>
                      <td class="py-2 px-3 text-gray-700">{resi.jenis || '-'}</td>
                      <td class="py-2 px-3 text-right text-gray-700">{resi.eks}</td>
                      <td class="py-2 px-3 text-gray-700">{resi.status || '-'}</td>
                      <td class="py-2 px-3 text-gray-700">{resi.gudang || 'Belum diketahui'}</td>
                      <td class="py-2 px-3 text-right">
                        {#if resi.boleh}
                          <button type="button" on:click={() => pilih(resi)}
                            class="text-sm text-gray-700 underline hover:text-gray-900">Tambahkan</button>
                        {:else}
                          <span class="text-xs text-gray-500">{resi.alasan}</span>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {:else if kataKunci && !galat}
            <p class="text-sm text-gray-500 mt-3">Belum ada hasil — tekan Cari.</p>
          {/if}
        </div>

        {#if terpilih.length > 0}
          <div class="mt-4">
            <p class="text-sm text-gray-700 mb-2">
              {terpilih.length} resi dipilih · {terpilih.reduce((a, r) => a + Number(r.eks), 0)} eks
            </p>
            <div class="flex flex-wrap gap-2">
              {#each terpilih as resi}
                <span class="inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700 border border-gray-200">
                  {resi.no_resi}
                  <button type="button" on:click={() => buang(resi.id)} class="text-gray-500 hover:text-gray-800">
                    <HeroIcon name="x-mark" class="w-3 h-3" />
                  </button>
                </span>
              {/each}
            </div>
            <button type="button" on:click={buat} disabled={sibuk}
              class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-gray-800 disabled:opacity-50">
              <HeroIcon name="arrow-right" class="w-4 h-4" />
              Buat dokumen perpindahan
            </button>
          </div>
        {/if}
      </section>
    {/if}

    <section class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="font-semibold text-gray-900 mb-3">Dokumen Perpindahan</h2>
      {#if transferList.length > 0}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 text-left text-gray-600">
                <th class="py-2 pr-4">Kode</th>
                <th class="py-2 pr-4">Asal → Tujuan</th>
                <th class="py-2 pr-4 text-right">Resi</th>
                <th class="py-2 pr-4 text-right">Eks</th>
                <th class="py-2 pr-4">Status</th>
                <th class="py-2 pr-4">Dibuat</th>
                <th class="py-2 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {#each transferList as dokumen}
                <tr class="border-b border-gray-100">
                  <td class="py-2 pr-4 font-medium text-gray-900">{dokumen.kode}</td>
                  <td class="py-2 pr-4 text-gray-700">{dokumen.asal} → {dokumen.tujuan}</td>
                  <td class="py-2 pr-4 text-right text-gray-700">{dokumen.jumlah_resi}</td>
                  <td class="py-2 pr-4 text-right text-gray-700">{dokumen.jumlah_eks}</td>
                  <td class="py-2 pr-4">
                    <span class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                      {labelStatus(dokumen.status)}
                    </span>
                  </td>
                  <td class="py-2 pr-4 text-gray-600">
                    {dokumen.dibuat_oleh} · {dokumen.dibuat_pada}
                    {#if dokumen.diterima_oleh}
                      <div class="text-xs text-gray-500">Diterima {dokumen.diterima_oleh} · {dokumen.diterima_pada}</div>
                    {/if}
                  </td>
                  <td class="py-2 text-right whitespace-nowrap">
                    {#if bolehTerimaDokumen(dokumen)}
                      <button type="button" on:click={() => terima(dokumen)} disabled={sibuk}
                        class="text-sm text-gray-700 underline hover:text-gray-900 mr-3">Terima</button>
                    {/if}
                    {#if bolehBatalkanDokumen(dokumen)}
                      <button type="button" on:click={() => batalkan(dokumen)} disabled={sibuk}
                        class="text-sm text-gray-500 underline hover:text-gray-800">Batalkan</button>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {:else}
        <p class="text-sm text-gray-500">Belum ada dokumen perpindahan.</p>
      {/if}
    </section>
  </div>
</AdminLayout>
