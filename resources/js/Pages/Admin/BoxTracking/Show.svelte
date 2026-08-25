<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';

  // Props
  export let box = {};
  export let items = [];
  export let jenis_quran = '';
  export const errors = {};
  export const flash = {};

  function goBack() {
    router.visit('/admin/box-tracking');
  }

  function getStatusColor(status) {
    const statusMap = {
      'empty': 'bg-gray-100 text-gray-800',
      'filling': 'bg-blue-100 text-blue-800',
      'full': 'bg-yellow-100 text-yellow-800', 
      'sealed': 'bg-green-100 text-green-800'
    };
    return statusMap[status] || 'bg-gray-100 text-gray-800';
  }

  function getStatusLabel(status) {
    const statusMap = {
      'empty': 'Kosong',
      'filling': 'Sedang Diisi',
      'full': 'Penuh',
      'sealed': 'Tersegel'
    };
    return statusMap[status] || status;
  }

  function printBoxLabel() {
    window.open(`/admin/thermal-print/box/${box.id}`, '_blank');
  }
</script>

<svelte:head>
  <title>Detail Kerdus {box.kode_kerdus} - Admin</title>
</svelte:head>

<AdminLayout>
  <!-- Header -->
  <div class="mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Detail Kerdus</h2>
          <p class="text-gray-600">Informasi lengkap kerdus {box.kode_kerdus}</p>
        </div>
        <div class="flex gap-3">
          <button
            on:click={printBoxLabel}
            class="px-4 py-2 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print Label
          </button>
          <button
            on:click={goBack}
            class="px-4 py-2 text-gray-600 hover:text-gray-800 flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
          </button>
        </div>
      </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Box Information -->
    <div class="lg:col-span-1">
      <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Kerdus</h3>
        
        <div class="space-y-4">
          <div>
            <span class="text-sm font-medium text-gray-600">Kode Kerdus:</span>
            <p class="text-lg font-bold text-gray-900 font-mono">{box.kode_kerdus}</p>
          </div>
          
          <div>
            <span class="text-sm font-medium text-gray-600">Status:</span>
            <div class="mt-1">
              <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {getStatusColor(box.status)}">
                {getStatusLabel(box.status)}
              </span>
            </div>
          </div>
          
          <div>
            <span class="text-sm font-medium text-gray-600">Jenis Qur'an:</span>
            <p class="text-base font-medium text-gray-900">{jenis_quran}</p>
          </div>
          
          <div>
            <span class="text-sm font-medium text-gray-600">Progress:</span>
            <div class="mt-2">
              <div class="flex items-center justify-between text-sm">
                <span>{box.terisi} / {box.kapasitas} Mushaf</span>
                <span>{box.progress_percentage}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3 mt-1">
                <div 
                  class="h-3 rounded-full {box.progress_percentage === 0 ? 'bg-gray-200' : box.progress_percentage < 50 ? 'bg-blue-500' : box.progress_percentage < 100 ? 'bg-yellow-500' : 'bg-green-500'}" 
                  style="width: {box.progress_percentage}%"
                ></div>
              </div>
            </div>
          </div>
          
          <div>
            <span class="text-sm font-medium text-gray-600">Petugas:</span>
            <p class="text-base font-medium text-gray-900">{box.user_name}</p>
          </div>
          
          <div>
            <span class="text-sm font-medium text-gray-600">Tanggal Tugas:</span>
            <p class="text-base text-gray-900">{box.task_date}</p>
          </div>
          
          <div>
            <span class="text-sm font-medium text-gray-600">Dibuat:</span>
            <p class="text-base text-gray-900">{box.created_at}</p>
          </div>
          
          {#if box.seal_code}
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
              <span class="text-sm font-medium text-green-800">Seal Code:</span>
              <p class="text-lg font-bold text-green-900 font-mono">{box.seal_code}</p>
              <span class="text-sm text-green-700">Tersegel: {box.sealed_at}</span>
            </div>
          {/if}
        </div>
      </div>
    </div>

    <!-- Box Contents -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-lg shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Isi Kerdus ({items.length} items)</h3>
        </div>

        {#if items.length > 0}
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Urutan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Resi</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donatur</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakif</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Packed</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                {#each items as item}
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center justify-center w-8 h-8 bg-[#eb3434] text-white rounded-full text-sm font-bold">
                        {item.urutan_dalam_box}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{item.no_resi}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{item.donatur}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{item.wakif}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{item.packed_at}</div>
                      <div class="text-xs text-gray-500">oleh {item.packed_by_name}</div>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {:else}
          <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Kerdus Masih Kosong</h3>
            <p class="text-gray-600">Belum ada mushaf yang dimasukkan ke dalam kerdus ini</p>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>

<FlashMessage />
