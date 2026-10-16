<script>
  /**
   * SharedCollaboration.svelte — antarmuka kolaborasi kerdus bersama.
   *
   * Halaman ini sebelumnya terdaftar di routes/web.php tapi berkasnya tidak ada,
   * sehingga membukanya melempar error Inertia (komponen tidak ditemukan).
   *
   * Isi sebenarnya sudah lama tersedia di
   * Components/SharedBoxCollaboration.svelte (auto-refresh 10 detik, memanggil
   * /admin/warehouse/shared-boxes-status). Halaman ini hanya menyediakan
   * kerangka AdminLayout + judul, supaya tidak ada logika yang diduplikasi.
   *
   * Props dari Warehouse\DashboardController@sharedCollaboration:
   *   sharedBoxes — daftar kerdus bersama yang melibatkan user ini
   *   currentUser — { id, name, role }
   */
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../Components/UI/HeroIcon.svelte';
  import SharedBoxCollaboration from '../../Components/SharedBoxCollaboration.svelte';

  export let sharedBoxes = [];
  export let currentUser = null;
</script>

<AdminLayout>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Kolaborasi Kerdus</h1>
            <p class="text-gray-600 mt-1">
              Kerdus yang dikerjakan bersama oleh beberapa staf gudang
            </p>
          </div>
          <div class="flex items-center gap-3">
            {#if currentUser}
              <span class="text-sm text-gray-600">
                Masuk sebagai <span class="font-medium text-gray-900">{currentUser.name}</span>
              </span>
            {/if}
            <a
              href="/admin/warehouse/packing"
              class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
            >
              <HeroIcon name="arrow-left" class="w-4 h-4" />
              Proses Packing
            </a>
          </div>
        </div>
      </div>

      <!-- Panel kolaborasi (komponen bersama, sudah menangani auto-refresh) -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <SharedBoxCollaboration {sharedBoxes} {currentUser} />
      </div>
    </div>
  </div>
</AdminLayout>
