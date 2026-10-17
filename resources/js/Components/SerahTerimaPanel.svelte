<script>
  /**
   * Serah terima kurir — pindai TERAKHIR sebelum mushaf berpindah tangan.
   *
   *   - mode 'tujuan'       : di lokasi lembaga. Pindai kerdus (seluruh isinya) atau per
   *                           mushaf, isi nama orang yang menerima. Resi → "Tiba di Tujuan";
   *                           "Diterima" tetap dinyatakan distribusi.
   *   - mode 'tengah_jalan' : orang lain meminta mushaf dalam perjalanan. Pindai PER MUSHAF,
   *                           isi nama, HP, dan alamat penerima baru. Resinya dialihkan.
   *
   * Dua langkah: setiap pindai hanya MEMERIKSA (server tidak menulis apa pun) dan menambah
   * resi ke daftar; baru tombol "Serahkan" yang mencatat. Kurir bisa memindai beberapa kali
   * (mis. 2 mushaf) lalu mengisi satu formulir.
   *
   * Induknya memanggil `tambahKode(teks)` dari hasil pemindai/ketikan.
   */
  import axios from 'axios';
  import HeroIcon from './UI/HeroIcon.svelte';
  import DokumentasiUpload from './DokumentasiUpload.svelte';
  import { toast } from '../utils/notifications.js';

  export let mode = 'tujuan';

  let daftar = []; // resi yang akan diserahkan
  let kodeKerdus = []; // kerdus yang dipindai (mode tujuan)
  let pilihanTujuan = null; // { kodeKerdus, tujuan: [...] } bila satu kerdus berisi >1 tujuan
  let memeriksa = false;
  let menyimpan = false;

  let namaPenerima = '';
  let noHp = '';
  let alamat = '';
  let catatan = '';
  let latitude = null;
  let longitude = null;
  let statusLokasi = '';
  let unggahFoto;

  $: tengahJalan = mode === 'tengah_jalan';
  $: tujuanAktif = daftar[0]?.tujuan || null;
  $: bisaSimpan = daftar.length > 0 && namaPenerima.trim() && (!tengahJalan || (noHp.trim() && alamat.trim())) && !menyimpan;

  export function kosongkan() {
    daftar = [];
    kodeKerdus = [];
    pilihanTujuan = null;
    namaPenerima = '';
    noHp = '';
    alamat = '';
    catatan = '';
    unggahFoto?.clearAll();
  }

  /** Dipanggil induk dengan teks hasil pindai atau ketikan. */
  export async function tambahKode(teks) {
    const kode = (teks || '').trim();
    if (!kode || memeriksa) return;

    memeriksa = true;

    try {
      const { data } = await axios.post('/admin/kurir/serah-terima/periksa', { kode, mode });
      terimaHasilPeriksa(data);
    } catch (err) {
      toast.error(err.response?.data?.message || 'Gagal memeriksa hasil pindai.');
    } finally {
      memeriksa = false;
    }
  }

  function terimaHasilPeriksa(data) {
    const ditolak = data.resi.filter((r) => !r.boleh);

    if (data.jumlah_boleh === 0) {
      toast.error(data.message);
      return;
    }

    if (ditolak.length > 0) {
      toast.warning(`${ditolak.length} resi dilewati: ${ditolak[0].alasan}`);
    }

    // Satu kerdus bisa berisi resi untuk beberapa lembaga — kurir memilih di mana ia berada.
    if (data.kode_kerdus && data.tujuan.length > 1 && !tujuanAktif) {
      pilihanTujuan = { kodeKerdus: data.kode_kerdus, tujuan: data.tujuan, resi: data.resi };
      return;
    }

    const kunci = tujuanAktif || data.tujuan[0]?.kunci;
    const masuk = data.resi.filter((r) => r.boleh && (tengahJalan || r.tujuan === kunci));
    tambahKeDaftar(masuk, data.kode_kerdus);

    const beda = data.resi.filter((r) => r.boleh && !tengahJalan && r.tujuan !== kunci).length;
    if (beda > 0) {
      toast.warning(`${beda} resi untuk tujuan lain tidak ditambahkan — serahkan di tujuannya masing-masing.`);
    }
  }

  function pilihTujuan(t) {
    tambahKeDaftar(pilihanTujuan.resi.filter((r) => r.boleh && r.tujuan === t.kunci), pilihanTujuan.kodeKerdus);
    pilihanTujuan = null;
  }

  function tambahKeDaftar(resi, kode) {
    const sudah = new Set(daftar.map((r) => r.id));
    const baru = resi.filter((r) => !sudah.has(r.id));

    if (baru.length === 0) {
      toast.info('Resi ini sudah ada di daftar.');
      return;
    }

    daftar = [...daftar, ...baru];
    if (kode && !kodeKerdus.includes(kode)) kodeKerdus = [...kodeKerdus, kode];
    toast.success(`${baru.length} resi ditambahkan. Total ${daftar.length}.`);

    if (!latitude && !statusLokasi) ambilLokasi();
  }

  function keluarkan(id) {
    daftar = daftar.filter((r) => r.id !== id);
  }

  function ambilLokasi() {
    if (!navigator.geolocation) {
      statusLokasi = 'Perangkat tidak mendukung GPS.';
      return;
    }

    statusLokasi = 'Mengambil lokasi...';
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        latitude = pos.coords.latitude;
        longitude = pos.coords.longitude;
        statusLokasi = `Lokasi tercatat (±${Math.round(pos.coords.accuracy)} m).`;
      },
      () => {
        statusLokasi = 'Lokasi tidak bisa diambil (izin ditolak). Serah terima tetap bisa disimpan.';
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
    );
  }

  async function serahkan() {
    if (!bisaSimpan) return;

    menyimpan = true;

    try {
      const form = new FormData();
      form.append('mode', mode);
      daftar.forEach((r) => form.append('pengiriman_ids[]', String(r.id)));
      // Dicatat hanya bila tepat satu kerdus — beberapa kerdus tetap tercatat per resi.
      if (!tengahJalan && kodeKerdus.length === 1) form.append('kode_kerdus', kodeKerdus[0]);
      form.append('nama_penerima', namaPenerima.trim());
      if (noHp.trim()) form.append('no_hp_penerima', noHp.trim());
      if (tengahJalan) form.append('alamat_penerima', alamat.trim());
      if (catatan.trim()) form.append('catatan', catatan.trim());
      if (latitude !== null) form.append('latitude', String(latitude));
      if (longitude !== null) form.append('longitude', String(longitude));
      (unggahFoto?.getFiles() || []).forEach((f) => form.append('foto[]', f));

      const { data } = await axios.post('/admin/kurir/serah-terima', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      toast.success(data.message);
      kosongkan();
    } catch (err) {
      const galat = err.response?.data?.errors;
      toast.error(galat ? Object.values(galat)[0][0] : err.response?.data?.message || 'Gagal menyimpan serah terima.');
    } finally {
      menyimpan = false;
    }
  }
</script>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 space-y-4" data-testid="serah-terima">
  <div>
    <h3 class="text-sm font-semibold text-gray-900">
      {tengahJalan ? 'Serahkan di Tengah Jalan' : 'Serah Terima di Tujuan'}
    </h3>
    <p class="text-xs text-gray-500 mt-1">
      {#if tengahJalan}
        Pindai QR tiap mushaf yang diminta (bukan kerdus), lalu isi data penerimanya. Resi dialihkan ke
        penerima ini dan tidak lagi menjadi jatah tujuan awalnya.
      {:else}
        Pindai QR kerdus atau tiap mushaf yang diserahkan, lalu isi nama penerimanya. Status resi menjadi
        "Tiba di Tujuan"; distribusi yang menyatakan "Diterima".
      {/if}
    </p>
  </div>

  {#if memeriksa}
    <div class="flex items-center gap-2 text-sm text-gray-600">
      <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-[#eb3434]"></div>
      Memeriksa...
    </div>
  {/if}

  {#if pilihanTujuan}
    <div class="border border-gray-200 rounded-lg p-3">
      <p class="text-sm text-gray-900 mb-2">
        Kerdus <span class="font-mono">{pilihanTujuan.kodeKerdus}</span> berisi resi untuk {pilihanTujuan.tujuan.length} tujuan.
        Anda sedang di mana?
      </p>
      <div class="flex flex-col gap-2">
        {#each pilihanTujuan.tujuan as t (t.kunci)}
          <button
            type="button"
            on:click={() => pilihTujuan(t)}
            class="text-left px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-sm"
          >
            <span class="font-medium text-gray-900">{t.nama_lembaga || t.nama_penerima || 'Tanpa nama'}</span>
            <span class="text-gray-500"> · {t.jumlah_resi} resi</span>
            {#if t.alamat_tujuan}<span class="block text-xs text-gray-500 line-clamp-1">{t.alamat_tujuan}</span>{/if}
          </button>
        {/each}
      </div>
    </div>
  {/if}

  {#if daftar.length === 0}
    <p class="text-sm text-gray-500 border border-dashed border-gray-300 rounded-lg p-4 text-center">
      Belum ada {tengahJalan ? 'mushaf' : 'kerdus/mushaf'} yang dipindai.
    </p>
  {:else}
    <div>
      {#if !tengahJalan && daftar[0]}
        <p class="text-xs text-gray-500 mb-1">
          Tujuan: <span class="text-gray-900">{daftar[0].nama_lembaga || daftar[0].nama_penerima || '—'}</span>
        </p>
      {/if}
      <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg max-h-56 overflow-y-auto">
        {#each daftar as r (r.id)}
          <li class="px-3 py-2 flex items-center gap-2 text-sm">
            <span class="font-mono text-gray-900">{r.no_resi}</span>
            <span class="text-xs text-gray-500 truncate flex-1">{r.jenis || ''}{tengahJalan && r.nama_lembaga ? ` · ${r.nama_lembaga}` : ''}</span>
            <button type="button" on:click={() => keluarkan(r.id)} class="text-gray-400 hover:text-gray-600 p-1" aria-label="Keluarkan {r.no_resi}">
              <HeroIcon name="x-mark" class="w-4 h-4" />
            </button>
          </li>
        {/each}
      </ul>
      <p class="text-xs text-gray-500 mt-1">{daftar.length} resi · {daftar.reduce((n, r) => n + (r.jumlah_quran || 1), 0)} mushaf</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div class="sm:col-span-2">
        <label for="st-nama" class="block text-sm font-medium text-gray-700 mb-1">
          {tengahJalan ? 'Nama penerima baru' : 'Nama yang menerima di lokasi'} <span class="text-red-600">*</span>
        </label>
        <input id="st-nama" type="text" bind:value={namaPenerima} maxlength="255"
          class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm" />
      </div>
      <div>
        <label for="st-hp" class="block text-sm font-medium text-gray-700 mb-1">
          No. HP / WhatsApp {#if tengahJalan}<span class="text-red-600">*</span>{/if}
        </label>
        <input id="st-hp" type="tel" inputmode="tel" bind:value={noHp} maxlength="20"
          class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm" />
      </div>
      <div>
        <span class="block text-sm font-medium text-gray-700 mb-1">Lokasi GPS</span>
        <button type="button" on:click={ambilLokasi}
          class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm text-gray-700 border border-gray-300 hover:bg-gray-50">
          <HeroIcon name="map-pin" class="w-4 h-4" />
          {latitude ? 'Ambil ulang lokasi' : 'Ambil lokasi'}
        </button>
        {#if statusLokasi}<p class="text-xs text-gray-500 mt-1">{statusLokasi}</p>{/if}
      </div>
      {#if tengahJalan}
        <div class="sm:col-span-2">
          <label for="st-alamat" class="block text-sm font-medium text-gray-700 mb-1">Alamat penerima <span class="text-red-600">*</span></label>
          <textarea id="st-alamat" rows="2" bind:value={alamat} maxlength="1000"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"></textarea>
        </div>
      {/if}
      <div class="sm:col-span-2">
        <label for="st-catatan" class="block text-sm font-medium text-gray-700 mb-1">Catatan (opsional)</label>
        <input id="st-catatan" type="text" bind:value={catatan} maxlength="500"
          placeholder={tengahJalan ? 'Mis. diminta takmir masjid di perjalanan' : 'Mis. diterima pengurus TPQ'}
          class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm" />
      </div>
      <div class="sm:col-span-2">
        <DokumentasiUpload bind:this={unggahFoto} label="Foto serah terima (opsional)" maxPhotos={5} defaultMode="camera" />
      </div>
    </div>

    <div class="flex gap-2">
      <button type="button" on:click={serahkan} disabled={!bisaSimpan}
        class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        {menyimpan ? 'Menyimpan...' : `Serahkan ${daftar.length} resi`}
      </button>
      <button type="button" on:click={kosongkan} disabled={menyimpan}
        class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 disabled:opacity-50">
        Batal
      </button>
    </div>
  {/if}
</div>
