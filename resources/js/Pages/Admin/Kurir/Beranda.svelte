<script>
  /**
   * Beranda kurir — "Hari Ini & Rute".
   *
   * Di HP: muatan aktif, kartu geser tujuan yang belum dikunjungi, lalu daftar rute.
   * Di laptop (lg ke atas): rute di kiri, muatan & tujuan yang dituju di kanan.
   *
   * Kurir tidak selalu mengantar sesuai urutan muat (22/11/2026): kartu yang sedang
   * tampil adalah tujuan yang dituju, dan pilihannya diingat di HP. Mushaf yang diminta
   * di tengah jalan tidak menjadi tujuan baru — tampil di daftar "Diserahkan di jalan".
   *
   * Halaman ini hanya membaca. Memuat barang dan serah terima ada di halaman Pindai
   * (tombol-tombol di sini membukanya langsung pada mode yang tepat lewat ?mode=).
   */
  import { inertia } from '@inertiajs/svelte';
  import { onMount, tick } from 'svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { tujuanBelumDikunjungi, pilihDituju, bacaDituju, simpanDituju } from '../../../utils/tujuanKurir.js';

  /** @type {null | {id:number, kode_muatan:string, nama_muatan:?string, tanggal_muatan:?string, total_resi:number, total_mushaf:number, total_kerdus:number, jumlah_tujuan:number, jumlah_tujuan_tiba:number}} */
  export let muatan = null;
  /** @type {Array<{kunci:string, nama:string, penerima:?string, alamat:?string, no_hp:?string, jumlah_resi:number, jumlah_mushaf:number, jumlah_kerdus:number, diserahkan_di_jalan:number, status:'tiba'|'berikutnya'|'menunggu'|'di_jalan', tiba_pukul:?string}>} */
  export let tujuan = [];
  /** @type {Array<{nama:string, alamat:?string, jumlah_mushaf:number, dari:string, pukul:?string}>} */
  export let diJalan = [];

  let pilihan = muatan ? bacaDituju(muatan.id) : null;
  let slider;
  let raf = 0;

  $: belum = tujuanBelumDikunjungi(tujuan);
  $: dituju = pilihDituju(tujuan, pilihan);
  $: persen = muatan && muatan.jumlah_tujuan > 0 ? Math.round((muatan.jumlah_tujuan_tiba / muatan.jumlah_tujuan) * 100) : 0;

  onMount(async () => {
    await tick();
    gulirKe(dituju, 'auto');
  });

  function pilih(kunci, gulir = true) {
    pilihan = kunci;
    if (muatan) simpanDituju(muatan.id, kunci);
    if (gulir) gulirKe(kunci, 'smooth');
  }

  function kartuSlider() {
    return slider ? [...slider.querySelectorAll('[data-kunci]')] : [];
  }

  function gulirKe(kunci, behavior) {
    const kartu = kartuSlider();
    const el = kartu.find((k) => k.dataset.kunci === kunci);
    if (el) slider.scrollTo({ left: el.offsetLeft - kartu[0].offsetLeft, behavior });
  }

  // Kartu yang berhenti di tepi kiri menjadi tujuan yang dituju. Kartu terakhir tidak
  // bisa menempel ke kiri, jadi posisi gulir paling kanan berarti kartu terakhir.
  function saatGulir() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
      const kartu = kartuSlider();
      if (kartu.length < 2) return;
      const langkah = kartu[1].offsetLeft - kartu[0].offsetLeft || 1;
      const maks = slider.scrollWidth - slider.clientWidth;
      const ke = slider.scrollLeft >= maks - 4 ? kartu.length - 1 : Math.round(slider.scrollLeft / langkah);
      const kunci = kartu[Math.max(0, Math.min(kartu.length - 1, ke))].dataset.kunci;
      if (kunci !== dituju) pilih(kunci, false);
    });
  }

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
          {:else if belum.length === 0}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center space-y-1">
              <p class="text-sm font-semibold text-gray-900">Semua tujuan sudah dikunjungi</p>
              <p class="text-sm text-gray-500">Status Diterima dikonfirmasi tim distribusi.</p>
            </div>
          {:else}
            <div class="space-y-2">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#eb3434]">Belum dikunjungi · {belum.length} tujuan</p>
                {#if belum.length > 1}
                  <p class="text-xs text-gray-500">Geser untuk pilih tujuan lain</p>
                {/if}
              </div>

              <div
                bind:this={slider}
                on:scroll={saatGulir}
                class="geser flex gap-3 overflow-x-auto snap-x snap-mandatory scroll-px-6 -mx-6 px-6 pb-1 lg:mx-0 lg:px-0 lg:scroll-px-0"
                aria-label="Tujuan yang belum dikunjungi"
              >
                {#each belum as t, n (t.kunci)}
                  <article
                    data-kunci={t.kunci}
                    class="snap-start shrink-0 {belum.length > 1 ? 'w-[86%]' : 'w-full'} flex flex-col gap-3 bg-white rounded-xl shadow-sm border-2 p-4 transition-colors
                      {t.kunci === dituju ? 'border-[#eb3434]' : 'border-gray-200'}"
                  >
                    <p class="text-xs font-semibold uppercase tracking-wider {t.kunci === dituju ? 'text-[#eb3434]' : 'text-gray-500'}">
                      Tujuan {tujuan.indexOf(t) + 1} dari {tujuan.length}{t.status === 'berikutnya' ? ' · saran' : ''}
                    </p>
                    <div>
                      <h2 class="text-base font-bold text-gray-900">{t.nama}</h2>
                      {#if t.alamat}
                        <p class="text-sm text-gray-500">{t.alamat}</p>
                      {/if}
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700 tabular-nums">
                      {#if t.jumlah_kerdus > 0}
                        <span><b>{t.jumlah_kerdus}</b> kerdus</span>
                      {/if}
                      <span><b>{t.jumlah_mushaf}</b> mushaf</span>
                      {#if t.penerima}
                        <span>PIC <b>{t.penerima}</b></span>
                      {/if}
                    </div>
                    {#if t.diserahkan_di_jalan > 0}
                      <p class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5">
                        {t.diserahkan_di_jalan} mushaf diserahkan di jalan
                      </p>
                    {/if}
                    <div class="grid grid-cols-2 gap-2">
                      {#if t.alamat}
                        <a
                          href={linkPeta(t.alamat)}
                          target="_blank"
                          rel="noopener"
                          class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
                        >
                          <HeroIcon name="map-pin" class="w-4 h-4" />
                          Navigasi
                        </a>
                      {/if}
                      {#if t.no_hp}
                        <a
                          href="tel:{t.no_hp}"
                          class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
                          title={t.no_hp}
                        >
                          <HeroIcon name="phone" class="w-4 h-4" />
                          Telepon
                        </a>
                      {/if}
                    </div>
                    <a
                      href="/admin/kurir/pindai?mode=tujuan"
                      use:inertia
                      on:click={() => pilih(t.kunci, false)}
                      class="mt-auto w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-semibold text-white bg-[#eb3434] hover:bg-red-600 shadow-sm transition-colors"
                    >
                      <HeroIcon name="check-circle" class="w-5 h-5" />
                      Sampai di sini · Serah terima
                    </a>
                  </article>
                {/each}
              </div>

              {#if belum.length > 1}
                <div class="flex justify-center gap-1.5">
                  {#each belum as t (t.kunci)}
                    <button
                      type="button"
                      on:click={() => pilih(t.kunci)}
                      aria-label="Tuju {t.nama}"
                      aria-current={t.kunci === dituju}
                      class="h-1.5 rounded-full transition-all {t.kunci === dituju ? 'w-5 bg-[#eb3434]' : 'w-1.5 bg-gray-300'}"
                    ></button>
                  {/each}
                </div>
              {/if}
            </div>
          {/if}

          {#if diJalan.length > 0}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-2">
              <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                Diserahkan di jalan · bukan tujuan, tidak dihitung di progres
              </p>
              <ul class="space-y-2">
                {#each diJalan as d, i (i)}
                  <li class="flex items-center gap-3 rounded-lg border border-dashed border-amber-300 px-3 py-2">
                    <span class="w-8 h-8 shrink-0 rounded-full bg-amber-50 text-amber-700 flex items-center justify-center">
                      <HeroIcon name="hand-heart" class="w-4 h-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold text-gray-900">{d.nama}</p>
                      <p class="text-xs text-gray-500 tabular-nums">
                        {d.jumlah_mushaf} mushaf{d.dari ? ` · dari ${d.dari}` : ''}{d.alamat ? ` · ${d.alamat}` : ''}
                      </p>
                    </div>
                    {#if d.pukul}
                      <span class="text-xs text-gray-500 tabular-nums">{d.pukul}</span>
                    {/if}
                  </li>
                {/each}
              </ul>
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
              Diminta di jalan
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
                            : t.status === 'di_jalan'
                              ? 'bg-amber-50 border-amber-300 text-amber-700'
                              : t.kunci === dituju
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
                          {t.kunci === dituju ? 'border-red-200 bg-red-50' : 'border-gray-100 bg-white'}
                          {t.status === 'tiba' || t.status === 'di_jalan' ? 'opacity-75' : ''}"
                      >
                        <div class="flex items-start justify-between gap-2">
                          <p class="text-sm font-semibold text-gray-900">{t.nama}</p>
                          {#if t.status === 'tiba'}
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-green-50 text-green-700">
                              Tiba{t.tiba_pukul ? ` ${t.tiba_pukul}` : ''}
                            </span>
                          {:else if t.status === 'di_jalan'}
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Semua diserahkan di jalan</span>
                          {:else if t.kunci === dituju}
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-[#eb3434] text-white">Dituju</span>
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
                        {#if t.diserahkan_di_jalan > 0}
                          <p class="text-xs font-semibold text-amber-700">{t.diserahkan_di_jalan} mushaf diserahkan di jalan</p>
                        {/if}
                        {#if t.status === 'menunggu' || (t.status === 'berikutnya' && t.kunci !== dituju)}
                          <button
                            type="button"
                            on:click={() => pilih(t.kunci)}
                            class="mt-1 inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
                          >
                            <HeroIcon name="map-pin" class="w-3.5 h-3.5" />
                            Tuju ini
                          </button>
                        {/if}
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

<style>
  .geser {
    scrollbar-width: none;
    overscroll-behavior-x: contain;
  }
  .geser::-webkit-scrollbar {
    display: none;
  }
</style>
