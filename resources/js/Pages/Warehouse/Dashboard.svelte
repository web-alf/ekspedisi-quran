<script>
  import { onMount } from 'svelte';
  import { page } from '@inertiajs/svelte';
  import { router } from '@inertiajs/svelte';
  import { showError } from '../../stores/toast.js';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import SharedBoxCollaboration from '../../Components/SharedBoxCollaboration.svelte';
  import SealReadyBoxes from '../../Components/SealReadyBoxes.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
  import RingkasanSection from '../../Components/Warehouse/RingkasanSection.svelte';
  import StatTile from '../../Components/Warehouse/StatTile.svelte';
  import PilTitik from '../../Components/Warehouse/PilTitik.svelte';
  import KartuStok from '../../Components/Warehouse/KartuStok.svelte';
  import PerluTindakan from '../../Components/Warehouse/PerluTindakan.svelte';
  import FreelancerKerjaHariIni from '../../Components/Warehouse/FreelancerKerjaHariIni.svelte';
  import { hasPermission } from '../../utils/permissions.js';
  import { can } from '../../utils/permissions.js';

  // Boleh memulai/melanjutkan packing? Endpoint /start-scanning dijaga izin
  // warehouse.packing.scan, jadi tanpa izin itu tombolnya hanya akan menghasilkan
  // 403 di konsol browser. Halaman ini sendiri dijaga warehouse.dashboard, izin
  // yang juga dimiliki manager untuk memantau — jadi izin itu tidak cukup untuk
  // menyimpulkan bahwa tombolnya boleh ditampilkan. Tombol yang pasti gagal lebih
  // buruk daripada tidak ada tombol sama sekali: pengguna mengira aplikasinya rusak.
  $: canStartPacking = hasPermission('warehouse.packing.scan');

  // Halaman ini juga dibuka oleh peran yang hanya memantau, tanpa
  // `warehouse.boxes.view`. Tautan ke Pelacakan Kerdus hanya ditampilkan bila
  // halamannya memang boleh dibuka — tautan yang pasti 403 lebih buruk daripada
  // tidak ada tautan sama sekali.
  $: bolehLacakKerdus = can.warehouse.boxes.view();
  
  // Props - FIXED: Added missing props
  export let todayTask = null;
  export let activeBox = null;
  export let notifications = [];
  export let performanceData = {};
  // Daftar kerdus milik staf ini. Server sudah lama mengirim prop ini
  // (DashboardController::getRecentBoxesForUser), tetapi halaman tidak pernah
  // mendeklarasikannya sehingga hasilnya dibuang tanpa pesan: kerdus yang
  // isinya sudah jalan tidak terlihat di mana pun di dashboard ini.
  export let recentBoxes = [];
  // Unused incoming props removed to silence build warnings
  export let currentTime = '';
  export let currentDate = '';
  
  export let sharedBoxes = [];  // ADDED for collaboration
  // Ringkasan gudang untuk SPV (18/10/2026): null untuk staf — bagian ini
  // hanya muncul bagi akun SPV yang bergudang.
  export let ringkasanGudang = null;
  export let ringkasanManager = null;

  // Status aktif kartu stok (manager): 'semua' / id gudang.
  let stokAktif = 'semua';

  // Urutan tahap beban gudang — sama untuk SPV dan Manager.
  const TAHAP_BEBAN = [
    ['pemesanan', 'Pemesanan'],
    ['packing', 'Packing'],
    ['selesai_packing', 'Selesai packing'],
    ['dalam_kerdus', 'Dalam kerdus'],
  ];

  function totalBeban(kunci) {
    return (ringkasanManager?.per_gudang ?? []).reduce((n, g) => n + (g.beban?.[kunci] ?? 0), 0);
  }

  // SPV & Manager hanya memantau: kartu pribadi staf (tugas, kerdus, performa)
  // tidak relevan bagi mereka kecuali memang ikut mengerjakan tugas packing.
  $: adalahPemantau = Boolean(ringkasanGudang || ringkasanManager);

  // Dashboard SPV menampilkan seluruh freelancer yang ditugaskan hari ini (termasuk yang belum mulai);
  // yang ditugaskan tapi belum mulai berstatus "Belum mulai" persis seperti di halaman Performa Kerja.
  $: stafKerjaHariIni = (ringkasanGudang?.packing?.per_staf ?? []).map((s) => ({
    ...s,
    kode: s.kode ?? ringkasanGudang?.gudang?.kode,
  }));

  $: persenPacking = ringkasanGudang?.packing?.target
    ? Math.round((ringkasanGudang.packing.selesai / ringkasanGudang.packing.target) * 100)
    : 0;

  // ===== Turunan ringkasan dua gudang (Manager / PJ, 22/10/2026) =====
  // Nilai untuk kartu "Ringkasan Packing Hari Ini" (gabungan) dan "Performa
  // Gudang" (per gudang). Gudang diurut dari persen tertinggi supaya orang
  // gudang langsung melihat yang terdepan, bukan urutan dari database.
  const DONUT_KELILING = 2 * Math.PI * 27; // keliling donut SVG r=27

  $: gabunganTarget = ringkasanManager?.gabungan?.target ?? 0;
  $: gabunganSelesai = ringkasanManager?.gabungan?.selesai ?? 0;
  $: pctGabungan = gabunganTarget > 0 ? Math.round((gabunganSelesai / gabunganTarget) * 100) : 0;
  $: sisaGabungan = Math.max(0, gabunganTarget - gabunganSelesai);
  $: perGudangUrut = ringkasanManager
    ? [...ringkasanManager.per_gudang].sort(
        (a, b) => (b.persen - a.persen) || (b.selesai - a.selesai) || a.nama.localeCompare(b.nama)
      )
    : [];
  // Bar bertumpuk kartu gabungan: kontribusi tiap gudang terhadap target
  // gabungan, di-clamp supaya hasil di atas target tidak meluber.
  $: segGabungan = (() => {
    if (gabunganTarget <= 0) return [0, 0];
    const segA = Math.max(0, Math.min(100, ((perGudangUrut[0]?.selesai ?? 0) / gabunganTarget) * 100));
    const segB = Math.max(0, Math.min(100 - segA, ((perGudangUrut[1]?.selesai ?? 0) / gabunganTarget) * 100));
    return [segA, segB];
  })();
  // Sama dengan SPV: seluruh freelancer yang ditugaskan hari ini, diberi kode gudangnya.
  $: stafKerjaManager = perGudangUrut.flatMap((g) => (g.staf_kerja || []).map((st) => ({ ...st, kode: st.kode ?? g.kode })));

  // Palet Tailwind (bukan hex lepas) supaya seragam dengan halaman lain.
  // Ditulis utuh agar tidak dibuang purge Tailwind.
  function warnaGudang(i) {
    return i === 0
      ? { bg: 'bg-indigo-500', stroke: 'stroke-indigo-500' }
      : { bg: 'bg-indigo-300', stroke: 'stroke-indigo-300' };
  }

  function donutOffset(persen) {
    const p = Math.max(0, Math.min(100, persen || 0));
    return DONUT_KELILING * (1 - p / 100);
  }

  // "Terdepan hari ini" hanya bila persennya beda dan memang di atas 0.
  function tampilTerdepan(i) {
    if (i !== 0 || perGudangUrut.length < 2) return false;
    return perGudangUrut[0].persen > 0 && perGudangUrut[0].persen !== perGudangUrut[1].persen;
  }
  
  // State
  let showNotifications = false;
  // Penghitung poling 15 detik: tiap tick ke-4 ikut meminta ?ringkasan=1.
  let hitungRefreshRingkasan = 0;
  let isStartingTask = false;
  let showSharedBoxCollaboration = false;
  
  // Progress colors
  function getProgressColor(percentage) {
    if (percentage >= 80) return 'bg-green-600';
    if (percentage >= 60) return 'bg-yellow-500';
    if (percentage >= 40) return 'bg-orange-500';
    return 'bg-red-500';
  }
  
  // Get status badge
  function getStatusBadge(status) {
    const badges = {
      'assigned': { text: 'Belum Mulai', class: 'bg-gray-100 text-gray-800' },
      'in_progress': { text: 'Sedang Berjalan', class: 'bg-blue-100 text-blue-800' },
      'completed': { text: 'Selesai', class: 'bg-green-100 text-green-800' },
      'expired': { text: 'Kadaluarsa', class: 'bg-red-100 text-red-800' }
    };
    return badges[status] || badges.assigned;
  }
  
  // Start scanning
  async function startScanning() {
    if (isStartingTask) return;
    
    isStartingTask = true;
    try {
      const response = await fetch('/admin/warehouse/start-scanning', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Redirect to packing page
        router.visit('/admin/warehouse/packing');
      } else {
        showError('Gagal Memulai Scanning', data.message || 'Gagal memulai scanning');
      }
    } catch (error) {
      console.error('Error starting task:', error);
      showError('Kesalahan', 'Terjadi kesalahan saat memulai tugas');
    } finally {
      isStartingTask = false;
    }
  }
  
  // Mark notification as read
  async function markNotificationRead(notificationId) {
    try {
      await fetch(`/admin/warehouse/notification/${notificationId}/read`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      // Remove from list
      notifications = notifications.filter(n => n.id !== notificationId);
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  }
  
  // Real-time updates with smart refresh
  let isRefreshing = false;
  let refreshInterval;
  let lastUpdate = null;
  
  // Auto refresh with real-time API endpoint
  onMount(() => {
    refreshInterval = setInterval(async () => {
      await updateDashboardStatus();
    }, 15000); // 15 seconds for better real-time feeling
    
    return () => {
      if (refreshInterval) {
        clearInterval(refreshInterval);
      }
    };
  });
  
  async function updateDashboardStatus() {
    if (isRefreshing) return;

    isRefreshing = true;
    hitungRefreshRingkasan += 1;
    try {
      // Setiap poling ke-4 (±1 menit) ikut meminta ringkasan (?ringkasan=1)
      // supaya kartu Ringkasan Packing / Performa Gudang bergerak tanpa
      // reload halaman, tanpa memikul ringkasan penuh di setiap tick 15 dtk.
      const mintaRingkasan = hitungRefreshRingkasan % 4 === 0;
      const response = await fetch('/admin/warehouse/dashboard-status' + (mintaRingkasan ? '?ringkasan=1' : ''), {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          const data = result.data;
          
          // Update task data if changed
          if (data.todayTask) {
            // Check if task progress changed
            if (!todayTask || todayTask.progress_percentage !== data.todayTask.progress_percentage) {
              todayTask = data.todayTask;
            }
          }
          
          // Update active box if changed
          if (data.activeBox) {
            if (!activeBox || activeBox.progress_percentage !== data.activeBox.progress_percentage) {
              activeBox = data.activeBox;
            }
          }
          
          // Update shared boxes data
          if (data.sharedBoxes && data.sharedBoxes.length > 0) {
            sharedBoxes = data.sharedBoxes;
          }

          // Daftar kerdus ikut diperbarui. Sengaja TIDAK memakai syarat
          // "length > 0" seperti sharedBoxes: daftar yang wajar jadi kosong
          // tidak boleh membuat tampilan mempertahankan daftar lama.
          if (Array.isArray(data.recentBoxes)) {
            recentBoxes = data.recentBoxes;
          }

          // Ringkasan SPV/manager ikut disegarkan bila endpoint mengirimnya
          // (pasangan ?ringkasan=1 di atas) — kartu performa gudang ikut hidup.
          if (data.ringkasanGudang !== undefined) {
            ringkasanGudang = data.ringkasanGudang;
          }
          if (data.ringkasanManager !== undefined) {
            ringkasanManager = data.ringkasanManager;
          }
          
          // Update notifications count
          if (data.notificationsCount !== undefined) {
            // Update notification badge (we could add a reactive variable for this)
            const notificationBadge = document.querySelector('.notification-badge');
            if (notificationBadge) {
              notificationBadge.textContent = data.notificationsCount;
              notificationBadge.style.display = data.notificationsCount > 0 ? 'flex' : 'none';
            }
          }
          
          lastUpdate = new Date(data.timestamp);
        }
      } else {
        console.warn('Failed to refresh dashboard status:', response.status);
      }
    } catch (error) {
      console.error('Error refreshing dashboard status:', error);
    } finally {
      isRefreshing = false;
    }
  }
  
  // Manual refresh function for button
  async function manualRefresh() {
    await updateDashboardStatus();
    // Show brief feedback
    const refreshBtn = document.querySelector('.refresh-btn');
    if (refreshBtn) {
      refreshBtn.classList.add('animate-spin');
      setTimeout(() => {
        refreshBtn.classList.remove('animate-spin');
      }, 1000);
    }
  }
</script>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

      <!-- Header - Responsive -->
      <div class="mb-6 sm:mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="text-center sm:text-left">
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">Dashboard Gudang</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1">{currentDate} • {currentTime}</p>
          </div>
          <div class="flex items-center justify-center sm:justify-end space-x-2 sm:space-x-3">
            <!-- System Online Status - Responsive -->
            <div class="inline-flex items-center px-2 sm:px-3 py-2 bg-green-100 text-green-800 rounded-lg text-xs sm:text-sm font-medium">
              <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse mr-2"></div>
              <span class="hidden sm:inline">Sistem Online</span>
              <span class="sm:hidden">Online</span>
            </div>
            
            <!-- Notifications button - Responsive -->
            <div class="relative">
              <button
                on:click={() => showNotifications = !showNotifications}
                class="relative p-2 bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 border border-gray-100 hover:border-gray-200"
              >
                <HeroIcon name="bell" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" />
                {#if notifications.length > 0}
                  <span class="notification-badge absolute -top-2 -right-2 h-4 w-4 sm:h-5 sm:w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                    {notifications.length}
                  </span>
                {/if}
              </button>
            
              {#if showNotifications}
                <div class="notification-dropdown absolute right-0 mt-2 w-72 sm:w-80 bg-white rounded-lg shadow-lg z-50">
                  <div class="p-3 sm:p-4 border-b">
                    <h3 class="font-semibold text-sm sm:text-base">Notifikasi</h3>
                  </div>
                  <div class="max-h-64 sm:max-h-96 overflow-y-auto">
                    {#if notifications.length > 0}
                      {#each notifications as notification}
                        <button type="button" class="p-3 sm:p-4 w-full text-left border-b hover:bg-gray-50" on:click={() => markNotificationRead(notification.id)}>
                          <div class="flex items-start">
                            <div class="flex-1">
                              <p class="font-medium text-xs sm:text-sm">{notification.title}</p>
                              <p class="text-xs sm:text-sm text-gray-600 mt-1">{notification.message}</p>
                              <p class="text-xs text-gray-400 mt-1">{new Date(notification.created_at).toLocaleString('id-ID')}</p>
                            </div>
                          </div>
                        </button>
                      {/each}
                    {:else}
                      <div class="p-4 text-center text-gray-500">
                        <p class="text-sm">Tidak ada notifikasi</p>
                      </div>
                    {/if}
                  </div>
                </div>
              {/if}
            </div>
          </div>
        </div>
    </div>
    
      <!-- Ringkasan SPV (satu gudang) dan Manager (dua gudang) memakai komponen
           yang sama dengan urutan yang sama: Packing → (Performa, manager) →
           Perlu Tindakan → Beban → Stok. Ubah komponennya, bukan salah satu blok. -->
      {#if ringkasanGudang}
        <RingkasanSection
          icon="cube"
          judul="Packing Hari Ini — {ringkasanGudang.gudang.nama}"
          keterangan="Gabungan seluruh tugas staf gudang ini"
        >
          <div slot="kanan" class="text-right flex-none">
            <p class="text-2xl sm:text-3xl font-bold text-indigo-600 leading-none">{persenPacking}%</p>
            <p class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">tercapai hari ini</p>
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <StatTile label="Target hari ini" nilai={ringkasanGudang.packing.target} satuan="mushaf" />
            <StatTile label="Sudah dikemas" nilai={ringkasanGudang.packing.selesai} satuan="mushaf">
              <PilTitik class="mt-2">{persenPacking}%</PilTitik>
            </StatTile>
            <StatTile label="Kerdus tersegel" nilai={ringkasanGudang.packing.kerdus_tersegel} satuan="kerdus" />
            <StatTile
              label="Staf belum mulai"
              nilai={ringkasanGudang.packing.staf_belum_mulai}
              satuan="staf"
              peringatan={ringkasanGudang.packing.staf_belum_mulai > 0}
            />
          </div>

          <div class="mb-1 flex justify-between items-baseline gap-2 text-xs text-gray-500">
            <span>Progress kemas gudang</span>
            <span><b class="text-gray-900">{ringkasanGudang.packing.selesai}</b> / {ringkasanGudang.packing.target} mushaf · {ringkasanGudang.packing.jumlah_staf ?? ringkasanGudang.packing.per_staf.length} staf bertugas</span>
          </div>
          <div class="h-2.5 rounded-full bg-gray-200 overflow-hidden">
            <div class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: {Math.min(100, persenPacking)}%"></div>
          </div>
        </RingkasanSection>

        <RingkasanSection
          icon="users"
          judul="Freelancer yang Kerja Hari Ini — {ringkasanGudang.gudang.nama}"
          keterangan="Capaian dan progres tiap staf yang ditugaskan hari ini"
        >
          <FreelancerKerjaHariIni staf={stafKerjaHariIni} />
        </RingkasanSection>

        <RingkasanSection
          icon="exclamation-circle"
          warna="amber"
          judul="Perlu Tindakan"
          keterangan="Yang menunggu keputusan SPV {ringkasanGudang.gudang.nama}"
        >
          <PerluTindakan
            transfer={ringkasanGudang.perlu_tindakan.transfer_menunggu}
            transferStok={ringkasanGudang.perlu_tindakan.transfer_stok_menunggu}
            muat={[{ ...ringkasanGudang.gudang, kerdus_menunggu_muat: ringkasanGudang.perlu_tindakan.kerdus_menunggu_muat }]}
          />
        </RingkasanSection>

        <RingkasanSection
          icon="clipboard-document-list"
          warna="blue"
          judul="Beban Gudang"
          keterangan="Resi yang tercatat sedang dikerjakan {ringkasanGudang.gudang.nama}"
        >
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            {#each TAHAP_BEBAN as [kunci, label] (kunci)}
              <StatTile {label} nilai={ringkasanGudang.beban[kunci] ?? 0} satuan="resi" />
            {/each}
          </div>
          <p class="text-xs text-gray-400 mt-3">Angka "Dalam kerdus" adalah bagian dari "Selesai packing" — bukan tambahan.</p>
        </RingkasanSection>

        <RingkasanSection
          icon="archive-box"
          warna="green"
          judul="Stok Mushaf"
          keterangan="Stok tanpa muwakif — A5, A6, dan IQRA dipisah varian terjemahannya · {ringkasanGudang.gudang.nama}"
        >
          <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            {#each ringkasanGudang.stok_kartu as baris (baris.kode_jenis + (baris.varian || 'x'))}
              <KartuStok {baris} />
            {/each}
          </div>
          <p class="text-xs text-gray-400 mt-3">Resi lama tanpa lokasi tidak dihitung di sini.</p>
        </RingkasanSection>
      {/if}

      {#if ringkasanManager}
        <RingkasanSection
          icon="cube"
          judul="Packing Hari Ini — Semua Gudang"
          keterangan="Gabungan target & hasil {perGudangUrut.map((g) => g.nama).join(' + ')}"
        >
          <div slot="kanan" class="text-right flex-none">
            <p class="text-2xl sm:text-3xl font-bold text-indigo-600 leading-none">{pctGabungan}%</p>
            <p class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">tercapai hari ini</p>
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <StatTile label="Target hari ini" nilai={gabunganTarget} satuan="mushaf" />
            <StatTile label="Sudah dikemas" nilai={gabunganSelesai} satuan="mushaf">
              <PilTitik class="mt-2">{pctGabungan}%</PilTitik>
            </StatTile>
            <StatTile label="Kerdus tersegel" nilai={ringkasanManager.gabungan.kerdus_tersegel} satuan="kerdus" />
            <StatTile
              label="Staf belum mulai"
              nilai={ringkasanManager.gabungan.staf_belum_mulai}
              satuan="staf"
              peringatan={ringkasanManager.gabungan.staf_belum_mulai > 0}
            />
          </div>

          <div class="mb-1 flex justify-between items-baseline gap-2 text-xs text-gray-500">
            <span>Progress kemas gabungan</span>
            <span><b class="text-gray-900">{gabunganSelesai}</b> / {gabunganTarget} mushaf · {ringkasanManager.gabungan.staf_bertugas} staf bertugas</span>
          </div>
          <!-- Setiap segmen = kontribusi satu gudang terhadap target gabungan
               (segmen pertama = gudang terdepan, warnanya sama dengan kartu
               Performa Gudang di bawah); lihat legendanya. -->
          <div class="flex h-2.5 rounded-full bg-gray-200 overflow-hidden">
            {#each segGabungan as lebar, i}
              {#if lebar > 0}
                <span class="h-full transition-all duration-500 {warnaGudang(i).bg}" style="width: {lebar}%"></span>
              {/if}
            {/each}
          </div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 mt-2 text-xs text-gray-500">
            {#each perGudangUrut as g, i (g.id)}
              <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full flex-none {warnaGudang(i).bg}"></span>{g.nama} · {g.selesai}</span>
            {/each}
            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-gray-300 flex-none"></span>Sisa {sisaGabungan}</span>
          </div>
          {#if ringkasanManager.gabungan.tugas_tanpa_gudang > 0}
            <p class="mt-2 text-xs text-amber-700" data-tugas-tanpa-gudang>
              {ringkasanManager.gabungan.tugas_tanpa_gudang} tugas hari ini belum bertagih gudang dan tidak termasuk angka di atas.
            </p>
          {/if}
        </RingkasanSection>

        <RingkasanSection
          icon="chart-pie"
          judul="Performa Gudang"
          keterangan="Capaian tiap gudang hari ini, diurut dari yang terdepan"
        >
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {#each perGudangUrut as g, i (g.id)}
              <div class="border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <p class="font-semibold text-gray-900">{g.nama}</p>
                      <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold tracking-wide border border-gray-200 text-gray-500">{g.kode}</span>
                    </div>
                    {#if tampilTerdepan(i)}
                      <PilTitik warna="green" class="mt-1.5">Terdepan hari ini</PilTitik>
                    {/if}
                  </div>
                  <div class="relative w-16 h-16 flex-none">
                    <svg viewBox="0 0 64 64" class="w-16 h-16">
                      <circle cx="32" cy="32" r="27" fill="none" class="stroke-gray-200" stroke-width="7"></circle>
                      <circle cx="32" cy="32" r="27" fill="none" class={warnaGudang(i).stroke} stroke-width="7" stroke-linecap="round"
                        stroke-dasharray={DONUT_KELILING} stroke-dashoffset={donutOffset(g.persen)} transform="rotate(-90 32 32)"></circle>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-[15px] font-bold text-gray-900">{g.persen}%</span>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-y-2 text-sm border-t border-gray-100 pt-3">
                  <span class="text-gray-500">Target</span><span class="text-right font-medium text-gray-900">{g.target} mushaf</span>
                  <span class="text-gray-500">Sudah dikemas</span><span class="text-right font-medium text-gray-900">{g.selesai} mushaf</span>
                  <span class="text-gray-500">Kerdus tersegel</span><span class="text-right font-medium text-gray-900">{g.kerdus_tersegel} kerdus</span>
                  <span class="text-gray-500">Staf bertugas</span><span class="text-right font-medium text-gray-900">{g.staf_total} staf</span>
                  <span class="text-gray-500">Menunggu dimuat</span><span class="text-right font-medium text-gray-900">{g.kerdus_menunggu_muat} kerdus penuh</span>
                </div>

                <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-dashed border-gray-200">
                  {#if g.staf_belum_mulai > 0}
                    <PilTitik warna="amber">{g.staf_belum_mulai} staf belum mulai</PilTitik>
                  {:else}
                    <PilTitik warna="green">Semua staf sudah mulai</PilTitik>
                  {/if}
                  <span class="w-14 h-1.5 rounded-full bg-gray-200 overflow-hidden flex-none">
                    <span class="block h-full rounded-full {warnaGudang(i).bg}" style="width: {Math.min(100, g.persen)}%"></span>
                  </span>
                </div>
              </div>
            {/each}
          </div>
        </RingkasanSection>

        <RingkasanSection
          icon="users"
          judul="Freelancer yang Kerja Hari Ini — Semua Gudang"
          keterangan="Capaian dan progres seluruh staf yang ditugaskan hari ini"
        >
          <FreelancerKerjaHariIni staf={stafKerjaManager} />
        </RingkasanSection>

        <RingkasanSection
          icon="exclamation-circle"
          warna="amber"
          judul="Perlu Tindakan"
          keterangan="Yang menunggu keputusan manager gudang"
        >
          <PerluTindakan
            transfer={ringkasanManager.transfer_menunggu}
            transferStok={ringkasanManager.transfer_stok_menunggu}
            muat={ringkasanManager.per_gudang}
            tampilTujuan
          />
        </RingkasanSection>

        <RingkasanSection
          icon="clipboard-document-list"
          warna="blue"
          judul="Beban Gudang"
          keterangan="Resi yang tercatat sedang dikerjakan — gabungan dengan rincian per gudang"
        >
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            {#each TAHAP_BEBAN as [kunci, label] (kunci)}
              <StatTile {label} nilai={totalBeban(kunci)} satuan="resi">
                <p class="text-[11px] text-gray-500 mt-1 truncate">
                  {ringkasanManager.per_gudang.map((g) => g.kode + ' ' + (g.beban[kunci] ?? 0)).join(' · ')}
                </p>
              </StatTile>
            {/each}
          </div>
          <p class="text-xs text-gray-400 mt-3">Angka "Dalam kerdus" adalah bagian dari "Selesai packing" — bukan tambahan.</p>
        </RingkasanSection>

        <RingkasanSection
          icon="archive-box"
          warna="green"
          judul="Stok Mushaf"
          keterangan="Stok tanpa muwakif — A5, A6, dan IQRA dipisah varian terjemahannya, per gudang"
        >
          <div slot="kanan" class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg" role="tablist" aria-label="Saring kartu stok per gudang">
            <button type="button" role="tab" aria-selected={stokAktif === 'semua'} on:click={() => (stokAktif = 'semua')}
              class="px-3 py-1.5 rounded-md text-sm font-medium {stokAktif === 'semua' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}">Semua</button>
            {#each ringkasanManager.per_gudang as g (g.id)}
              <button type="button" role="tab" aria-selected={stokAktif === String(g.id)} on:click={() => (stokAktif = String(g.id))}
                class="px-3 py-1.5 rounded-md text-sm font-medium {stokAktif === String(g.id) ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}">{g.nama.replace('Gudang ', '')}</button>
            {/each}
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            {#each ringkasanManager.per_gudang.filter((g) => stokAktif === 'semua' || stokAktif === String(g.id)) as g (g.id)}
              {#each g.stok_kartu as baris (g.id + baris.kode_jenis + (baris.varian || 'x'))}
                <KartuStok {baris} gudang={g.nama} />
              {/each}
            {/each}
          </div>
          <p class="text-xs text-gray-400 mt-3">Resi lama tanpa lokasi tidak dihitung di sini.</p>
        </RingkasanSection>
      {/if}


    {#if !adalahPemantau || todayTask}
    <!-- Today's Task Section - Responsive -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
      <div class="flex items-center justify-center sm:justify-start mb-4 sm:mb-6">
        <div class="flex items-center space-x-2 sm:space-x-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
            <HeroIcon name="clipboard-document-check" class="w-4 h-4" />
          </div>
          <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Tugas Hari Ini</h2>
        </div>
      </div>
          {#if todayTask}
            <div class="text-center">
              <!-- Status Badge -->
              <div class="mb-6">
                <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium {getStatusBadge(todayTask.status).class}">
                  {getStatusBadge(todayTask.status).text}
                </span>
              </div>
              
              <!-- Progress Display - Responsive -->
              <div class="mb-4 sm:mb-6">
                <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">
                  {todayTask.total_selesai}<span class="text-lg sm:text-xl text-gray-500">/{todayTask.total_target}</span>
                </div>
                <div class="text-base sm:text-lg text-gray-600 mb-3 sm:mb-4">
                  <span class="font-semibold">{todayTask.progress_percentage}%</span> tercapai
                </div>
                
                <!-- Progress Bar - Responsive -->
                <div class="max-w-xs sm:max-w-md mx-auto">
                  <div class="w-full bg-gray-200 rounded-full h-2 sm:h-3 mb-2">
                    <div 
                      class="h-2 sm:h-3 rounded-full transition-all duration-500 {getProgressColor(todayTask.progress_percentage)}"
                      style="width: {todayTask.progress_percentage}%"
                    ></div>
                  </div>
                  <div class="flex justify-between text-xs sm:text-sm text-gray-500">
                    <span>0 mushaf</span>
                    <span>{todayTask.total_target} mushaf</span>
                  </div>
                </div>
              </div>
              
              <!-- Action Button - Responsive -->
              {#if todayTask.is_completed}
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 sm:p-6">
                  <div class="mb-2 flex justify-center">
                    <HeroIcon name="check-circle" class="w-10 h-10 text-green-600" />
                  </div>
                  <p class="text-green-800 font-semibold text-base sm:text-lg mb-1">Selamat! Tugas hari ini telah selesai!</p>
                  <p class="text-sm sm:text-base text-green-600">Total: <span class="font-semibold">{todayTask.total_selesai} mushaf</span> berhasil dipacking</p>
                </div>
              {:else if canStartPacking}
                <button
                  on:click={startScanning}
                  disabled={isStartingTask}
                  class="inline-flex items-center px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base w-full sm:w-auto justify-center"
                >
                  {#if isStartingTask}
                    <svg class="animate-spin -ml-1 mr-2 sm:mr-3 h-4 w-4 sm:h-6 sm:w-6 text-white" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memulai...
                  {:else}
                    <HeroIcon name="qr-code" class="w-5 h-5 mr-2" />
                    {todayTask.status === 'assigned' ? 'Mulai Packing' : 'Lanjutkan Packing'}
                  {/if}
                </button>
              {:else}
                <!-- Menerima tugas tetapi tidak berwenang memindai: ini kondisi
                     yang membingungkan, jadi dijelaskan alih-alih tombol yang
                     pasti gagal. -->
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 sm:p-6">
                  <div class="mb-2 flex justify-center">
                    <HeroIcon name="lock-closed" class="w-9 h-9 sm:w-10 sm:h-10 text-slate-500" />
                  </div>
                  <p class="text-slate-800 font-semibold text-base sm:text-lg mb-1">Pemantauan saja</p>
                  <p class="text-sm sm:text-base text-slate-600">
                    Peran Anda tidak mencakup pemindaian. Tugas ini dikerjakan oleh petugas gudang.
                  </p>
                </div>
              {/if}
            </div>
          {:else}
            <div class="text-center py-8 sm:py-12">
              <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full mb-4 sm:mb-6">
                <HeroIcon name="inbox-stack" class="w-8 h-8 sm:w-10 sm:h-10 text-gray-400" />
              </div>
              <div class="text-gray-900 text-lg sm:text-xl font-bold mb-2 sm:mb-3">Tidak ada tugas untuk hari ini</div>
              <div class="text-gray-600 text-sm sm:text-lg px-4 flex items-center justify-center gap-1.5">
                {#if new Date().getDay() === 0 || new Date().getDay() === 6}
                  <HeroIcon name="calendar" class="w-5 h-5 text-amber-500 inline" />
                  <span>Hari libur - tidak ada penugasan</span>
                {:else}
                  <HeroIcon name="phone" class="w-5 h-5 text-blue-500 inline" />
                  <span>Hubungi supervisor jika ada kesalahan</span>
                {/if}
              </div>
            </div>
          {/if}
    </div>
    {/if}

    {#if !adalahPemantau || recentBoxes.length > 0}
    <!-- Kerdus Terbaru - Responsive -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="flex items-center space-x-2 sm:space-x-3">
          <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
            <HeroIcon name="archive-box" class="w-4 h-4" />
          </div>
          <div>
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Kerdus Terbaru</h2>
            <p class="text-xs sm:text-sm text-gray-500">10 kerdus terakhir yang Anda kerjakan</p>
          </div>
        </div>
        {#if bolehLacakKerdus}
          <a
            href="/admin/box-tracking"
            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
          >
            <HeroIcon name="magnifying-glass" class="w-4 h-4" />
            Lacak Kerdus
          </a>
        {/if}
      </div>

      {#if recentBoxes.length > 0}
        <div class="space-y-3">
          {#each recentBoxes as box}
            <div class="border border-gray-100 rounded-lg p-3 sm:p-4 hover:border-gray-200 transition-colors">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-sm sm:text-base font-semibold text-gray-900 truncate">{box.kode_kerdus}</span>
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {box.status_info.bg} {box.status_info.text}">
                      {box.status_info.label}
                    </span>
                  </div>
                  <div class="text-xs sm:text-sm text-gray-500 mt-1">
                    {box.jenis_quran} · dibuat {box.created_at}
                    {#if box.sealed_at}<span class="text-gray-400">· tersegel {box.sealed_at}</span>{/if}
                  </div>
                </div>
                <div class="sm:w-48 sm:flex-shrink-0">
                  <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                      <div
                        class="h-2 rounded-full {getProgressColor(box.progress_percentage)}"
                        style="width: {box.progress_percentage}%"
                      ></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-900 whitespace-nowrap">
                      {box.jumlah_terisi}/{box.kapasitas}
                    </span>
                  </div>
                  <div class="text-right text-xs text-gray-500 mt-1">{box.progress_percentage}%</div>
                </div>
              </div>
            </div>
          {/each}
        </div>
      {:else}
        <div class="text-center py-8 sm:py-10 border border-dashed border-gray-200 rounded-lg">
          <div class="inline-flex items-center justify-center w-14 h-14 bg-gray-100 rounded-full mb-3">
            <HeroIcon name="archive-box" class="w-7 h-7 text-gray-400" />
          </div>
          <div class="text-gray-900 font-semibold mb-1">Belum ada kerdus</div>
          <div class="text-sm text-gray-600 px-4">
            Kerdus baru tercatat setelah Anda memindai resi di halaman Proses Packing.
          </div>
        </div>
      {/if}
    </div>

    {/if}

    {#if !adalahPemantau}
    <!-- Performance Stats Grid - Responsive -->
    <div class="performance-stats grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
      <!-- This Week Performance -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 hover:shadow-md transition-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6">
          <div class="flex items-center space-x-2 sm:space-x-3 mb-2 sm:mb-0">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
              <HeroIcon name="chart-bar" class="w-4 h-4" />
            </div>
            <div>
              <h3 class="text-base sm:text-lg font-semibold text-gray-900">Performa Minggu Ini</h3>
              <p class="text-xs sm:text-sm text-gray-500">7 hari terakhir</p>
            </div>
          </div>
        </div>
        
        <div class="space-y-3 sm:space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Target</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.total_target || (todayTask ? todayTask.total_target : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Selesai</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.total_achieved || (todayTask ? todayTask.total_selesai : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Tingkat capaian</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.completion_rate || (todayTask ? todayTask.progress_percentage : 0)}%</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Hari Kerja</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.week?.days_worked || 1} hari</span>
          </div>
        </div>
      </div>
      
      <!-- This Month Performance -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 hover:shadow-md transition-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6">
          <div class="flex items-center space-x-2 sm:space-x-3 mb-2 sm:mb-0">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
              <HeroIcon name="calendar-days" class="w-4 h-4" />
            </div>
            <div>
              <h3 class="text-base sm:text-lg font-semibold text-gray-900">Performa Bulan Ini</h3>
              <p class="text-xs sm:text-sm text-gray-500">30 hari terakhir</p>
            </div>
          </div>
        </div>
        
        <div class="space-y-3 sm:space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Target</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.total_target || (todayTask ? todayTask.total_target : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Total Selesai</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.total_achieved || (todayTask ? todayTask.total_selesai : 0)} mushaf</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Tingkat capaian</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.completion_rate || (todayTask ? todayTask.progress_percentage : 0)}%</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Hari target penuh</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.perfect_days || 0} hari</span>
          </div>
        </div>
      </div>
    </div>
    {/if}
  </div>
</AdminLayout>

<style>
  /* Ensure notification dropdown doesn't get cut off */
  .relative {
    position: relative;
  }
  
  /* Enhanced responsive adjustments */
  @media (max-width: 640px) {
    /* Mobile optimizations */
    .notification-dropdown {
      width: calc(100vw - 2rem);
      right: -1rem;
      max-width: 320px;
    }
    
    /* Touch-friendly buttons */
    button {
      min-height: 44px;
      touch-action: manipulation;
    }
    
    /* Better spacing on mobile */
    .grid-cols-1 {
      gap: 1rem;
    }
    
    /* Prevent horizontal scroll */
    .max-w-7xl {
      max-width: 100%;
    }
    
    /* Optimize text sizes */
    .text-3xl {
      font-size: 1.75rem;
    }
    
    .text-xl {
      font-size: 1.125rem;
    }
  }
  
  @media (min-width: 640px) and (max-width: 1024px) {
    /* Tablet optimizations */
    .performance-stats {
      grid-template-columns: 1fr;
      gap: 1.5rem;
    }
  }
  
  @media (min-width: 1024px) {
    /* Desktop optimizations */
    .performance-stats {
      grid-template-columns: repeat(2, 1fr);
      gap: 2rem;
    }
  }
  
  /* Progress bar animations */
  .transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
  }
  
  /* Enhanced visual hierarchy */
  .performance-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(249,250,251,0.9) 100%);
    backdrop-filter: blur(10px);
  }
</style>
