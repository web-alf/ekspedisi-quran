<script>
  /**
   * Panel Pindai Kerdus — bagian dari halaman kerja Muatan & Distribusi.
   *
   * Satu pindai kerdus = satu catatan: siapa yang memindai, jam berapa, dan berapa eks
   * per jenis. Jumlahnya diambil dari data packing (isi kerdus sudah pasti), jadi petugas
   * tidak mengetik ulang jumlah. Kerdus baru boleh dimuat ke kurir setelah dipindai di
   * sini. Status pengiriman TIDAK berubah di sini.
   *
   * Dua jalur masuk, dua-duanya sah dan tetap tercatat:
   *   - kamera (QR)    -> metode 'qr'
   *   - ketikan tangan -> metode 'manual', untuk label rusak / kamera tak bisa dipakai
   *
   * Kerdus yang BELUM TERDAFTAR pun bisa dicatat, asalkan jenis & jumlah eksnya diisi.
   * Server yang memutuskan (menandai `perlu_isian`), jadi jalur ini tetap jalan walau
   * tampilan ini diubah.
   *
   * Riwayat pindai tidak lagi punya tabel sendiri (dulu menduplikasi kode & jenis kerdus
   * yang sama): pindai terakhir tampil sebagai kolom di daftar kerdus, dan riwayat
   * lengkapnya ada di detail kerdus. Kunci permintaan daftar kerdus berawalan `siap_`
   * supaya tidak menimpa filter tabel muatan di halaman yang sama.
   */
  import { router } from '@inertiajs/svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  import LazyQRScanner from './LazyQRScanner.svelte';
  import Pagination from './Pagination.svelte';
  import { toast } from '../utils/notifications.js';
  import axios from 'axios';

  export let pindai = {};

  $: jenisList = pindai.jenisList || [];
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

  // Isian kerdus yang belum terdaftar.
  let perluIsian = null; // { kode }
  let isianJenis = '';
  let isianJumlah = '';
  let isianCatatan = '';
  let menyimpanIsian = false;


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

      if (jawaban.perlu_isian) {
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

  function batalIsian() {
    perluIsian = null;
    isianJenis = '';
    isianJumlah = '';
    isianCatatan = '';
  }

  // --- Kerdus siap dipindai ---
  // Kerdus penuh/tersegel yang belum dimuat ke kurir. Barisnya sama dengan Tracking
  // Kerdus gudang, supaya kedua sisi membaca kerdus yang sama dengan cara yang sama.
  $: siap = pindai.siap || { daftar: { data: [], total: 0 }, filters: {}, gudangList: [] };
  $: daftarSiap = siap.daftar?.data || [];

  let siapGudang = pindai.siap?.filters?.gudang || '';
  let siapCari = pindai.siap?.filters?.cari || '';
  let jedaSiap;
  let kartuPemindai;

  // --- Detail isi kerdus (klik kode kerdus) ---
  let detail = null; // jawaban server: { kerdus, mushaf, riwayat_pindai }
  let detailKode = '';
  let memuatDetail = false;
  let galatDetail = '';

  async function bukaDetail(box) {
    detailKode = box.kode_kerdus;
    detail = null;
    galatDetail = '';
    memuatDetail = true;

    try {
      const { data } = await axios.get(`/admin/pindai-kerdus/kerdus/${box.id}`);
      // Abaikan jawaban lama bila petugas sudah membuka kerdus lain.
      if (detailKode === box.kode_kerdus) detail = data;
    } catch (err) {
      galatDetail = err.response?.data?.message || 'Gagal memuat isi kerdus.';
    } finally {
      memuatDetail = false;
    }
  }

  function tutupDetail() {
    detailKode = '';
    detail = null;
    galatDetail = '';
  }

  const labelMetode = { qr: 'Pindai QR', manual: 'Ketik manual' };

  function warnaProgress(persen) {
    if (persen === 0) return 'bg-gray-200';
    if (persen < 50) return 'bg-blue-500';
    if (persen < 100) return 'bg-yellow-500';
    return 'bg-green-500';
  }

  /** Sama seperti filter riwayat: hanya kunci `siap_*` yang berubah, sisanya dibawa. */
  function terapkanFilterSiap() {
    const params = Object.fromEntries(new URL(window.location.href).searchParams);
    delete params.siap_page;

    for (const [kunci, nilai] of Object.entries({ siap_gudang: siapGudang, siap_cari: siapCari })) {
      if (nilai) {
        params[kunci] = nilai;
      } else {
        delete params[kunci];
      }
    }

    router.get(window.location.pathname, params, { preserveState: true, preserveScroll: true, only: ['pindaiKerdus'] });
  }

  function cariSiapDitunda() {
    clearTimeout(jedaSiap);
    jedaSiap = setTimeout(terapkanFilterSiap, 350);
  }

  /** Bawa petugas ke pemindai: verifikasi tetap harus lewat QR di kerdus fisiknya. */
  function mulaiPindai() {
    kameraAktif = true;
    kartuPemindai?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
</script>

<section class="space-y-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Pindai Kerdus</h2>
    <p class="text-sm text-gray-600">
      Pindai QR kerdus (atau ketik kodenya) sebelum dimuat. Jumlah dan jenis mushaf diambil
      dari data packing, lalu tercatat siapa yang memindai dan jam berapa. Kerdus baru bisa
      dimuat ke kurir setelah dipindai di sini. Status pengiriman tidak berubah karena pindai di sini.
    </p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Kiri: pemindai & isian -->
    <div class="lg:col-span-2 space-y-6">
      <div bind:this={kartuPemindai} class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 scroll-mt-20">
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
              {#if !hasilTerakhir.success}Gagal{:else if hasilTerakhir.hasil === 'cocok'}Tercatat — boleh dimuat{:else if hasilTerakhir.hasil === 'selisih'}Selisih — diblokir dari muatan{:else}Tercatat{/if}
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

  <!-- Kerdus siap dipindai: tabel sengaja meniru Tracking Kerdus gudang (Admin/BoxTracking/Index). -->
  <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden" data-testid="kerdus-siap">
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1 min-w-0">
          <h3 class="text-lg font-semibold text-gray-900">Kerdus Siap Dipindai</h3>
          <p class="text-sm text-gray-500 mt-0.5">
            Kerdus penuh atau tersegel yang belum dimuat ke kurir ({siap.daftar?.total || 0} kerdus).
          </p>
        </div>
        <button
          type="button"
          on:click={mulaiPindai}
          class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 transition-colors whitespace-nowrap"
        >
          <HeroIcon name="camera" class="w-4 h-4" />
          Pindai kerdus
        </button>
      </div>

      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <select
          bind:value={siapGudang}
          on:change={terapkanFilterSiap}
          aria-label="Saring gudang"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm"
        >
          <option value="">Semua gudang</option>
          {#each siap.gudangList || [] as g}
            <option value={String(g.id)}>{g.nama}</option>
          {/each}
          <option value="belum">Gudang belum diketahui</option>
        </select>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
            <HeroIcon name="magnifying-glass" class="h-4 w-4" />
          </div>
          <input
            type="text"
            bind:value={siapCari}
            on:input={cariSiapDitunda}
            placeholder="Cari kode kerdus"
            aria-label="Cari kode kerdus siap"
            class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
          />
        </div>
      </div>
    </div>

    {#if daftarSiap.length > 0}
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Kerdus</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Quran</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Isi (Satuan)</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QR Kerdus</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seal Code</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dipindai</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dipindai oleh</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            {#each daftarSiap as box (box.id)}
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <button
                    type="button"
                    on:click={() => bukaDetail(box)}
                    class="text-sm font-medium text-[#eb3434] hover:text-red-700 hover:underline text-left"
                    title="Lihat mushaf di dalam kerdus ini"
                  >
                    {box.kode_kerdus}
                  </button>
                  <div class="text-sm text-gray-500">{box.satuan?.label || `${box.item_count} pcs`} mushaf</div>
                  {#if box.gudang}<div class="text-xs text-gray-400">{box.gudang}</div>{/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {box.status_info.bg} {box.status_info.text}">
                    {box.status_info.label}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{box.jenis_quran}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                      <div class="h-2 rounded-full {warnaProgress(box.progress_percentage)}" style="width: {box.progress_percentage}%"></div>
                    </div>
                    <span class="text-sm text-gray-600">{box.terisi}/{box.kapasitas}</span>
                  </div>
                  <div class="text-xs text-gray-500">{box.progress_percentage}%</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{box.satuan?.label || '-'}</div>
                  <div class="text-xs text-gray-500">
                    dari {box.satuan_kapasitas?.label || box.kapasitas} / kerdus
                    {#if box.satuan_keterangan}<span class="text-gray-400">· {box.satuan_keterangan}</span>{/if}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  {#if box.qr_code_base64}
                    <img
                      src={box.qr_code_base64}
                      alt="QR {box.kode_kerdus}"
                      title="QR kerdus {box.kode_kerdus} — siap dipindai"
                      class="h-16 w-16 border border-gray-200 rounded bg-white p-1"
                    />
                  {:else}
                    <span class="text-xs text-gray-400">QR belum dibuat</span>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{box.user_name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {box.seal_code || '-'}
                  {#if box.sealed_at}
                    <div class="text-xs text-gray-500">{box.sealed_at}</div>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{box.created_at}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {#if box.dipindai_pada}
                    {box.dipindai_pada}
                  {:else}
                    <span class="text-gray-400">Belum dipindai</span>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {#if box.dipindai_metode}
                    <span class="inline-flex items-center gap-1.5">
                      <HeroIcon name={box.dipindai_metode === 'manual' ? 'pencil-square' : 'qr-code'} class="w-4 h-4 text-gray-400" />
                      {labelMetode[box.dipindai_metode] || box.dipindai_metode}
                    </span>
                  {:else}
                    <span class="text-gray-400">—</span>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{box.dipindai_oleh || '—'}</td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      {#if (siap.daftar?.last_page || 1) > 1}
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <Pagination data={siap.daftar} tanpaPemilihPerHalaman={true} />
        </div>
      {/if}
    {:else}
      <div class="p-8 text-center">
        <HeroIcon name="archive-box" class="w-10 h-10 text-gray-300 mx-auto mb-2" />
        <p class="text-sm text-gray-500">Tidak ada kerdus penuh/tersegel yang menunggu dimuat.</p>
      </div>
    {/if}
  </div>
</section>

<!-- Detail isi kerdus: mushaf (resi) di dalamnya + riwayat pindai kerdus ini. -->
{#if detailKode}
  <!-- z-[10000]: harus di atas sidebar (z-[9998]) dan header lengket (z-[9990]) AdminLayout. -->
  <div class="fixed inset-0 z-[10000] flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4" role="presentation" on:click|self={tutupDetail}>
    <div class="bg-white w-full sm:max-w-4xl max-h-[90vh] rounded-t-xl sm:rounded-xl shadow-xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="judul-detail-kerdus">
      <div class="px-6 py-4 border-b border-gray-200 flex items-start gap-3">
        <div class="flex-1 min-w-0">
          <h3 id="judul-detail-kerdus" class="text-lg font-semibold text-gray-900 font-mono">{detailKode}</h3>
          {#if detail}
            <p class="text-sm text-gray-500 mt-0.5">
              {detail.kerdus.jenis_quran} · {detail.kerdus.jumlah_mushaf} mushaf
              {#if detail.kerdus.gudang}· {detail.kerdus.gudang}{/if}
              · dikemas {detail.kerdus.user_name}
              {#if detail.kerdus.sealed_at}· disegel {detail.kerdus.sealed_at}{/if}
            </p>
          {/if}
        </div>
        {#if detail}
          <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {detail.kerdus.status_info.bg} {detail.kerdus.status_info.text}">
            {detail.kerdus.status_info.label}
          </span>
        {/if}
        <button type="button" on:click={tutupDetail} class="text-gray-400 hover:text-gray-600" aria-label="Tutup">
          <HeroIcon name="x-mark" class="w-5 h-5" />
        </button>
      </div>

      <div class="overflow-y-auto">
        {#if memuatDetail}
          <div class="p-6 space-y-3" aria-busy="true">
            {#each Array(5) as _}
              <div class="h-4 bg-gray-100 rounded animate-pulse"></div>
            {/each}
          </div>
        {:else if galatDetail}
          <p class="p-6 text-sm text-red-700">{galatDetail}</p>
        {:else if detail}
          {#if detail.mushaf.length === 0}
            <p class="p-6 text-sm text-gray-500">Kerdus ini belum berisi mushaf.</p>
          {:else}
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No Resi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakif</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donatur</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dikemas</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  {#each detail.mushaf as m, i (m.no_resi || i)}
                    <tr class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{m.urutan ?? i + 1}</td>
                      <td class="px-4 py-3 text-sm font-mono whitespace-nowrap">
                        {#if m.tracking_url}
                          <a href={m.tracking_url} target="_blank" rel="noopener" class="text-[#eb3434] hover:underline">{m.no_resi}</a>
                        {:else}
                          {m.no_resi || '—'}
                        {/if}
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                        {m.jenis || '—'}{#if m.jumlah > 1}<span class="text-gray-500"> × {m.jumlah}</span>{/if}
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-900">{m.wakif || '—'}</td>
                      <td class="px-4 py-3 text-sm text-gray-700">{m.donatur || '—'}</td>
                      <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{m.status || '—'}</td>
                      <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                        {m.dikemas_oleh || '—'}
                        {#if m.dikemas_pada}<div class="text-xs text-gray-500">{m.dikemas_pada}</div>{/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}

          <div class="px-6 py-4 border-t border-gray-200">
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Riwayat pindai kerdus ini</h4>
            {#if detail.riwayat_pindai.length === 0}
              <p class="text-sm text-gray-500">Belum pernah dipindai distribusi.</p>
            {:else}
              <ul class="divide-y divide-gray-100 text-sm">
                {#each detail.riwayat_pindai as r}
                  <li class="py-2 flex flex-wrap gap-x-4 gap-y-1">
                    <span class="text-gray-900 whitespace-nowrap">{r.waktu}</span>
                    <span class="text-gray-700">{labelMetode[r.metode] || r.metode}</span>
                    <span class="text-gray-700">{r.oleh}</span>
                    <span class="text-gray-500">{r.jumlah_mushaf} eks</span>
                    {#if r.catatan}<span class="text-gray-500">· {r.catatan}</span>{/if}
                  </li>
                {/each}
              </ul>
            {/if}
          </div>
        {/if}
      </div>
    </div>
  </div>
{/if}

