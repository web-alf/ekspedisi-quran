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
   */
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import LazyQRScanner from '../../../Components/LazyQRScanner.svelte';
  import { toast } from '../../../utils/notifications.js';
  import axios from 'axios';

  export let muatanHariIni = [];
  export let muatanLain = [];

  let muatanTerpilih = muatanHariIni.length > 0 ? muatanHariIni[0].id : '';
  let mode = 'pcs'; // 'pcs' | 'box' | 'status'
  let memindai = false; // mengunci agar satu QR tidak terkirim berkali-kali
  let inputManual = '';
  let kameraAktif = false;

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
      toast.error(pesan);
    } finally {
      memindai = false;
    }
  }

  function onScanSuccess(e) {
    const teks = e.detail?.text || '';
    if (!teks.trim()) return;

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
</script>

<svelte:head>
  <title>Scan Barang - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="px-6 py-8">
      <div class="mb-6">
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">Scan Barang</h2>
          <p class="text-sm text-gray-600">
            Muat barang ke muatan Anda (per-pcs atau per-kerdus), atau ubah status perjalanan sebuah resi.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kiri: scanner -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Pilih muatan tujuan — tidak relevan saat mengubah status -->
          {#if mode !== 'status'}
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <label for="muatan-tujuan" class="block text-sm font-medium text-gray-700 mb-2">Muatan Tujuan</label>
            {#if muatanHariIni.length === 0 && muatanLain.length === 0}
              <div class="flex items-start gap-2.5 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3">
                <HeroIcon name="information-circle" class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <span>
                  Belum ada muatan untuk Anda. Hasil pindai tetap diperiksa keabsahannya, tetapi tidak dicatat
                  ke muatan mana pun sampai muatan dibuat.
                </span>
              </div>
            {:else}
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
              <div class="mt-2 text-xs text-gray-500">
                Terisi: <span class="font-semibold text-gray-900">{totalResi}</span> resi ·
                <span class="font-semibold text-gray-900">{totalMushaf}</span> mushaf
              </div>
            {/if}
          </div>
          {/if}

          <!-- Mode -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
              <button
                on:click={() => gantiMode('pcs')}
                class="flex flex-col items-center gap-1.5 p-4 rounded-lg border-2 transition-colors
                  {mode === 'pcs' ? 'border-[#eb3434] bg-red-50' : 'border-gray-200 hover:border-gray-300'}"
              >
                <HeroIcon name="qr-code" class="w-6 h-6 {mode === 'pcs' ? 'text-[#eb3434]' : 'text-gray-400'}" />
                <span class="text-sm font-medium {mode === 'pcs' ? 'text-[#eb3434]' : 'text-gray-700'}">Per PCS</span>
                <span class="text-xs text-gray-500">Muat satu resi</span>
              </button>

              <button
                on:click={() => gantiMode('box')}
                class="flex flex-col items-center gap-1.5 p-4 rounded-lg border-2 transition-colors
                  {mode === 'box' ? 'border-[#eb3434] bg-red-50' : 'border-gray-200 hover:border-gray-300'}"
              >
                <HeroIcon name="archive-box" class="w-6 h-6 {mode === 'box' ? 'text-[#eb3434]' : 'text-gray-400'}" />
                <span class="text-sm font-medium {mode === 'box' ? 'text-[#eb3434]' : 'text-gray-700'}">Per Kerdus</span>
                <span class="text-xs text-gray-500">Muat seisi box</span>
              </button>

              <button
                on:click={() => gantiMode('status')}
                class="flex flex-col items-center gap-1.5 p-4 rounded-lg border-2 transition-colors
                  {mode === 'status' ? 'border-[#eb3434] bg-red-50' : 'border-gray-200 hover:border-gray-300'}"
              >
                <HeroIcon name="truck" class="w-6 h-6 {mode === 'status' ? 'text-[#eb3434]' : 'text-gray-400'}" />
                <span class="text-sm font-medium {mode === 'status' ? 'text-[#eb3434]' : 'text-gray-700'}">Ubah Status</span>
                <span class="text-xs text-gray-500">Perjalanan resi</span>
              </button>
            </div>

            <!--
              Penjelasan per mode. Dua mode pertama MEMUAT barang ke muatan; mode
              ketiga MENGUBAH STATUS. Membedakannya secara eksplisit penting:
              keliru memilih berarti status resi berubah tanpa disengaja.
            -->
            <div class="mb-4 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3">
              {#if mode === 'status'}
                Satu pindai = satu perubahan status perjalanan resi. Status yang tersedia mengikuti
                aturan perjalanan dan wewenang Anda.
              {:else}
                Satu pindai = barang dimuat ke muatan untuk dibawa. Status resi tidak berubah.
              {/if}
            </div>

            <button
              on:click={() => (kameraAktif = !kameraAktif)}
              class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-medium
                {kameraAktif ? 'text-gray-700 border border-gray-300 hover:bg-gray-50' : 'text-white bg-[#eb3434] hover:bg-red-600'} transition-colors"
            >
              <HeroIcon name="camera" class="w-4 h-4" />
              {kameraAktif ? 'Matikan Kamera' : 'Nyalakan Kamera'}
            </button>

            {#if kameraAktif}
              <div class="mt-4 bg-gray-100 rounded-lg overflow-hidden">
                <LazyQRScanner
                  width={400}
                  height={300}
                  facingMode="environment"
                  showToggleCamera={true}
                  on:scanSuccess={onScanSuccess}
                />
              </div>
            {/if}

            <!-- Input manual -->
            <div class="mt-4 pt-4 border-t border-gray-100">
              <label for="input-manual" class="block text-sm font-medium text-gray-700 mb-2">
                {mode === 'status' ? 'Atau ketik nomor resi' : 'Atau ketik kode manual'}
              </label>
              <div class="flex gap-2">
                <input
                  id="input-manual"
                  type="text"
                  bind:value={inputManual}
                  on:keydown={(e) => e.key === 'Enter' && kirimManual()}
                  placeholder={mode === 'box' ? 'KB-20261005-...' : 'EQ-2026-00001'}
                  class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
                />
                <button
                  on:click={kirimManual}
                  disabled={(mode === 'status' ? memuatInfo : memindai) || !inputManual.trim()}
                  class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                >
                  {mode === 'status' ? (memuatInfo ? '...' : 'Cari') : (memindai ? '...' : 'Pindai')}
                </button>
              </div>
            </div>
          </div>

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

        <!-- Kanan: hasil & riwayat -->
        <div class="space-y-6">
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
            <div class="px-4 sm:px-6 py-4 border-b border-gray-100">
              <h3 class="text-sm font-semibold text-gray-900">Riwayat Pindai</h3>
            </div>
            {#if riwayat.length === 0}
              <div class="p-8 text-center text-sm text-gray-500">Belum ada hasil pindai.</div>
            {:else}
              <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                {#each riwayat as r, i (i)}
                  <li class="px-4 py-3 flex items-start gap-2.5">
                    <HeroIcon
                      name={r.success ? 'check-circle' : 'x-circle'}
                      class="w-4 h-4 shrink-0 mt-0.5 {r.success ? 'text-green-600' : 'text-red-600'}"
                    />
                    <div class="min-w-0 flex-1">
                      <p class="text-xs text-gray-700 truncate">{r.message}</p>
                      <p class="text-[11px] text-gray-400 mt-0.5">
                        {r.jenis === 'box' ? 'Kerdus' : 'PCS'} · {r.waktu}
                      </p>
                    </div>
                  </li>
                {/each}
              </ul>
            {/if}
          </div>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>
