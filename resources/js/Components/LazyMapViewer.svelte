<script>
  import { onMount, createEventDispatcher } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  export let center = [-2.5, 118]; // Indonesia center
  export let zoom = 5;
  export let height = 400;
  export let markers = [];
  export let geoJsonData = null;
  export let loadingText = 'Loading map...';
  export let mapId = 'map-' + Math.random().toString(36).substr(2, 9);

  const dispatch = createEventDispatcher();
  
  let mapContainer;
  let mapInstance;
  let loading = true;
  let error = null;
  let markersLayer = [];

  onMount(async () => {
    try {
      // Dynamically import Leaflet
      const leafletModule = await import('leaflet');
      const L = leafletModule.default || leafletModule;
      
      // Initialize map
      mapInstance = L.map(mapContainer, {
        center: center,
        zoom: zoom,
        scrollWheelZoom: false
      });

      // Add tile layer
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
      }).addTo(mapInstance);

      // Add GeoJSON if provided
      if (geoJsonData) {
        L.geoJSON(geoJsonData, {
          style: {
            color: '#3b82f6',
            weight: 2,
            fillOpacity: 0.1
          },
          onEachFeature: (feature, layer) => {
            if (feature.properties && feature.properties.name) {
              layer.bindPopup(feature.properties.name);
            }
          }
        }).addTo(mapInstance);
      }

      // Add markers if provided
      updateMarkers();

      // Dispatch ready event
      dispatch('mapReady', { map: mapInstance, L });

      loading = false;
    } catch (err) {
      console.error('Failed to load map:', err);
      error = 'Failed to load map. Please check your internet connection.';
      loading = false;
    }
  });

  // Update markers when props change
  $: if (mapInstance && markers) {
    updateMarkers();
  }

  async function updateMarkers() {
    if (!mapInstance) return;
    
    try {
      const leafletModule = await import('leaflet');
      const L = leafletModule.default || leafletModule;
      
      // Clear existing markers
      markersLayer.forEach(marker => mapInstance.removeLayer(marker));
      markersLayer = [];

      // Add new markers
      markers.forEach(marker => {
        const leafletMarker = L.marker([marker.lat, marker.lng])
          .addTo(mapInstance);
        
        if (marker.popup) {
          leafletMarker.bindPopup(marker.popup);
        }

        if (marker.tooltip) {
          leafletMarker.bindTooltip(marker.tooltip);
        }

        markersLayer.push(leafletMarker);
      });

      // Fit bounds if we have markers
      if (markers.length > 0) {
        const group = L.featureGroup(markersLayer);
        mapInstance.fitBounds(group.getBounds(), { padding: [20, 20] });
      }
    } catch (err) {
      console.error('Failed to update markers:', err);
    }
  }

  // Update center when prop changes
  $: if (mapInstance && center) {
    mapInstance.setView(center, zoom);
  }

  // Public methods
  export function getMap() {
    return mapInstance;
  }

  export function addMarker(lat, lng, options = {}) {
    markers = [...markers, { lat, lng, ...options }];
  }

  export function clearMarkers() {
    markers = [];
  }
</script>

<div class="w-full relative overflow-hidden rounded-lg border border-gray-200" style="height: {height}px">
  {#if loading}
    <div class="absolute inset-0 flex items-center justify-center bg-gray-50 z-10">
      <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
        <p class="text-sm text-gray-600">{loadingText}</p>
      </div>
    </div>
  {:else if error}
    <div class="absolute inset-0 flex items-center justify-center bg-red-50 z-10">
      <div class="text-center">
        <div class="text-red-400 mb-2">
          <HeroIcon name="map" class="w-8 h-8 mx-auto" />
        </div>
        <p class="text-sm text-red-700">{error}</p>
        <button 
          class="mt-2 px-3 py-1 text-xs bg-red-100 hover:bg-red-200 text-red-800 rounded"
          on:click={() => window.location.reload()}
        >
          Retry
        </button>
      </div>
    </div>
  {/if}
  
  <div bind:this={mapContainer} id={mapId} class="w-full h-full"></div>
</div>

<style>
  /* Leaflet styles will be loaded dynamically */
  :global(.leaflet-container) {
    height: 100% !important;
    width: 100% !important;
  }
</style>