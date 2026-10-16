<script>
  /**
   * PackingHistory.svelte — riwayat tugas packing milik staf gudang.
   *
   * Halaman ini sebelumnya terdaftar di routes/web.php tapi berkasnya tidak ada,
   * sehingga membukanya melempar error Inertia (komponen tidak ditemukan).
   *
   * Props dari Warehouse\PackingController@history:
   *   history — paginator DailyPackingTask milik user ini, tiap baris membawa
   *             relasi `packing_boxes` (kolom terpilih) + `packing_items_count`.
   *
   * Hanya menampilkan tugas milik sendiri (Gate viewOwnHistory), jadi tidak ada
   * filter user di sini.
   */
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
  import Pagination from '../../Components/Pagination.svelte';

  export let history = { data: [], current_page: 1, last_page: 1, total: 0 };
  export let perPage = 20;
  export let perPageOptions = [10, 20, 50, 100, 200];

  // Status mengikuti DailyPackingTask::STATUS_* — 'completed' belum pernah
  // muncul di data produksi, jadi jangan diasumsikan selalu ada.
  const statusLabel = {
    assigned: 'Ditugaskan',
    in_progress: 'Berjalan',
    completed: 'Selesai',
    expired: 'Kadaluarsa'
  };

  const statusClass = {
    assigned: 'bg-gray-100 text-gray-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    expired: 'bg-red-100 text-red-800'
  };

  function persenBaris(tugas) {
    if (!tugas.total_target) {
      return 0;
    }

    return Math.round(((tugas.total_selesai || 0) / tugas.total_target) * 100);
  }

  function tanggalIndo(nilai) {
    if (!nilai) {
      return '—';
    }

    return new Date(nilai).toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });
  }

  function jam(nilai) {
    if (!nilai) {
      return null;
    }

    return new Date(nilai).toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit'
    });
  }
</script>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Riwayat Packing</h1>
            <p class="text-gray-600 mt-1">Tugas packing harian Anda beserta kerdus yang dipakai</p>
          </div>
          <div class="flex items-center gap-3">
            <a
              href="/admin/warehouse/packing"
              class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
            >
              <HeroIcon name="arrow-left" class="w-4 h-4" />
              Proses Packing
            </a>
            <a
              href="/admin/warehouse"
              class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
            >
              <HeroIcon name="home" class="w-4 h-4" />
              Dasbor Gudang
            </a>
          </div>
        </div>
      </div>

      <!-- Daftar tugas -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-4 sm:p-6 border-b border-gray-200 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Tugas Harian</h2>
            <p class="text-sm text-gray-600">
              Menampilkan {history.from || 0}–{history.to || 0} dari {history.total || 0} tugas
            </p>
          </div>
        </div>

        {#if history.data && history.data.length > 0}
          <div class="divide-y divide-gray-200">
            {#each history.data as tugas (tugas.id)}
              <div class="p-4 sm:p-6 hover:bg-gray-50">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 flex-wrap">
                      <h3 class="text-base font-semibold text-gray-900">
                        {tanggalIndo(tugas.tanggal_tugas)}
                      </h3>
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {statusClass[
                          tugas.status
                        ] || 'bg-gray-100 text-gray-800'}"
                      >
                        {statusLabel[tugas.status] || tugas.status}
                      </span>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                      <span>
                        Target: <span class="font-medium text-gray-900">{tugas.total_target || 0}</span>
                      </span>
                      <span>
                        Selesai: <span class="font-medium text-green-700">{tugas.total_selesai || 0}</span>
                      </span>
                      {#if tugas.started_at}
                        <span>Mulai: {jam(tugas.started_at)}</span>
                      {/if}
                      {#if tugas.completed_at}
                        <span>Selesai: {jam(tugas.completed_at)}</span>
                      {/if}
                    </div>

                    <!-- Progres -->
                    <div class="mt-3">
                      <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                        <span>Progres</span>
                        <span class="font-medium">{persenBaris(tugas)}%</span>
                      </div>
                      <div class="w-full bg-gray-200 rounded-full h-2">
                        <div
                          class="h-2 rounded-full transition-all duration-300 {persenBaris(tugas) >= 100
                            ? 'bg-green-600'
                            : persenBaris(tugas) >= 60
                              ? 'bg-yellow-500'
                              : 'bg-[#eb3434]'}"
                          style="width: {Math.min(persenBaris(tugas), 100)}%"
                        ></div>
                      </div>
                    </div>
                  </div>

                  <!-- Ringkasan kerdus -->
                  <div class="sm:w-56 flex-shrink-0">
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                      <div class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                        <HeroIcon name="cube" class="w-4 h-4 text-gray-500" />
                        Kerdus ({tugas.packing_boxes?.length || 0})
                      </div>
                      {#if tugas.packing_boxes && tugas.packing_boxes.length > 0}
                        <ul class="space-y-1">
                          {#each tugas.packing_boxes as box (box.id)}
                            <li class="flex items-center justify-between text-xs">
                              <span class="font-mono text-gray-700 truncate" title={box.kode_kerdus}>
                                {box.kode_kerdus}
                              </span>
                              <span class="text-gray-500 whitespace-nowrap ml-2">
                                {box.jumlah_terisi || 0}/{box.kapasitas || 0}
                              </span>
                            </li>
                          {/each}
                        </ul>
                      {:else}
                        <p class="text-xs text-gray-500">Belum ada kerdus</p>
                      {/if}
                    </div>
                  </div>
                </div>
              </div>
            {/each}
          </div>

          <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
            <Pagination data={history} {perPage} {perPageOptions} />
          </div>
        {:else}
          <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-gray-100 rounded-full mb-3">
              <HeroIcon name="clock" class="w-6 h-6 text-gray-400" />
            </div>
            <p class="text-gray-600 font-medium">Belum ada riwayat packing</p>
            <p class="text-sm text-gray-500 mt-1">
              Tugas packing harian Anda akan tercatat di sini setelah dimulai
            </p>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
