<script>
  import { onMount } from 'svelte';
  import { page } from '@inertiajs/svelte';
  import { router } from '@inertiajs/svelte';
  import { showError } from '../../stores/toast.js';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import SharedBoxCollaboration from '../../Components/SharedBoxCollaboration.svelte';
  import SealReadyBoxes from '../../Components/SealReadyBoxes.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
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

  $: persenPacking = ringkasanGudang?.packing?.target
    ? Math.round((ringkasanGudang.packing.selesai / ringkasanGudang.packing.target) * 100)
    : 0;
  
  // State
  let showNotifications = false;
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
    try {
      const response = await fetch('/admin/warehouse/dashboard-status', {
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
              <span class="hidden sm:inline">System Online</span>
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
    
      {#if ringkasanGudang}
        <!-- Ringkasan gudang untuk SPV: semua angka sudah ter-coup ke gudang akun. -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 flex-none">
              <HeroIcon name="cube" class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Packing Hari Ini — {ringkasanGudang.gudang.nama}</h2>
              <p class="text-xs sm:text-sm text-gray-500">Gabungan seluruh tugas staf gudang ini</p>
            </div>
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Target hari ini</p>
              <p class="text-2xl font-bold text-gray-900">{ringkasanGudang.packing.target} <span class="text-sm font-medium text-gray-400">mushaf</span></p>
            </div>
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Sudah dikemas</p>
              <p class="text-2xl font-bold text-gray-900">{ringkasanGudang.packing.selesai} <span class="text-sm font-medium text-gray-400">mushaf</span></p>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 mt-1">{persenPacking}%</span>
            </div>
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Kerdus tersegel</p>
              <p class="text-2xl font-bold text-gray-900">{ringkasanGudang.packing.kerdus_tersegel} <span class="text-sm font-medium text-gray-400">kerdus</span></p>
            </div>
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">Belum mulai</p>
              <p class="text-2xl font-bold {ringkasanGudang.packing.staf_belum_mulai > 0 ? 'text-amber-600' : 'text-gray-900'}">{ringkasanGudang.packing.staf_belum_mulai} <span class="text-sm font-medium text-gray-400">staf</span></p>
            </div>
          </div>

          <div class="mb-1 flex justify-between text-xs text-gray-500">
            <span>Progress kemas gudang</span>
            <span><b class="text-gray-900">{ringkasanGudang.packing.selesai}</b> / {ringkasanGudang.packing.target} mushaf</span>
          </div>
          <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
            <div class="h-full rounded-full bg-blue-500 transition-all duration-500" style="width: {persenPacking}%"></div>
          </div>

          {#if ringkasanGudang.packing.per_staf.length > 0}
            <div class="mt-4 -mx-1 px-1 flex gap-2 overflow-x-auto">
              {#each ringkasanGudang.packing.per_staf as staf (staf.nama)}
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap {staf.mulai ? 'bg-white border border-gray-200 text-gray-600' : 'bg-amber-50 text-amber-700'}">
                  {staf.nama} <b class="text-gray-900 ml-1">{staf.selesai}/{staf.target}</b>{staf.mulai ? '' : ' · belum mulai'}
                </span>
              {/each}
            </div>
          {/if}
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 flex-none">
              <HeroIcon name="exclamation-circle" class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Perlu Tindakan Anda</h2>
              <p class="text-xs sm:text-sm text-gray-500">Yang menunggu keputusan SPV {ringkasanGudang.gudang.nama}</p>
            </div>
          </div>

          {#if ringkasanGudang.perlu_tindakan.transfer_menunggu.length > 0}
            {#each ringkasanGudang.perlu_tindakan.transfer_menunggu as t (t.kode)}
              <div class="border border-gray-200 rounded-lg p-4 mb-3 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 flex-none">
                  <HeroIcon name="arrow-down-tray" class="w-6 h-6" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-gray-900">Dokumen {t.kode} menunggu diterima</p>
                  <p class="text-sm text-gray-600 mt-0.5">Dari {t.asal} · {t.jumlah_resi} resi ({t.jumlah_eks} mushaf)</p>
                  <p class="text-xs text-gray-400 mt-1">Lokasi mushaf berpindah ke gudang ini setelah Anda menekan Terima.</p>
                </div>
                <a href="/admin/transfer-mushaf" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 text-center flex-none">Terima di sini</a>
              </div>
            {/each}
          {:else}
            <div class="rounded-lg border border-dashed border-gray-300 p-4 flex items-center gap-3 mb-3">
              <span class="text-green-500 text-lg flex-none">✓</span>
              <p class="text-sm text-gray-600">Tidak ada kiriman antar gudang yang menunggu. Semua beres.</p>
            </div>
          {/if}

          <div class="rounded-lg border border-dashed border-gray-300 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <span class="flex-none text-indigo-600"><HeroIcon name="truck" class="w-5 h-5" /></span>
            <p class="text-sm text-gray-600 flex-1">
              <b class="text-gray-900">{ringkasanGudang.perlu_tindakan.kerdus_menunggu_muat} kerdus</b> penuh menunggu dimuat ke kurir.
            </p>
            <a href="/admin/muatan" class="text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg flex-none">Lihat daftar →</a>
          </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 flex-none">
              <HeroIcon name="archive-box" class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Beban Gudang</h2>
              <p class="text-xs sm:text-sm text-gray-500">Resi yang tercatat sedang dikerjakan gudang ini</p>
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
            {#each [['Pemesanan', ringkasanGudang.beban.pemesanan], ['Produksi', ringkasanGudang.beban.produksi], ['Packing', ringkasanGudang.beban.packing], ['Selesai packing', ringkasanGudang.beban.selesai_packing], ['Dalam kerdus', ringkasanGudang.beban.dalam_kerdus]] as [label, nilai]}
              <div class="border border-gray-200 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-1">{label}</p>
                <p class="text-xl font-bold text-gray-900">{nilai}</p>
                <p class="text-[11px] text-gray-400">resi</p>
              </div>
            {/each}
          </div>
          <p class="text-xs text-gray-400 -mt-3 mb-5">Angka "Dalam kerdus" adalah bagian dari "Selesai packing" — bukan tambahan.</p>

          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-2">
            <p class="text-sm font-medium text-gray-700">Stok mushaf gudang ini</p>
            <p class="text-xs text-gray-400">Resi lama tanpa lokasi tidak dihitung di sini</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-gray-500 border-b border-gray-200">
                  <th class="py-2 pr-4 font-medium">Jenis</th>
                  <th class="py-2 pr-4 font-medium hidden sm:table-cell">Varian</th>
                  <th class="py-2 pr-4 font-medium text-right">Stok tersedia</th>
                  <th class="py-2 font-medium text-right">Setara dus</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                {#each ringkasanGudang.stok as baris (baris.jenis + baris.varian)}
                  <tr>
                    <td class="py-2 pr-4 font-medium text-gray-900">{baris.jenis}</td>
                    <!-- Varian tampil di bawah jenis di ponsel; kolom sendiri di layar lebar. -->
                    <td class="py-2 pr-4 text-gray-600 hidden sm:table-cell">{baris.varian}</td>
                    <td class="py-2 pr-4 text-right text-gray-700">{baris.eks} eks<div class="text-[11px] text-gray-400 sm:hidden">{baris.varian}</div></td>
                    <td class="py-2 text-right text-gray-600">{baris.dus} dus</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </section>
      {/if}

    <!-- Today's Task Section - Responsive -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
      <div class="flex items-center justify-center sm:justify-start mb-4 sm:mb-6">
        <div class="flex items-center space-x-2 sm:space-x-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
            <HeroIcon name="pencil-square" class="w-5 h-5" />
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
                  <span class="font-semibold">{todayTask.progress_percentage}%</span> Progress Completed
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
                    <HeroIcon name="sparkles" class="w-10 h-10 text-green-600" />
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
                    <HeroIcon name="plus" class="w-5 h-5 mr-2" />
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

    <!-- Kerdus Terbaru - Responsive -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6 sm:mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="flex items-center space-x-2 sm:space-x-3">
          <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
            <HeroIcon name="archive-box" class="w-5 h-5" />
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

    <!-- Performance Stats Grid - Responsive -->
    <div class="performance-stats grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
      <!-- This Week Performance -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 hover:shadow-md transition-shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6">
          <div class="flex items-center space-x-2 sm:space-x-3 mb-2 sm:mb-0">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
              <HeroIcon name="chart-bar" class="w-5 h-5" />
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
            <span class="text-sm sm:text-base text-gray-600">Completion Rate</span>
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
              <HeroIcon name="calendar-days" class="w-5 h-5" />
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
            <span class="text-sm sm:text-base text-gray-600">Completion Rate</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.completion_rate || (todayTask ? todayTask.progress_percentage : 0)}%</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm sm:text-base text-gray-600">Perfect Days</span>
            <span class="text-sm sm:text-xl font-semibold text-gray-900">{performanceData.month?.perfect_days || 0} hari</span>
          </div>
        </div>
      </div>
    </div>
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
