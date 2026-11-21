<script>
  /**
   * Beranda kurir — "Hari Ini & Rute".
   *
   * Di HP: muatan aktif, tujuan berikutnya dengan satu tombol utama, lalu daftar rute.
   * Di laptop (lg ke atas): rute di kiri, muatan & tujuan berikutnya di kanan.
   *
   * Halaman ini hanya membaca. Memuat barang dan serah terima ada di halaman Pindai
   * (tombol-tombol di sini membukanya langsung pada mode yang tepat lewat ?mode=).
   */
  import { inertia } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';

  /** @type {null | {id:number, kode_muatan:string, nama_muatan:?string, tanggal_muatan:?string, total_resi:number, total_mushaf:number, total_kerdus:number, jumlah_tujuan:number, jumlah_tujuan_tiba:number}} */
  export let muatan = null;
  /** @type {Array<{kunci:string, nama:string, penerima:?string, alamat:?string, no_hp:?string, jumlah_resi:number, jumlah_mushaf:number, jumlah_kerdus:number, status:'tiba'|'berikutnya'|'menunggu', tiba_pukul:?string}>} */
  export let tujuan = [];

  $: berikutnya = tujuan.find((t) => t.status === 'berikutnya') || null;
  $: urutanBerikutnya = berikutnya ? tujuan.indexOf(berikutnya) + 1 : 0;
  $: persen = muatan && muatan.jumlah_tujuan > 0 ? Math.round((muatan.jumlah_tujuan_tiba / muatan.jumlah_tujuan) * 100) : 0;

  const hariIni = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  function formatTanggal(tanggal) {
    if (!tanggal) return '';
    return new Date(`${tanggal}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
  }

  function linkPeta(alamat) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(alamat)}`;
  }
</script>

<svelte:head>
  <title>Hari Ini - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="max-w-6xl mx-auto space-y-4">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Hari Ini</h1>
      <p class="text-sm text-gray-500">{hariIni}</p>
    </div>

    {#if !muatan}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sm:p-10 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mb-4">
          <HeroIcon name="truck" class="w-7 h-7 text-[#eb3434]" />
        </div>
        <h2 class="text-lg font-bold text-gray-900">Belum ada muatan yang perlu diantar</h2>
        <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">
          Muatan yang dibuat tim distribusi untuk Anda akan muncul di sini, lengkap dengan daftar tujuannya.
        </p>
        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
          <a
            href="/admin/kurir/pindai"
            use:inertia
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#eb3434] hover:bg-red-600 transition-colors"
          >
            <HeroIcon name="qr-code" class="w-4 h-4" />
            Buka Pindai
          </a>
          <a
            href="/admin/kurir/muatan"
            use:inertia
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
          >
            <HeroIcon name="clock" class="w-4 h-4" />
            Riwayat Muatan
          </a>
        </div>
      </div>
    {:else}
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <!-- Kanan di laptop, atas di HP: muatan + tujuan berikutnya -->
        <div class="space-y-4 lg:col-start-3 lg:row-start-1">
          <div class="rounded-xl bg-gradient-to-br from-[#eb3434] to-red-600 text-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/75">Muatan aktif</p>
                <p class="font-mono text-lg font-bold truncate">{muatan.kode_muatan}</p>
                {#if muatan.nama_muatan}
                  <p class="text-sm text-white/85 truncate">{muatan.nama_muatan}</p>
                {/if}
              </div>
              <span class="shrink-0 text-xs font-semibold bg-white/15 rounded-full px-2.5 py-1">
                {formatTanggal(muatan.tanggal_muatan)}
              </span>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 tabular-nums">
              <div class="bg-white/10 rounded-lg px-3 py-2">
                <p class="text-lg font-bold leading-tight">{muatan.jumlah_tujuan_tiba}/{muatan.jumlah_tujuan}</p>
                <p class="text-[11px] text-white/80">Tujuan tiba</p>
              </div>
              <div class="bg-white/10 rounded-lg px-3 py-2">
                <p class="text-lg font-bold leading-tight">{muatan.total_kerdus}</p>
                <p class="text-[11px] text-white/80">Kerdus</p>
              </div>
              <div class="bg-white/10 rounded-lg px-3 py-2">
                <p class="text-lg font-bold leading-tight">{muatan.total_mushaf}</p>
                <p class="text-[11px] text-white/80">Mushaf</p>
              </div>
            </div>

            <div class="mt-4">
              <div class="flex justify-between text-xs text-white/85 mb-1.5">
                <span>Progres antar</span>
                <span class="tabular-nums">{persen}%</span>
              </div>
              <div class="h-2 rounded-full bg-white/20 overflow-hidden">
                <div class="h-full rounded-full bg-white transition-all" style="width: {persen}%"></div>
              </div>
            </div>
          </div>

          {#if muatan.jumlah_tujuan === 0}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
              <p class="text-sm font-semibold text-gray-900">Muatan ini masih kosong</p>
              <p class="text-sm text-gray-500">Pindai kerdus atau mushaf yang akan Anda bawa supaya tujuannya muncul di sini.</p>
              <a
                href="/admin/kurir/pindai?mode=box"
                use:inertia
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-semibold text-white bg-[#eb3434] hover:bg-red-600 transition-colors"
              >
                <HeroIcon name="archive-box" class="w-4 h-4" />
                Pindai muat barang
              </a>
            </div>
          {:else if berikutnya}
            <div class="bg-white rounded-xl shadow-sm border-2 border-[#eb3434] p-4 space-y-3">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#eb3434]">
                  Tujuan berikutnya · {urutanBerikutnya} dari {tujuan.length}
                </p>
              </div>
              <div>
                <h2 class="text-base font-bold text-gray-900">{berikutnya.nama}</h2>
                {#if berikutnya.alamat}
                  <p class="text-sm text-gray-500">{berikutnya.alamat}</p>
                {/if}
              </div>
              <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700 tabular-nums">
                {#if berikutnya.jumlah_kerdus > 0}
                  <span><b>{berikutnya.jumlah_kerdus}</b> kerdus</span>
                {/if}
                <span><b>{berikutnya.jumlah_mushaf}</b> mushaf</span>
                {#if berikutnya.penerima}
                  <span>PIC <b>{berikutnya.penerima}</b></span>
                {/if}
              </div>
              <div class="grid grid-cols-2 gap-2">
                {#if berikutnya.alamat}
                  <a
                    href={linkPeta(berikutnya.alamat)}
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
                  >
                    <HeroIcon name="map-pin" class="w-4 h-4" />
                    Navigasi
                  </a>
                {/if}
                {#if berikutnya.no_hp}
                  <a
                    href="tel:{berikutnya.no_hp}"
                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
                    title={berikutnya.no_hp}
                  >
                    <HeroIcon name="phone" class="w-4 h-4" />
                    Telepon
                  </a>
                {/if}
              </div>
              <a
                href="/admin/kurir/pindai?mode=tujuan"
                use:inertia
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-semibold text-white bg-[#eb3434] hover:bg-red-600 shadow-sm transition-colors"
              >
                <HeroIcon name="check-circle" class="w-5 h-5" />
                Saya sudah sampai · Serah terima
              </a>
            </div>
          {/if}

          <div class="grid grid-cols-3 gap-2">
            <a
              href="/admin/kurir/pindai?mode=box"
              use:inertia
              class="flex flex-col items-center gap-1.5 rounded-xl bg-white border border-gray-100 shadow-sm px-2 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
              <HeroIcon name="archive-box" class="w-5 h-5 text-[#eb3434]" />
              Pindai muat
            </a>
            <a
              href="#rute"
              class="flex flex-col items-center gap-1.5 rounded-xl bg-white border border-gray-100 shadow-sm px-2 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors lg:hidden"
            >
              <HeroIcon name="map" class="w-5 h-5 text-[#eb3434]" />
              Lihat rute
            </a>
            <a
              href="/admin/kurir/pindai?mode=tengah_jalan"
              use:inertia
              class="flex flex-col items-center gap-1.5 rounded-xl bg-white border border-gray-100 shadow-sm px-2 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
              <HeroIcon name="user" class="w-5 h-5 text-[#eb3434]" />
              Tengah jalan
            </a>
            <a
              href="/admin/muatan/{muatan.id}"
              use:inertia
              class="flex flex-col items-center gap-1.5 rounded-xl bg-white border border-gray-100 shadow-sm px-2 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors lg:col-span-2"
            >
              <HeroIcon name="document-text" class="w-5 h-5 text-[#eb3434]" />
              Detail muatan
            </a>
          </div>
        </div>

        <!-- Kiri di laptop, bawah di HP: daftar rute -->
        <section id="rute" class="scroll-mt-20 lg:col-span-2 lg:col-start-1 lg:row-start-1">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-100">
              <div>
                <h2 class="text-sm font-bold text-gray-900">Rute hari ini</h2>
                <p class="text-xs text-gray-500">{tujuan.length} tujuan · urut sesuai muatan</p>
              </div>
              {#if muatan.jumlah_tujuan_tiba > 0}
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-green-50 text-green-700 border border-green-200">
                  {muatan.jumlah_tujuan_tiba} tiba
                </span>
              {/if}
            </div>

            {#if tujuan.length === 0}
              <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada tujuan. Tujuan muncul setelah barang dipindai ke muatan.</p>
            {:else}
              <ol class="p-4">
                {#each tujuan as t, i (t.kunci)}
                  <li class="grid grid-cols-[28px_minmax(0,1fr)] gap-3">
                    <div class="flex flex-col items-center">
                      <span
                        class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-xs font-bold border-2
                          {t.status === 'tiba'
                            ? 'bg-green-600 border-green-600 text-white'
                            : t.status === 'berikutnya'
                              ? 'bg-[#eb3434] border-[#eb3434] text-white'
                              : 'bg-white border-gray-200 text-gray-500'}"
                      >
                        {#if t.status === 'tiba'}
                          <HeroIcon name="check" class="w-3.5 h-3.5" />
                        {:else}
                          {i + 1}
                        {/if}
                      </span>
                      {#if i < tujuan.length - 1}
                        <span class="flex-1 w-0.5 bg-gray-200 my-1"></span>
                      {/if}
                    </div>

                    <div class="pb-4 min-w-0">
                      <div
                        class="rounded-lg border p-3 space-y-1
                          {t.status === 'berikutnya' ? 'border-red-200 bg-red-50' : 'border-gray-100 bg-white'}
                          {t.status === 'tiba' ? 'opacity-75' : ''}"
                      >
                        <div class="flex items-start justify-between gap-2">
                          <p class="text-sm font-semibold text-gray-900">{t.nama}</p>
                          {#if t.status === 'tiba'}
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-green-50 text-green-700">
                              Tiba{t.tiba_pukul ? ` ${t.tiba_pukul}` : ''}
                            </span>
                          {:else if t.status === 'berikutnya'}
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-[#eb3434] text-white">Berikutnya</span>
                          {:else}
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Menunggu</span>
                          {/if}
                        </div>
                        {#if t.alamat}
                          <p class="text-xs text-gray-500">{t.alamat}</p>
                        {/if}
                        <p class="text-xs text-gray-600 tabular-nums">
                          {#if t.jumlah_kerdus > 0}<b>{t.jumlah_kerdus}</b> kerdus · {/if}<b>{t.jumlah_mushaf}</b> mushaf · {t.jumlah_resi} resi
                        </p>
                      </div>
                    </div>
                  </li>
                {/each}
              </ol>
            {/if}
          </div>
        </section>
      </div>
    {/if}
  </div>
</AdminLayout>
