<script>
  /**
   * JobMonitor.svelte — pemantau tugas latar (queue) untuk role gudang.
   *
   * Halaman ini sebelumnya terdaftar di routes/web.php tapi berkasnya tidak ada,
   * sehingga membukanya melempar error Inertia (komponen tidak ditemukan).
   *
   * Props dari Warehouse\JobMonitorController@index:
   *   stats      — ringkasan 7 hari (total/completed/failed/active/dst)
   *   activeJobs — job berstatus pending|processing milik user ini
   *   recentJobs — 10 job terakhir milik user ini
   *
   * Pemantauan per-job (progress bar, cancel, retry) diserahkan ke
   * Components/Warehouse/JobProgressCard supaya tidak ada dua sumber kebenaran.
   */
  import { onMount, onDestroy } from 'svelte';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
  import JobProgressCard from '../../Components/Warehouse/JobProgressCard.svelte';

  export let stats = {};
  export let activeJobs = [];
  export let recentJobs = [];

  let autoRefresh = true;
  let refreshTimer = null;

  $: totalJobs = stats.total_jobs || 0;
  $: completedJobs = stats.completed || 0;
  $: failedJobs = stats.failed || 0;
  $: activeCount = stats.active || 0;
  $: avgSuccessRate = stats.avg_success_rate;

  function muatUlang() {
    if (!autoRefresh) {
      return;
    }
    fetch('/admin/warehouse/job-monitor/stats', {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          stats = { ...stats, ...data.data.user_stats };
        }
      })
      .catch((error) => {
        console.error('Gagal memuat statistik job:', error);
      });
  }

  onMount(() => {
    refreshTimer = setInterval(muatUlang, 15000);
  });

  onDestroy(() => {
    if (refreshTimer) {
      clearInterval(refreshTimer);
    }
  });
</script>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Monitor Tugas</h1>
            <p class="text-gray-600 mt-1">Pantau proses latar (impor, cetak QR, operasi kerdus massal)</p>
          </div>
          <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                bind:checked={autoRefresh}
                class="rounded border-gray-300 text-[#eb3434] focus:ring-red-500"
              />
              Segarkan otomatis
            </label>
            <a
              href="/admin/warehouse"
              class="inline-flex items-center gap-1 text-gray-600 hover:text-gray-900 text-sm"
            >
              <HeroIcon name="arrow-left" class="w-4 h-4" />
              Dasbor Gudang
            </a>
          </div>
        </div>
      </div>

      <!-- Ringkasan 7 hari -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Tugas</p>
              <p class="text-2xl font-bold text-gray-900">{totalJobs}</p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
              <HeroIcon name="clipboard-document-list" class="w-6 h-6 text-blue-600" />
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2">Tujuh hari terakhir</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Sedang Berjalan</p>
              <p class="text-2xl font-bold text-blue-600">{activeCount}</p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
              <HeroIcon name="arrow-path" class="w-6 h-6 text-yellow-600" />
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2">Menunggu atau diproses</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Selesai</p>
              <p class="text-2xl font-bold text-green-600">{completedJobs}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
              <HeroIcon name="check-circle" class="w-6 h-6 text-green-600" />
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2">
            {#if avgSuccessRate}
              Rata-rata berhasil {Math.round(avgSuccessRate)}%
            {:else}
              Belum ada data
            {/if}
          </p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Gagal</p>
              <p class="text-2xl font-bold {failedJobs > 0 ? 'text-red-600' : 'text-gray-900'}">{failedJobs}</p>
            </div>
            <div class="p-3 bg-red-100 rounded-full">
              <HeroIcon name="x-circle" class="w-6 h-6 text-red-600" />
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2">Dapat dijalankan ulang</p>
        </div>
      </div>

      <!-- Tugas aktif -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-4 sm:p-6 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Tugas Berjalan</h2>
          <p class="text-sm text-gray-600">Progres diperbarui otomatis setiap 2 detik</p>
        </div>

        <div class="p-4 sm:p-6">
          {#if activeJobs.length > 0}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              {#each activeJobs as job (job.job_id)}
                <JobProgressCard {job} autoRefresh={autoRefresh} />
              {/each}
            </div>
          {:else}
            <div class="text-center py-10">
              <div class="inline-flex items-center justify-center w-12 h-12 bg-gray-100 rounded-full mb-3">
                <HeroIcon name="inbox-stack" class="w-6 h-6 text-gray-400" />
              </div>
              <p class="text-gray-600 font-medium">Tidak ada tugas yang berjalan</p>
              <p class="text-sm text-gray-500 mt-1">Tugas latar akan muncul di sini saat dimulai</p>
            </div>
          {/if}
        </div>
      </div>

      <!-- Riwayat terakhir -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-4 sm:p-6 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Tugas Terakhir</h2>
          <p class="text-sm text-gray-600">Sepuluh tugas terbaru milik Anda</p>
        </div>

        <div class="overflow-x-auto">
          {#if recentJobs.length > 0}
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progres</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durasi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                {#each recentJobs as job (job.job_id)}
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                      <div class="text-sm font-medium text-gray-900">{job.title || 'Tanpa judul'}</div>
                      <div class="text-xs text-gray-500">{job.job_id}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {job.status === 'completed'
                          ? 'bg-green-100 text-green-800'
                          : job.status === 'failed'
                            ? 'bg-red-100 text-red-800'
                            : job.status === 'processing'
                              ? 'bg-blue-100 text-blue-800'
                              : job.status === 'cancelled'
                                ? 'bg-gray-100 text-gray-800'
                                : 'bg-yellow-100 text-yellow-800'}"
                      >
                        {job.status_display || job.status}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {Number(job.progress_percentage || 0).toFixed(1)}%
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {job.processed_items || 0} / {job.total_items || 0}
                      {#if job.failed_items > 0}
                        <span class="text-red-600">({job.failed_items} gagal)</span>
                      {/if}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {job.duration_formatted || '—'}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          {:else}
            <div class="text-center py-10">
              <p class="text-gray-600 font-medium">Belum ada riwayat tugas</p>
              <p class="text-sm text-gray-500 mt-1">Riwayat tujuh hari terakhir akan tampil di sini</p>
            </div>
          {/if}
        </div>
      </div>
    </div>
  </div>
</AdminLayout>
