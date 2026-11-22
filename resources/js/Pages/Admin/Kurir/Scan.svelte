<script>
  /**
   * Pindai barang untuk kurir — PER-PCS (no_resi) maupun PER-BOX (kode_kerdus).
   *
   * Dua mode ini memang berbeda artinya:
   *   - PCS: satu QR = satu resi = satu mushaf. Dipakai untuk memastikan satu
   *     barang tertentu benar-benar ikut.
   *   - BOX: satu QR = satu kerdus, yang memuat SEISI kerdus sekaligus (A5 bisa
   *     20 mushaf, A6 40, Iqra sampai 160 eks). Dipakai saat memuat barang curah
   *     supaya tidak perlu memindai satu per satu.
   *
   * Kurir boleh memuat barang ke muatannya sendiri. Menyelesaikan distribusi
   * BUKAN di sini — itu wewenang role distribusi (lihat halaman detail muatan).
   *
   * Dua mode serah terima (SerahTerimaPanel): pindai TERAKHIR di lokasi tujuan
   * (resi → "Tiba di Tujuan"), dan penyerahan di tengah jalan (resi dialihkan ke
   * penerima baru). Keduanya tidak memakai muatan tujuan — resinya harus sudah
   * ada di muatan kurir ini.
   */
  import { page } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import LazyQRScanner from '../../../Components/LazyQRScanner.svelte';
  import SerahTerimaPanel from '../../../Components/SerahTerimaPanel.svelte';
  import { toast } from '../../../utils/notifications.js';
  import axios from 'axios';

  export let muatanHariIni = [];
  export let muatanLain = [];

  let muatanTerpilih = muatanHariIni.length > 0 ? muatanHariIni[0].id : '';
  // Mode awal boleh datang dari ?mode= (mis. tombol "Serah terima" di beranda kurir
  // membuka langsung mode 'tujuan'). Nilai di luar daftar diabaikan.
  const MODE_SAH = ['pcs', 'box', 'status', 'tujuan', 'tengah_jalan'];
  const modeDariUrl = new URL($page.url, window.location.origin).searchParams.get('mode');
  // Bawaan per kerdus (desain kurir 22/11/2026): kurir lebih sering memuat kerdus utuh.
  let mode = MODE_SAH.includes(modeDariUrl) ? modeDariUrl : 'box'; // 'pcs' | 'box' | 'status' | 'tujuan' | 'tengah_jalan'
  let panelSerahTerima;
  $: modeSerahTerima = mode === 'tujuan' || mode === 'tengah_jalan';
  let memindai = false; // mengunci agar satu QR tidak terkirim berkali-kali
  let inputManual = '';
  let inputEl;

  // Lima mode dikelompokkan jadi tiga tab: Muat barang, Serah terima, Ubah status.
  const GRUP_MODE = { box: 'muat', pcs: 'muat', tujuan: 'serah', tengah_jalan: 'serah', status: 'status' };
  const TAB = [
    { id: 'muat', label: 'Muat barang', awal: 'box' },
    { id: 'serah', label: 'Serah terima', awal: 'tujuan' },
    { id: 'status', label: 'Ubah status', awal: 'status' },
  ];
  const SUB_MODE = {
    muat: [{ id: 'box', label: 'Per kerdus' }, { id: 'pcs', label: 'Per mushaf' }],
    serah: [{ id: 'tujuan', label: 'Di tujuan' }, { id: 'tengah_jalan', label: 'Tengah jalan' }],
    status: [],
  };
  const ARAHAN = {
    box: 'Arahkan ke QR di sisi kerdus',
    pcs: 'Arahkan ke QR resi di mushaf',
    tujuan: 'Pindai kerdus atau mushaf yang diserahkan',
    tengah_jalan: 'Pindai QR tiap mushaf yang diminta',
    status: 'Pindai QR resi',
  };
  $: grup = GRUP_MODE[mode];

  /** Laptop (lg ke atas) memakai scanner USB/Bluetooth; HP memakai kamera. */
  function layarLebar() {
    try {
      return window.matchMedia('(min-width: 1024px)').matches;
    } catch {
      return false;
    }
  }

  // Di HP kamera langsung menyala; di laptop kolom kode yang langsung aktif.
  let kameraAktif = !layarLebar();

  onMount(() => {
    if (layarLebar()) inputEl?.focus();
  });

  // Umpan balik yang terasa di jalan: kilat di area pindai dan getar singkat.
  let kilat = null; // 'ok' | 'gagal' | null
  let kilatTimer;
  function beriUmpanBalik(berhasil) {
    kilat = berhasil ? 'ok' : 'gagal';
    clearTimeout(kilatTimer);
    kilatTimer = setTimeout(() => (kilat = null), 300);
    try {
      navigator.vibrate?.(berhasil ? 60 : [80, 60, 80]);
    } catch {
      // Getar tidak didukung — kilat saja sudah cukup.
    }
  }

  /** @returns {'ok'|'info'|'gagal'} */
  function nadaRiwayat(r) {
    if (!r.success) return 'gagal';
    return r.tercatat === false ? 'info' : 'ok';
  }

  function kodeRiwayat(r) {
    return r.data?.kode_kerdus || r.data?.no_resi || '';
  }

  let hasilTerakhir = null;
  let riwayat = [];
  let totalResi = muatanHariIni.length > 0 ? muatanHariIni[0].total_resi : 0;
  let totalMushaf = muatanHariIni.length > 0 ? muatanHariIni[0].total_mushaf : 0;

  // --- Mode "Ubah Status" ---
  //
  // Sama artinya dengan tab "Scan QR" di halaman Pengiriman: satu pindai =
  // satu perubahan status pengiriman. Bedanya moda pcs/box: mode itu MEMUAT
  // barang ke muatan dan sama sekali tidak mengubah status.
  let infoResi = null;
  let statusTerpilih = '';
  let statusTersedia = [];
  let catatanStatus = '';
  let lokasiStatus = '';
  let memuatInfo = false;
  let menyimpanStatus = false;

  function pilihMuatan(e) {
    muatanTerpilih = e.target.value;
    const semua = [...muatanHariIni, ...muatanLain];
    const m = semua.find((x) => String(x.id) === String(muatanTerpilih));
    totalResi = m ? m.total_resi : 0;
    totalMushaf = m ? m.total_mushaf : 0;
  }

  function bersihkanStatus() {
    infoResi = null;
    statusTerpilih = '';
    statusTersedia = [];
    catatanStatus = '';
    lokasiStatus = '';
  }

  /**
   * Nomor resi dari teks QR.
   *
   * QR bisa berisi JSON (hasil generate sistem), URL tracking, atau resi polos —
   * ketiganya harus diterima supaya kurir tidak perlu tahu bentuk QR-nya.
   */
  function ambilNoResi(teks) {
    try {
      const data = JSON.parse(teks);
      if (data.no_resi) return data.no_resi;
      if (data.type === 'ekspedisi_quran' && data.no_resi) return data.no_resi;
    } catch {
      // bukan JSON — lanjut ke pola teks
    }

    const langsung = teks.match(/EQ-\d{4}-\d{5}/);
    if (langsung) return langsung[0];

    const dariUrl = teks.match(/tracking\/([A-Z]{2}-\d{4}-\d{5})/);
    if (dariUrl) return dariUrl[1];

    return null;
  }

  async function muatInfoResi(noResi) {
    memuatInfo = true;
    bersihkanStatus();

    try {
      const { data } = await axios.get(`/admin/pengiriman-status/${noResi}`, {
        headers: { Accept: 'application/json' },
      });

      if (!data.success) {
        toast.error(data.message || 'Resi tidak ditemukan.');
        return;
      }

      infoResi = data.pengiriman;
      statusTersedia = data.validStatuses || [];
      statusTerpilih = statusTersedia.length > 0 ? statusTersedia[0].id : '';

      if (statusTersedia.length === 0) {
        toast.info('Tidak ada status lanjutan yang bisa dipilih untuk resi ini.');
      }
    } catch (err) {
      toast.error(err.response?.data?.message || 'Gagal mengambil data resi.');
    } finally {
      memuatInfo = false;
    }
  }

  async function simpanStatus() {
    if (!infoResi || !statusTerpilih || menyimpanStatus) return;

    menyimpanStatus = true;

    try {
      const formData = new FormData();
      formData.append('no_resi', infoResi.no_resi);
      formData.append('status_id', String(statusTerpilih));
      formData.append('catatan', catatanStatus || '');
      formData.append('lokasi', lokasiStatus || '');

      const { data } = await axios.post('/admin/pengiriman/scan-status/update', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      if (data.success) {
        const pesan = data.message || 'Status berhasil diubah.';
        toast.success(pesan);
        const entri = {
          success: true,
          jenis: 'status',
          message: pesan,
          data: data.data,
          waktu: new Date().toLocaleTimeString('id-ID'),
        };
        hasilTerakhir = entri;
        riwayat = [entri, ...riwayat].slice(0, 25);
        bersihkanStatus();
      } else {
        toast.error(data.message || 'Gagal mengubah status.');
      }
    } catch (err) {
      const pesan = err.response?.data?.message || 'Gagal mengubah status.';
      toast.error(pesan);
      hasilTerakhir = {
        success: false,
        jenis: 'status',
        message: pesan,
        data: null,
        waktu: new Date().toLocaleTimeString('id-ID'),
      };
      riwayat = [hasilTerakhir, ...riwayat].slice(0, 25);
    } finally {
      menyimpanStatus = false;
    }
  }

  async function kirimPindai(kode) {
    if (!kode || !kode.trim() || memindai) return;

    memindai = true;

    try {
      const url = mode === 'box' ? '/admin/kurir/pindai/box' : '/admin/kurir/pindai/pcs';
      const { data } = await axios.post(url, {
        kode: kode.trim(),
        muatan_id: muatanTerpilih || null,
      });

      hasilTerakhir = { ...data, waktu: new Date().toLocaleTimeString('id-ID') };
      beriUmpanBalik(data.success);

      if (data.success && data.tercatat) {
        toast.success(data.message);
        if (data.total_resi !== undefined) totalResi = data.total_resi;
        if (data.total_mushaf !== undefined) totalMushaf = data.total_mushaf;
      } else if (data.success) {
        // Sah tapi belum ada muatan aktif — bukan kegagalan, hanya tidak dicatat.
        toast.info(data.message);
      } else {
        toast.error(data.message);
      }

      riwayat = [hasilTerakhir, ...riwayat].slice(0, 25);
      inputManual = '';
    } catch (err) {
      const pesan = err.response?.data?.message || 'Gagal memindai.';
      hasilTerakhir = {
        success: false,
        jenis: mode,
        message: pesan,
        data: err.response?.data?.data || null,
        waktu: new Date().toLocaleTimeString('id-ID'),
      };
      riwayat = [hasilTerakhir, ...riwayat].slice(0, 25);
      beriUmpanBalik(false);
      toast.error(pesan);
    } finally {
      memindai = false;
      // Scanner USB mengetik ke kolom yang sedang fokus — kembalikan fokus tanpa perlu klik.
      if (layarLebar()) inputEl?.focus();
    }
  }

  function onScanSuccess(e) {
    const teks = e.detail?.text || '';
    if (!teks.trim()) return;

    if (modeSerahTerima) {
      panelSerahTerima?.tambahKode(teks);
      return;
    }

    if (mode === 'status') {
      const noResi = ambilNoResi(teks);
      if (!noResi) {
        toast.error('QR ini tidak memuat nomor resi.');
        return;
      }
      muatInfoResi(noResi);
      return;
    }

    if (memindai) return;
    kirimPindai(teks);
  }

  /**
   * Kode yang diketik manual. Diperlakukan sama dengan hasil pindai, supaya
   * jalur cadangan (kamera tidak bisa dipakai) tetap bisa mengubah status.
   */
  function kirimManual() {
    const kode = inputManual.trim();
    if (!kode) return;

    if (modeSerahTerima) {
      inputManual = '';
      panelSerahTerima?.tambahKode(kode);
      return;
    }

    if (mode === 'status') {
      const noResi = ambilNoResi(kode);
      if (!noResi) {
        toast.error('Masukkan nomor resi yang benar, mis. EQ-2025-00001.');
        return;
      }
      inputManual = '';
      muatInfoResi(noResi);
      return;
    }

    kirimPindai(kode);
  }

  function gantiMode(m) {
    mode = m;
    hasilTerakhir = null;
    bersihkanStatus();
    inputManual = '';
  }

  function gantiTab(tab) {
    if (GRUP_MODE[mode] !== tab.id) gantiMode(tab.awal);
  }
</script>

<svelte:head>
  <title>Scan Barang - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <!-- Tanpa pembungkus px-6 sendiri: AdminLayout sudah memberi padding. Dulu padding
       dobel (48px kiri-kanan) membuat halaman ini sempit sekali di HP kurir. -->
  <div class="mb-4">
    <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Pindai</h1>
    <p class="text-sm text-gray-500">Muat barang, serah terima di lokasi, atau ubah status perjalanan resi.</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 items-start">
    <!-- Kiri: mode & pemindai -->
    <div class="lg:col-span-2 space-y-4">
      <div class="space-y-2">
        <div class="grid grid-cols-3 gap-1 p-1 bg-gray-100 rounded-lg" role="tablist" aria-label="Jenis pekerjaan">
          {#each TAB as tab (tab.id)}
            <button
              type="button"
              role="tab"
              aria-selected={grup === tab.id}
              on:click={() => gantiTab(tab)}
              class="py-2 px-1 rounded-md text-sm font-semibold transition-colors
                {grup === tab.id ? 'bg-white text-[#eb3434] shadow-sm' : 'text-gray-500 hover:text-gray-700'}"
            >
              {tab.label}
            </button>
          {/each}
        </div>

        {#if SUB_MODE[grup].length > 0}
          <div class="grid grid-cols-2 gap-2" role="group" aria-label="Cara pindai">
            {#each SUB_MODE[grup] as sub (sub.id)}
              <button
                type="button"
                aria-pressed={mode === sub.id}
                on:click={() => gantiMode(sub.id)}
                class="py-2 rounded-lg border text-sm font-semibold transition-colors
                  {mode === sub.id ? 'border-[#eb3434] bg-red-50 text-[#eb3434]' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'}"
              >
                {sub.label}
              </button>
            {/each}
          </div>
        {/if}

        <!--
          Penjelasan per mode. Mode muat MEMUAT barang ke muatan; mode status MENGUBAH
          STATUS. Membedakannya secara eksplisit penting: keliru memilih berarti status
          resi berubah tanpa disengaja.
        -->
        <p class="text-xs text-gray-500">
          {#if mode === 'status'}
            Satu pindai = satu perubahan status perjalanan resi, sesuai aturan perjalanan dan wewenang Anda.
          {:else if mode === 'tujuan'}
            Pindai terakhir di lokasi penerima: kerdus (seluruh isinya) atau per mushaf. Status resi menjadi "Tiba di Tujuan".
          {:else if mode === 'tengah_jalan'}
            Ada yang meminta mushaf dalam perjalanan? Pindai QR tiap mushaf, lalu isi data penerimanya. Resi dialihkan ke penerima itu.
          {:else if mode === 'box'}
            Satu pindai = seisi kerdus dimuat ke muatan. Status resi tidak berubah.
          {:else}
            Satu pindai = satu resi dimuat ke muatan. Status resi tidak berubah.
          {/if}
        </p>
      </div>

      <!-- Pemindai -->
      <div class="relative bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3 overflow-hidden">
        <div class="flex items-center justify-between gap-2">
          <p class="text-sm font-semibold text-gray-900">{ARAHAN[mode]}</p>
          <button
            type="button"
            on:click={() => (kameraAktif = !kameraAktif)}
            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors
              {kameraAktif ? 'text-gray-700 border border-gray-300 hover:bg-gray-50' : 'text-white bg-[#eb3434] hover:bg-red-600'}"
          >
            <HeroIcon name="camera" class="w-4 h-4" />
            {kameraAktif ? 'Matikan kamera' : 'Nyalakan kamera'}
          </button>
        </div>

        {#if kameraAktif}
          <div class="bg-gray-100 rounded-lg overflow-hidden">
            <LazyQRScanner
              width={400}
              height={300}
              facingMode="environment"
              showToggleCamera={true}
              on:scanSuccess={onScanSuccess}
            />
          </div>
        {/if}

        <div class="flex gap-2">
          <input
            id="input-manual"
            type="text"
            bind:this={inputEl}
            bind:value={inputManual}
            on:keydown={(e) => e.key === 'Enter' && kirimManual()}
            aria-label={mode === 'status' || mode === 'tengah_jalan' ? 'Ketik nomor resi' : 'Ketik kode manual'}
            placeholder={mode === 'box' ? 'Ketik kode KB-…' : mode === 'tujuan' ? 'Ketik KB-… atau EQ-…' : 'Ketik nomor resi EQ-…'}
            autocomplete="off"
            class="flex-1 min-w-0 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
          />
          <button
            on:click={kirimManual}
            disabled={(mode === 'status' ? memuatInfo : memindai) || !inputManual.trim()}
            class="px-4 py-2.5 rounded-lg text-sm font-semibold text-[#eb3434] bg-red-50 border border-red-200 hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
          >
            {mode === 'status' ? (memuatInfo ? '...' : 'Cari') : modeSerahTerima ? 'Tambah' : (memindai ? '...' : 'Masukkan')}
          </button>
        </div>
        <p class="hidden lg:block text-xs text-gray-500">
          Pakai scanner USB/Bluetooth: kolom ini aktif terus dan kursor kembali ke sini setelah setiap pindaian.
        </p>

        {#if kilat}
          <div
            class="absolute inset-0 pointer-events-none {kilat === 'ok' ? 'bg-green-500/25' : 'bg-red-500/25'}"
            aria-hidden="true"
          ></div>
        {/if}
      </div>

      <!-- Muatan yang sedang diisi — tidak relevan saat mengubah status / serah terima -->
      {#if grup === 'muat'}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
          {#if muatanHariIni.length === 0 && muatanLain.length === 0}
            <div class="flex items-start gap-2.5 text-sm text-gray-600">
              <HeroIcon name="information-circle" class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
              <span>
                Belum ada muatan untuk Anda. Hasil pindai tetap diperiksa keabsahannya, tetapi tidak dicatat
                ke muatan mana pun sampai muatan dibuat.
              </span>
            </div>
          {:else}
            <div class="flex items-end justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Termuat</p>
                <p class="tabular-nums text-gray-900">
                  <span class="text-2xl font-extrabold">{totalResi}</span>
                  <span class="text-sm text-gray-500">resi · {totalMushaf} mushaf</span>
                </p>
              </div>
            </div>
            <div>
              <label for="muatan-tujuan" class="block text-xs font-medium text-gray-500 mb-1">Dimuat ke</label>
              <select
                id="muatan-tujuan"
                value={muatanTerpilih}
                on:change={pilihMuatan}
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              >
                <option value="">Tidak dicatat ke muatan</option>
                {#if muatanHariIni.length > 0}
                  <optgroup label="Hari ini">
                    {#each muatanHariIni as m}
                      <option value={m.id}>{m.kode_muatan} — {m.total_resi} resi</option>
                    {/each}
                  </optgroup>
                {/if}
                {#if muatanLain.length > 0}
                  <optgroup label="Sebelumnya">
                    {#each muatanLain as m}
                      <option value={m.id}>{m.kode_muatan} · {m.tanggal_muatan}</option>
                    {/each}
                  </optgroup>
                {/if}
              </select>
            </div>
          {/if}
        </div>
      {/if}

      {#if modeSerahTerima}
        {#key mode}
          <SerahTerimaPanel bind:this={panelSerahTerima} {mode} />
        {/key}
      {/if}

      <!-- Panel Ubah Status — hanya di mode 'status' -->
      {#if mode === 'status' && memuatInfo}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
          <div class="flex items-center gap-3 text-sm text-gray-600">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-[#eb3434]"></div>
            Mengambil data resi...
          </div>
        </div>
      {/if}

      {#if mode === 'status' && infoResi}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
          <h3 class="text-sm font-semibold text-gray-900 mb-3">Ubah Status Resi</h3>

          <dl class="text-xs space-y-1.5 mb-4">
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500">No. Resi</dt>
              <dd class="font-mono text-gray-900 text-right">{infoResi.no_resi}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500">Penerima</dt>
              <dd class="text-gray-900 text-right">{infoResi.nama_penerima || '—'}</dd>
            </div>
            {#if infoResi.jenis_quran}
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Jenis</dt>
                <dd class="text-gray-900 text-right">{infoResi.jenis_quran}</dd>
              </div>
            {/if}
            <div class="flex justify-between gap-3">
              <dt class="text-gray-500">Status sekarang</dt>
              <dd class="text-gray-900 text-right">{infoResi.current_status?.nama || '—'}</dd>
            </div>
          </dl>

          {#if statusTersedia.length === 0}
            <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3">
              Tidak ada status lanjutan yang bisa dipilih untuk resi ini. Kalau statusnya sudah final,
              memang tidak ada kelanjutannya.
            </div>
          {:else}
            <label for="status-baru" class="block text-sm font-medium text-gray-700 mb-2">Status Baru</label>
            <select
              id="status-baru"
              bind:value={statusTerpilih}
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm mb-3"
            >
              {#each statusTersedia as s}
                <option value={s.id}>{s.nama}</option>
              {/each}
            </select>

            <label for="catatan-status" class="block text-sm font-medium text-gray-700 mb-2">Catatan (opsional)</label>
            <textarea
              id="catatan-status"
              bind:value={catatanStatus}
              rows="2"
              placeholder="Mis. paket diserahkan ke penerima langsung"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm mb-3"
            ></textarea>

            <label for="lokasi-status" class="block text-sm font-medium text-gray-700 mb-2">Lokasi (opsional)</label>
            <input
              id="lokasi-status"
              type="text"
              bind:value={lokasiStatus}
              placeholder="Mis. Kelurahan Sukamaju, Jakarta"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm mb-4"
            />

            <div class="flex gap-2">
              <button
                on:click={simpanStatus}
                disabled={menyimpanStatus || !statusTerpilih}
                class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {menyimpanStatus ? 'Menyimpan...' : 'Simpan Status'}
              </button>
              <button
                on:click={bersihkanStatus}
                disabled={menyimpanStatus}
                class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 disabled:opacity-50 transition-colors"
              >
                Batal
              </button>
            </div>
          {/if}
        </div>
      {/if}
    </div>

    <!-- Kanan di laptop, bawah di HP: hasil & riwayat -->
    <div class="space-y-4">
      {#if hasilTerakhir}
        <div class="bg-white rounded-xl shadow-sm border {hasilTerakhir.success ? 'border-green-200' : 'border-red-200'} p-4 sm:p-6">
          <div class="flex items-center gap-2 mb-3">
            <HeroIcon
              name={hasilTerakhir.success ? 'check-circle' : 'x-circle'}
              class="w-5 h-5 {hasilTerakhir.success ? 'text-green-600' : 'text-red-600'}"
            />
            <span class="text-sm font-semibold {hasilTerakhir.success ? 'text-green-700' : 'text-red-700'}">
              {hasilTerakhir.success ? 'Berhasil' : 'Gagal'}
            </span>
            <span class="text-xs text-gray-400 ml-auto">{hasilTerakhir.waktu}</span>
          </div>
          <p class="text-sm text-gray-700 mb-3">{hasilTerakhir.message}</p>

          {#if hasilTerakhir.data}
            <dl class="text-xs space-y-1.5 border-t border-gray-100 pt-3">
              {#if hasilTerakhir.jenis === 'box'}
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">Kerdus</dt>
                  <dd class="font-mono text-gray-900 text-right">{hasilTerakhir.data.kode_kerdus}</dd>
                </div>
                {#if hasilTerakhir.data.jenis}
                  <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Jenis</dt>
                    <dd class="text-gray-900 text-right">{hasilTerakhir.data.jenis}</dd>
                  </div>
                {/if}
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">Isi kerdus</dt>
                  <dd class="text-gray-900 text-right">{hasilTerakhir.data.jumlah_resi} resi</dd>
                </div>
              {:else}
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">No. Resi</dt>
                  <dd class="font-mono text-gray-900 text-right">{hasilTerakhir.data.no_resi}</dd>
                </div>
                {#if hasilTerakhir.data.nama_penerima}
                  <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Penerima</dt>
                    <dd class="text-gray-900 text-right">{hasilTerakhir.data.nama_penerima}</dd>
                  </div>
                {/if}
                {#if hasilTerakhir.data.jenis}
                  <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Jenis</dt>
                    <dd class="text-gray-900 text-right">{hasilTerakhir.data.jenis}</dd>
                  </div>
                {/if}
                {#if hasilTerakhir.data.status}
                  <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Status</dt>
                    <dd class="text-gray-900 text-right">{hasilTerakhir.data.status}</dd>
                  </div>
                {/if}
              {/if}
            </dl>
          {/if}
        </div>
      {/if}

      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
          <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pindaian terakhir</h3>
        </div>
        {#if riwayat.length === 0}
          <div class="p-8 text-center text-sm text-gray-500">Belum ada hasil pindai.</div>
        {:else}
          <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
            {#each riwayat as r, i (i)}
              <li class="px-4 py-3 flex items-center gap-3">
                <span
                  class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center
                    {nadaRiwayat(r) === 'ok' ? 'bg-green-50 text-green-600' : nadaRiwayat(r) === 'info' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'}"
                >
                  <HeroIcon
                    name={nadaRiwayat(r) === 'ok' ? 'check' : nadaRiwayat(r) === 'info' ? 'information-circle' : 'x-mark'}
                    class="w-4 h-4"
                  />
                </span>
                <div class="min-w-0 flex-1">
                  {#if kodeRiwayat(r)}
                    <p class="text-sm font-semibold font-mono text-gray-900 truncate">{kodeRiwayat(r)}</p>
                  {/if}
                  <p class="text-xs text-gray-500 truncate">{r.message}</p>
                </div>
                <span class="text-[11px] text-gray-400 tabular-nums shrink-0">{r.waktu}</span>
              </li>
            {/each}
          </ul>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
