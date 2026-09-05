<script>
  import { onMount, tick, onDestroy } from 'svelte';
  import HeroIcon from './UI/HeroIcon.svelte';
  
  export let type = 'line';
  export let data = {};
  export let options = {};
  export let chartId = 'chart-' + Math.random().toString(36).substr(2, 9);
  export let height = 400;
  export let loadingText = 'Loading chart...';

  let chartCanvas;
  let chartInstance;
  let loading = true;
  let error = null;

  let Chart;
  let chartReady = false;

  onMount(async () => {
    try {
      // Dynamically import Chart.js
      const module = await import('chart.js/auto');
      Chart = module.default;
      chartReady = true;
      loading = false;
    } catch (err) {
      console.error('Failed to load Chart.js:', err);
      error = 'Failed to load chart library. Please try refreshing the page.';
      loading = false;
    }
  });

  // Initialize chart when both Chart.js is ready and canvas is available
  $: initializeChart(Chart, chartReady, chartCanvas, loading, error, chartInstance);

  function initializeChart(Chart, chartReady, chartCanvas, loading, error, chartInstance) {
    if (Chart && chartReady && chartCanvas && !loading && !error && !chartInstance) {
      try {
        const ctx = chartCanvas.getContext('2d');
        if (!ctx) {
          console.error('Cannot get 2D context from canvas');
          error = 'Chart rendering not supported. Please try a different browser.';
          return;
        }
        
        chartInstance = new Chart(ctx, {
          type,
          data,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            ...options
          }
        });
      } catch (err) {
        console.error('Failed to initialize chart:', err);
        error = 'Failed to initialize chart. Please try refreshing the page.';
      }
    }
  }

  // Recreate chart when data or options change to prevent legend duplication
  $: if (chartInstance && (data || options) && !loading && !error) {
    try {
      // Destroy existing chart to prevent legend duplication
      chartInstance.destroy();
      chartInstance = null;
      
      // Recreate chart with new data
      if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
        if (ctx) {
          chartInstance = new Chart(ctx, {
            type,
            data,
            options: {
              responsive: true,
              maintainAspectRatio: false,
              ...options
            }
          });
        }
      }
    } catch (err) {
      console.error('Failed to update chart:', err);
    }
  }

  // Cleanup on destroy
  function destroyChart() {
    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }
  }

  // Ensure cleanup happens when component is destroyed
  onDestroy(() => {
    destroyChart();
  });
</script>

<div class="w-full relative" style="height: {height}px">
  {#if loading}
    <div class="absolute inset-0 flex items-center justify-center bg-gray-50 rounded-lg">
      <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
        <p class="text-sm text-gray-600">{loadingText}</p>
      </div>
    </div>
  {:else if error}
    <div class="absolute inset-0 flex items-center justify-center bg-red-50 rounded-lg border border-red-200">
      <div class="text-center">
        <div class="text-red-400 mb-2">
          <HeroIcon name="exclamation-circle" class="w-8 h-8 mx-auto" />
        </div>
        <p class="text-sm text-red-700">{error}</p>
      </div>
    </div>
  {:else}
    <canvas bind:this={chartCanvas} {chartId} class="w-full h-full"></canvas>
  {/if}
</div>

<style>
  canvas {
    max-height: 100%;
  }
</style>