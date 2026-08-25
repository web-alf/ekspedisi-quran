<script>
  import { TelInput, normalizedCountries, getCountryForPartialE164Number } from 'svelte-tel-input';
  import 'svelte-tel-input/styles/flags.css';

  export let value = '';
  export let id = 'phone';
  export let name = 'phone';
  export let placeholder = '';
  export let required = false;
  export let disabled = false;
  export let errors = '';

  let country = 'ID';
  let showDropdown = false;
  let searchQuery = '';
  let dropdownRef;
  let telInputRef;

  // Internal value for TelInput (null when empty)
  let telValue = value || null;

  function parseExternalValue(val) {
    if (!val) {
      country = 'ID';
      telValue = null;
      return;
    }
    const detected = getCountryForPartialE164Number(val);
    if (detected) {
      country = detected;
    }
    telValue = val;
  }

  // Sync with external value changes (e.g. autocomplete)
  let previousExternalValue = value;
  $: if (value !== previousExternalValue) {
    previousExternalValue = value;
    parseExternalValue(value);
  }

  $: filteredCountries = searchQuery.trim()
    ? normalizedCountries.filter((c) =>
        c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        c.dialCode.includes(searchQuery) ||
        c.iso2.toLowerCase().includes(searchQuery.toLowerCase())
      )
    : [];

  let selectedIndex = -1;
  $: if (filteredCountries.length > 0 && selectedIndex >= filteredCountries.length) {
    selectedIndex = filteredCountries.length - 1;
  }

  function handleUpdateValue(event) {
    const newValue = event.detail;
    value = newValue || '';
    previousExternalValue = value;
  }

  function selectCountry(iso2) {
    country = iso2;
    showDropdown = false;
    searchQuery = '';
    selectedIndex = -1;
    // Focus the phone input after selection
    if (telInputRef?.el) {
      telInputRef.el.focus();
    }
  }

  function handleDropdownKeydown(e) {
    if (!showDropdown) {
      return;
    }

    if (e.key === 'Escape') {
      showDropdown = false;
      searchQuery = '';
      selectedIndex = -1;
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (filteredCountries.length === 0) {
        return;
      }
      selectedIndex = (selectedIndex + 1) % filteredCountries.length;
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (filteredCountries.length === 0) {
        return;
      }
      selectedIndex = (selectedIndex - 1 + filteredCountries.length) % filteredCountries.length;
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedIndex >= 0 && filteredCountries[selectedIndex]) {
        selectCountry(filteredCountries[selectedIndex].iso2);
      }
    }
  }

  function handleDocumentClick(e) {
    if (dropdownRef && !dropdownRef.contains(e.target)) {
      showDropdown = false;
      searchQuery = '';
      selectedIndex = -1;
    }
  }

  $: selectedCountryDialCode = normalizedCountries.find((c) => c.iso2 === country)?.dialCode || '';
</script>

<svelte:window on:click={handleDocumentClick} />

<div bind:this={dropdownRef} class="relative">
  <label for={id} class="block text-sm font-medium text-gray-700 mb-2">
    <slot name="label">Nomor Telepon</slot>
    {#if required}
      <span class="text-red-500">*</span>
    {/if}
  </label>

  <div class="flex">
    <!-- Country Selector Button -->
    <button
      type="button"
      on:click={() => (showDropdown = !showDropdown)}
      {disabled}
      class="inline-flex items-center px-3 py-2 border border-gray-300 bg-gray-50 text-gray-700 text-sm rounded-l-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent border-r-0 {errors ? 'border-red-500 bg-red-50' : ''}"
      aria-haspopup="listbox"
      aria-expanded={showDropdown}
    >
      <span class="flag flag-{country.toLowerCase()} w-6 h-4 inline-block mr-2"></span>
      <span class="font-medium">+{selectedCountryDialCode}</span>
      <svg class="w-4 h-4 ml-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Phone Number Input -->
    <TelInput
      bind:this={telInputRef}
      value={telValue}
      {country}
      {id}
      {name}
      {placeholder}
      {required}
      {disabled}
      class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-r-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm {errors ? 'border-red-500 bg-red-50' : ''}"
      options={{ autoPlaceholder: true, spaces: true, format: 'national' }}
      on:updateValue={handleUpdateValue}
    />
  </div>

  {#if errors}
    <p class="mt-1 text-sm text-red-600">{errors}</p>
  {/if}

  <!-- Dropdown -->
  {#if showDropdown}
    <div
      class="absolute z-50 mt-1 w-72 bg-white border border-gray-200 rounded-lg shadow-lg"
      on:keydown={handleDropdownKeydown}
      role="listbox"
      tabindex="-1"
    >
      <!-- Search -->
      <div class="p-2 border-b border-gray-100">
        <input
          type="text"
          bind:value={searchQuery}
          placeholder="Cari negara..."
          class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          on:click|stopPropagation
        />
      </div>

      <!-- Country List -->
      <ul class="max-h-60 overflow-y-auto">
        {#each filteredCountries as countryItem, index (countryItem.iso2)}
          <li>
            <button
              type="button"
              on:click={() => selectCountry(countryItem.iso2)}
              class="w-full text-left px-3 py-2 flex items-center hover:bg-gray-100 focus:bg-gray-100 focus:outline-none {countryItem.iso2 === country ? 'bg-blue-50' : ''} {index === selectedIndex ? 'bg-gray-100' : ''}"
              role="option"
              aria-selected={countryItem.iso2 === country}
            >
              <span class="flag flag-{countryItem.iso2.toLowerCase()} w-6 h-4 inline-block mr-2"></span>
              <span class="flex-1 text-sm text-gray-700 truncate">{countryItem.name}</span>
              <span class="text-sm text-gray-500 font-medium ml-2">+{countryItem.dialCode}</span>
            </button>
          </li>
        {:else}
          {#if searchQuery.trim()}
            <li class="px-3 py-2 text-sm text-gray-500">Tidak ditemukan</li>
          {:else}
            <li class="px-3 py-2 text-sm text-gray-500">Ketik untuk mencari negara...</li>
          {/if}
        {/each}
      </ul>
    </div>
  {/if}
</div>
