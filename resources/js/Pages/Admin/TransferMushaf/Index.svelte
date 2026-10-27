<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { jenisPunyaVarian, varianSesudahGantiJenis } from '../../../utils/stokPratinjau.js';

  // DUA DOKUMEN PERPINDAHAN ANTAR GUDANG DALAM SATU HALAMAN (keputusan pemilik 26/10/2026):
  //
  //  - Tab "Mushaf ber-resi" (LOKASI MUSHAF, 2026-10-15): yang dipindah adalah mushaf
  //    wakaf yang sudah punya resi, jadi satuannya RESI dan lokasinya berpindah saat
  //    diterima (pengiriman.gudang_id).
  //  - Tab "Stok tanpa muwakif" (Fase 3, dipindah dari halaman Stok Mushaf): satuannya
  //    EKS per jenis × varian; stok baru berpindah saat gudang TUJUAN menekan Terima.
  //    Dulu form + daftarnya menumpang di halaman Stok Mushaf sehingga dokumen stok yang
  //    menunggu diterima tidak pernah terlihat di halaman perpindahan maupun di kartu
  //    "Perlu Tindakan" dashboard.
  export let gudangList = [];
  export let transferList = [];
  export let bolehTransfer = false;
  export let gudangSaya = null;
  export let tercakupGudang = false;
  export let peranSuperAdmin = false;

  // Tab stok
  export let penggunaId = null;
  export let peranPjGudang = false;
  export let bolehTransferStok = false;
  export let jenisList = [];
  export let stokList = [];
  export let transferStokList = [];

  let pesan = '';
  let galat = '';
  let sibuk = false;

  // Form dokumen baru (mushaf ber-resi)
  let tujuan = '';
  let asalManual = '';
  let catatan = '';
  let kataKunci = '';
  let hasilCari = [];
  let terpilih = [];

  // Form dokumen baru (stok tanpa muwakif)
  let stokAsal = '';
  let stokTujuan = '';
  let stokJenis = '';
  let stokTerjemahan = '';
  let stokJumlah = 1;
  let stokCatatan = '';

  // IQRA tidak punya varian: pilihannya disembunyikan dan nilainya diisi otomatis.
  let stokJenisSebelumnya = '';
  $: if (String(stokJenis) !== String(stokJenisSebelumnya)) {
    stokTerjemahan = varianSesudahGantiJenis(jenisList, stokJenis, stokTerjemahan, stokJenisSebelumnya);
    stokJenisSebelumnya = stokJenis;
  }
  $: adaVarianStok = jenisPunyaVarian(jenisList, stokJenis);

  let tabAktif = 'mushaf';

  // Tab yang tersedia mengikuti izin: tab stok hanya untuk yang boleh membuat/menerima
  // dokumen transfer stok (`stok.transfer`).
  $: tabTersedia = [
    { kunci: 'mushaf', label: 'Mushaf ber-resi' },
    ...(bolehTransferStok ? [{ kunci: 'stok', label: 'Stok tanpa muwakif' }] : []),
  ];
  $: if (!tabTersedia.some((t) => t.kunci === tabAktif)) {
    tabAktif = tabTersedia[0]?.kunci ?? 'mushaf';
  }

  // SPV tercakup gudang: asal terkunci ke gudangnya (server juga menolak gudang lain).
  $: if (tercakupGudang && gudangSaya !== null && String(stokAsal) !== String(gudangSaya)) {
    stokAsal = String(gudangSaya);
  }

  const namaGudang = (id) => gudangList.find((g) => String(g.id) === String(id))?.nama || '-';

  function kapasitasJenis(id) {
    const jenis = jenisList.find((item) => String(item.id) === String(id));

    return jenis ? Number(jenis.default_capacity) : 0;
  }

  function namaJenis(id) {
    const jenis = jenisList.find((item) => String(item.id) === String(id));

    return jenis ? jenis.kode_jenis : '';
  }

  /** Padanan dus: 1 dus = kapasitas jenis (A5 20, A6 40, IQRO 160). */
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

  /** Sisa stok asal untuk jenis × varian yang dipilih — supaya dokumen tidak melebihi stok. */
  $: stokAsalId = tercakupGudang ? gudangSaya : stokAsal;
  $: sisaStokAsal = (() => {
    if (!stokAsalId || !stokJenis || stokTerjemahan === '') {
      return null;
    }

    const baris = stokList.find(
      (s) =>
        String(s.gudang_id) === String(stokAsalId) &&
        String(s.jenis_id) === String(stokJenis) &&
        Boolean(s.terjemahan) === (Number(stokTerjemahan) === 1),
    );

    return baris ? baris.eks : 0;
  })();

  function kirimJson(endpoint, body, sukses) {
    galat = '';
    pesan = '';
    sibuk = true;

    const meta = document.querySelector('meta[name="csrf-token"]');

    // `Accept: application/json` + X-Requested-With WAJIB: tanpa keduanya Laravel
    // menjawab galat validasi sebagai REDIRECT 302 ke HTML, lalu `res.json()` gagal dan
    // layar hanya menampilkan "Gagal menghubungi server." tanpa sebabnya.
    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': meta ? meta.content : '',
      },
      body: JSON.stringify(body),
    })
      .then(async (res) => {
        const teks = await res.text();
        let data = {};

        try {
          data = JSON.parse(teks);
        } catch {
          data = { success: false, message: `Gagal (kode ${res.status}).` };
        }

        if (data.success) {
          pesan = data.message;
          sukses?.();
        } else {
          galat = data.message || `Gagal (kode ${res.status}).`;
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

  function buatTransferStok() {
    const asal = tercakupGudang ? gudangSaya : stokAsal;

    if (!asal) { galat = 'Pilih gudang asal lebih dulu.'; return; }
    if (!stokTujuan) { galat = 'Pilih gudang tujuan lebih dulu.'; return; }
    if (!stokJenis) { galat = 'Pilih jenis mushaf lebih dulu.'; return; }
    if (stokTerjemahan === '') { galat = 'Pilih varian (dengan/tanpa terjemahan) lebih dulu.'; return; }

    kirimJson('/admin/warehouse/stok/transfer', {
      gudang_asal_id: Number(asal),
      gudang_tujuan_id: Number(stokTujuan),
      jenis_quran_id: Number(stokJenis),
      terjemahan: Number(stokTerjemahan),
      jumlah_eks: Number(stokJumlah) || 0,
      catatan: stokCatatan || null,
    }, () => {
      stokJumlah = 1;
      stokCatatan = '';
      router.reload({ only: ['transferStokList', 'stokList'] });
    });
  }

  function terimaTransferStok(dokumen) {
    kirimJson(`/admin/warehouse/stok/transfer/${dokumen.id}/terima`, {},
      () => router.reload({ only: ['transferStokList', 'stokList'] }));
  }

  function batalkanTransferStok(dokumen) {
    kirimJson(`/admin/warehouse/stok/transfer/${dokumen.id}/batalkan`, {},
      () => router.reload({ only: ['transferStokList'] }));
  }

  const bolehTerimaDokumen = (dokumen) =>
    dokumen.status === 'diproses' && (peranSuperAdmin || (gudangSaya && Number(gudangSaya) === Number(dokumen.tujuan_id)));

  const bolehBatalkanDokumen = (dokumen) =>
    dokumen.status === 'diproses' && (peranSuperAdmin || (gudangSaya && Number(gudangSaya) === Number(dokumen.asal_id)));

  // Aturan yang sama dengan server (StokMushafController::bolehTerima/bolehBatalkan):
  // menerima = gudang TUJUAN (atau PJ/super-admin), membatalkan = pembuat, gudang ASAL,
  // PJ, atau super-admin.
  const bolehTerimaStok = (dokumen) =>
    dokumen.status === 'diproses' &&
    (peranSuperAdmin || peranPjGudang || (gudangSaya && Number(gudangSaya) === Number(dokumen.tujuan_id)));

  const bolehBatalkanStok = (dokumen) =>
    dokumen.status === 'diproses' &&
    (peranSuperAdmin || peranPjGudang || Number(penggunaId) === Number(dokumen.dibuat_oleh_id) ||
      (gudangSaya && Number(gudangSaya) === Number(dokumen.asal_id)));

  const labelStatus = (status) =>
    ({ diproses: 'Menunggu diterima', diterima: 'Diterima', dibatalkan: 'Dibatalkan' }[status] || status);

  const labelStatusStok = (status) =>
    ({ diproses: 'Menunggu diterima', diterima: 'Diterima', dibatalkan: 'Dibatalkan' }[status] || status);
</script>

<AdminLayout>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Perpindahan Mushaf Antar Gudang</h1>
      <p class="text-sm text-gray-500 mt-1">
        Dua jenis dokumen, satu tempat: mushaf yang sudah ber-resi dipindahkan sebagai RESI, sedangkan
        stok tanpa muwakif dipindahkan sebagai EKS per jenis &amp; varian. Keduanya baru berpindah saat
        gudang tujuan menekan Terima — selama dokumen berjalan, barangnya masih tercatat di gudang asal.
      </p>
    </div>

    {#if tabTersedia.length > 1}
      <div class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg" role="tablist" aria-label="Jenis dokumen">
        {#each tabTersedia as t (t.kunci)}
          <button type="button" role="tab" aria-selected={tabAktif === t.kunci} on:click={() => (tabAktif = t.kunci)}
            class="px-3 py-1.5 rounded-md text-sm font-medium {tabAktif === t.kunci ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}">
            {t.label}
          </button>
        {/each}
      </div>
    {/if}

    {#if pesan}<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">{pesan}</div>{/if}
    {#if galat}<div class="rounded-lg border border-gray-200 bg-red-50 px-4 py-3 text-sm text-red-700">{galat}</div>{/if}

    {#if tabAktif === 'mushaf'}
      {#if bolehTransfer}
        <section class="bg-white rounded-lg border border-gray-200 p-5">
          <h2 class="font-semibold text-gray-900 mb-3">Dokumen Perpindahan Baru — Mushaf ber-resi</h2>

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
        <h2 class="font-semibold text-gray-900 mb-3">Dokumen Perpindahan — Mushaf ber-resi</h2>
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
    {:else}
      {#if bolehTransferStok}
        <section class="bg-white rounded-lg border border-gray-200 p-5">
          <h2 class="font-semibold text-gray-900 mb-1">Dokumen Transfer Stok Baru — tanpa muwakif</h2>
          <p class="text-xs text-gray-500 mb-3">
            {#if tercakupGudang}Dari <span class="font-medium text-gray-700">{namaGudang(gudangSaya)}</span>. {/if}Dibuat dulu; stok berpindah saat gudang TUJUAN menerima (barang fisik sudah tiba). Varian harus sama dari asal ke tujuan.
          </p>

          <div class="grid grid-cols-2 gap-3 items-end {tercakupGudang ? 'sm:grid-cols-5' : 'sm:grid-cols-6'}">
            {#if !tercakupGudang}
              <label class="block text-sm">
                <span class="block text-gray-700 mb-1">Dari</span>
                <select bind:value={stokAsal} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                  <option value="">Gudang asal...</option>
                  {#each gudangList as gudang}
                    <option value={gudang.id}>{gudang.nama}</option>
                  {/each}
                </select>
              </label>
            {/if}

            <label class="block text-sm">
              <span class="block text-gray-700 mb-1">Ke</span>
              <select bind:value={stokTujuan} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">Gudang tujuan...</option>
                {#each gudangList as gudang}
                  {#if String(gudang.id) !== String(stokAsalId)}
                    <option value={gudang.id}>{gudang.nama}</option>
                  {/if}
                {/each}
              </select>
            </label>

            <label class="block text-sm">
              <span class="block text-gray-700 mb-1">Jenis</span>
              <select bind:value={stokJenis} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">Jenis...</option>
                {#each jenisList as jenis}
                  <option value={jenis.id}>{jenis.nama_jenis}</option>
                {/each}
              </select>
            </label>

            {#if adaVarianStok}
              <label class="block text-sm">
                <span class="block text-gray-700 mb-1">Varian</span>
                <select bind:value={stokTerjemahan} class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                  <option value="">Pilih varian...</option>
                  <option value="1">Ada terjemahan</option>
                  <option value="0">Tanpa terjemahan</option>
                </select>
              </label>
            {/if}

            <label class="block text-sm">
              <span class="block text-gray-700 mb-1">Jumlah (eks)</span>
              <input type="number" min="1" max="500" bind:value={stokJumlah}
                class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </label>

            <label class="block text-sm {tercakupGudang ? 'col-span-2 sm:col-span-5' : 'col-span-2 sm:col-span-6'}">
              <span class="block text-gray-700 mb-1">Catatan</span>
              <input type="text" bind:value={stokCatatan} placeholder="mis. permintaan SPV Boyolali"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </label>
          </div>

          {#if stokJenis && Number(stokJumlah) > 0}
            <p class="text-xs text-gray-500 mt-2">
              {Number(stokJumlah) || 0} eks = {padananDus(stokJumlah, stokJenis)}
              {#if sisaStokAsal !== null}
                · sisa stok asal {sisaStokAsal} eks
                {#if Number(stokJumlah) > sisaStokAsal}<span class="text-red-600 font-medium"> — melebihi stok asal</span>{/if}
              {/if}
            </p>
          {/if}

          <button type="button" on:click={buatTransferStok}
            disabled={sibuk || !stokTujuan || !stokJenis || stokTerjemahan === '' || Number(stokJumlah) < 1 || (sisaStokAsal !== null && Number(stokJumlah) > sisaStokAsal)}
            class="mt-4 w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
            Buat Dokumen Transfer Stok
          </button>
        </section>
      {/if}

      <section class="bg-white rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Dokumen Transfer — Stok tanpa muwakif</h2>
        {#if transferStokList.length === 0}
          <p class="text-sm text-gray-500">Belum ada dokumen.</p>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 text-left text-gray-600">
                  <th class="py-2 pr-4">Kode</th>
                  <th class="py-2 pr-4">Asal → Tujuan</th>
                  <th class="py-2 pr-4">Jenis</th>
                  <th class="py-2 pr-4">Varian</th>
                  <th class="py-2 pr-4 text-right">Eks</th>
                  <th class="py-2 pr-4">Status</th>
                  <th class="py-2 pr-4">Dibuat</th>
                  <th class="py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {#each transferStokList as dokumen}
                  <tr class="border-b border-gray-100">
                    <td class="py-2 pr-4 font-medium text-gray-900">{dokumen.kode}</td>
                    <td class="py-2 pr-4 text-gray-700">{dokumen.asal} → {dokumen.tujuan}</td>
                    <td class="py-2 pr-4 text-gray-700">{dokumen.jenis}</td>
                    <td class="py-2 pr-4 text-gray-700">{dokumen.terjemahan}</td>
                    <td class="py-2 pr-4 text-right text-gray-900">{dokumen.jumlah_eks}</td>
                    <td class="py-2 pr-4">
                      <span class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                        {labelStatusStok(dokumen.status)}
                      </span>
                    </td>
                    <td class="py-2 pr-4 text-gray-600">
                      {dokumen.dibuat_oleh} · {dokumen.dibuat_pada}
                      {#if dokumen.catatan}<div class="text-xs text-gray-500">{dokumen.catatan}</div>{/if}
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                      {#if bolehTerimaStok(dokumen)}
                        <button type="button" on:click={() => terimaTransferStok(dokumen)} disabled={sibuk}
                          class="text-sm text-gray-700 underline hover:text-gray-900 mr-3">Terima</button>
                      {/if}
                      {#if bolehBatalkanStok(dokumen)}
                        <button type="button" on:click={() => batalkanTransferStok(dokumen)} disabled={sibuk}
                          class="text-sm text-gray-500 underline hover:text-gray-800">Batalkan</button>
                      {/if}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}
      </section>
    {/if}
  </div>
</AdminLayout>
