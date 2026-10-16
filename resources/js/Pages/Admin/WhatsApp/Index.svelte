<script>
  import { router, page } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import PerPageSelector from '../../../Components/PerPageSelector.svelte';
  import { hasPermission } from '../../../utils/permissions.js';

  export let pengaturan = null;
  export let templates = [];
  export let notifikasi = {};
  export let ringkasan = { menunggu: 0, terkirim: 0, gagal: 0 };
  export let perPage = 20;
  export let perPageOptions = [10, 20, 50, 100, 200];

  const bolehUbahPengaturan = hasPermission('whatsapp.settings.write');
  const bolehUbahTemplate = hasPermission('whatsapp.templates.write');
  const bolehUjiKirim = hasPermission('whatsapp.system.test');
  const bolehKirimUlang = hasPermission('whatsapp.notifications.send');

  // Tab aktif dibaca dari URL, bukan disimpan di memori: pindah halaman pada
  // tabel antrean akan memuat ulang komponen, dan tab yang hilang terasa seperti
  // halaman yang salah.
  $: activeTab = new URL($page.url, 'http://x').searchParams.get('tab') || 'pengaturan';

  function pindahTab(tab) {
    router.get('/admin/whatsapp', { tab, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function gantiPerHalaman() {
    router.get('/admin/whatsapp', { tab: activeTab, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
  }

  // --- Pengaturan ---
  let menyimpanPengaturan = false;
  let form = {
    base_url: pengaturan?.base_url ?? 'https://api.starsender.online',
    sender_number: pengaturan?.sender_number ?? '',
    is_active: pengaturan?.is_active ?? false,
    delay_seconds: pengaturan?.delay_seconds ?? 3,
    max_per_minute: pengaturan?.max_per_minute ?? 20,
    quiet_hours_start: pengaturan?.quiet_hours_start ?? '',
    quiet_hours_end: pengaturan?.quiet_hours_end ?? '',
    api_key: ''
  };

  function simpanPengaturan() {
    menyimpanPengaturan = true;
    router.put('/admin/whatsapp/pengaturan', form, {
      preserveScroll: true,
      onSuccess: () => {
        // Kunci API tidak pernah dikirim balik oleh server, jadi kolomnya
        // dikosongkan lagi supaya tidak ada yang mengira nilainya tersimpan
        // di layar.
        form.api_key = '';
        menyimpanPengaturan = false;
      },
      onError: () => { menyimpanPengaturan = false; }
    });
  }

  // --- Uji kirim ---
  let mengujiKirim = false;
  let formUji = { nomor: '', pesan: '' };

  function ujiKirim() {
    mengujiKirim = true;
    router.post('/admin/whatsapp/uji-kirim', formUji, {
      preserveScroll: true,
      onSuccess: () => { mengujiKirim = false; },
      onError: () => { mengujiKirim = false; }
    });
  }

  // --- Template ---
  let sunting = null;

  function mulaiSunting(template) {
    sunting = { id: template.id, title: template.title, content: template.content, is_active: template.is_active };
  }

  function simpanTemplate() {
    router.put(`/admin/whatsapp/template/${sunting.id}`, {
      title: sunting.title,
      content: sunting.content,
      is_active: sunting.is_active
    }, {
      preserveScroll: true,
      onSuccess: () => { sunting = null; }
    });
  }

  function jadikanAktif(template, aktif) {
    router.put(`/admin/whatsapp/template/${template.id}`, {
      title: template.title,
      content: template.content,
      is_active: aktif
    }, { preserveScroll: true });
  }

  // --- Antrean ---
  function kirimUlang(item) {
    router.post(`/admin/whatsapp/notifikasi/${item.id}/kirim-ulang`, {}, { preserveScroll: true });
  }

  function labelStatus(status) {
    return { menunggu: 'Menunggu', terkirim: 'Terkirim', gagal: 'Gagal', dilewati: 'Dilewati' }[status] || status;
  }

  function kelasTitik(status) {
    return {
      menunggu: 'bg-amber-500',
      terkirim: 'bg-emerald-500',
      gagal: 'bg-red-500',
      dilewati: 'bg-gray-400'
    }[status] || 'bg-gray-400';
  }

  const daftarTab = [
    { id: 'pengaturan', label: 'Pengaturan' },
    { id: 'template', label: 'Template Pesan' },
    { id: 'antrean', label: 'Antrean & Riwayat' }
  ];
</script>

<svelte:head>
  <title>Notifikasi WhatsApp</title>
</svelte:head>

<AdminLayout>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Notifikasi WhatsApp</h1>
      <p class="mt-1 text-sm text-gray-500">
        Pesan otomatis ke donatur lewat StarSender. Notifikasi tidak pernah menggagalkan alur pengiriman —
        kegagalannya tercatat di sini untuk dikirim ulang.
      </p>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          <span class="text-sm text-gray-500">Menunggu</span>
        </div>
        <p class="mt-2 text-2xl font-semibold text-gray-900">{ringkasan.menunggu}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span class="text-sm text-gray-500">Terkirim</span>
        </div>
        <p class="mt-2 text-2xl font-semibold text-gray-900">{ringkasan.terkirim}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red-500"></span>
          <span class="text-sm text-gray-500">Gagal</span>
        </div>
        <p class="mt-2 text-2xl font-semibold text-gray-900">{ringkasan.gagal}</p>
      </div>
    </div>

    <!-- Tab -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex gap-6">
        {#each daftarTab as tab}
          <button
            on:click={() => pindahTab(tab.id)}
            class="pb-3 text-sm font-medium border-b-2 transition-colors {activeTab === tab.id
              ? 'border-[#eb3434] text-gray-900'
              : 'border-transparent text-gray-500 hover:text-gray-700'}"
          >
            {tab.label}
          </button>
        {/each}
      </nav>
    </div>

    {#if activeTab === 'pengaturan'}
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Sambungan StarSender</h2>

            <div class="space-y-4">
              <div>
                <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1">Kunci API Device</label>
                <input
                  id="api_key"
                  type="password"
                  bind:value={form.api_key}
                  disabled={!bolehUbahPengaturan}
                  placeholder={pengaturan?.kunci_terisi ? '•••••• (sudah terisi — isi hanya bila ingin mengganti)' : 'Belum diisi'}
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50"
                />
                <p class="mt-1 text-xs text-gray-500">
                  Diambil dari menu Device di dashboard StarSender, bukan dari menu profil.
                  Kosongkan bila tidak ingin mengubah. Nilainya disimpan terenkripsi dan tidak pernah ditampilkan kembali.
                </p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="base_url" class="block text-sm font-medium text-gray-700 mb-1">Alamat dasar</label>
                  <input id="base_url" type="text" bind:value={form.base_url} disabled={!bolehUbahPengaturan}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
                </div>
                <div>
                  <label for="sender_number" class="block text-sm font-medium text-gray-700 mb-1">Nomor pengirim (catatan)</label>
                  <input id="sender_number" type="text" bind:value={form.sender_number} disabled={!bolehUbahPengaturan}
                    placeholder="628123456789"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="delay_seconds" class="block text-sm font-medium text-gray-700 mb-1">Jeda antar pesan (detik)</label>
                  <input id="delay_seconds" type="number" min="0" max="120" bind:value={form.delay_seconds} disabled={!bolehUbahPengaturan}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
                </div>
                <div>
                  <label for="max_per_minute" class="block text-sm font-medium text-gray-700 mb-1">Batas pesan per menit</label>
                  <input id="max_per_minute" type="number" min="1" max="600" bind:value={form.max_per_minute} disabled={!bolehUbahPengaturan}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="quiet_hours_start" class="block text-sm font-medium text-gray-700 mb-1">Jam tenang — mulai</label>
                  <input id="quiet_hours_start" type="time" bind:value={form.quiet_hours_start} disabled={!bolehUbahPengaturan}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
                </div>
                <div>
                  <label for="quiet_hours_end" class="block text-sm font-medium text-gray-700 mb-1">Jam tenang — selesai</label>
                  <input id="quiet_hours_end" type="time" bind:value={form.quiet_hours_end} disabled={!bolehUbahPengaturan}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
                </div>
              </div>
              <p class="text-xs text-gray-500">
                Kosongkan jam tenang bila pesan boleh dikirim kapan saja. Kalau diisi, pesan yang muncul di rentang itu
                ditahan dan dikirim menyusul oleh perintah terjadwal — berguna karena penutupan batch berjalan pukul 02.30.
              </p>

              <label class="flex items-center gap-3 pt-2">
                <input type="checkbox" bind:checked={form.is_active} disabled={!bolehUbahPengaturan}
                  class="rounded border-gray-300 text-[#eb3434] focus:ring-[#eb3434]" />
                <span class="text-sm text-gray-700">Aktifkan pengiriman notifikasi</span>
              </label>
            </div>

            {#if bolehUbahPengaturan}
              <div class="mt-6 flex justify-end">
                <button
                  on:click={simpanPengaturan}
                  disabled={menyimpanPengaturan}
                  class="inline-flex items-center gap-2 rounded-lg bg-[#eb3434] px-4 py-2 text-sm font-medium text-white hover:bg-[#d42c2c] disabled:opacity-60"
                >
                  <HeroIcon name="check" class="w-4 h-4" />
                  {menyimpanPengaturan ? 'Menyimpan...' : 'Simpan pengaturan'}
                </button>
              </div>
            {/if}
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Uji kirim</h2>
            <p class="text-xs text-gray-500 mb-4">
              Gerbangnya: kalau satu pesan uji tidak sampai, sisa fiturnya belum bisa dipakai.
            </p>

            <div class="space-y-4">
              <div>
                <label for="uji_nomor" class="block text-sm font-medium text-gray-700 mb-1">Nomor tujuan</label>
                <input id="uji_nomor" type="text" bind:value={formUji.nomor} placeholder="08123456789" disabled={!bolehUjiKirim}
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50" />
              </div>
              <div>
                <label for="uji_pesan" class="block text-sm font-medium text-gray-700 mb-1">Isi pesan (opsional)</label>
                <textarea id="uji_pesan" rows="3" bind:value={formUji.pesan} disabled={!bolehUjiKirim}
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434] disabled:bg-gray-50"></textarea>
              </div>
              {#if bolehUjiKirim}
                <button
                  on:click={ujiKirim}
                  disabled={mengujiKirim || !formUji.nomor}
                  class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-[#eb3434] px-4 py-2 text-sm font-medium text-white hover:bg-[#d42c2c] disabled:opacity-60"
                >
                  <HeroIcon name="paper-airplane" class="w-4 h-4" />
                  {mengujiKirim ? 'Mengirim...' : 'Kirim pesan uji'}
                </button>
              {/if}
            </div>
          </div>
        </div>
      </div>

    {:else if activeTab === 'template'}
      <div class="space-y-4">
        {#if templates.length === 0}
          <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-sm text-gray-500">
            Belum ada template. Jalankan <span class="font-mono text-xs">php artisan db:seed --class=WhatsAppTemplateSeeder</span> untuk mengisi bawaan.
          </div>
        {/if}

        {#each templates as template (template.id)}
          <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex items-center gap-3 mb-1">
                  <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600">
                    <span class="w-2 h-2 rounded-full {template.is_active ? 'bg-emerald-500' : 'bg-gray-400'}"></span>
                    {template.is_active ? 'Aktif' : 'Nonaktif'}
                  </span>
                  <span class="font-mono text-xs text-gray-400">{template.name}</span>
                </div>
                <h3 class="text-sm font-semibold text-gray-900">{template.title}</h3>
                {#if template.description}
                  <p class="mt-1 text-xs text-gray-500">{template.description}</p>
                {/if}
              </div>

              <div class="flex items-center gap-2 shrink-0">
                {#if bolehUbahTemplate}
                  <button
                    on:click={() => jadikanAktif(template, !template.is_active)}
                    class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    title={template.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                  >
                    <HeroIcon name={template.is_active ? 'bell-slash' : 'bell'} class="w-4 h-4" />
                  </button>
                  <button on:click={() => mulaiSunting(template)}
                    class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Sunting">
                    <HeroIcon name="pencil-square" class="w-4 h-4" />
                  </button>
                {/if}
              </div>
            </div>

            {#if sunting && sunting.id === template.id}
              <div class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Judul</label>
                  <input type="text" bind:value={sunting.title}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434]" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Isi pesan</label>
                  <textarea rows="6" bind:value={sunting.content}
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-[#eb3434] focus:ring-1 focus:ring-[#eb3434]"></textarea>
                  <p class="mt-1 text-xs text-gray-500">
                    Boleh memakai placeholder:
                    {#each template.variables as variabel}
                      <span class="font-mono bg-gray-100 rounded px-1 mr-1">{`{${variabel}}`}</span>
                    {/each}
                  </p>
                </div>
                <div class="flex justify-end gap-2">
                  <button on:click={() => (sunting = null)}
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                  </button>
                  <button on:click={simpanTemplate}
                    class="rounded-lg bg-[#eb3434] px-4 py-2 text-sm font-medium text-white hover:bg-[#d42c2c]">
                    Simpan
                  </button>
                </div>
              </div>
            {:else}
              <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-medium text-gray-500 mb-2">Pratinjau yang akan diterima wakif</p>
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-3">
                  <pre class="whitespace-pre-wrap break-words text-xs text-gray-700 font-sans">{template.contoh}</pre>
                </div>
              </div>
            {/if}
          </div>
        {/each}
      </div>

    {:else}
      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        {#if !notifikasi.data || notifikasi.data.length === 0}
          <div class="p-8 text-center text-sm text-gray-500">Belum ada notifikasi.</div>
        {:else}
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Penerima</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Peristiwa</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Waktu</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                {#each notifikasi.data as item (item.id)}
                  <tr class="hover:bg-gray-50 align-top">
                    <td class="px-4 py-3 whitespace-nowrap">
                      <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700">
                        <span class="w-2 h-2 rounded-full {kelasTitik(item.status)}"></span>
                        {labelStatus(item.status)}
                      </span>
                      {#if item.attempts > 0}
                        <span class="block text-xs text-gray-400 mt-0.5">Percobaan {item.attempts}</span>
                      {/if}
                    </td>
                    <td class="px-4 py-3">
                      <span class="block text-gray-900">{item.recipient_name || 'Tanpa nama'}</span>
                      <span class="block text-xs text-gray-500 font-mono">{item.recipient}</span>
                      {#if item.no_resi}
                        <span class="block text-xs text-gray-400 font-mono">{item.no_resi}</span>
                      {/if}
                    </td>
                    <td class="px-4 py-3">
                      <span class="block font-mono text-xs text-gray-500">{item.event_key}</span>
                      <span class="block text-xs text-gray-400 mt-0.5 max-w-md truncate" title={item.body}>{item.body}</span>
                      {#if item.error_message}
                        <span class="block text-xs text-red-600 mt-1 max-w-md">{item.error_message}</span>
                      {/if}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">
                      {item.sent_at || item.created_at}
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                      {#if bolehKirimUlang && item.status !== 'terkirim'}
                        <button on:click={() => kirimUlang(item)}
                          class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                          title="Kirim ulang">
                          <HeroIcon name="arrow-path" class="w-3.5 h-3.5" />
                          Kirim ulang
                        </button>
                      {/if}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}
      </div>

      <div class="mt-4 flex items-center justify-between">
        <PerPageSelector bind:perPage options={perPageOptions} id="per_page_whatsapp" onchange={gantiPerHalaman} />
        {#if notifikasi.last_page > 1}
          <nav class="flex items-center gap-1">
            {#each Array.from({ length: notifikasi.last_page }, (_, i) => i + 1) as halaman}
              <button
                on:click={() => router.get('/admin/whatsapp', { tab: 'antrean', per_page: perPage, page: halaman }, { preserveState: true, preserveScroll: true })}
                class="px-3 py-1.5 text-sm rounded-md {halaman === notifikasi.current_page ? 'bg-[#eb3434] text-white' : 'text-gray-700 hover:bg-gray-100'}"
              >
                {halaman}
              </button>
            {/each}
          </nav>
        {/if}
      </div>
    {/if}
  </div>
</AdminLayout>
