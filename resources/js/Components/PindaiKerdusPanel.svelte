<script>
  /**
   * Panel Pindai Kerdus — bagian dari halaman kerja Muatan & Distribusi.
   *
   * Satu pindai kerdus = satu VERIFIKASI ISI: petugas menghitung isi fisik kerdus dan
   * mengetik jumlahnya, server membandingkan dengan data (cocok / selisih). Angka data
   * sengaja tidak ditampilkan sebelum petugas mengetik hitungannya (hitungan buta).
   * Kerdus hanya bisa dimuat ke kurir bila verifikasi terakhirnya cocok. Status
   * pengiriman TIDAK berubah di sini.
   *
   * Dua jalur masuk, dua-duanya sah dan tetap tercatat:
   *   - kamera (QR)    -> metode 'qr'
   *   - ketikan tangan -> metode 'manual', untuk label rusak / kamera tak bisa dipakai
   *
   * Kerdus yang BELUM TERDAFTAR pun bisa dicatat, asalkan jenis & jumlah eksnya diisi.
   * Server yang memutuskan (menandai `perlu_isian`), jadi jalur ini tetap jalan walau
   * tampilan ini diubah.
   *
   * Kunci permintaan diberi awalan `pindai_` (pindai_tanggal, pindai_page, dst.) supaya
   * filter tabel kerdus dan filter tabel muatan di halaman yang sama tidak saling menimpa.
   */
  import { router } from '@inertiajs/svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  import LazyQRScanner from './LazyQRScanner.svelte';
  import Pagination from './Pagination.svelte';
  import { toast } from '../utils/notifications.js';
  import axios from 'axios';

  export let pindai = {};
  export let perPage = 20;
  export let perPageOptions = [10, 20, 50, 100, 200];

  $: log = pindai.log || { data: [], current_page: 1, last_page: 1, per_page: 20, total: 0 };
  $: jenisList = pindai.jenisList || [];
  $: petugasList = pindai.petugasList || [];
  $: tanggalHariIni = pindai.tanggalHariIni || '';

  // --- Pemindai ---
  let kameraAktif = false;
  let kodeManual = '';
  let memindai = false;
  let hasilTerakhir = null;
  let riwayatSesi = [];

  // Ringkasan mengikuti jawaban server tiap pindai, bukan dihitung ulang di layar — kalau
  // dihitung di layar, dua orang yang memindai bersamaan akan melihat angka berbeda dari
  // yang tercatat.
  let ringkasHariIni = pindai.hariIni || { pindai: 0, kerdus: 0, mushaf: 0, per_jenis: [] };
  let ringkasTotal = pindai.total || { pindai: 0, kerdus: 0, mushaf: 0, per_jenis: [] };

  // Hitung fisik kerdus terdaftar (server menandai `perlu_hitung`).
  let perluHitung = null; // { kode, jenis, metode }
  let jumlahFisik = '';
  let catatanHitung = '';
  let menyimpanHitung = false;

  // Isian kerdus yang belum terdaftar.
  let perluIsian = null; // { kode }
  let isianJenis = '';
  let isianJumlah = '';
  let isianCatatan = '';
  let menyimpanIsian = false;

  // --- Filter riwayat ---
  let tanggal = pindai.filters?.tanggal ?? '';
  let jenisFilter = pindai.filters?.jenis_quran_id ?? '';
  let petugasFilter = pindai.filters?.user_id ?? '';
  let cari = pindai.filters?.search ?? '';
  let jedaCari;

  const labelStatus = {
    empty: 'Kosong',
    filling: 'Sedang diisi',
    full: 'Penuh',
    sealed: 'Tersegel',
  };

  function jamSekarang() {
    return new Date().toLocaleTimeString('id-ID');
  }

  /** Kode kerdus dari isi QR: bisa teks polos, JSON pemindai lama, atau URL. */
  function ambilKodeKerdus(teks) {
    const mentah = (teks || '').trim();
    if (!mentah) return '';

    try {
      const data = JSON.parse(mentah);
      const dariJson = data?.kode_kerdus || data?.box_code;
      if (dariJson) return String(dariJson).trim();
    } catch {
      // bukan JSON — lanjut ke pola teks
    }

    const cocok = mentah.match(/KB-[A-Za-z0-9-]+/);
    return cocok ? cocok[0] : mentah;
  }

  async function catat(kode, metode, tambahan = null) {
    const bersih = (kode || '').trim();
    if (!bersih || memindai) return;

    memindai = true;

    try {
      const { data } = await axios.post('/admin/pindai-kerdus', {
        kode: bersih,
        metode,
        ...(tambahan || {}),
      });

      hasilTerakhir = { ...data, waktu: jamSekarang() };
      riwayatSesi = [hasilTerakhir, ...riwayatSesi].slice(0, 25);
      ringkasHariIni = data.hari_ini || ringkasHariIni;
      ringkasTotal = data.total || ringkasTotal;
      perluIsian = null;
      perluHitung = null;

      if (data.hasil === 'selisih') {
        toast.error(data.message);
      } else if (data.ulang) {
        toast.warning(data.message);
      } else {
        toast.success(data.message);
      }

      // Tabel riwayat dibaca dari SERVER, jadi ia belum tahu ada baris baru. Muat ulang
      // prop-nya saja (bukan seluruh halaman) supaya riwayatnya ikut bergerak tanpa
      // mengganggu ketikan yang sedang berjalan maupun tabel muatan di bawahnya.
      router.reload({ only: ['pindaiKerdus'] });
    } catch (err) {
      const jawaban = err.response?.data || {};
      const pesan = jawaban.message || 'Gagal mencatat pindai.';

      // Kerdus terdaftar: server meminta hitungan fisik petugas.
      if (jawaban.perlu_hitung) {
        perluHitung = { kode: jawaban.kode || bersih, jenis: jawaban.jenis, metode };
        jumlahFisik = '';
        catatanHitung = '';
        toast.info(pesan);
      } else if (jawaban.perlu_isian) {
        perluIsian = { kode: jawaban.kode || bersih };
        isianJenis = jenisList.length > 0 ? jenisList[0].id : '';
        isianJumlah = '';
        isianCatatan = '';
        toast.warning(pesan);
      } else {
        hasilTerakhir = { success: false, message: pesan, waktu: jamSekarang() };
        riwayatSesi = [hasilTerakhir, ...riwayatSesi].slice(0, 25);
        toast.error(pesan);
      }
    } finally {
      memindai = false;
    }
  }

  function onScanSuccess(e) {
    const kode = ambilKodeKerdus(e.detail?.text || '');
    if (!kode) {
      toast.error('QR ini tidak memuat kode kerdus.');
      return;
    }

    catat(kode, 'qr');
  }

  function kirimManual() {
    const kode = ambilKodeKerdus(kodeManual);
    if (!kode) return;

    kodeManual = '';
    catat(kode, 'manual');
  }

  async function simpanIsianManual() {
    if (!perluIsian || !isianJenis || !isianJumlah || menyimpanIsian) return;

    menyimpanIsian = true;

    await catat(perluIsian.kode, 'manual', {
      jenis_quran_id: isianJenis,
      jumlah_mushaf: Number(isianJumlah),
      catatan: isianCatatan || null,
    });

    menyimpanIsian = false;
    if (!perluIsian) {
      isianJenis = '';
      isianJumlah = '';
      isianCatatan = '';
    }
  }

  async function simpanHitung() {
    if (!perluHitung || jumlahFisik === '' || jumlahFisik === null || menyimpanHitung) return;

    menyimpanHitung = true;

    await catat(perluHitung.kode, perluHitung.metode, {
      jumlah_fisik: Number(jumlahFisik),
      catatan: catatanHitung || null,
    });

    menyimpanHitung = false;
  }

  function batalHitung() {
    perluHitung = null;
    jumlahFisik = '';
    catatanHitung = '';
  }

  function batalIsian() {
    perluIsian = null;
    isianJenis = '';
    isianJumlah = '';
    isianCatatan = '';
  }

  /**
   * Menerapkan filter riwayat TANPA menghapus filter/tabel lain di halaman yang sama:
   * parameter yang sudah ada di URL dibawa serta, yang berubah hanya kunci `pindai_*`.
   */
  function terapkanFilter() {
    const params = Object.fromEntries(new URL(window.location.href).searchParams);

    params.pindai_tanggal = tanggal;
    params.pindai_page = 1;

    for (const [kunci, nilai] of Object.entries({
      pindai_jenis_quran_id: jenisFilter,
      pindai_user_id: petugasFilter,
      pindai_cari: cari,
    })) {
      if (nilai) {
        params[kunci] = nilai;
      } else {
        delete params[kunci];
      }
    }

    router.get(window.location.pathname, params, { preserveState: true, preserveScroll: true });
  }

  function cariDitunda() {
    clearTimeout(jedaCari);
    jedaCari = setTimeout(terapkanFilter, 350);
  }

  function resetFilter() {
    tanggal = tanggalHariIni;
    jenisFilter = '';
    petugasFilter = '';
    cari = '';
    terapkanFilter();
  }

  function waktuTampil(baris) {
    return baris.tanggal === tanggalHariIni ? baris.waktu : baris.waktu_lengkap;
  }
</script>

<section class="space-y-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Pindai Kerdus</h2>
    <p class="text-sm text-gray-600">
      Verifikasi isi kerdus sebelum dimuat: pindai kerdus, hitung isi fisiknya, lalu ketik
      jumlahnya. Hanya kerdus berstatus <span class="font-medium">Cocok</span> yang bisa dimuat
      ke kurir. Status pengiriman tidak berubah karena pindai di sini.
    </p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Kiri: pemindai & isian -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Pindai QR Kerdus</h3>

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

        <div class="mt-4 pt-4 border-t border-gray-100">
          <label for="kode-manual" class="block text-sm font-medium text-gray-700 mb-2">
            Atau ketik kode kerdus
          </label>
          <div class="flex gap-2">
            <input
              id="kode-manual"
              type="text"
              bind:value={kodeManual}
              on:keydown={(e) => e.key === 'Enter' && kirimManual()}
              placeholder="KB-20261006-003-A5-01"
              class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
            />
            <button
              on:click={kirimManual}
              disabled={memindai || !kodeManual.trim()}
              class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
            >
              {memindai ? '...' : 'Catat'}
            </button>
          </div>
          <p class="mt-2 text-xs text-gray-500">
            Kode diketik diperlakukan sama dengan hasil pindai, hanya metodenya dicatat “manual”.
          </p>
        </div>
      </div>

      <!-- Hitung fisik kerdus terdaftar -->
      {#if perluHitung}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex items-start gap-2.5 mb-4">
            <HeroIcon name="archive-box" class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
            <div>
              <h3 class="text-sm font-semibold text-gray-900">
                Hitung isi kerdus <span class="font-mono">{perluHitung.kode}</span>
              </h3>
              <p class="text-xs text-gray-500 mt-1">
                {perluHitung.jenis ? `Jenis ${perluHitung.jenis}. ` : ''}Hitung mushaf di dalam kerdus,
                lalu ketik jumlahnya. Sistem akan mencocokkan dengan data.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label for="hitung-jumlah" class="block text-sm font-medium text-gray-700 mb-2">Jumlah fisik (eks)</label>
              <input
                id="hitung-jumlah"
                type="number"
                min="0"
                inputmode="numeric"
                bind:value={jumlahFisik}
                on:keydown={(e) => e.key === 'Enter' && simpanHitung()}
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              />
            </div>

            <div class="sm:col-span-2">
              <label for="hitung-catatan" class="block text-sm font-medium text-gray-700 mb-2">
                Catatan (opsional)
              </label>
              <input
                id="hitung-catatan"
                type="text"
                bind:value={catatanHitung}
                placeholder="Mis. kerdus penyok, 1 mushaf basah"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              />
            </div>
          </div>

          <div class="flex gap-2 mt-4">
            <button
              on:click={simpanHitung}
              disabled={menyimpanHitung || jumlahFisik === '' || jumlahFisik === null}
              class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {menyimpanHitung ? 'Mencocokkan...' : 'Cocokkan'}
            </button>
            <button
              on:click={batalHitung}
              disabled={menyimpanHitung}
              class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            >
              Batal
            </button>
          </div>
        </div>
      {/if}

      <!-- Kerdus belum terdaftar -->
      {#if perluIsian}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex items-start gap-2.5 mb-4">
            <HeroIcon name="information-circle" class="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
            <div>
              <h3 class="text-sm font-semibold text-gray-900">
                Kerdus <span class="font-mono">{perluIsian.kode}</span> belum terdaftar
              </h3>
              <p class="text-xs text-gray-500 mt-1">
                Labelnya rusak atau kerdusnya belum tercatat di sistem. Isi jenis dan jumlah eksnya
                supaya perpindahannya tetap terekam.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label for="isian-jenis" class="block text-sm font-medium text-gray-700 mb-2">Jenis</label>
              <select
                id="isian-jenis"
                bind:value={isianJenis}
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              >
                {#each jenisList as j}
                  <option value={j.id}>{j.nama}</option>
                {/each}
              </select>
            </div>

            <div>
              <label for="isian-jumlah" class="block text-sm font-medium text-gray-700 mb-2">Jumlah eks</label>
              <input
                id="isian-jumlah"
                type="number"
                min="1"
                bind:value={isianJumlah}
                placeholder="20"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              />
            </div>

            <div>
              <label for="isian-catatan" class="block text-sm font-medium text-gray-700 mb-2">
                Catatan (opsional)
              </label>
              <input
                id="isian-catatan"
                type="text"
                bind:value={isianCatatan}
                placeholder="Mis. label sobek"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
              />
            </div>
          </div>

          <div class="flex gap-2 mt-4">
            <button
              on:click={simpanIsianManual}
              disabled={menyimpanIsian || !isianJenis || !isianJumlah}
              class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {menyimpanIsian ? 'Menyimpan...' : 'Catat manual'}
            </button>
            <button
              on:click={batalIsian}
              disabled={menyimpanIsian}
              class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            >
              Batal
            </button>
          </div>
        </div>
      {/if}

      <!-- Hasil pindai terakhir -->
      {#if hasilTerakhir}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
          <div class="flex items-center gap-2 mb-3">
            <HeroIcon
              name={hasilTerakhir.success && hasilTerakhir.hasil !== 'selisih' ? 'check-circle' : 'x-circle'}
              class="w-5 h-5 {hasilTerakhir.success && hasilTerakhir.hasil !== 'selisih' ? 'text-green-600' : 'text-red-600'}"
            />
            <span class="text-sm font-semibold {hasilTerakhir.success && hasilTerakhir.hasil !== 'selisih' ? 'text-gray-900' : 'text-red-700'}">
              {#if !hasilTerakhir.success}Gagal{:else if hasilTerakhir.hasil === 'cocok'}Cocok — boleh dimuat{:else if hasilTerakhir.hasil === 'selisih'}Selisih — diblokir dari muatan{:else}Tercatat{/if}
            </span>
            <span class="text-xs text-gray-400 ml-auto">{hasilTerakhir.waktu}</span>
          </div>

          <p class="text-sm text-gray-700 mb-3">{hasilTerakhir.message}</p>

          {#if hasilTerakhir.data}
            <dl class="text-xs space-y-1.5 border-t border-gray-100 pt-3">
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Kerdus</dt>
                <dd class="font-mono text-gray-900 text-right">{hasilTerakhir.data.kode_kerdus}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Jenis</dt>
                <dd class="text-gray-900 text-right">{hasilTerakhir.data.jenis || 'Tanpa jenis'}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">{hasilTerakhir.data.jumlah_fisik !== undefined && hasilTerakhir.data.jumlah_fisik !== null ? 'Jumlah di data' : 'Jumlah'}</dt>
                <dd class="font-semibold text-gray-900 text-right">{hasilTerakhir.data.jumlah_mushaf} eks</dd>
              </div>
              {#if hasilTerakhir.data.jumlah_fisik !== undefined && hasilTerakhir.data.jumlah_fisik !== null}
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">Jumlah fisik</dt>
                  <dd class="font-semibold text-right {hasilTerakhir.data.hasil === 'selisih' ? 'text-red-700' : 'text-gray-900'}">
                    {hasilTerakhir.data.jumlah_fisik} eks
                  </dd>
                </div>
              {/if}
              {#if hasilTerakhir.data.jumlah_resi !== null && hasilTerakhir.data.jumlah_resi !== undefined}
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">Isi kerdus</dt>
                  <dd class="text-gray-900 text-right">
                    {hasilTerakhir.data.jumlah_resi} resi
                    {#if hasilTerakhir.data.kapasitas}· {hasilTerakhir.data.jumlah_mushaf}/{hasilTerakhir.data.kapasitas} eks{/if}
                  </dd>
                </div>
              {/if}
              {#if hasilTerakhir.data.status}
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">Kondisi kerdus</dt>
                  <dd class="text-gray-900 text-right">{labelStatus[hasilTerakhir.data.status] || hasilTerakhir.data.status}</dd>
                </div>
              {/if}
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Metode</dt>
                <dd class="text-gray-900 text-right">{hasilTerakhir.data.metode === 'manual' ? 'Ketik manual' : 'Pindai QR'}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Dipindai oleh</dt>
                <dd class="text-gray-900 text-right">{hasilTerakhir.data.oleh}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Jam</dt>
                <dd class="text-gray-900 text-right">{hasilTerakhir.data.jam}</dd>
              </div>
            </dl>
          {/if}
        </div>
      {/if}
    </div>

    <!-- Kanan: ringkasan & riwayat sesi -->
    <div class="space-y-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-sm font-semibold text-gray-900">Ringkasan Hari Ini</h3>
          <span class="text-xs text-gray-400 ml-auto">{tanggalHariIni}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="border border-gray-200 rounded-lg p-3">
            <div class="text-2xl font-semibold text-gray-900">{ringkasHariIni.kerdus}</div>
            <div class="text-xs text-gray-500 mt-0.5">kerdus dipindai</div>
          </div>
          <div class="border border-gray-200 rounded-lg p-3">
            <div class="text-2xl font-semibold text-gray-900">{ringkasHariIni.pindai}</div>
            <div class="text-xs text-gray-500 mt-0.5">kali pindai</div>
          </div>
        </div>

        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Jumlah mushaf</div>
        <ul class="space-y-2">
          {#each ringkasHariIni.per_jenis as j}
            <li class="flex items-center justify-between gap-3 text-sm">
              <span class="inline-flex items-center gap-2 text-gray-700">
                <span class="inline-flex items-center justify-center min-w-11 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                  {j.kode || '—'}
                </span>
                {j.nama}
              </span>
              <span class="font-semibold text-gray-900">{j.mushaf} eks</span>
            </li>
          {/each}
        </ul>

        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
          <span class="text-gray-500">Total hari ini</span>
          <span class="font-semibold text-gray-900">{ringkasHariIni.mushaf} eks</span>
        </div>

        <div class="mt-3 text-xs text-gray-500 leading-relaxed">
          Kerdus yang dipindai lebih dari sekali dihitung <span class="font-medium">sekali</span>.
          Sejak awal: {ringkasTotal.kerdus} kerdus · {ringkasTotal.mushaf} eks · {ringkasTotal.pindai} kali pindai.
        </div>

        {#if ringkasTotal.selisih > 0}
          <div class="mt-3 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700">
            {ringkasTotal.selisih} kerdus masih <span class="font-semibold">selisih</span> dan diblokir dari muatan.
          </div>
        {/if}
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-100">
          <h3 class="text-sm font-semibold text-gray-900">Pindai Sesi Ini</h3>
        </div>
        {#if riwayatSesi.length === 0}
          <div class="p-8 text-center text-sm text-gray-500">Belum ada pindai di sesi ini.</div>
        {:else}
          <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
            {#each riwayatSesi as r, i (i)}
              <li class="px-4 py-3 flex items-start gap-2.5">
                <HeroIcon
                  name={r.success && r.hasil !== 'selisih' ? 'check-circle' : 'x-circle'}
                  class="w-4 h-4 shrink-0 mt-0.5 {r.success && r.hasil !== 'selisih' ? 'text-green-600' : 'text-red-600'}"
                />
                <div class="min-w-0 flex-1">
                  <p class="text-xs text-gray-700 truncate">{r.message}</p>
                  <p class="text-[11px] text-gray-400 mt-0.5">{r.waktu}</p>
                </div>
              </li>
            {/each}
          </ul>
        {/if}
      </div>
    </div>
  </div>

  <!-- Riwayat tersimpan -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div>
        <label for="filter-pindai-tanggal" class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
        <input
          id="filter-pindai-tanggal"
          type="date"
          bind:value={tanggal}
          on:change={terapkanFilter}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
        />
        <p class="mt-1 text-xs text-gray-500">Kosongkan untuk melihat semua tanggal.</p>
      </div>

      <div>
        <label for="filter-pindai-jenis" class="block text-sm font-medium text-gray-700 mb-2">Jenis</label>
        <select
          id="filter-pindai-jenis"
          bind:value={jenisFilter}
          on:change={terapkanFilter}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
        >
          <option value="">Semua jenis</option>
          {#each jenisList as j}
            <option value={j.id}>{j.nama}</option>
          {/each}
        </select>
      </div>

      <div>
        <label for="filter-pindai-petugas" class="block text-sm font-medium text-gray-700 mb-2">Dipindai oleh</label>
        <select
          id="filter-pindai-petugas"
          bind:value={petugasFilter}
          on:change={terapkanFilter}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
        >
          <option value="">Semua petugas</option>
          {#each petugasList as p}
            <option value={p.id}>{p.nama}</option>
          {/each}
        </select>
      </div>

      <div>
        <label for="filter-pindai-cari" class="block text-sm font-medium text-gray-700 mb-2">Cari kode kerdus</label>
        <div class="flex gap-2">
          <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
              <HeroIcon name="magnifying-glass" class="h-4 w-4" />
            </div>
            <input
              id="filter-pindai-cari"
              type="text"
              bind:value={cari}
              on:input={cariDitunda}
              placeholder="KB-20261006..."
              class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
            />
          </div>
          <button
            on:click={resetFilter}
            class="px-3 py-2 rounded-lg text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors whitespace-nowrap"
          >
            Reset
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    {#if log.data.length === 0}
      <div class="p-12 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
          <HeroIcon name="archive-box" class="w-7 h-7 text-gray-400" />
        </div>
        <h3 class="text-base font-semibold text-gray-900 mb-1">Belum ada kerdus dipindai</h3>
        <p class="text-sm text-gray-500">
          Pindai QR kerdus atau ketik kodenya di atas — jejaknya akan muncul di tabel ini.
        </p>
      </div>
    {:else}
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jam</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kode Kerdus</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jenis</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Data</th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Fisik</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Hasil</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Metode</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dipindai oleh</th>
              <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Catatan</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            {#each log.data as baris (baris.id)}
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{waktuTampil(baris)}</td>
                <td class="px-4 py-3">
                  <div class="text-sm font-mono font-medium text-gray-900">{baris.kode_kerdus}</div>
                  <div class="flex flex-wrap gap-1 mt-1">
                    {#if baris.asal === 'mandiri'}
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-gray-100 text-gray-600 border border-gray-200">
                        belum terdaftar
                      </span>
                    {/if}
                    {#if baris.ulang}
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-gray-100 text-gray-600 border border-gray-200">
                        pindai ulang
                      </span>
                    {/if}
                  </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <span class="inline-flex items-center gap-2">
                    {#if baris.jenis_kode}
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                        {baris.jenis_kode}
                      </span>
                    {/if}
                    {baris.jenis}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                  {baris.jumlah_mushaf} eks
                </td>
                <td class="px-4 py-3 text-sm font-semibold text-right whitespace-nowrap {baris.hasil === 'selisih' ? 'text-red-700' : 'text-gray-900'}">
                  {baris.jumlah_fisik !== null && baris.jumlah_fisik !== undefined ? `${baris.jumlah_fisik} eks` : '—'}
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  {#if baris.hasil === 'cocok'}
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200">Cocok</span>
                  {:else if baris.hasil === 'selisih'}
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 border border-red-200">Selisih</span>
                  {:else}
                    <span class="text-gray-400">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1.5">
                    <HeroIcon name={baris.metode === 'manual' ? 'pencil-square' : 'qr-code'} class="w-4 h-4 text-gray-400" />
                    {baris.metode === 'manual' ? 'Ketik manual' : 'Pindai QR'}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{baris.oleh}</td>
                <td class="px-4 py-3 text-sm text-gray-500">{baris.catatan || '—'}</td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <div class="px-4 py-3 border-t border-gray-100">
        <!-- Pemilih ukuran halaman disembunyikan: tabel muatan di halaman yang sama
             sudah punya, dan `per_page` berlaku untuk keduanya. -->
        <Pagination data={log} {perPage} perPageOptions={perPageOptions} tanpaPemilihPerHalaman={true} />
      </div>
    {/if}
  </div>
</section>
