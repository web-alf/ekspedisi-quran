/**
 * Scanner Memory Management Utilities
 * 
 * Comprehensive memory management utilities for QR scanner components
 * to prevent memory leaks and handle mobile device constraints.
 */

/**
 * Enhanced cleanup for Html5Qrcode instances
 * @param {Html5Qrcode|Html5QrcodeScanner} scanner 
 * @param {string} elementId 
 */
export async function cleanupQrScanner(scanner, elementId = null) {
  if (!scanner) return;
  
  try {
    // Stop scanner if running
    if (scanner.getState && typeof scanner.getState === 'function') {
      const { Html5QrcodeScannerState } = await import('html5-qrcode');
      const state = await scanner.getState();
      if (state === Html5QrcodeScannerState.SCANNING) {
        await scanner.stop();
      }
    }
    
    // Clear scanner instance
    if (scanner.clear && typeof scanner.clear === 'function') {
      await scanner.clear();
    }
    
    // Stop all media tracks manually as additional cleanup
    await stopAllMediaTracks();
    
    // Clear DOM element
    if (elementId) {
      const element = document.getElementById(elementId);
      if (element) {
        element.innerHTML = '';
      }
    }
    
    logMemoryUsage('Scanner cleanup completed');
    
  } catch (err) {
    console.warn('Scanner cleanup warning:', err);
  }
}

/**
 * Stop all active media tracks to prevent memory leaks
 */
export async function stopAllMediaTracks() {
  if (!navigator.mediaDevices) return;
  
  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoDevices = devices.filter(device => device.kind === 'videoinput');
    
    // Attempt to stop tracks for each video device
    for (const device of videoDevices) {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
          video: { deviceId: device.deviceId } 
        });
        
        stream.getTracks().forEach(track => {
          track.stop();
          console.debug('Stopped media track:', track.label || device.label);
        });
      } catch (e) {
        // Ignore errors for inactive or permission-denied streams
      }
    }
  } catch (err) {
    console.warn('Error stopping media tracks:', err);
  }
}

/**
 * Get optimal scanner configuration for mobile devices
 * @param {Object} options 
 */
export function getMobileOptimizedConfig(options = {}) {
  return {
    fps: options.fps || 8, // Reduced for mobile performance
    qrbox: options.qrbox || { width: 250, height: 250 },
    aspectRatio: options.aspectRatio || 1,
    disableFlip: options.disableFlip || false,
    rememberLastUsedCamera: true,
    videoConstraints: {
      facingMode: { ideal: "environment" },
      width: { min: 640, ideal: 1280, max: 1920 },
      height: { min: 480, ideal: 720, max: 1080 },
      ...options.videoConstraints
    },
    // Mobile optimizations
    experimentalFeatures: {
      useBarCodeDetectorIfSupported: true,
      ...options.experimentalFeatures
    }
  };
}

/**
 * Enhanced error handling for scanner initialization
 * @param {Error} error 
 */
export function handleScannerError(error) {
  console.error('Scanner error:', error);
  
  if (error.name === 'NotAllowedError') {
    return 'Kamera tidak diizinkan. Silakan refresh halaman dan izinkan akses kamera.';
  } else if (error.name === 'NotFoundError') {
    return 'Kamera tidak ditemukan. Pastikan perangkat memiliki kamera.';
  } else if (error.name === 'OverconstrainedError') {
    return 'Kamera tidak kompatibel. Coba gunakan perangkat lain.';
  } else if (error.name === 'NotReadableError') {
    return 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi lain dan coba lagi.';
  } else {
    return `Gagal memulai scanner: ${error.message}`;
  }
}

/**
 * Log memory usage for debugging
 * @param {string} context 
 */
export function logMemoryUsage(context = '') {
  if (typeof performance !== 'undefined' && performance.memory) {
    const memoryInfo = performance.memory;
    const usedMB = (memoryInfo.usedJSHeapSize / 1024 / 1024).toFixed(2);
    const totalMB = (memoryInfo.totalJSHeapSize / 1024 / 1024).toFixed(2);
    const limitMB = (memoryInfo.jsHeapSizeLimit / 1024 / 1024).toFixed(2);
    
    console.debug(`Memory ${context}:`, {
      used: `${usedMB}MB`,
      total: `${totalMB}MB`,
      limit: `${limitMB}MB`,
      percentage: `${((memoryInfo.usedJSHeapSize / memoryInfo.totalJSHeapSize) * 100).toFixed(1)}%`
    });
    
    return {
      usedMB: parseFloat(usedMB),
      totalMB: parseFloat(totalMB),
      limitMB: parseFloat(limitMB),
      percentage: (memoryInfo.usedJSHeapSize / memoryInfo.totalJSHeapSize) * 100
    };
  }
  return null;
}

/**
 * Check if memory usage is too high
 * @param {number} threshold - Memory usage threshold (0-1)
 */
export function isMemoryUsageHigh(threshold = 0.8) {
  if (typeof performance !== 'undefined' && performance.memory) {
    const memoryInfo = performance.memory;
    const usagePercentage = memoryInfo.usedJSHeapSize / memoryInfo.totalJSHeapSize;
    return usagePercentage > threshold;
  }
  return false;
}

/**
 * Setup page visibility and memory management listeners
 * @param {Function} cleanupCallback - Function to call when page is hidden or memory is high
 * @param {Object} options 
 */
export function setupMemoryManagement(cleanupCallback, options = {}) {
  const {
    memoryCheckInterval = 30000, // 30 seconds
    memoryThreshold = 0.8, // 80% memory usage
    enableMemoryChecks = true
  } = options;
  
  // Page visibility handler
  const handleVisibilityChange = async () => {
    if (document.hidden) {
      console.debug('Page hidden, triggering scanner cleanup for memory conservation');
      await cleanupCallback('visibilitychange');
    }
  };
  
  // Beforeunload handler
  const handleBeforeUnload = async (e) => {
    await cleanupCallback('beforeunload');
  };
  
  // Memory pressure handler
  const handleMemoryPressure = async () => {
    console.warn('High memory usage detected, triggering cleanup');
    await cleanupCallback('memory_pressure');
  };
  
  // Add event listeners
  document.addEventListener('visibilitychange', handleVisibilityChange);
  window.addEventListener('beforeunload', handleBeforeUnload);
  
  let memoryCheckIntervalId = null;
  
  // Setup memory monitoring
  if (enableMemoryChecks && 'memory' in performance) {
    const checkMemory = () => {
      if (isMemoryUsageHigh(memoryThreshold)) {
        const memoryStats = logMemoryUsage('High usage detected');
        if (memoryStats) {
          console.warn(`Memory usage too high: ${memoryStats.percentage.toFixed(1)}%`);
          handleMemoryPressure();
        }
      }
    };
    
    memoryCheckIntervalId = setInterval(checkMemory, memoryCheckInterval);
  }
  
  // Return cleanup function
  return () => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    
    if (memoryCheckIntervalId) {
      clearInterval(memoryCheckIntervalId);
    }
  };
}

/**
 * Force garbage collection if available (development only)
 */
export function forceGarbageCollection() {
  if (window.gc && typeof window.gc === 'function') {
    try {
      window.gc();
      console.debug('Forced garbage collection');
    } catch (e) {
      console.debug('Garbage collection not available');
    }
  }
}

/**
 * Debounced scan handler to prevent rapid duplicate scans
 * @param {Function} handler 
 * @param {number} delay 
 */
export function createDebouncedScanHandler(handler, delay = 2000) {
  let lastScanTime = 0;
  let lastScanResult = '';
  
  return (decodedText, ...args) => {
    const now = Date.now();
    
    // Prevent duplicate scans
    if (decodedText === lastScanResult && (now - lastScanTime) < delay) {
      return;
    }
    
    lastScanTime = now;
    lastScanResult = decodedText;
    
    handler(decodedText, ...args);
  };
}