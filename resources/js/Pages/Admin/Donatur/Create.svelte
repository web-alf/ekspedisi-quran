<script>
  import { useForm } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import PhoneInput from '../../../Components/PhoneInput.svelte';
  
  export let errors = {};
  
  // Get today's date for default
  const today = new Date().toISOString().split('T')[0];
  
  const form = useForm({
    kode_donatur: '',
    nama_donatur: '',
    no_hp: '',
    email_donatur: '',
    alamat_donatur: '',
    donation_date: today,
    jenis_wakaf_dipilih: [],
    jumlah_a5: 0,
    jumlah_a6: 0,
    jumlah_iqra: 0,
    prayer_mode: 'semua_donatur', // 'semua_donatur' or 'customize_individual'
    doa_untuk_semua: '',
    wakif_details: []
  });
  
  let validationErrors = {};
  let showValidation = false;

  // Autocomplete state
  let suggestions = [];
  let showSuggestions = false;
  let suggestionLoading = false;
  let kodeDonaturTimeout;
  let selectedSuggestionIndex = -1;
  
  // Reactive calculations
  $: totalQuran = parseInt($form.jumlah_a5 || 0) + parseInt($form.jumlah_a6 || 0) + parseInt($form.jumlah_iqra || 0);
  
  // Auto-generate wakif details when prayer_mode changes or quantities change
  $: if ($form.prayer_mode === 'customize_individual' && (parseInt($form.jumlah_a5 || 0) + parseInt($form.jumlah_a6 || 0) + parseInt($form.jumlah_iqra || 0)) > 0) {
    $form.wakif_details = generateWakifDetails();
  }
  
  // Handle wakaf type selection (same pattern as mushaf request)
  function handleWakafTypeChange(event) {
    const value = event.target.value;
    const checked = event.target.checked;
    
    if (checked) {
      // Add to array and set default quantity to 1
      $form.jenis_wakaf_dipilih = [...$form.jenis_wakaf_dipilih, value];
      if (value === 'A5' && !$form.jumlah_a5) {
        $form.jumlah_a5 = 1;
      } else if (value === 'A6' && !$form.jumlah_a6) {
        $form.jumlah_a6 = 1;
      } else if (value === 'IQRA' && !$form.jumlah_iqra) {
        $form.jumlah_iqra = 1;
      }
    } else {
      // Remove from array and reset quantity to 0
      $form.jenis_wakaf_dipilih = $form.jenis_wakaf_dipilih.filter(item => item !== value);
      if (value === 'A5') {
        $form.jumlah_a5 = 0;
      } else if (value === 'A6') {
        $form.jumlah_a6 = 0;
      } else if (value === 'IQRA') {
        $form.jumlah_iqra = 0;
      }
    }
    
    // Reset wakif details when quantities change
    if ($form.prayer_mode === 'customize_individual') {
      $form.wakif_details = generateWakifDetails();
    }
  }

  // Autocomplete functions
  async function fetchSuggestions(query) {
    if (!query || query.length < 2) {
      suggestions = [];
      showSuggestions = false;
      return;
    }

    suggestionLoading = true;
    try {
      const response = await fetch(`/admin/api/donatur/search-kode?q=${encodeURIComponent(query)}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (response.ok) {
        const result = await response.json();
        suggestions = result.data || [];
        showSuggestions = suggestions.length > 0;
        selectedSuggestionIndex = -1;
      } else {
        suggestions = [];
        showSuggestions = false;
      }
    } catch (error) {
      suggestions = [];
      showSuggestions = false;
    } finally {
      suggestionLoading = false;
    }
  }

  function handleKodeDonaturInput(event) {
    clearTimeout(kodeDonaturTimeout);
    kodeDonaturTimeout = setTimeout(() => {
      fetchSuggestions(event.target.value);
    }, 300);
  }

  function selectSuggestion(donatur) {
    $form.kode_donatur = donatur.kode_donatur;
    $form.nama_donatur = donatur.nama_donatur || '';
    $form.no_hp = donatur.no_hp || '';
    $form.email_donatur = donatur.email_donatur || '';
    $form.alamat_donatur = donatur.alamat_donatur || '';

    suggestions = [];
    showSuggestions = false;
    selectedSuggestionIndex = -1;
  }

  function handleBlur() {
    setTimeout(() => {
      showSuggestions = false;
      selectedSuggestionIndex = -1;
    }, 200);

    const exactMatch = suggestions.find(s => s.kode_donatur === $form.kode_donatur);
    if (exactMatch) {
      selectSuggestion(exactMatch);
    }
  }

  function handleKeydown(event) {
    if (!showSuggestions || suggestions.length === 0) return;

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      selectedSuggestionIndex = (selectedSuggestionIndex + 1) % suggestions.length;
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      selectedSuggestionIndex = (selectedSuggestionIndex - 1 + suggestions.length) % suggestions.length;
    } else if (event.key === 'Enter') {
      event.preventDefault();
      if (selectedSuggestionIndex >= 0) {
        selectSuggestion(suggestions[selectedSuggestionIndex]);
      }
    } else if (event.key === 'Escape') {
      showSuggestions = false;
      selectedSuggestionIndex = -1;
    }
  }

  function generateWakifDetails() {
    const details = [];
    let globalSequence = 1;
    const existingDetails = $form.wakif_details || [];
    
    // Generate A5 items
    for (let i = 1; i <= parseInt($form.jumlah_a5 || 0); i++) {
      // Try to find existing detail for this position
      const existing = existingDetails.find(item => 
        item.wakaf_type === 'A5' && item.sequence_in_type === i
      );
      
      details.push(existing ? {
        ...existing,
        global_sequence: globalSequence++
      } : {
        wakaf_type: 'A5',
        sequence_in_type: i,
        global_sequence: globalSequence++,
        wakif_name: $form.prayer_mode === 'semua_donatur' ? $form.nama_donatur : '',
        doa_request: $form.prayer_mode === 'semua_donatur' ? $form.doa_untuk_semua : '',
        relationship_to_donatur: 'Diri sendiri'
      });
    }
    
    // Generate A6 items
    for (let i = 1; i <= parseInt($form.jumlah_a6 || 0); i++) {
      const existing = existingDetails.find(item => 
        item.wakaf_type === 'A6' && item.sequence_in_type === i
      );
      
      details.push(existing ? {
        ...existing,
        global_sequence: globalSequence++
      } : {
        wakaf_type: 'A6',
        sequence_in_type: i,
        global_sequence: globalSequence++,
        wakif_name: $form.prayer_mode === 'semua_donatur' ? $form.nama_donatur : '',
        doa_request: $form.prayer_mode === 'semua_donatur' ? $form.doa_untuk_semua : '',
        relationship_to_donatur: 'Diri sendiri'
      });
    }
    
    // Generate IQRA items
    for (let i = 1; i <= parseInt($form.jumlah_iqra || 0); i++) {
      const existing = existingDetails.find(item => 
        item.wakaf_type === 'IQRA' && item.sequence_in_type === i
      );
      
      details.push(existing ? {
        ...existing,
        global_sequence: globalSequence++
      } : {
        wakaf_type: 'IQRA',
        sequence_in_type: i,
        global_sequence: globalSequence++,
        wakif_name: $form.prayer_mode === 'semua_donatur' ? $form.nama_donatur : '',
        doa_request: $form.prayer_mode === 'semua_donatur' ? $form.doa_untuk_semua : '',
        relationship_to_donatur: 'Diri sendiri'
      });
    }
    
    return details;
  }
  
  // Copy data to all wakif details
  function copyToAll(index) {
    const sourceItem = $form.wakif_details[index];
    $form.wakif_details = $form.wakif_details.map(item => ({
      ...item,
      wakif_name: sourceItem.wakif_name,
      doa_request: sourceItem.doa_request,
      relationship_to_donatur: sourceItem.relationship_to_donatur
    }));
  }
  
  // Copy from previous item
  function copyFromPrevious(index) {
    if (index > 0) {
      const previousItem = $form.wakif_details[index - 1];
      $form.wakif_details[index] = {
        ...$form.wakif_details[index],
        wakif_name: previousItem.wakif_name,
        doa_request: previousItem.doa_request,
        relationship_to_donatur: previousItem.relationship_to_donatur
      };
    }
  }
  
  function validateForm() {
    validationErrors = {};
    
    // Required fields
    if (!$form.kode_donatur) {
      validationErrors.kode_donatur = 'Kode donatur harus diisi';
    }
    if (!$form.nama_donatur) {
      validationErrors.nama_donatur = 'Nama donatur harus diisi';
    }
    if (!$form.no_hp) {
      validationErrors.no_hp = 'Nomor HP donatur harus diisi';
    }
    if (!$form.donation_date) {
      validationErrors.donation_date = 'Tanggal wakaf harus diisi';
    }
    
    // Wakaf type validation
    if (!$form.jenis_wakaf_dipilih || $form.jenis_wakaf_dipilih.length === 0) {
      validationErrors.jenis_wakaf_dipilih = 'Pilih minimal 1 jenis wakaf';
    } else {
      // Validate quantities for selected types
      if ($form.jenis_wakaf_dipilih.includes('A5') && (!$form.jumlah_a5 || $form.jumlah_a5 <= 0)) {
        validationErrors.jumlah_a5 = 'Jumlah Mushaf A5 harus diisi jika dipilih';
      }
      if ($form.jenis_wakaf_dipilih.includes('A6') && (!$form.jumlah_a6 || $form.jumlah_a6 <= 0)) {
        validationErrors.jumlah_a6 = 'Jumlah Mushaf A6 harus diisi jika dipilih';
      }
      if ($form.jenis_wakaf_dipilih.includes('IQRA') && (!$form.jumlah_iqra || $form.jumlah_iqra <= 0)) {
        validationErrors.jumlah_iqra = 'Jumlah IQRA harus diisi jika dipilih';
      }
    }
    
    // Validate wakif details if customize individual
    if ($form.prayer_mode === 'customize_individual') {
      $form.wakif_details.forEach((detail, index) => {
        if (!detail.wakif_name) {
          validationErrors[`wakif_${index}_name`] = `Nama wakif untuk Al-Qur'an #${index + 1} harus diisi`;
        }
      });
    }
    
    return Object.keys(validationErrors).length === 0;
  }
  
  function handleSubmit() {
    showValidation = true;
    
    if (!validateForm()) {
      // Scroll to first error
      setTimeout(() => {
        const firstError = document.querySelector('.border-red-300');
        if (firstError) {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 100);
      return;
    }
    
    $form.post('/admin/donatur');
  }
  
  function handleCancel() {
    window.history.back();
  }
</script>

<svelte:head>
  <title>Tambah Donatur - Admin Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <!-- Page Header -->
  <div class="mb-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Tambah Donatur</h2>
        <p class="text-gray-600">Donatur dapat mewakafkan Al-Qur'an untuk diri sendiri atau orang lain</p>
      </div>
      <div class="flex space-x-2">
        <button
          on:click={handleCancel}
          class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        >
          Batal
        </button>
      </div>
    </div>
  </div>

  <form on:submit|preventDefault={handleSubmit} class="space-y-6">
    <!-- Informasi Donatur -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Informasi Donatur</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Kode Donatur -->
        <div class="relative">
          <label for="kode_donatur" class="block text-sm font-medium text-gray-700 mb-2">
            Kode Donatur <span class="text-red-500">*</span>
          </label>
          <input
            id="kode_donatur"
            bind:value={$form.kode_donatur}
            on:input={handleKodeDonaturInput}
            on:blur={handleBlur}
            on:keydown={handleKeydown}
            type="text"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(validationErrors.kode_donatur || errors.kode_donatur) ? 'border-red-300' : ''}"
            placeholder="Contoh: DON001"
            required
            autocomplete="off"
          />
          {#if suggestionLoading}
            <div class="absolute right-3 top-9 text-gray-400 text-sm">Mencari...</div>
          {/if}

          <!-- Suggestions Dropdown -->
          {#if showSuggestions && suggestions.length > 0}
            <ul class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto">
              {#each suggestions as suggestion, index}
                <li>
                  <button
                    type="button"
                    on:click={() => selectSuggestion(suggestion)}
                    class="w-full text-left px-4 py-3 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none {index === selectedSuggestionIndex ? 'bg-gray-100' : ''}"
                  >
                    <div class="font-medium text-gray-900">{suggestion.kode_donatur}</div>
                    <div class="text-sm text-gray-600">{suggestion.nama_donatur} · {suggestion.no_hp}</div>
                  </button>
                </li>
              {/each}
            </ul>
          {/if}

          {#if showValidation && validationErrors.kode_donatur}
            <p class="mt-1 text-sm text-red-600">{validationErrors.kode_donatur}</p>
          {/if}
          {#if errors.kode_donatur}
            <p class="mt-1 text-sm text-red-600">{errors.kode_donatur}</p>
          {/if}
        </div>

        <!-- Nama Donatur -->
        <div>
          <label for="donatur_name" class="block text-sm font-medium text-gray-700 mb-2">
            Nama Donatur <span class="text-red-500">*</span>
          </label>
          <input
            id="donatur_name"
            bind:value={$form.nama_donatur}
            type="text"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(validationErrors.nama_donatur || errors.nama_donatur) ? 'border-red-300' : ''}"
            placeholder="Nama lengkap donatur"
            required
          />
          {#if showValidation && validationErrors.nama_donatur}
            <p class="mt-1 text-sm text-red-600">{validationErrors.nama_donatur}</p>
          {/if}
          {#if errors.nama_donatur}
            <p class="mt-1 text-sm text-red-600">{errors.nama_donatur}</p>
          {/if}
        </div>

        <!-- Nomor Telepon -->
        <PhoneInput
          id="donatur_phone"
          name="no_hp"
          bind:value={$form.no_hp}
          placeholder="81234567890"
          required
          errors={(showValidation && validationErrors.no_hp) ? validationErrors.no_hp : (errors.no_hp || '')}
        >
          <span slot="label">Nomor Telepon Donatur</span>
        </PhoneInput>

        <!-- Email -->
        <div>
          <label for="donatur_email" class="block text-sm font-medium text-gray-700 mb-2">
            Email Donatur <span class="text-gray-400">(Opsional)</span>
          </label>
          <input
            id="donatur_email"
            bind:value={$form.email_donatur}
            type="email"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.email_donatur ? 'border-red-300' : ''}"
            placeholder="email@example.com"
          />
          {#if errors.email_donatur}
            <p class="mt-1 text-sm text-red-600">{errors.email_donatur}</p>
          {/if}
        </div>

        <!-- Alamat Donatur -->
        <div class="md:col-span-2">
          <label for="alamat_donatur" class="block text-sm font-medium text-gray-700 mb-2">
            Alamat Donatur <span class="text-gray-400">(Opsional)</span>
          </label>
          <textarea
            id="alamat_donatur"
            bind:value={$form.alamat_donatur}
            rows="3"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.alamat_donatur ? 'border-red-300' : ''}"
            placeholder="Alamat lengkap donatur"
          ></textarea>
          {#if errors.alamat_donatur}
            <p class="mt-1 text-sm text-red-600">{errors.alamat_donatur}</p>
          {/if}
        </div>

        <!-- Tanggal Wakaf -->
        <div>
          <label for="donation_date" class="block text-sm font-medium text-gray-700 mb-2">
            Tanggal Wakaf <span class="text-red-500">*</span>
          </label>
          <input
            id="donation_date"
            bind:value={$form.donation_date}
            type="date"
            max={today}
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(validationErrors.donation_date || errors.donation_date) ? 'border-red-300' : ''}"
            required
          />
          {#if showValidation && validationErrors.donation_date}
            <p class="mt-1 text-sm text-red-600">{validationErrors.donation_date}</p>
          {/if}
          {#if errors.donation_date}
            <p class="mt-1 text-sm text-red-600">{errors.donation_date}</p>
          {/if}
        </div>
      </div>
    </div>

    <!-- Jenis & Jumlah Wakaf -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">📚 Jenis & Jumlah Wakaf</h3>
      
      {#if showValidation && validationErrors.jenis_wakaf_dipilih}
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
          <p class="text-sm text-red-600">{validationErrors.jenis_wakaf_dipilih}</p>
        </div>
      {/if}

      <div class="space-y-4">
        <!-- Mushaf A5 -->
        <div class="border border-gray-200 rounded-lg p-4">
          <label class="flex items-center cursor-pointer">
            <input 
              type="checkbox" 
              value="A5" 
              on:change={handleWakafTypeChange} 
              class="w-5 h-5 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434]" 
            />
            <span class="ml-3 text-gray-700 font-medium">📖 Mushaf Al-Qur'an A5</span>
          </label>
          
          {#if $form.jenis_wakaf_dipilih.includes('A5')}
            <div class="mt-3 ml-8">
              <label for="jumlah-a5" class="block text-sm font-medium text-gray-700 mb-2">
                Jumlah Mushaf A5 <span class="text-red-500">*</span>
              </label>
              <input 
                type="number" 
                id="jumlah-a5"
                bind:value={$form.jumlah_a5} 
                min="1" 
                max="1000" 
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(showValidation && validationErrors.jumlah_a5) ? 'border-red-300' : ''}" 
                placeholder="Jumlah Mushaf A5" 
                required 
              />
              {#if showValidation && validationErrors.jumlah_a5}
                <p class="mt-1 text-sm text-red-600">{validationErrors.jumlah_a5}</p>
              {/if}
            </div>
          {/if}
        </div>

        <!-- Mushaf A6 -->
        <div class="border border-gray-200 rounded-lg p-4">
          <label class="flex items-center cursor-pointer">
            <input 
              type="checkbox" 
              value="A6" 
              on:change={handleWakafTypeChange} 
              class="w-5 h-5 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434]" 
            />
            <span class="ml-3 text-gray-700 font-medium">📗 Mushaf Al-Qur'an A6</span>
          </label>
          
          {#if $form.jenis_wakaf_dipilih.includes('A6')}
            <div class="mt-3 ml-8">
              <label for="jumlah-a6" class="block text-sm font-medium text-gray-700 mb-2">
                Jumlah Mushaf A6 <span class="text-red-500">*</span>
              </label>
              <input 
                type="number" 
                id="jumlah-a6"
                bind:value={$form.jumlah_a6} 
                min="1" 
                max="1000" 
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(showValidation && validationErrors.jumlah_a6) ? 'border-red-300' : ''}" 
                placeholder="Jumlah Mushaf A6" 
                required 
              />
              {#if showValidation && validationErrors.jumlah_a6}
                <p class="mt-1 text-sm text-red-600">{validationErrors.jumlah_a6}</p>
              {/if}
            </div>
          {/if}
        </div>

        <!-- IQRA -->
        <div class="border border-gray-200 rounded-lg p-4">
          <label class="flex items-center cursor-pointer">
            <input 
              type="checkbox" 
              value="IQRA" 
              on:change={handleWakafTypeChange} 
              class="w-5 h-5 text-[#eb3434] border-gray-300 rounded focus:ring-[#eb3434]" 
            />
            <span class="ml-3 text-gray-700 font-medium">📘 IQRA</span>
          </label>
          
          {#if $form.jenis_wakaf_dipilih.includes('IQRA')}
            <div class="mt-3 ml-8">
              <label for="jumlah-iqra" class="block text-sm font-medium text-gray-700 mb-2">
                Jumlah IQRA <span class="text-red-500">*</span>
              </label>
              <input 
                type="number" 
                id="jumlah-iqra"
                bind:value={$form.jumlah_iqra} 
                min="1" 
                max="1000" 
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(showValidation && validationErrors.jumlah_iqra) ? 'border-red-300' : ''}" 
                placeholder="Jumlah IQRA" 
                required 
              />
              {#if showValidation && validationErrors.jumlah_iqra}
                <p class="mt-1 text-sm text-red-600">{validationErrors.jumlah_iqra}</p>
              {/if}
            </div>
          {/if}
        </div>
      </div>

      <!-- Summary -->
      {#if totalQuran > 0}
        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <h4 class="text-sm font-medium text-blue-900 mb-2">📊 Ringkasan Wakaf</h4>
          <div class="space-y-1 text-sm text-blue-800">
            {#if $form.jumlah_a5 > 0}
              <div>Mushaf A5: {$form.jumlah_a5} eksemplar</div>
            {/if}
            {#if $form.jumlah_a6 > 0}
              <div>Mushaf A6: {$form.jumlah_a6} eksemplar</div>
            {/if}
            {#if $form.jumlah_iqra > 0}
              <div>IQRA: {$form.jumlah_iqra} eksemplar</div>
            {/if}
            <div class="font-semibold border-t border-blue-300 pt-2 mt-2">
              Total: {totalQuran} Al-Qur'an/IQRA
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- Pengaturan Wakif -->
    {#if totalQuran > 0}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">👥 Pengaturan Wakif</h3>
        
        <!-- Radio Button Settings -->
        <div class="space-y-4">
          <label class="flex items-start cursor-pointer">
            <input 
              type="radio" 
              bind:group={$form.prayer_mode}
              value="semua_donatur"
              class="w-5 h-5 text-[#eb3434] border-gray-300 focus:ring-[#eb3434] mt-1" 
            />
            <div class="ml-3">
              <span class="text-gray-700 font-medium">Semua Al-Qur'an atas nama donatur</span>
              <p class="text-sm text-gray-500">Jika dipilih, semua Al-Qur'an akan diwakafkan atas nama {$form.nama_donatur || 'donatur'}</p>
            </div>
          </label>

          {#if $form.prayer_mode === 'semua_donatur'}
            <div class="ml-8">
              <label for="doa-semua" class="block text-sm font-medium text-gray-700 mb-2">
                Doa untuk semua Al-Qur'an <span class="text-gray-400">(Opsional)</span>
              </label>
              <textarea
                id="doa-semua"
                bind:value={$form.doa_untuk_semua}
                rows="3"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                placeholder="Contoh: Semoga Allah memberikan berkah dan manfaat..."
              ></textarea>
            </div>
          {/if}

          <label class="flex items-start cursor-pointer">
            <input 
              type="radio" 
              bind:group={$form.prayer_mode}
              value="customize_individual"
              class="w-5 h-5 text-[#eb3434] border-gray-300 focus:ring-[#eb3434] mt-1" 
            />
            <div class="ml-3">
              <span class="text-gray-700 font-medium">Customize individual Al-Qur'an</span>
              <p class="text-sm text-gray-500">Atur nama wakif dan doa untuk setiap Al-Qur'an secara terpisah</p>
            </div>
          </label>
        </div>

        <!-- Individual Customization -->
        {#if $form.prayer_mode === 'customize_individual' && $form.wakif_details.length > 0}
          <div class="mt-6 space-y-4">
            <h4 class="text-md font-medium text-gray-900">Detail per Al-Qur'an</h4>
            
            {#each $form.wakif_details as detail, index}
              <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-3">
                  <h5 class="text-sm font-medium text-gray-700">
                    {detail.wakaf_type} #{detail.sequence_in_type} (Al-Qur'an #{detail.global_sequence})
                  </h5>
                  <div class="flex space-x-2">
                    {#if index === 0}
                      <button
                        type="button"
                        on:click={() => copyToAll(index)}
                        class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded"
                      >
                        Copy ke semua
                      </button>
                    {:else}
                      <button
                        type="button"
                        on:click={() => copyFromPrevious(index)}
                        class="text-xs bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded"
                      >
                        Sama seperti di atas
                      </button>
                    {/if}
                  </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label for={`wakif-name-${index}`} class="block text-sm font-medium text-gray-700 mb-1">
                      Atas Nama <span class="text-red-500">*</span>
                    </label>
                    <input
                      id={`wakif-name-${index}`}
                      bind:value={detail.wakif_name}
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {(showValidation && validationErrors[`wakif_${index}_name`]) ? 'border-red-300' : ''}"
                      placeholder="Nama wakif"
                      required
                    />
                    {#if showValidation && validationErrors[`wakif_${index}_name`]}
                      <p class="mt-1 text-xs text-red-600">{validationErrors[`wakif_${index}_name`]}</p>
                    {/if}
                  </div>
                  
                  <div>
                    <label for={`wakif-doa-${index}`} class="block text-sm font-medium text-gray-700 mb-1">
                      Doa <span class="text-gray-400">(Opsional)</span>
                    </label>
                    <input
                      id={`wakif-doa-${index}`}
                      bind:value={detail.doa_request}
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                      placeholder="Doa yang dititipkan"
                    />
                  </div>
                  
                  <div>
                    <label for={`wakif-rel-${index}`} class="block text-sm font-medium text-gray-700 mb-1">
                      Hubungan <span class="text-gray-400">(Opsional)</span>
                    </label>
                    <select
                      id={`wakif-rel-${index}`}
                      bind:value={detail.relationship_to_donatur}
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                    >
                      <option value="">Pilih hubungan</option>
                      <option value="Diri sendiri">Diri sendiri</option>
                      <option value="Suami/Istri">Suami/Istri</option>
                      <option value="Anak">Anak</option>
                      <option value="Orang tua">Orang tua</option>
                      <option value="Saudara">Saudara</option>
                      <option value="Keluarga">Keluarga</option>
                      <option value="Teman">Teman</option>
                      <option value="Almarhum">Almarhum</option>
                      <option value="Lainnya">Lainnya</option>
                    </select>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    {/if}

    <!-- Submit Button -->
    <div class="flex justify-end space-x-4">
      <button
        type="button"
        on:click={handleCancel}
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors"
      >
        Batal
      </button>
      <button
        type="submit"
        disabled={$form.processing || totalQuran === 0}
        class="bg-[#eb3434] hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-3 rounded-lg font-medium transition-colors"
      >
        {#if $form.processing}
          Menyimpan...
        {:else}
          Simpan Donasi ({totalQuran} Al-Qur'an)
        {/if}
      </button>
    </div>
  </form>
</AdminLayout>
