<!-- Komponen untuk input alamat Indonesia dengan cascade dropdown -->
<script>
  import { onMount } from 'svelte';
  
  export let form = {};
  export let errors = {};
  
  // State untuk menyimpan data wilayah
  let provinces = [];
  let regencies = [];
  let districts = [];
  let villages = [];
  
  // Loading states
  let loadingProvinces = false;
  let loadingRegencies = false;
  let loadingDistricts = false;
  let loadingVillages = false;
  
  // Debug states
  let debugInfo = '';
  let apiError = null;
  let currentApiSource = 'open-api.my.id (via Laravel Backend)';
  
  // Cache untuk mengurangi API calls
  let cache = {
    provinces: null,
    regencies: {},
    districts: {},
    villages: {}
  };
  
  // Laravel API endpoints (menggunakan backend sebagai proxy)
  const API_BASE = '/api/wilayah';
  
  // Fungsi untuk fetch data dengan cache dan debugging
  async function fetchWithCache(url, cacheKey, cacheType = 'simple') {
    debugInfo = `Fetching: ${url}`;
    
    try {
      // Check cache first
      if (cacheType === 'simple' && cache[cacheKey]) {
        return cache[cacheKey];
      } else if (cacheType === 'keyed' && cache[cacheKey.type] && cache[cacheKey.type][cacheKey.key]) {
        return cache[cacheKey.type][cacheKey.key];
      }
      
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const responseData = await response.json();
      
      // Handle Laravel API response format
      let data;
      if (responseData.error) {
        throw new Error(responseData.message || responseData.error);
      } else if (Array.isArray(responseData)) {
        data = responseData;
      } else if (responseData.data && Array.isArray(responseData.data)) {
        data = responseData.data;
      } else {
        throw new Error('Invalid response format from Laravel API');
      }
      
      // Validate data structure
      if (!Array.isArray(data)) {
        throw new Error('API returned non-array data');
      }
      
      // Empty data check without console log
      
      // Store in cache
      if (cacheType === 'simple') {
        cache[cacheKey] = data;
      } else if (cacheType === 'keyed') {
        if (!cache[cacheKey.type]) cache[cacheKey.type] = {};
        cache[cacheKey.type][cacheKey.key] = data;
      }
      
      debugInfo = `✅ Loaded ${data.length} items`;
      apiError = null;
      return data;
      
    } catch (error) {
      console.error('AddressFormIndonesia API Error:', error);
      apiError = error.message;
      debugInfo = `❌ Error: ${error.message}`;
      return [];
    }
  }
  
  // Load provinces on mount
  onMount(async () => {
    loadingProvinces = true;
    
    try {
      provinces = await fetchWithCache(`${API_BASE}/provinces`, 'provinces');
    } catch (error) {
      console.error('Failed to load provinces:', error);
      apiError = 'Gagal memuat data provinsi';
    } finally {
      loadingProvinces = false;
    }
  });
  
  // Handle province change
  async function handleProvinceChange() {
    // Reset dependent fields
    form.kota_kabupaten = '';
    form.kota_kabupaten_id = '';
    form.kecamatan = '';
    form.kecamatan_id = '';
    form.kelurahan_desa = '';
    form.kelurahan_desa_id = '';
    regencies = [];
    districts = [];
    villages = [];
    
    if (!form.provinsi_id) {
      return;
    }
    
    loadingRegencies = true;
    try {
      regencies = await fetchWithCache(
        `${API_BASE}/regencies/${form.provinsi_id}`,
        { type: 'regencies', key: form.provinsi_id },
        'keyed'
      );
    } catch (error) {
      console.error('Failed to load regencies:', error);
      apiError = 'Gagal memuat data kota/kabupaten';
    } finally {
      loadingRegencies = false;
    }
  }
  
  // Handle regency change
  async function handleRegencyChange() {
    // Reset dependent fields
    form.kecamatan = '';
    form.kecamatan_id = '';
    form.kelurahan_desa = '';
    form.kelurahan_desa_id = '';
    districts = [];
    villages = [];
    
    if (!form.kota_kabupaten_id) {
      return;
    }
    
    loadingDistricts = true;
    try {
      districts = await fetchWithCache(
        `${API_BASE}/districts/${form.kota_kabupaten_id}`,
        { type: 'districts', key: form.kota_kabupaten_id },
        'keyed'
      );
    } catch (error) {
      console.error('Failed to load districts:', error);
      apiError = 'Gagal memuat data kecamatan';
    } finally {
      loadingDistricts = false;
    }
  }
  
  // Handle district change
  async function handleDistrictChange() {
    // Reset dependent fields
    form.kelurahan_desa = '';
    form.kelurahan_desa_id = '';
    villages = [];
    
    if (!form.kecamatan_id) {
      return;
    }
    
    loadingVillages = true;
    try {
      villages = await fetchWithCache(
        `${API_BASE}/villages/${form.kecamatan_id}`,
        { type: 'villages', key: form.kecamatan_id },
        'keyed'
      );
    } catch (error) {
      console.error('Failed to load villages:', error);
      apiError = 'Gagal memuat data kelurahan/desa';
    } finally {
      loadingVillages = false;
    }
  }
  
  // Update provinsi name when ID changes
  $: if (form.provinsi_id && provinces.length > 0) {
    const selectedProvince = provinces.find(p => p.id === form.provinsi_id);
    if (selectedProvince) {
      form.provinsi = selectedProvince.name;
    }
  }
  
  // Update kota_kabupaten name when ID changes  
  $: if (form.kota_kabupaten_id && regencies.length > 0) {
    const selectedRegency = regencies.find(r => r.id === form.kota_kabupaten_id);
    if (selectedRegency) {
      form.kota_kabupaten = selectedRegency.name;
    }
  }
  
  // Update kecamatan name when ID changes
  $: if (form.kecamatan_id && districts.length > 0) {
    const selectedDistrict = districts.find(d => d.id === form.kecamatan_id);
    if (selectedDistrict) {
      form.kecamatan = selectedDistrict.name;
    }
  }
  
  // Update kelurahan_desa name when ID changes
  $: if (form.kelurahan_desa_id && villages.length > 0) {
    const selectedVillage = villages.find(v => v.id === form.kelurahan_desa_id);
    if (selectedVillage) {
      form.kelurahan_desa = selectedVillage.name;
    }
  }
  
  // Manual retry function
  async function retryLoadProvinces() {
    loadingProvinces = true;
    cache.provinces = null; // Clear cache
    apiError = null;
    try {
      provinces = await fetchWithCache(`${API_BASE}/provinces`, 'provinces');
    } finally {
      loadingProvinces = false;
    }
  }
  
  // Test API connection
  async function testConnection() {
    debugInfo = 'Testing connection...';
    
    try {
      const testUrl = `${API_BASE}/provinces`;
      const response = await fetch(testUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.ok) {
        const responseData = await response.json();
        const data = Array.isArray(responseData) ? responseData : responseData.data || [];
        debugInfo = `✅ API OK - Found ${data.length} provinces`;
        apiError = null;
        
        // Reload provinces
        provinces = data;
        cache.provinces = data;
      } else {
        throw new Error(`HTTP ${response.status}`);
      }
    } catch (error) {
      console.error('API connection test failed:', error);
      debugInfo = `❌ API Error: ${error.message}`;
      apiError = error.message;
    }
  }
</script>

<div class="space-y-6">

  <!-- Provinsi -->
  <div>
    <label for="select-provinsi" class="block text-sm font-medium text-gray-700 mb-2">Provinsi *</label>
    <div class="relative">
      <select 
        id="select-provinsi"
        bind:value={form.provinsi_id}
        on:change={handleProvinceChange}
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.provinsi ? 'border-red-500' : ''}"
        required
        disabled={loadingProvinces}
      >
        <option value="">
          {loadingProvinces ? 'Memuat provinsi...' : 
           provinces.length === 0 ? 'Tidak ada data provinsi - coba retry' : 'Pilih Provinsi'}
        </option>
        {#each provinces as provinsi}
          <option value={provinsi.id}>{provinsi.name}</option>
        {/each}
      </select>
      
      {#if loadingProvinces}
        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
          <div class="animate-spin w-4 h-4 border-2 border-[#eb3434] border-t-transparent rounded-full"></div>
        </div>
      {/if}
    </div>
    {#if errors.provinsi}
      <p class="text-red-500 text-sm mt-1">{errors.provinsi}</p>
    {/if}
  </div>

  <!-- Kota/Kabupaten dan Kecamatan -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Kota/Kabupaten -->
    <div>
      <label for="select-kota" class="block text-sm font-medium text-gray-700 mb-2">Kota/Kabupaten *</label>
      <div class="relative">
        <select
          id="select-kota"
          bind:value={form.kota_kabupaten_id}
          on:change={handleRegencyChange}
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.kota_kabupaten ? 'border-red-500' : ''}"
          required
          disabled={!form.provinsi_id || loadingRegencies}
        >
          <option value="">
            {!form.provinsi_id ? 'Pilih provinsi dulu' : 
             loadingRegencies ? 'Memuat kota/kabupaten...' : 
             regencies.length === 0 ? 'Tidak ada data' : 'Pilih Kota/Kabupaten'}
          </option>
          {#each regencies as regency}
            <option value={regency.id}>{regency.name}</option>
          {/each}
        </select>
        
        {#if loadingRegencies}
          <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin w-4 h-4 border-2 border-[#eb3434] border-t-transparent rounded-full"></div>
          </div>
        {/if}
      </div>
      {#if errors.kota_kabupaten}
        <p class="text-red-500 text-sm mt-1">{errors.kota_kabupaten}</p>
      {/if}
    </div>

    <!-- Kecamatan -->
    <div>
      <label for="select-kecamatan" class="block text-sm font-medium text-gray-700 mb-2">Kecamatan *</label>
      <div class="relative">
        <select
          id="select-kecamatan"
          bind:value={form.kecamatan_id}
          on:change={handleDistrictChange}
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.kecamatan ? 'border-red-500' : ''}"
          required
          disabled={!form.kota_kabupaten_id || loadingDistricts}
        >
          <option value="">
            {!form.kota_kabupaten_id ? 'Pilih kota/kabupaten dulu' : 
             loadingDistricts ? 'Memuat kecamatan...' : 
             districts.length === 0 ? 'Tidak ada data' : 'Pilih Kecamatan'}
          </option>
          {#each districts as district}
            <option value={district.id}>{district.name}</option>
          {/each}
        </select>
        
        {#if loadingDistricts}
          <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin w-4 h-4 border-2 border-[#eb3434] border-t-transparent rounded-full"></div>
          </div>
        {/if}
      </div>
      {#if errors.kecamatan}
        <p class="text-red-500 text-sm mt-1">{errors.kecamatan}</p>
      {/if}
    </div>
  </div>

  <!-- Kelurahan/Desa dan Kode Pos -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Kelurahan/Desa -->
    <div>
      <label for="select-kelurahan" class="block text-sm font-medium text-gray-700 mb-2">Kelurahan/Desa *</label>
      <div class="relative">
        <select
          id="select-kelurahan"
          bind:value={form.kelurahan_desa_id}
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.kelurahan_desa ? 'border-red-500' : ''}"
          required
          disabled={!form.kecamatan_id || loadingVillages}
        >
          <option value="">
            {!form.kecamatan_id ? 'Pilih kecamatan dulu' : 
             loadingVillages ? 'Memuat kelurahan/desa...' : 
             villages.length === 0 ? 'Tidak ada data' : 'Pilih Kelurahan/Desa'}
          </option>
          {#each villages as village}
            <option value={village.id}>{village.name}</option>
          {/each}
        </select>
        
        {#if loadingVillages}
          <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin w-4 h-4 border-2 border-[#eb3434] border-t-transparent rounded-full"></div>
          </div>
        {/if}
      </div>
      {#if errors.kelurahan_desa}
        <p class="text-red-500 text-sm mt-1">{errors.kelurahan_desa}</p>
      {/if}
    </div>

    <!-- Kode Pos -->
    <div>
      <label for="kode-pos" class="block text-sm font-medium text-gray-700 mb-2">Kode Pos</label>
      <input
        type="text"
        id="kode-pos"
        bind:value={form.kode_pos}
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.kode_pos ? 'border-red-500' : ''}"
        placeholder="Contoh: 40132"
        maxlength="5"
        inputmode="numeric"
      />
      {#if errors.kode_pos}
        <p class="text-red-500 text-sm mt-1">{errors.kode_pos}</p>
      {/if}
      <p class="text-sm text-gray-500 mt-1">Masukkan 5 digit kode pos</p>
    </div>
  </div>

  <!-- Alamat Detail -->
  <div>
    <label for="alamat-detail" class="block text-sm font-medium text-gray-700 mb-2">Alamat Detail *</label>
    <textarea
      id="alamat-detail"
      bind:value={form.alamat_detail}
      rows="3"
      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {errors.alamat_detail ? 'border-red-500' : ''}"
      placeholder="Contoh: Jalan Raya Lembang No. 123, RT 02/RW 05, Gang Masjid"
      required
    ></textarea>
    <p class="text-sm text-gray-500 mt-1">Masukkan detail jalan, nomor rumah, RT/RW, gang, patokan, dll.</p>
    {#if errors.alamat_detail}
      <p class="text-red-500 text-sm mt-1">{errors.alamat_detail}</p>
    {/if}
  </div>

  <!-- Fallback: Manual Input (jika API gagal) -->
  {#if provinces.length === 0 && !loadingProvinces && apiError}
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
      <h4 class="text-sm font-semibold text-yellow-800 mb-3">⚠️ Mode Manual (API Tidak Tersedia)</h4>
      <p class="text-sm text-yellow-700 mb-4">Karena koneksi API bermasalah, silakan input alamat secara manual:</p>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="manual-provinsi" class="block text-sm font-medium text-yellow-700 mb-2">Provinsi *</label>
          <input
            type="text"
            id="manual-provinsi"
            bind:value={form.provinsi}
            class="w-full px-3 py-2 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm"
            placeholder="Contoh: Jawa Barat"
            required
          />
        </div>
        
        <div>
          <label for="manual-kota" class="block text-sm font-medium text-yellow-700 mb-2">Kota/Kabupaten *</label>
          <input
            type="text"
            id="manual-kota"
            bind:value={form.kota_kabupaten}
            class="w-full px-3 py-2 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm"
            placeholder="Contoh: Bandung"
            required
          />
        </div>
        
        <div>
          <label for="manual-kecamatan" class="block text-sm font-medium text-yellow-700 mb-2">Kecamatan *</label>
          <input
            type="text"
            id="manual-kecamatan"
            bind:value={form.kecamatan}
            class="w-full px-3 py-2 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm"
            placeholder="Contoh: Coblong"
            required
          />
        </div>
        
        <div>
          <label for="manual-kelurahan" class="block text-sm font-medium text-yellow-700 mb-2">Kelurahan/Desa *</label>
          <input
            type="text"
            id="manual-kelurahan"
            bind:value={form.kelurahan_desa}
            class="w-full px-3 py-2 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm"
            placeholder="Contoh: Lebak Gede"
            required
          />
        </div>
      </div>
    </div>
  {/if}
</div>
