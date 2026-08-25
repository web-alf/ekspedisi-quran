<script>
  export let previewImage = null;
  export let imageInfo = null;
  export let fieldPositions = {};
  export let availableFields = {};
  export let onPositionUpdate = () => {};
  
  let selectedField = null;
  let isDragging = false;
  let previewContainer = null;
  let imageElement = null;
  let showCoordinates = true;
  let showFieldLabels = true;
  let previewScale = 1;
  
  // Calculate scale factor for responsive preview
  $: if (imageElement && imageInfo) {
    const containerWidth = previewContainer?.clientWidth || 800;
    const maxWidth = Math.min(containerWidth - 40, 800);
    previewScale = Math.min(maxWidth / imageInfo.width, 1);
  }
  
  // Handle field position click/drag
  function handleFieldClick(event, fieldName) {
    if (!imageElement || !imageInfo) return;
    
    const rect = imageElement.getBoundingClientRect();
    const x = Math.round((event.clientX - rect.left) / previewScale);
    const y = Math.round((event.clientY - rect.top) / previewScale);
    
    // Update position
    const updatedPosition = {
      ...fieldPositions[fieldName],
      x: Math.max(0, Math.min(x, imageInfo.width)),
      y: Math.max(0, Math.min(y, imageInfo.height))
    };
    
    onPositionUpdate(fieldName, updatedPosition);
    selectedField = fieldName;
  }
  
  // Handle image click for positioning
  function handleImageClick(event) {
    if (!selectedField || !imageElement || !imageInfo) return;
    handleFieldClick(event, selectedField);
  }
  
  // Get field style for overlay
  function getFieldStyle(fieldName, position) {
    if (!position || !imageInfo) return '';
    
    const scaledX = position.x * previewScale;
    const scaledY = position.y * previewScale;
    
    return `
      position: absolute;
      left: ${scaledX}px;
      top: ${scaledY}px;
      font-size: ${Math.max(8, (position.font_size || 12) * previewScale)}px;
      color: ${position.color || '#000000'};
      font-family: 'Times New Roman', Times, serif;
      background: rgba(255, 255, 255, 0.8);
      padding: 2px 4px;
      border-radius: 2px;
      border: 2px solid ${selectedField === fieldName ? '#eb3434' : 'transparent'};
      cursor: pointer;
      user-select: none;
      font-weight: bold;
      z-index: 10;
      transform: translate(-50%, -50%);
    `;
  }
  
  // Get sample data for preview - HANYA 2 field yang dibutuhkan
  function getSampleText(fieldName) {
    const samples = {
      wakif_name: 'Bapak Ahmad Sulaiman',
      mushaf_count: '10 eksemplar'
    };
    return samples[fieldName] || fieldName;
  }
  
  // Handle zoom
  function handleZoom(direction) {
    if (direction === 'in') {
      previewScale = Math.min(previewScale * 1.2, 2);
    } else {
      previewScale = Math.max(previewScale / 1.2, 0.3);
    }
  }
  
  // Reset zoom
  function resetZoom() {
    if (imageInfo && previewContainer) {
      const containerWidth = previewContainer.clientWidth - 40;
      const maxWidth = Math.min(containerWidth, 800);
      previewScale = Math.min(maxWidth / imageInfo.width, 1);
    }
  }
</script>

<div class="bg-white rounded-lg border border-gray-200 p-6" bind:this={previewContainer}>
  <!-- Header Controls -->
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-900">Preview Template dengan Variabel</h3>
    
    <div class="flex items-center space-x-4">
      <!-- View Options -->
      <div class="flex items-center space-x-2">
        <label class="flex items-center text-sm">
          <input type="checkbox" bind:checked={showCoordinates} class="mr-1" />
          Koordinat
        </label>
        <label class="flex items-center text-sm">
          <input type="checkbox" bind:checked={showFieldLabels} class="mr-1" />
          Label Field
        </label>
      </div>
      
      <!-- Zoom Controls -->
      <div class="flex items-center space-x-1">
        <button
          on:click={() => handleZoom('out')}
          class="p-1 text-gray-500 hover:text-gray-700 border rounded"
          title="Zoom Out"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
          </svg>
        </button>
        
        <span class="text-xs text-gray-500 px-2">{Math.round(previewScale * 100)}%</span>
        
        <button
          on:click={() => handleZoom('in')}
          class="p-1 text-gray-500 hover:text-gray-700 border rounded"
          title="Zoom In"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        
        <button
          on:click={resetZoom}
          class="p-1 text-gray-500 hover:text-gray-700 border rounded text-xs"
          title="Reset Zoom"
        >
          Reset
        </button>
      </div>
    </div>
  </div>

  {#if previewImage}
    <!-- Instructions -->
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
      <div class="flex items-start space-x-2">
        <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-sm text-blue-700">
          <p class="font-medium mb-1">Cara menggunakan preview:</p>
          <ol class="list-decimal list-inside space-y-1 text-xs">
            <li>Pilih field di panel samping untuk mengedit posisinya</li>
            <li>Klik pada gambar untuk memposisikan field yang dipilih</li>
            <li>Field yang dipilih akan diberi border merah</li>
            <li>Koordinat X,Y akan ditampilkan secara real-time</li>
          </ol>
        </div>
      </div>
    </div>

    <!-- Preview Container -->
    <div class="relative bg-gray-100 rounded-lg overflow-auto" style="max-height: 600px;">
      <!-- Template Image -->
      <div class="relative inline-block">
        <img
          bind:this={imageElement}
          src={previewImage}
          alt="Template Preview"
          class="block"
          style="width: {imageInfo ? imageInfo.width * previewScale : 'auto'}px; height: {imageInfo ? imageInfo.height * previewScale : 'auto'}px;"
          on:click={handleImageClick}
        />
        
        <!-- Field Overlays -->
        {#if showFieldLabels}
          {#each Object.entries(fieldPositions) as [fieldName, position]}
            {#if position && availableFields[fieldName]}
              <div
                style={getFieldStyle(fieldName, position)}
                on:click|stopPropagation={() => selectedField = fieldName}
                class="transition-all duration-200 hover:scale-110"
              >
                {getSampleText(fieldName)}
                {#if showCoordinates}
                  <div class="text-xs text-gray-500 mt-1">
                    ({position.x}, {position.y})
                  </div>
                {/if}
              </div>
            {/if}
          {/each}
        {/if}
        
        <!-- Crosshair for selected field -->
        {#if selectedField && showCoordinates}
          <div class="absolute inset-0 pointer-events-none">
            <div class="absolute w-full h-px bg-red-500 opacity-50" style="top: 50%; transform: translateY(-50%);"></div>
            <div class="absolute h-full w-px bg-red-500 opacity-50" style="left: 50%; transform: translateX(-50%);"></div>
          </div>
        {/if}
      </div>
    </div>

    <!-- Image Info -->
    {#if imageInfo}
      <div class="mt-4 text-sm text-gray-600 bg-gray-50 p-3 rounded">
        <div class="grid grid-cols-3 gap-4">
          <div>
            <span class="font-medium">Dimensi:</span> {imageInfo.width} × {imageInfo.height}px
          </div>
          <div>
            <span class="font-medium">Ukuran:</span> {imageInfo.size}
          </div>
          <div>
            <span class="font-medium">Scale:</span> {Math.round(previewScale * 100)}%
          </div>
        </div>
      </div>
    {/if}
  {:else}
    <!-- No Preview State -->
    <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
      <div class="text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <p class="text-gray-500">Upload template untuk melihat preview</p>
      </div>
    </div>
  {/if}
</div>

<!-- Field Selection Panel -->
{#if Object.keys(availableFields).length > 0}
  <div class="mt-6 bg-gray-50 rounded-lg p-4">
    <h4 class="text-sm font-medium text-gray-900 mb-3">Pilih Field untuk Diposisikan:</h4>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
      {#each Object.entries(availableFields) as [fieldKey, fieldLabel]}
        <button
          on:click={() => selectedField = fieldKey}
          class="p-2 text-sm border rounded-lg transition-colors {selectedField === fieldKey 
            ? 'bg-[#eb3434] text-white border-[#eb3434]' 
            : 'bg-white text-gray-700 border-gray-300 hover:border-[#eb3434]'}"
        >
          {fieldLabel}
          {#if fieldPositions[fieldKey]}
            <div class="text-xs opacity-75 mt-1">
              ({fieldPositions[fieldKey].x}, {fieldPositions[fieldKey].y})
            </div>
          {/if}
        </button>
      {/each}
    </div>
    
    {#if selectedField}
      <div class="mt-3 p-3 bg-white rounded border border-[#eb3434]">
        <p class="text-sm font-medium text-[#eb3434]">
          Field terpilih: {availableFields[selectedField]}
        </p>
        <p class="text-xs text-gray-600 mt-1">
          Klik pada gambar di atas untuk memposisikan field ini
        </p>
      </div>
    {/if}
  </div>
{/if}
