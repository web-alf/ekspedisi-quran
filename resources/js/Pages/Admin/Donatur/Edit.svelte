<script>
  import { useForm } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import PhoneInput from '../../../Components/PhoneInput.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  
  export let donatur;
  export let errors = {};
  
  // Initialize form with only editable fields
  const form = useForm({
    kode_donatur: donatur.kode_donatur || '',
    nama_donatur: donatur.nama_donatur || '',
    no_hp: donatur.no_hp || '',
    email_donatur: donatur.email_donatur || '',
    alamat_donatur: donatur.alamat_donatur || '',
    donation_date: donatur.donation_date || '',
    doa_untuk_semua: donatur.doa_untuk_semua || '',
    prayer_mode: donatur.prayer_mode || 'semua_donatur'
  });

  // Form for updating individual wakif names
  const wakifForm = useForm({
    wakif_updates: []
  });
  
  let validationErrors = {};
  let showValidation = false;
  
  // Read-only calculated values
  const actualA5Count = donatur.actualA5Count || 0;
  const actualA6Count = donatur.actualA6Count || 0;
  const actualIqraCount = donatur.actualIqraCount || 0;
  const totalActual = actualA5Count + actualA6Count + actualIqraCount;
  
  // Wakaf items for individual editing
  let wakafItems = donatur.wakafItems || [];
  let editingWakifNames = false;
  
  function handleSubmit() {
    showValidation = true;
    validationErrors = {};
    
    // Client-side validation
    if (!$form.kode_donatur.trim()) {
      validationErrors.kode_donatur = 'Kode donatur wajib diisi';
    }
    
    if (!$form.nama_donatur.trim()) {
      validationErrors.nama_donatur = 'Nama donatur wajib diisi';
    }
    
    if (!$form.no_hp.trim()) {
      validationErrors.no_hp = 'Nomor HP wajib diisi';
    }
    
    if (!$form.donation_date) {
      validationErrors.donation_date = 'Tanggal donasi wajib diisi';
    }
    
    if (!$form.prayer_mode) {
      validationErrors.prayer_mode = 'Mode doa harus dipilih';
    }
    
    // If there are validation errors, don't submit
    if (Object.keys(validationErrors).length > 0) {
      return;
    }
    
    // Submit form
    $form.patch(`/admin/donatur/${donatur.id}`, {
      onSuccess: () => {
        showValidation = false;
      },
      onError: (serverErrors) => {
        validationErrors = { ...validationErrors, ...serverErrors };
      }
    });
  }
  
  function handleWakifUpdate() {
    // Prepare wakif updates from current wakaf items
    const updates = wakafItems
      .filter(item => item.status === 'pending') // Only pending items can be edited
      .map(item => ({
        wakaf_item_id: item.id,
        wakif_name: item.wakif_name || '',
        doa_request: item.doa_request || '',
        relationship_to_donatur: item.relationship_to_donatur || ''
      }));
    
    $wakifForm.wakif_updates = updates;
    
    $wakifForm.patch(`/admin/donatur/${donatur.id}/update-wakif-names`, {
      onSuccess: () => {
        editingWakifNames = false;
        // Refresh the page to get updated data
        location.reload();
      },
      onError: (serverErrors) => {
        console.error('Error updating wakif names:', serverErrors);
      }
    });
  }

  function updateWakifName(itemId, newName) {
    wakafItems = wakafItems.map(item => 
      item.id === itemId ? { ...item, wakif_name: newName } : item
    );
  }
</script>

<AdminLayout>
  <div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Edit Donatur</h1>
      <p class="text-gray-600">Ubah informasi donatur dan detail donasi</p>
    </div>

    <!-- Form -->
    <form on:submit|preventDefault={handleSubmit} class="space-y-8">
      <!-- Basic Information -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Informasi Donatur</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Kode Donatur -->
          <div>
            <label for="kode_donatur" class="block text-sm font-medium text-gray-700 mb-2">
              Kode Donatur <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="kode_donatur"
              bind:value={$form.kode_donatur}
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                {(errors.kode_donatur || validationErrors.kode_donatur) ? 'border-red-500 bg-red-50' : ''}"
              placeholder="Contoh: D001"
            />
            {#if errors.kode_donatur || validationErrors.kode_donatur}
            <p class="mt-1 text-sm text-red-600">{errors.kode_donatur || validationErrors.kode_donatur}</p>
            {/if}
            <p class="mt-1 text-xs text-gray-500">
              Kode donatur hanya dapat diubah jika belum ada pengiriman aktif.
            </p>
          </div>

          <!-- Nama Donatur -->
          <div>
            <label for="nama_donatur" class="block text-sm font-medium text-gray-700 mb-2">
              Nama Donatur <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="nama_donatur"
              bind:value={$form.nama_donatur}
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                {(errors.nama_donatur || validationErrors.nama_donatur) ? 'border-red-500 bg-red-50' : ''}"
              placeholder="Nama lengkap donatur"
            />
            {#if errors.nama_donatur || validationErrors.nama_donatur}
            <p class="mt-1 text-sm text-red-600">{errors.nama_donatur || validationErrors.nama_donatur}</p>
            {/if}
          </div>

          <!-- Nomor Telepon -->
          <PhoneInput
            id="no_hp"
            name="no_hp"
            bind:value={$form.no_hp}
            placeholder="81234567890"
            required
            errors={errors.no_hp || validationErrors.no_hp || ''}
          >
            <span slot="label">Nomor Telepon</span>
          </PhoneInput>

          <!-- Email -->
          <div>
            <label for="email_donatur" class="block text-sm font-medium text-gray-700 mb-2">
              Email <span class="text-gray-400">(Opsional)</span>
            </label>
            <input
              type="email"
              id="email_donatur"
              bind:value={$form.email_donatur}
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="email@contoh.com"
            />
          </div>

          <!-- Tanggal Donasi -->
          <div class="md:col-span-2">
            <label for="donation_date" class="block text-sm font-medium text-gray-700 mb-2">
              Tanggal Donasi <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              id="donation_date"
              bind:value={$form.donation_date}
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                {(errors.donation_date || validationErrors.donation_date) ? 'border-red-500 bg-red-50' : ''}"
            />
            {#if errors.donation_date || validationErrors.donation_date}
            <p class="mt-1 text-sm text-red-600">{errors.donation_date || validationErrors.donation_date}</p>
            {/if}
          </div>

          <!-- Alamat -->
          <div class="md:col-span-2">
            <label for="alamat_donatur" class="block text-sm font-medium text-gray-700 mb-2">
              Alamat <span class="text-gray-400">(Opsional)</span>
            </label>
            <textarea
              id="alamat_donatur"
              bind:value={$form.alamat_donatur}
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Alamat lengkap donatur"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Donation Summary (Read-only) -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Ringkasan Donasi</h2>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
          <h3 class="text-sm font-medium text-blue-900 mb-4">Total Aktual (Berdasarkan Wakaf Items)</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            {#if actualA5Count > 0}
            <div class="text-center">
              <div class="text-lg font-bold text-blue-700">{actualA5Count}</div>
              <div class="text-blue-600">Al-Qur'an A5</div>
            </div>
            {/if}
            
            {#if actualA6Count > 0}
            <div class="text-center">
              <div class="text-lg font-bold text-blue-700">{actualA6Count}</div>
              <div class="text-blue-600">Al-Qur'an A6</div>
            </div>
            {/if}
            
            {#if actualIqraCount > 0}
            <div class="text-center">
              <div class="text-lg font-bold text-blue-700">{actualIqraCount}</div>
              <div class="text-blue-600">IQRA</div>
            </div>
            {/if}
            
            <div class="text-center {(actualA5Count > 0 || actualA6Count > 0 || actualIqraCount > 0) ? 'border-l border-blue-300 pl-4' : ''}">
              <div class="text-xl font-bold text-blue-800">{totalActual}</div>
              <div class="text-blue-700">Total Aktual</div>
            </div>
          </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
          <p class="text-sm text-yellow-800">
            <strong>Catatan:</strong> Jumlah ini dihitung berdasarkan wakaf items yang sudah terbuat. 
            Untuk mengubah jumlah, silakan kelola wakaf items secara individual.
          </p>
        </div>
      </div>

      <!-- Prayer & Customization Options -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Opsi Doa & Kustomisasi</h2>
        
        <!-- Prayer Mode -->
        <fieldset class="mb-6">
          <legend class="block text-sm font-medium text-gray-700 mb-3">
            Mode Doa <span class="text-red-500">*</span>
          </legend>

          <div class="space-y-3">
            <div class="flex items-center">
              <input
                type="radio"
                id="prayer_semua_donatur"
                bind:group={$form.prayer_mode}
                value="semua_donatur"
                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 focus:ring-2"
              />
              <label for="prayer_semua_donatur" class="ml-2 text-sm text-gray-900">
                Semua Atas Nama Donatur
              </label>
            </div>
            
            <div class="flex items-center">
              <input
                type="radio"
                id="prayer_customize_individual"
                bind:group={$form.prayer_mode}
                value="customize_individual"
                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 focus:ring-2"
              />
              <label for="prayer_customize_individual" class="ml-2 text-sm text-gray-900">
                Kustomisasi Individual
              </label>
            </div>
          </div>

          {#if errors.prayer_mode || validationErrors.prayer_mode}
          <p class="mt-2 text-sm text-red-600">
            {errors.prayer_mode || validationErrors.prayer_mode}
          </p>
          {/if}
          
          {#if $form.prayer_mode === 'semua_donatur'}
            <div class="mt-4">
              <label for="doa_untuk_semua" class="block text-sm font-medium text-gray-700 mb-2">
                Doa untuk semua wakaf
              </label>
              <textarea
                id="doa_untuk_semua"
                bind:value={$form.doa_untuk_semua}
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Masukkan doa untuk semua wakaf..."
              ></textarea>
            </div>
          {/if}
        </fieldset>

        <!-- Individual Wakif Names Section -->
        {#if $form.prayer_mode === 'customize_individual' && wakafItems.length > 0}
        <div class="border-t border-gray-200 pt-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-md font-medium text-gray-900">Nama Wakif Individual</h3>
            <div class="flex space-x-2">
              {#if !editingWakifNames}
                <button
                  type="button"
                  on:click={() => editingWakifNames = true}
                  class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors"
                >
                  Edit Nama
                </button>
              {:else}
                <button
                  type="button"
                  on:click={() => editingWakifNames = false}
                  class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="button"
                  on:click={handleWakifUpdate}
                  disabled={$wakifForm.processing}
                  class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-md hover:bg-green-200 disabled:opacity-50 transition-colors"
                >
                  {$wakifForm.processing ? 'Menyimpan...' : 'Simpan'}
                </button>
              {/if}
            </div>
          </div>

          <div class="space-y-2 max-h-60 overflow-y-auto">
            {#each wakafItems as item, index (item.id)}
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                <div class="flex-1">
                  <span class="text-sm font-medium text-gray-900">
                    {item.wakaf_type} #{item.sequence_in_type}
                  </span>
                  <span class="text-xs text-gray-500 ml-2">
                    Status: {item.status}
                  </span>
                </div>
                
                <div class="flex-2 max-w-xs">
                  {#if editingWakifNames && item.status === 'pending'}
                    <input
                      type="text"
                      value={item.wakif_name || ''}
                      on:input={(e) => updateWakifName(item.id, e.target.value)}
                      placeholder="Nama wakif"
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                  {:else}
                    <span class="text-sm text-gray-700">
                      {item.wakif_name || 'Belum diisi'}
                    </span>
                    {#if item.status !== 'pending'}
                      <span class="text-xs text-gray-500 ml-2">(tidak dapat diedit)</span>
                    {/if}
                  {/if}
                </div>
              </div>
            {/each}
          </div>

          <div class="mt-3 text-xs text-gray-500">
            <strong>Catatan:</strong> Hanya item dengan status "pending" yang dapat diedit nama wakifnya.
          </div>
        </div>
        {/if}
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-between">
        <!-- Left side - Additional Actions -->
        <div class="flex items-center space-x-3">
          <!-- Kelola Wakaf Items Button -->
          <a
            href="/admin/donatur/{donatur.id}/wakaf-items"
            class="px-4 py-2 text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
          >
            <HeroIcon name="cube" class="w-4 h-4" />
            Kelola Wakaf Items
          </a>
        </div>

        <!-- Right side - Form Actions -->
        <div class="flex items-center space-x-4">
          <a
            href="/admin/donatur/{donatur.id}"
            class="px-6 py-2 text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Batal
          </a>
        
        <button
          type="submit"
          disabled={$form.processing}
          class="px-6 py-2 text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {#if $form.processing}
            <span class="flex items-center">
              <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Menyimpan...
            </span>
          {:else}
            Simpan Perubahan
          {/if}
        </button>
        </div>
      </div>
    </form>
  </div>
</AdminLayout>
