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
  let mode = 'pcs'; // 'pcs' | 'box'
  let memindai = false; // mengunci agar satu QR tidak terkirim berkali-kali
  let inputManual = '';
  let kameraAktif = false;

  let hasilTerakhir = null;
  let riwayat = [];
  let totalResi = muatanHariIni.length > 0 ? muatanHariIni[0].total_resi : 0;
  let totalMushaf = muatanHariIni.length > 0 ? muatanHariIni[0].total_mushaf : 0;

  function pilihMuatan(e) {
    muatanTerpilih = e.target.value;
    const semua = [...muatanHariIni, ...muatanLain];
    const m = semua.find((x) => String(x.id) === String(muatanTerpilih));
    totalResi = m ? m.total_resi : 0;
    totalMushaf = m ? m.total_mushaf : 0;
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
    if (memindai) return;
    const teks = e.detail?.text || '';
    if (teks.trim()) kirimPindai(teks);
  }

  function gantiMode(m) {
    mode = m;
    hasilTerakhir = null;
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
            Pindai QR per-pcs (satu resi) atau per-box (satu kerdus sekaligus) untuk memuatnya ke muatan.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kiri: scanner -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Pilih muatan tujuan -->
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

          <!-- Mode -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <div class="grid grid-cols-2 gap-3 mb-4">
              <button
                on:click={() => gantiMode('pcs')}
                class="flex flex-col items-center gap-1.5 p-4 rounded-lg border-2 transition-colors
                  {mode === 'pcs' ? 'border-[#eb3434] bg-red-50' : 'border-gray-200 hover:border-gray-300'}"
              >
                <HeroIcon name="qr-code" class="w-6 h-6 {mode === 'pcs' ? 'text-[#eb3434]' : 'text-gray-400'}" />
                <span class="text-sm font-medium {mode === 'pcs' ? 'text-[#eb3434]' : 'text-gray-700'}">Per PCS</span>
                <span class="text-xs text-gray-500">Satu resi</span>
              </button>

              <button
                on:click={() => gantiMode('box')}
                class="flex flex-col items-center gap-1.5 p-4 rounded-lg border-2 transition-colors
                  {mode === 'box' ? 'border-[#eb3434] bg-red-50' : 'border-gray-200 hover:border-gray-300'}"
              >
                <HeroIcon name="archive-box" class="w-6 h-6 {mode === 'box' ? 'text-[#eb3434]' : 'text-gray-400'}" />
                <span class="text-sm font-medium {mode === 'box' ? 'text-[#eb3434]' : 'text-gray-700'}">Per Kerdus</span>
                <span class="text-xs text-gray-500">Seisi box sekaligus</span>
              </button>
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
                Atau ketik kode manual
              </label>
              <div class="flex gap-2">
                <input
                  id="input-manual"
                  type="text"
                  bind:value={inputManual}
                  on:keydown={(e) => e.key === 'Enter' && kirimPindai(inputManual)}
                  placeholder={mode === 'box' ? 'KB-20261005-...' : 'EQ-2026-00001'}
                  class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] text-sm font-mono"
                />
                <button
                  on:click={() => kirimPindai(inputManual)}
                  disabled={memindai || !inputManual.trim()}
                  class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                >
                  {memindai ? '...' : 'Pindai'}
                </button>
              </div>
            </div>
          </div>
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
