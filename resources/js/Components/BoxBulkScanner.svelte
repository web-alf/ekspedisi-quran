<script>
  import { createEventDispatcher, onMount } from 'svelte';
  import LazyQRScanner from './LazyQRScanner.svelte';
  
  const dispatch = createEventDispatcher();
  
  // Props
  export let isActive = false;
  export let scanMode = 'individual'; // 'individual' or 'box'
  
  // State
  let scanner = null;
  let scannerContainer;
  let scannerStarted = false;
  let lastScannedCode = '';
  let scanCooldown = false;
  
  // Scanner config
  const scannerConfig = {
    fps: 10,
    qrbox: { width: 250, height: 250 },
    aspectRatio: 1.0,
    rememberLastUsedCamera: true
  };

  onMount(() => {
    // Page visibility API for memory management
    const handleVisibilityChange = async () => {
      if (document.hidden && scannerStarted) {
        console.debug('Page hidden, stopping scanner for memory conservation');
        stopScanner();
      }
    };
    
    // Beforeunload cleanup
    const handleBeforeUnload = async (e) => {
      stopScanner();
    };
    
    // Event listeners for memory management
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('beforeunload', handleBeforeUnload);
    
    // Mobile memory pressure handling
    if ('memory' in performance) {
      const checkMemory = () => {
        const memoryInfo = performance.memory;
        const usedMemoryMB = memoryInfo.usedJSHeapSize / 1024 / 1024;
        const totalMemoryMB = memoryInfo.totalJSHeapSize / 1024 / 1024;
        
        // Stop scanner if memory usage is too high (>80% of heap)
        if (usedMemoryMB > totalMemoryMB * 0.8 && scannerStarted) {
          console.warn(`High memory usage detected: ${usedMemoryMB.toFixed(2)}MB/${totalMemoryMB.toFixed(2)}MB`);
          stopScanner();
          dispatch('scannerError', { error: 'Scanner stopped due to high memory usage' });
        }
      };
      
      // Check memory usage every 30 seconds
      const memoryCheckInterval = setInterval(checkMemory, 30000);
      
      return () => {
        clearInterval(memoryCheckInterval);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('beforeunload', handleBeforeUnload);
        if (scanner) {
          stopScanner();
        }
      };
    }
    
    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('beforeunload', handleBeforeUnload);
      if (scanner) {
        stopScanner();
      }
    };
  });

  $: if (isActive && !scannerStarted) {
    startScanner();
  } else if (!isActive && scannerStarted) {
    stopScanner();
  }

  async function startScanner() {
    if (scannerStarted || !scannerContainer) return;
    
    // Cleanup any existing scanner first
    stopScanner();
    
    try {
      // Enhanced config for mobile performance
      const config = {
        fps: 8, // Reduced for mobile performance
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        rememberLastUsedCamera: true,
        videoConstraints: {
          facingMode: { ideal: "environment" },
          width: { min: 640, ideal: 1280, max: 1920 },
          height: { min: 480, ideal: 720, max: 1080 }
        },
        // Mobile optimizations
        experimentalFeatures: {
          useBarCodeDetectorIfSupported: true
        }
      };
      
      scanner = new Html5QrcodeScanner("qr-scanner-container", config, false);
      
      scanner.render(
        (decodedText, decodedResult) => {
          handleScanSuccess(decodedText, decodedResult);
        },
        (error) => {
          // Silent error handling - QR scanning produces many errors during scanning
          if (error && !error.includes('NotFoundException') && 
              !error.includes('NotAllowedError') && 
              !error.includes('PermissionDeniedError')) {
            console.warn('QR Scan Error:', error);
          }
        }
      );
      
      scannerStarted = true;
      dispatch('scannerStarted');
      
      // Memory monitoring
      if (typeof performance !== 'undefined' && performance.memory) {
        console.debug('Memory after scanner start:', {
          used: (performance.memory.usedJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
          total: (performance.memory.totalJSHeapSize / 1024 / 1024).toFixed(2) + 'MB'
        });
      }
      
    } catch (error) {
      console.error('Failed to start scanner:', error);
      
      // Enhanced error handling
      if (error.name === 'NotAllowedError') {
        dispatch('scannerError', { error: 'Kamera tidak diizinkan. Silakan refresh halaman dan izinkan akses kamera.' });
      } else if (error.name === 'NotFoundError') {
        dispatch('scannerError', { error: 'Kamera tidak ditemukan. Pastikan perangkat memiliki kamera.' });
      } else {
        dispatch('scannerError', { error: error.message });
      }
    }
  }

  function stopScanner() {
    if (scanner && scannerStarted) {
      try {
        scanner.clear();
        
        // Additional cleanup: stop all media tracks manually
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
          navigator.mediaDevices.enumerateDevices().then(devices => {
            devices.forEach(device => {
              if (device.kind === 'videoinput') {
                // Try to stop any active streams
                try {
                  navigator.mediaDevices.getUserMedia({ video: { deviceId: device.deviceId } })
                    .then(stream => {
                      stream.getTracks().forEach(track => {
                        track.stop();
                        console.debug('Stopped media track:', track.label);
                      });
                    })
                    .catch(() => {}); // Ignore errors for inactive streams
                } catch (e) {
                  // Ignore errors
                }
              }
            });
          }).catch(() => {});
        }
        
        scannerStarted = false;
        dispatch('scannerStopped');
        
        // Clear DOM element
        const qrScannerElement = document.getElementById('qr-scanner-container');
        if (qrScannerElement) {
          qrScannerElement.innerHTML = '';
        }
        
        // Memory logging
        if (typeof performance !== 'undefined' && performance.memory) {
          console.debug('Memory after scanner stop:', {
            used: (performance.memory.usedJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
            total: (performance.memory.totalJSHeapSize / 1024 / 1024).toFixed(2) + 'MB'
          });
        }
        
      } catch (error) {
        console.error('Error stopping scanner:', error);
        // Force reset state even if cleanup fails
        scannerStarted = false;
        scanner = null;
      }
    }
    
    // Always reset scanner reference
    scanner = null;
  }

  function handleScanSuccess(decodedText, decodedResult) {
    // Prevent duplicate scans
    if (scanCooldown || decodedText === lastScannedCode) {
      return;
    }
    
    // Set cooldown to prevent rapid duplicate scans
    scanCooldown = true;
    lastScannedCode = decodedText;
    
    setTimeout(() => {
      scanCooldown = false;
    }, 2000);
    
    try {
      // Try to parse as JSON first
      let qrData;
      let isBoxCode = false;
      
      try {
        qrData = JSON.parse(decodedText);
      } catch (e) {
        // If not JSON, check if it's a box code format
        if (/^KB-\d{8}-\d{3}-[A-Z0-9]+-\d{2}$/.test(decodedText)) {
          // New simple box QR format
          qrData = { type: 'box', kode_kerdus: decodedText };
          isBoxCode = true;
        } else {
          // Other plain text (legacy individual QR codes)
          qrData = { type: 'individual', code: decodedText };
        }
      }
      
      // Dispatch based on scan mode and QR type
      if (qrData.type === 'box' || isBoxCode) {
        dispatch('boxScanned', { 
          qrData: isBoxCode ? decodedText : qrData, // Send raw text for new format
          rawText: decodedText,
          timestamp: Date.now()
        });
      } else {
        dispatch('individualScanned', { 
          qrData, 
          rawText: decodedText,
          timestamp: Date.now()
        });
      }
      
      // Visual feedback
      flashSuccess();
      
    } catch (error) {
      console.error('Error processing scan:', error);
      dispatch('scanError', { 
        error: error.message, 
        rawText: decodedText 
      });
      flashError();
    }
  }

  function flashSuccess() {
    if (scannerContainer) {
      scannerContainer.style.border = '3px solid #10B981';
      setTimeout(() => {
        if (scannerContainer) {
          scannerContainer.style.border = '2px solid #E5E7EB';
        }
      }, 1000);
    }
  }

  function flashError() {
    if (scannerContainer) {
      scannerContainer.style.border = '3px solid #EF4444';
      setTimeout(() => {
        if (scannerContainer) {
          scannerContainer.style.border = '2px solid #E5E7EB';
        }
      }, 1000);
    }
  }

  function toggleScanMode() {
    scanMode = scanMode === 'individual' ? 'box' : 'individual';
    dispatch('scanModeChanged', { scanMode });
  }
</script>

<div class="box-bulk-scanner">
  <!-- Scanner Mode Toggle -->
  <div class="mb-4 flex items-center justify-between">
    <div class="flex items-center space-x-3">
      <h3 class="text-lg font-semibold text-gray-900">
        {scanMode === 'box' ? '📦 Box Scanner' : '📱 Individual Scanner'}
      </h3>
      <button
        on:click={toggleScanMode}
        class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
      >
        Switch to {scanMode === 'box' ? 'Individual' : 'Box'} Mode
      </button>
    </div>
    
    <div class="flex items-center space-x-2">
      <span class="text-sm text-gray-600">Mode:</span>
      <span class="px-2 py-1 text-xs font-medium rounded-full {scanMode === 'box' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
        {scanMode === 'box' ? 'Bulk Operations' : 'Individual Items'}
      </span>
    </div>
  </div>

  <!-- Scanner Instructions -->
  <div class="mb-4 p-3 bg-gray-50 rounded-lg">
    <div class="flex items-start space-x-2">
      <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <div>
        <p class="text-sm font-medium text-gray-900">
          {scanMode === 'box' ? 'Box Bulk Operations Mode' : 'Individual Item Mode'}
        </p>
        <p class="text-xs text-gray-600 mt-1">
          {#if scanMode === 'box'}
            Scan box QR codes to update status/address for all items in the box at once. Perfect for institutional deliveries.
          {:else}
            Scan individual item QR codes for normal processing and tracking.
          {/if}
        </p>
      </div>
    </div>
  </div>

  <!-- Scanner Container -->
  <div 
    bind:this={scannerContainer}
    class="scanner-container relative border-2 border-gray-300 rounded-lg overflow-hidden"
    style="background: #000;"
  >
    {#if isActive}
      <div id="qr-scanner-container" class="w-full"></div>
    {:else}
      <div class="aspect-square flex items-center justify-center bg-gray-100">
        <div class="text-center">
          <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M12 12h-4.01"/>
          </svg>
          <p class="text-gray-500">Scanner Inactive</p>
          <p class="text-sm text-gray-400">Activate scanner to start scanning</p>
        </div>
      </div>
    {/if}
  </div>

  <!-- Scanner Status -->
  <div class="mt-3 flex items-center justify-between text-sm">
    <div class="flex items-center space-x-2">
      <div class="w-2 h-2 rounded-full {scannerStarted ? 'bg-green-500' : 'bg-gray-400'}"></div>
      <span class="text-gray-600">
        {scannerStarted ? 'Scanner Active' : 'Scanner Inactive'}
      </span>
    </div>
    
    {#if lastScannedCode}
      <div class="text-xs text-gray-500">
        Last: {lastScannedCode.substring(0, 20)}...
      </div>
    {/if}
  </div>

  <!-- Recent Scans (for debugging) -->
  {#if lastScannedCode}
    <div class="mt-4 p-2 bg-gray-50 rounded text-xs">
      <details>
        <summary class="cursor-pointer text-gray-600">Recent Scan Data</summary>
        <pre class="mt-2 text-gray-800 whitespace-pre-wrap">{lastScannedCode}</pre>
      </details>
    </div>
  {/if}
</div>

<style>
  .scanner-container {
    max-width: 400px;
    margin: 0 auto;
  }
  
  :global(#qr-scanner-container video) {
    width: 100% !important;
    height: auto !important;
  }
  
  :global(#qr-scanner-container) {
    background: transparent !important;
  }
  
  /* Hide the default scanner UI elements we don't need */
  :global(#qr-scanner-container #qr-shaded-region) {
    background: rgba(0, 0, 0, 0.5) !important;
  }
</style>