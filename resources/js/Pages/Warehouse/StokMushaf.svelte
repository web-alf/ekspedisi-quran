<script>
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import { gudangUntukEntri } from '../../utils/pilihanGudang.js';
  import { stokSaatIni, hitungHasil, bolehSimpan, labelVarian } from '../../utils/stokPratinjau.js';

  // Fase 3: stok mushaf tanpa muwakif per gudang. Dokumen transfer antar gudang sejak
  // 26/10/2026 hidup di halaman Perpindahan Mushaf (tab "Stok tanpa muwakif") — halaman ini
  // menyisakan isi stok, pencatatan pergerakan, dan tautan ke sana.
  // Temuan lapangan 19/10/2026: A5/A6 punya varian ber-terjemahan dan tanpa, jadi
  // setiap pergerakan stok WAJIB menyebut variannya (dua tumpukan terpisah).
  export let gudangList = [];
  export let jenisList = [];
  export let stokList = [];
  export let pergerakanList = [];
  export let bolehKelola = false;
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
  let hasilTerakhir = ''; // ringkasan sesudah simpan: "A5 tanpa terjemahan: 12 → 22 eks"

  // Isi stok dikelompokkan per gudang (26/10/2026, keputusan pemilik): tabel diganti kartu,
  // dan tiap gudang dapat judul + jumlah varian/eks. PJ yang memegang dua gudang langsung
  // melihat kelompoknya masing-masing; SPV hanya punya satu kelompok.
  $: kelompokStok = (() => {
    const perGudang = new Map();

    for (const baris of stokList) {
      const kunci = String(baris.gudang_id ?? baris.gudang);

      if (!perGudang.has(kunci)) {
        perGudang.set(kunci, { gudang: baris.gudang, eks: 0, baris: [] });
      }

      const grup = perGudang.get(kunci);
      grup.baris.push(baris);
      grup.eks += Number(baris.eks) || 0;
    }

    return [...perGudang.values()];
  })();

  /** Rincian dus dari angka yang sudah dihitung server ("2 dus + 10 eks"). */
  function rincianDus(baris) {
    const dus = Number(baris.dus) || 0;
    const sisa = Number(baris.sisaEks) || 0;

    if (dus === 0) {
      return `${sisa} eks`;
    }

    return sisa > 0 ? `${dus} dus + ${sisa} eks` : `${dus} dus utuh`;
  }

  // Pratinjau akibat pergerakan SEBELUM disimpan — satu-satunya cara mencegah pencatatan
  // pengurangan melebihi stok, yang sekarang baru ketahuan setelah server menolak (422).
  $: stokSekarang = stokSaatIni(stokList, {
      jenisId: entriJenis,
      terjemahan: entriTerjemahan,
      gudangId: tercakupGudang ? gudangSaya : entriGudang,
  });
  $: hasilEntri = hitungHasil(entriTipe, stokSekarang, Number(entriJumlah) || 0);
  $: alasanTolak = bolehSimpan({ tipe: entriTipe, gudang: entriGudang, jenis: entriJenis, terjemahan: entriTerjemahan, jumlah: entriJumlah }).alasan;
  $: stokKurang = entriTipe === 'keluar' && !hasilEntri.cukup;
  $: bolehRekam = !alasanTolak && !stokKurang && !sibuk;

  // SPV tercakup gudang: kolom gudang terkunci ke gudangnya dan opsi gudang lain tidak
  // pernah ditawarkan — server juga menolak (403), tapi menawarkan opsi yang pasti ditolak
  // membuat pengguna baru tahu setelah menekan Simpan.
  $: gudangEntri = gudangUntukEntri(gudangList, { gudangSaya, tercakupGudang });
  $: if (tercakupGudang && gudangSaya !== null && String(entriGudang) !== String(gudangSaya)) {
      entriGudang = String(gudangSaya);
  }

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
        {#each kelompokStok as grup, i (grup.gudang)}
          <div class={i > 0 ? 'mt-5 pt-4 border-t border-dashed border-gray-200' : ''}>
            <div class="flex items-baseline gap-2 flex-wrap mb-3">
              <p class="text-sm font-semibold text-gray-900">{grup.gudang}</p>
              <p class="text-xs text-gray-500">{grup.baris.length} varian · {grup.eks} eks</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {#each grup.baris as stok (stok.id)}
                <div class="border border-gray-200 rounded-xl p-4">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900 leading-snug">{stok.jenis}</p>
                    <span class="flex-none inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-gray-200 bg-gray-50 text-gray-600 whitespace-nowrap">{stok.terjemahan}</span>
                  </div>
                  <p class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold text-gray-900 leading-none">{stok.eks}</span>
                    <span class="text-xs text-gray-500">eks</span>
                  </p>
                  <p class="mt-1.5 text-xs text-gray-500">
                    <span class="font-semibold text-gray-700">{rincianDus(stok)}</span>
                    {#if kapasitasJenis(stok.jenis_id)}
                      · 1 dus = {kapasitasJenis(stok.jenis_id)} eks
                    {/if}
                  </p>
                </div>
              {/each}
            </div>
          </div>
        {/each}
      {:else}
        <p class="text-sm text-gray-500">Belum ada stok tercatat.</p>
      {/if}
    </section>

    {#if bolehKelola}
      <section class="bg-white rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Catat Pergerakan</h2>
        {#if tercakupGudang}
          <p class="text-xs text-gray-500 mb-4">
            Mencatat pergerakan stok <span class="font-medium text-gray-700">{gudangEntri[0]?.nama ?? 'gudang Anda'}</span> — gudang mengikuti akun Anda.
          </p>
        {:else}
          <p class="text-xs text-gray-500 mb-4">Pilih gudang tempat pergerakan ini terjadi.</p>
        {/if}

        <!-- Baris 1: apa yang terjadi -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
          <div class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg" role="tablist" aria-label="Tipe pergerakan">
            <button type="button" on:click={() => (entriTipe = 'masuk')}
              class="px-3 py-1.5 rounded-md text-sm font-medium {entriTipe === 'masuk' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}"
              aria-pressed={entriTipe === 'masuk'}>Masuk</button>
            <button type="button" on:click={() => (entriTipe = 'keluar')}
              class="px-3 py-1.5 rounded-md text-sm font-medium {entriTipe === 'keluar' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}"
              aria-pressed={entriTipe === 'keluar'}>Keluar</button>
          </div>

          {#if !tercakupGudang}
            <select bind:value={entriGudang} aria-label="Gudang"
              class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="">Pilih gudang...</option>
              {#each gudangList as gudang}
                <option value={gudang.id}>{gudang.nama}</option>
              {/each}
            </select>
          {/if}

          <p class="text-xs text-gray-500">
            {entriTipe === 'masuk'
              ? 'Stok bertambah dari barang yang masuk.'
              : 'Stok berkurang dan dicatat sebagai penyaluran ke muwakif.'}
          </p>
        </div>

        <!-- Baris 2: barang dan jumlah -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <label class="block">
            <span class="block text-sm text-gray-700 mb-1">Jenis <span class="text-[#eb3434]">*</span></span>
            <select bind:value={entriJenis} class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="">Pilih jenis...</option>
              {#each jenisList as jenis}
                <option value={jenis.id}>{jenis.kode_jenis}</option>
              {/each}
            </select>
          </label>

          <div>
            <span class="block text-sm text-gray-700 mb-1">Varian <span class="text-[#eb3434]">*</span></span>
            <div class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg" role="tablist" aria-label="Varian">
              {#each [[1, 'Dengan terjemahan'], [0, 'Tanpa terjemahan']] as [nilai, teks]}
                <button type="button" on:click={() => (entriTerjemahan = String(nilai))}
                  class="px-3 py-1.5 rounded-md text-sm font-medium {String(entriTerjemahan) === String(nilai) ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900'}"
                  aria-pressed={String(entriTerjemahan) === String(nilai)}>{teks}</button>
              {/each}
            </div>
          </div>

          <div>
            <label for="entri-jumlah" class="block text-sm text-gray-700 mb-1">Jumlah <span class="text-[#eb3434]">*</span></label>
            <div class="inline-flex items-stretch border border-gray-300 rounded-lg overflow-hidden">
              <button type="button" on:click={() => (entriJumlah = Math.max(1, (Number(entriJumlah) || 0) - 1))}
                class="w-9 bg-gray-50 text-gray-700 hover:bg-gray-100" aria-label="Kurangi jumlah">−</button>
              <input id="entri-jumlah" type="number" inputmode="numeric" min="1" max="500" bind:value={entriJumlah}
                class="w-20 text-center border-0 text-sm focus:outline-none" />
              <button type="button" on:click={() => (entriJumlah = (Number(entriJumlah) || 0) + 1)}
                class="w-9 bg-gray-50 text-gray-700 hover:bg-gray-100" aria-label="Tambah jumlah">+</button>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              1 dus {namaJenis(entriJenis) || 'jenis ini'} = {kapasitasJenis(entriJenis) || 0} eks{#if entriJenis && entriJumlah} · {Number(entriJumlah) || 0} eks = {padananDus(entriJumlah, entriJenis)}{/if}
            </p>
          </div>
        </div>

        <label for="entri-catatan" class="block mt-4">
          <span class="block text-sm text-gray-700 mb-1">Catatan <span class="text-gray-400">(opsional)</span></span>
          <input id="entri-catatan" type="text" bind:value={entriCatatan} placeholder="mis. terima barang produksi 21/10"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </label>

        <!-- Pratinjau akibat: stok sekarang -> sesudah -->
        <div class="mt-4 rounded-lg px-4 py-3 text-sm {stokKurang ? 'bg-red-50 border border-dashed border-red-200' : 'bg-gray-50 border border-dashed border-gray-300'}" aria-live="polite">
          {#if entriTerjemahan === '' || !entriJenis}
            <span class="text-gray-500">Pilih jenis dan varian untuk melihat stok sekarang.</span>
          {:else if stokKurang}
            <span class="text-red-700">
              Stok {namaJenis(entriJenis)} {labelVarian(entriTerjemahan).toLowerCase()} hanya <b>{stokSekarang} eks</b> — pengurangan {Number(entriJumlah) || 0} eks tidak cukup.
            </span>
          {:else}
            Stok {namaJenis(entriJenis)} {labelVarian(entriTerjemahan).toLowerCase()} saat ini <b>{stokSekarang} eks</b> → menjadi <b>{hasilEntri.akhir} eks</b>
          {/if}
        </div>

        {#if hasilTerakhir}
          <p class="mt-3 text-xs text-green-700" aria-live="polite">✓ {hasilTerakhir}</p>
        {/if}
        {#if alasanTolak && (entriJenis || entriTerjemahan !== '')}
          <p class="mt-2 text-xs text-gray-500">{alasanTolak}</p>
        {/if}

        <button
          type="button"
          disabled={!bolehRekam}
          on:click={() => {
            const ringkas = `${namaJenis(entriJenis)} ${labelVarian(entriTerjemahan).toLowerCase()}: ${stokSekarang} → ${hasilEntri.akhir} eks`;
            kirimJson('/admin/warehouse/stok/entri', { gudang_id: entriGudang, jenis_quran_id: entriJenis, terjemahan: Number(entriTerjemahan), tipe: entriTipe, jumlah_eks: Number(entriJumlah) || 0, catatan: entriCatatan }, () => {
              hasilTerakhir = ringkas;
              // Jenis & varian dipertahankan untuk entri berikutnya; hanya jumlah yang direset.
              entriJumlah = 1;
              entriCatatan = '';
              router.reload({ only: ['stokList', 'pergerakanList'] });
            });
          }}
          class="mt-4 w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {#if sibuk}
            Merekam...
          {:else if Number(entriJumlah) > 0}
            Catat {Number(entriJumlah)} eks {entriTipe}
          {:else}
            Catat pergerakan
          {/if}
        </button>
      </section>
    {/if}

    <section class="bg-white rounded-lg border border-gray-200 p-5">
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1 min-w-0">
          <h2 class="font-semibold text-gray-900">Dokumen Transfer Antar Gudang</h2>
          <p class="text-xs text-gray-500 mt-1">
            Form dan daftar dokumen transfer pindah ke halaman
            <span class="font-medium text-gray-700">Perpindahan Mushaf</span> (tab &quot;Stok tanpa muwakif&quot;),
            supaya kedua jenis dokumen perpindahan ada di satu tempat — dan dokumen yang menunggu diterima
            ikut muncul di kartu &quot;Perlu Tindakan&quot; dashboard.
          </p>
        </div>
        <a href="/admin/transfer-mushaf"
          class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-800 hover:bg-gray-50 flex-none">
          Buka Perpindahan Mushaf
        </a>
      </div>
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
