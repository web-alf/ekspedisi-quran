<script>
  import { onMount, onDestroy, tick } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import { fade } from 'svelte/transition';
  import { toast, dialog } from '../../utils/notifications.js';
  import { MobileDetection, PerformanceMonitor } from '../../utils/mobileDetection.js';
  import FileQRScanner from '../../Components/FileQRScanner.svelte';
  import { createScanner } from '../../utils/scanner-core.js';

  // Props
  // omitted unused props to reduce build warnings
  export let task = {};
  export let currentBox = null;
  export let allBoxes = [];
  export let nextItems = [];
  export let packedItems = [];
  // export let assignedItems = [];
  export let noTaskMessage = null;

  // Scanner state
  const scannerStore = createScanner('qr-reader', {
    onSuccess: onScanSuccess,
    onFailure: onScanFailure,
    onError: (msg) => { scanError = msg; },
    useBarcodeDetector: true,
  });

  let isScanning = false;
  let isInitializing = false;
  let permissionGranted = false;

  // Sync local reactive state with scanner store
  $: {
    const state = $scannerStore;
    isScanning = state.scanning;
    isInitializing = state.isInitializing;
    permissionGranted = state.permissionGranted;
  }
  let scanResult = '';
  let scanError = '';
  let isProcessing = false;
  let recentScans = [];
  
  // UI state
  let activeTab = 'scan'; // 'scan' | 'manual' | 'history'
  let manualInput = '';
  let showBoxFullModal = false;
  let showTaskCompleteModal = false;
  
  // Error state management
  let errorState = {
    hasError: false,
    message: '',
    retryCount: 0,
    maxRetries: 3
  };
  
  // Loading state consistency
  let loadingStates = {
    scanning: false,
    processing: false,
    saving: false
  };
  
  // Jenis warning modal state
  let showJenisWarningModal = false;
  let jenisWarningData = null;
  let jenisWarningMessage = '';
  
  // Assignment tab removed
  
  // Sound effects
  let successSound;
  let errorSound;

  // Mobile-specific state
  let isMobileDevice = false;
  let showFileScanner = false;
  let brightnessHint = '';
  let wakeLock = null;
  let performanceMonitor = null;
  let batteryOptimized = false;
  let cameraPermissionDenied = false;
  
  // DOM monitoring like BoxScanner
  let elementCheckInterval = null;
  // Prevent aggressive repeated restart attempts
  let autoRestartPending = false;
  
  // Mobile detection variable - available globally to avoid scope issues
  let isMobile = false;
  let isIOS = false;
  let isAndroid = false;
  let isChrome = false;
  let isSafari = false;
  
  // DOM monitoring reactive statement - CRITICAL DEFENSIVE PATTERN like BoxScanner
  $: if (typeof document !== 'undefined' && permissionGranted && isScanning && !elementCheckInterval) {
    // Check if qr-reader element exists periodically (but only once)
    elementCheckInterval = setInterval(() => {
      const qrReaderElement = document.getElementById('qr-reader');
      const videoElement = document.querySelector('#qr-reader video');
      
      if (!qrReaderElement && isScanning) {
        isScanning = false;
        isInitializing = false;
        clearInterval(elementCheckInterval);
        elementCheckInterval = null;
      } else if (qrReaderElement && !videoElement && isScanning) {
        // Auto-restart the scanner if the video element disappears
        if (!autoRestartPending) {
          autoRestartPending = true;
          setTimeout(() => {
            if (permissionGranted && activeTab === 'scan' && !showFileScanner && !showJenisWarningModal && !showBoxFullModal && !showTaskCompleteModal) {
              restartScanner();
            }
            autoRestartPending = false;
          }, 500);
        }
      }
    }, 3000);
    
    // Clear interval after 60 seconds
    setTimeout(() => {
      if (elementCheckInterval) {
        clearInterval(elementCheckInterval);
        elementCheckInterval = null;
      }
    }, 60000);
  }
  
  // Clear interval when scanner stops
  $: if (!isScanning && elementCheckInterval) {
    clearInterval(elementCheckInterval);
    elementCheckInterval = null;
  }
  
  onMount(() => {
    // Detect mobile device and capabilities
    isMobileDevice = MobileDetection.isMobile();
    brightnessHint = MobileDetection.showBrightnessHint() || '';
    
    // Initialize browser detection variables for global access
    isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    isAndroid = /Android/.test(navigator.userAgent);
    isChrome = /Chrome/.test(navigator.userAgent);
    isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);
    
    // Initialize sounds (smaller files for mobile)
    if (MobileDetection.isLowEndDevice()) {
      // Use lightweight notification sounds for low-end devices
      successSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmkaCAqNzPPIeSIHKYPM8NVFEQE=');
      errorSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmkaCAqNzPPIeSIHKYPM8NVFEQE=');
    } else {
      successSound = new Audio('/sounds/success.mp3');
      errorSound = new Audio('/sounds/error.mp3');
    }
    
    // Request wake lock for mobile devices during scanning
    if (isMobileDevice) {
      MobileDetection.requestWakeLock().then(lock => {
        wakeLock = lock;
        console.debug('Wake lock acquired for scanner');
      });
    }
    
    // Initialize performance monitoring
    performanceMonitor = new PerformanceMonitor();
    performanceMonitor.addCallback((metrics) => {
      // Handle performance issues
      if (metrics.memory && metrics.memory.percentage > 92 && isScanning) {
        console.warn('High memory usage, optimizing and auto-restarting scanner');
        cleanupScanner();
        // Informational message, but we will auto-restart
        scanError = 'Mengoptimalkan memori... melanjutkan pemindaian.';
        // Auto-restart shortly after cleanup if user is still on scan tab and no blocking modal
        setTimeout(() => {
          if (activeTab === 'scan' && !showFileScanner && !showJenisWarningModal && !showBoxFullModal && !showTaskCompleteModal && permissionGranted) {
            restartScanner();
          }
        }, 700);
      }
    });
    
    // Start monitoring if mobile
    if (isMobileDevice) {
      performanceMonitor.startMonitoring(15000); // Check every 15 seconds on mobile
    }
    
    // Page visibility API for memory management
    const handleVisibilityChange = async () => {
      if (document.hidden) {
        console.debug('Page hidden, cleaning up scanner for memory conservation');
        await cleanupScanner();
      }
    };
    
    // Beforeunload cleanup
    const handleBeforeUnload = async (e) => {
      await cleanupScanner();
    };
    
    // Mobile-specific memory management
    const handleMemoryWarning = async () => {
      console.warn('Memory warning received, cleaning up scanner');
      await cleanupScanner();
      // Auto-restart if conditions are safe for continuous scanning
      if (activeTab === 'scan' && !showFileScanner && !showJenisWarningModal && !showBoxFullModal && !showTaskCompleteModal && permissionGranted && !cameraPermissionDenied) {
        scanError = 'Mengoptimalkan memori... melanjutkan pemindaian.';
        setTimeout(() => {
          restartScanner();
        }, 700);
      } else {
        scanError = 'Scanner dihentikan untuk menghemat memori. Tekan "Mulai Scanner" untuk melanjutkan.';
      }
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
        if (usedMemoryMB > totalMemoryMB * 0.8 && isScanning) {
          console.warn(`High memory usage detected: ${usedMemoryMB.toFixed(2)}MB/${totalMemoryMB.toFixed(2)}MB`);
          handleMemoryWarning();
        }
      };
      
      // Check memory usage every 20 seconds
      const memoryCheckInterval = setInterval(checkMemory, 20000);
      
      return () => {
        clearInterval(memoryCheckInterval);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('beforeunload', handleBeforeUnload);
      };
    }
    
    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  });
  
  onDestroy(async () => {
    await cleanupScanner();
    
    // Clear DOM monitoring interval
    if (elementCheckInterval) {
      clearInterval(elementCheckInterval);
      elementCheckInterval = null;
    }
    
    // Release wake lock
    if (wakeLock) {
      try {
        await wakeLock.release();
        console.debug('Wake lock released');
      } catch (e) {
        console.debug('Wake lock release failed:', e);
      }
    }
    
    // Stop performance monitoring
    if (performanceMonitor) {
      performanceMonitor.stopMonitoring();
    }
    
    // Cleanup audio elements
    if (successSound) {
      successSound.pause();
      successSound.src = '';
      successSound = null;
    }
    if (errorSound) {
      errorSound.pause();
      errorSound.src = '';
      errorSound = null;
    }
    
    // Force garbage collection if available (for development)
    if (window.gc && typeof window.gc === 'function') {
      window.gc();
    }
  });
  
  // Assignment tab removed: filteredAssignedItems and assignmentStats removed
  
  // Error handling utilities
  function handleReactiveError(source, error) {
    console.error(`Reactive statement error in ${source}:`, error);
    errorState = {
      hasError: true,
      message: `Error in ${source}: ${error.message}`,
      retryCount: errorState.retryCount + 1,
      maxRetries: errorState.maxRetries
    };
  }
  
  function clearErrorState() {
    errorState = {
      hasError: false,
      message: '',
      retryCount: 0,
      maxRetries: 3
    };
  }
  
  // State validation utilities
  function validateStateConsistency() {
    const issues = [];
    
    if (currentBox && typeof currentBox.kapasitas !== 'number') {
      issues.push('currentBox.kapasitas is not a number');
    }
    
    if (currentBox && typeof currentBox.jumlah_terisi !== 'number') {
      issues.push('currentBox.jumlah_terisi is not a number');
    }
    
    if (!Array.isArray(nextItems)) {
      issues.push('nextItems is not an array');
    }
    
    if (!Array.isArray(packedItems)) {
      issues.push('packedItems is not an array');
    }
    
    if (issues.length > 0) {
      console.warn('State consistency issues:', issues);
      return false;
    }
    
    return true;
  }
  
  function requestCameraPermission() {
    scannerStore.requestPermission().catch(() => {
      showFileScanner = true;
    });
  }

  function initializeScanner() {
    scannerStore.init();
  }

  function startScannerElement(qrReaderElement) {
    scannerStore.cleanup();
    setTimeout(() => {
      scannerStore.init();
    }, 500);
  }

  
  async function startScanner() {
    if (activeTab !== 'scan') {
      console.warn('QR scanner can only be started on scan tab');
      return;
    }
    if (permissionGranted) {
      scannerStore.init();
    } else {
      scannerStore.requestPermission().then(() => scannerStore.init());
    }
  }

  async function stopScanner() {
    scannerStore.cleanup();
  }

  function restartScanner() {
    scanError = '';
    scannerStore.restart();
  }

  async function cleanupScanner() {
    scannerStore.cleanup();
  }
  
  function onScanSuccess(decodedText) {
    if (isProcessing || scanResult === decodedText) return;
    // Keep camera open; rely on isProcessing/scanResult to prevent duplicates
    // Mobile haptic feedback for successful scan
    if (isMobileDevice) {
      MobileDetection.vibrate([100, 50, 100]); // Success vibration pattern
    }
    
    scanResult = decodedText;
    processScannedItem(decodedText);
  }
  
  function onScanFailure(error) {
    // Ignore continuous scan errors - Html5QrcodeScanner will keep trying
    if (error.indexOf('NotFoundException') === -1) {
      console.warn(`QR scan error: ${error}`);
    }
  }
  
  async function processScannedItem(qrData) {
    if (loadingStates.processing || !qrData?.trim()) return;
    
    // Set loading state
    loadingStates.processing = true;
    isProcessing = true;
    scanError = '';
    clearErrorState();
    
    // Create backup of current state for rollback
    const stateBackup = {
      currentBox: currentBox ? { ...currentBox } : null,
      nextItems: [...nextItems],
      packedItems: [...packedItems],
      task: { ...task }
    };
    
    try {
      // Validate state before processing
      if (!validateStateConsistency()) {
        throw new Error('State consistency validation failed');
      }
      
      const response = await fetch('/admin/warehouse/packing/scan', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          qr_data: qrData.trim(),
          box_id: currentBox?.id || null
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      const payload = data?.data || {};
      
      if (data.success) {
        // Validate response data before updating state
        if (typeof payload !== 'object') {
          throw new Error('Invalid response data structure');
        }
        
        // Play success sound and vibration
        try {
          if (successSound && !MobileDetection.isLowEndDevice()) {
            successSound.play().catch(() => {
              // Ignore audio play errors
            });
          }
          
          // Mobile haptic feedback
          if (isMobileDevice) {
            MobileDetection.vibrate([200]); // Success vibration
          }
        } catch (e) {
          // Ignore audio/vibration errors
        }
        
        // Add to recent scans with defensive programming
        const newScan = {
          no_resi: payload.no_resi || (typeof qrData === 'string' ? qrData.trim() : 'Unknown'),
          time: new Date().toLocaleTimeString(),
          success: true
        };
        
        recentScans = [newScan, ...recentScans.slice(0, 9)]; // Keep last 10
        
        // Update local data with error handling
        // Sync task progress from server when provided
        if (payload.task_progress && typeof payload.task_progress === 'object') {
          const completed = Number(payload.task_progress.completed);
          const target = Number(payload.task_progress.target);
          if (!Number.isNaN(completed)) {
            task = { ...task, total_selesai: completed };
          }
          if (!Number.isNaN(target) && target > 0) {
            task = { ...task, total_target: target };
          }
          if (!Number.isNaN(completed) && !Number.isNaN(target) && target > 0) {
            task = { ...task, progress_percentage: Math.max(0, Math.min(100, (completed / target) * 100)) };
          }
        }

        // Ensure currentBox reflects server state
        if (payload.box && typeof payload.box === 'object') {
          const b = payload.box;
          const jumlahTerisi = Number(b.jumlah_terisi);
          const kapasitas = Number(b.kapasitas);
          if (!currentBox) {
            currentBox = {
              id: b.id,
              kode_kerdus: b.kode_kerdus,
              jumlah_terisi: jumlahTerisi || 0,
              kapasitas: kapasitas || 0,
              remaining_space: (!Number.isNaN(kapasitas) && !Number.isNaN(jumlahTerisi)) ? Math.max(0, kapasitas - jumlahTerisi) : 0,
              progress_percentage: (!Number.isNaN(kapasitas) && kapasitas > 0 && !Number.isNaN(jumlahTerisi)) ? Math.min(100, (jumlahTerisi / kapasitas) * 100) : 0,
              status: b.status || 'filling'
            };
          } else {
            currentBox = {
              ...currentBox,
              jumlah_terisi: jumlahTerisi || currentBox.jumlah_terisi || 0,
              kapasitas: !Number.isNaN(kapasitas) && kapasitas > 0 ? kapasitas : (currentBox.kapasitas || 0),
              remaining_space: (!Number.isNaN(kapasitas) && !Number.isNaN(jumlahTerisi)) ? Math.max(0, kapasitas - jumlahTerisi) : (currentBox.remaining_space || 0),
              progress_percentage: (!Number.isNaN(kapasitas) && kapasitas > 0 && !Number.isNaN(jumlahTerisi)) ? Math.min(100, (jumlahTerisi / kapasitas) * 100) : (currentBox.progress_percentage || 0),
              status: b.status || currentBox.status
            };
          }
        }

        const updateResult = updateLocalDataSafely(payload);
        if (!updateResult.success) {
          // Rollback state if update failed
          currentBox = stateBackup.currentBox;
          nextItems = stateBackup.nextItems;
          packedItems = stateBackup.packedItems;
          task = stateBackup.task;
          throw new Error(updateResult.error);
        }
        
        // Check if box is full
        if (data.box_full) {
          showBoxFullModal = true;
          await stopScanner();
        }
        
        // Check if task completed
        if (data.task_completed) {
          showTaskCompleteModal = true;
          await stopScanner();
        }
        
        // Clear scan result after delay
        setTimeout(() => {
          scanResult = '';
        }, 2000);
        
      } else if (data.type === 'jenis_warning') {
        // Handle jenis warning - show confirmation modal
        showJenisWarningModal = true;
        jenisWarningData = data.data;
        jenisWarningMessage = data.message || 'Konfirmasi diperlukan';
        await stopScanner();
        
      } else {
        // Handle API error response
        const errorMessage = data.message || 'Terjadi kesalahan tidak diketahui';
        
        try {
          if (errorSound && !MobileDetection.isLowEndDevice()) {
            errorSound.play().catch(() => {
              // Ignore audio play errors
            });
          }
          
          // Mobile error vibration
          if (isMobileDevice) {
            MobileDetection.vibrate([300, 100, 300]); // Error vibration pattern
          }
        } catch (e) {
          // Ignore audio/vibration errors
        }
        
        scanError = errorMessage;
        
        // Add to recent scans as error
        const errorScan = {
          no_resi: qrData.substring(0, 20) + (qrData.length > 20 ? '...' : ''),
          time: new Date().toLocaleTimeString(),
          success: false,
          error: errorMessage
        };
        
        recentScans = [errorScan, ...recentScans.slice(0, 9)]; // Keep last 10
      }
      
    } catch (err) {
      console.error('Error processing scan:', err);
      
      // Rollback state on error
      currentBox = stateBackup.currentBox;
      nextItems = stateBackup.nextItems;
      packedItems = stateBackup.packedItems;
      task = stateBackup.task;
      
      try {
        errorSound?.play().catch(() => {
          // Ignore audio play errors
        });
      } catch (e) {
        // Ignore audio errors
      }
      
      // Set error state for user feedback
      scanError = err.message || 'Gagal memproses scan';
      errorState = {
        hasError: true,
        message: err.message || 'Network or processing error',
        retryCount: errorState.retryCount + 1,
        maxRetries: errorState.maxRetries
      };
      
    } finally {
      // Always reset loading states
      loadingStates.processing = false;
      isProcessing = false;

      // Keep scanner alive for continuous scanning
      // If no blocking modal is open and we are still on scan tab, ensure scanner stays active
      if (!showJenisWarningModal && !showBoxFullModal && !showTaskCompleteModal && activeTab === 'scan' && !showFileScanner && permissionGranted) {
        // If scanner was stopped by underlying lib or memory guard, bring it back automatically
        if (!isScanning && !isInitializing && !cameraPermissionDenied) {
          restartScanner();
        }
      }
    }
  }
  
  async function confirmJenisChange() {
    try {
      const response = await fetch('/admin/warehouse/packing/scan-confirm', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          pengiriman_id: jenisWarningData.pengiriman_id,
          confirm_seal_others: true
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Refresh page data untuk update box status
        window.location.reload();
      } else {
        scanError = data.message;
      }
    } catch (err) {
      scanError = 'Gagal memproses konfirmasi';
    } finally {
      showJenisWarningModal = false;
      jenisWarningData = null;
    }
  }

  function cancelJenisChange() {
    showJenisWarningModal = false;
    jenisWarningData = null;
    jenisWarningMessage = '';
    startScanner(); // Resume scanning
  }
  
  function updateLocalDataSafely(data) {
    try {
      // Validate input data structure
      if (!data || typeof data !== 'object') {
        return { success: false, error: 'Invalid data structure provided' };
      }
      
      // Update task progress with validation
      if (data.task && typeof data.task === 'object') {
        const taskUpdate = {
          total_selesai: Number(data.task.total_selesai) || task.total_selesai || 0,
          remaining: Number(data.task.remaining) || task.remaining || 0,
          progress_percentage: Number(data.task.progress) || task.progress_percentage || 0
        };
        
        // Validate progress percentage bounds
        if (taskUpdate.progress_percentage < 0 || taskUpdate.progress_percentage > 100) {
          taskUpdate.progress_percentage = Math.max(0, Math.min(100, taskUpdate.progress_percentage));
        }
        
        task = { ...task, ...taskUpdate };
      }
      
      // Update current box with comprehensive validation
      if (currentBox && data.box && typeof data.box === 'object') {
        const boxData = data.box;
        
        // Validate numeric values
        const jumlahTerisi = Number(boxData.jumlah_terisi);
        const kapasitas = Number(currentBox.kapasitas);
        
        if (isNaN(jumlahTerisi) || isNaN(kapasitas)) {
          return { success: false, error: 'Invalid numeric values in box data' };
        }
        
        if (jumlahTerisi < 0 || jumlahTerisi > kapasitas) {
          console.warn(`Box capacity validation: ${jumlahTerisi}/${kapasitas} - allowing but logging`);
        }
        
        // Safe box update
        currentBox = {
          ...currentBox,
          jumlah_terisi: jumlahTerisi,
          remaining_space: Math.max(0, kapasitas - jumlahTerisi),
          progress_percentage: kapasitas > 0 ? Math.min(100, (jumlahTerisi / kapasitas) * 100) : 0
        };
      }
      
      // Update nextItems array safely
      if (data.no_resi && Array.isArray(nextItems)) {
        nextItems = nextItems.filter(item => {
          return item?.pengiriman?.no_resi !== data.no_resi;
        });
      }
      
      // Add to packed items with validation
      if (data.no_resi && Array.isArray(packedItems)) {
        const newPackedItem = {
          id: Date.now() + Math.random(), // Ensure uniqueness
          urutan: Number(data.box?.jumlah_terisi) || packedItems.length + 1,
          packed_at: new Date().toLocaleTimeString(),
          pengiriman: {
            no_resi: String(data.no_resi || 'Unknown'),
            donatur: String(data.donatur || '-'),
            jumlah_quran: Number(data.jumlah_quran) || 1
          }
        };
        
        // Prevent duplicate entries
        const existingIndex = packedItems.findIndex(item => 
          item.pengiriman.no_resi === newPackedItem.pengiriman.no_resi
        );
        
        if (existingIndex === -1) {
          packedItems = [newPackedItem, ...packedItems];
        } else {
          // Update existing entry
          packedItems[existingIndex] = { ...packedItems[existingIndex], ...newPackedItem };
          packedItems = [...packedItems]; // Trigger reactivity
        }
      }
      
      return { success: true };
      
    } catch (error) {
      console.error('Error in updateLocalDataSafely:', error);
      return { success: false, error: error.message };
    }
  }
  
  // Legacy function for backward compatibility - now calls safe version
  function updateLocalData(data) {
    const result = updateLocalDataSafely(data);
    if (!result.success) {
      console.error('updateLocalData failed:', result.error);
      throw new Error(result.error);
    }
  }
  
  async function sealCurrentBox() {
    if (!currentBox) return;
    
    const confirmSeal = await dialog.confirm({
      title: 'Segel Kerdus',
      message: 'Yakin ingin menyegel kerdus ini?',
      type: 'warning',
      confirmText: 'Ya, Segel',
      cancelText: 'Batal'
    });
    
    if (!confirmSeal) return;
    
    try {
      const response = await fetch(`/admin/warehouse/packing/box/${currentBox.id}/seal`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const data = await response.json();
      
      if (data.success) {
        toast.success(`Kerdus berhasil disegel dengan kode: ${data.seal_code}`);
        // Reload page to get new box
        router.reload();
      } else {
        toast.error(data.message || 'Gagal menyegel kerdus');
      }
    } catch (err) {
      console.error('Error sealing box:', err);
      toast.error('Terjadi kesalahan saat menyegel kerdus');
    }
  }
  
  async function handleManualInput() {
    if (!manualInput.trim()) return;
    
    await processScannedItem(manualInput.trim());
    manualInput = '';
  }

  function handleFileScanned(event) {
    const { decodedText, source } = event.detail;
    if (decodedText) {
      processScannedItem(decodedText);
    }
  }

  function toggleFileScanner() {
    showFileScanner = !showFileScanner;
    if (showFileScanner && isScanning) {
      stopScanner(); // Stop camera scanner when using file scanner
    }
  }
  
  function completeTask() {
    // Stop scanner before navigation
    stopScanner();
    
    // Close modal
    showTaskCompleteModal = false;
    
    // Navigate to warehouse dashboard
    router.visit('/admin/warehouse', {
      preserveState: false
    });
  }
  
  function getProgressColor(percentage) {
    if (percentage >= 80) return 'bg-green-500';
    if (percentage >= 60) return 'bg-yellow-500';
    if (percentage >= 40) return 'bg-orange-500';
    return 'bg-red-500';
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto">
    {#if noTaskMessage}
      <!-- No Task State -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
        <div class="flex flex-col items-center">
          <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
          <h2 class="text-xl font-semibold text-gray-900 mb-2">Tidak Ada Tugas Packing</h2>
          <p class="text-gray-600 mb-6 max-w-md">{noTaskMessage}</p>
          <div class="flex space-x-4">
            <a 
              href="/admin/warehouse"
              class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
            >
              Kembali ke Dashboard
            </a>
            <a 
              href="/admin/supervisor/warehouse-monitor"
              class="px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors"
            >
              Hubungi Supervisor
            </a>
          </div>
        </div>
      </div>
    {:else}
      <!-- Normal Packing Interface -->
      <!-- Header with Progress -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Proses Packing</h1>
            <p class="text-gray-600">Scan QR code mushaf untuk packing</p>
          </div>
          <a 
            href="/admin/warehouse" 
            class="text-gray-600 hover:text-gray-900"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </a>
        </div>
        
        <!-- Task Progress -->
        <div class="space-y-2">
          <div class="flex justify-between text-sm">
            <span>Progress Tugas: {task.total_selesai}/{task.total_target} mushaf</span>
            <span>{task.progress_percentage}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-3">
            <div 
              class="{getProgressColor(task.progress_percentage)} h-3 rounded-full transition-all duration-300"
              style="width: {task.progress_percentage}%"
            ></div>
          </div>
        </div>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Scanner -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <!-- Tabs - Mobile Optimized -->
            <div class="flex space-x-1 mb-6 {isMobileDevice ? 'text-sm' : ''}">
              <button
                on:click={async () => {
                  if (activeTab !== 'scan') {
                    await cleanupScanner();
                  }
                  activeTab = 'scan';
                  showFileScanner = false;
                }}
                class="flex-1 py-3 px-2 rounded-lg font-medium transition-colors {isMobileDevice ? 'min-h-[44px]' : 'py-2 px-4'} {activeTab === 'scan' ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
              >
                📷 Scan
              </button>
              <button
                on:click={async () => {
                  await cleanupScanner();
                  activeTab = 'manual';
                  showFileScanner = false;
                }}
                class="flex-1 py-3 px-2 rounded-lg font-medium transition-colors {isMobileDevice ? 'min-h-[44px]' : 'py-2 px-4'} {activeTab === 'manual' ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
              >
                ⌨️ Manual
              </button>
              <button
                on:click={async () => {
                  await cleanupScanner();
                  activeTab = 'history';
                  showFileScanner = false;
                }}
                class="flex-1 py-3 px-2 rounded-lg font-medium transition-colors {isMobileDevice ? 'min-h-[44px]' : 'py-2 px-4'} {activeTab === 'history' ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
              >
                📄 History
              </button>
              
            </div>
            
            <!-- Scanner Tab -->
            {#if activeTab === 'scan'}
              <div class="space-y-4">

                {#if !currentBox}
                  <!-- No box available - show info and allow scanning -->
                  <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-800 font-medium">Belum ada kerdus aktif</p>
                    <p class="text-sm text-yellow-600">Kerdus akan dibuat otomatis saat scan pertama</p>
                  </div>
                {/if}

                <!-- Mobile brightness hint -->
                {#if isMobileDevice && brightnessHint}
                  <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">{brightnessHint}</p>
                  </div>
                {/if}

                <!-- Scanner mode selector for mobile -->
                {#if isMobileDevice}
                  <div class="flex space-x-2 mb-4">
                    <button
                      on:click={() => showFileScanner = false}
                      class="flex-1 py-2 px-3 rounded-lg text-sm font-medium transition-colors {!showFileScanner ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'}"
                    >
                      📷 Kamera
                    </button>
                    <button
                      on:click={toggleFileScanner}
                      class="flex-1 py-2 px-3 rounded-lg text-sm font-medium transition-colors {showFileScanner ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'}"
                    >
                      📁 File
                    </button>
                  </div>
                {/if}
                
                <!-- File scanner for mobile or camera fallback -->
                {#if showFileScanner}
                  <FileQRScanner on:scan={handleFileScanned} />
                {:else}
                  {#if !isScanning && !cameraPermissionDenied}
                    <button 
                      on:click={startScanner}
                      class="w-full py-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 {isMobileDevice ? 'text-lg font-semibold min-h-[48px]' : 'py-3'}"
                      disabled={isProcessing}
                    >
                        {#if isProcessing}
                          <div class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Memproses...
                          </div>
                        {:else}
                          {isMobileDevice ? '📷 Mulai Scan' : 'Mulai Scanner'}
                        {/if}
                      </button>
                    {/if}
                {/if}
                  
                  <!-- QR Scanner Container -->
                  {#if !showFileScanner}
                    <div class="bg-gray-900 rounded-xl overflow-hidden mb-4 {!isScanning ? 'hidden' : ''}">
                      <div id="qr-reader" class="min-h-[250px] sm:min-h-[300px]"></div>
                    </div>
                  {/if}
                  
                  <!-- Processing Indicator -->
                  {#if loadingStates.processing || isProcessing}
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                      <div class="flex items-center gap-3">
                        <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                          <p class="text-blue-800 font-medium">Memproses scan...</p>
                          <p class="text-sm text-blue-600">Harap tunggu sebentar</p>
                        </div>
                      </div>
                    </div>
                  {/if}
                  
                  <!-- Error State Display -->
                  {#if errorState.hasError}
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                      <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-red-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1">
                          <p class="text-red-800 font-medium">Kesalahan Sistem</p>
                          <p class="text-sm text-red-600 mt-1">{errorState.message}</p>
                          {#if errorState.retryCount < errorState.maxRetries}
                            <button 
                              class="mt-2 text-sm bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200 transition-colors"
                              on:click={clearErrorState}
                            >
                              Coba Lagi ({errorState.retryCount}/{errorState.maxRetries})
                            </button>
                          {:else}
                            <p class="text-xs text-red-500 mt-1">Maksimum percobaan tercapai. Refresh halaman jika masalah berlanjut.</p>
                          {/if}
                        </div>
                      </div>
                    </div>
                  {/if}
                  
                  <!-- Scan Result -->
                  {#if scanResult && !isProcessing}
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                      <p class="text-green-800 font-medium">Scan berhasil!</p>
                      <p class="text-sm text-green-600">{scanResult}</p>
                    </div>
                  {/if}
                  
                  <!-- Scan Error -->
                  {#if scanError}
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                      <p class="text-red-800 font-medium">Error!</p>
                      <p class="text-sm text-red-600">{scanError}</p>
                    </div>
                  {/if}
                  
                  <!-- Mobile scanner controls -->
                  {#if isScanning && !showFileScanner}
                    <div class="flex gap-2">
                      <button 
                        on:click={stopScanner}
                        class="flex-1 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors {isMobileDevice ? 'min-h-[44px]' : 'py-2'}"
                      >
                        {isMobileDevice ? '⏹️ Stop' : 'Stop Scanner'}
                      </button>
                      {#if isMobileDevice && cameraPermissionDenied}
                        <button 
                          on:click={() => showFileScanner = true}
                          class="flex-1 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors min-h-[44px]"
                        >
                          📁 Use File
                        </button>
                      {/if}
                    </div>
                  {/if}
              </div>
            {/if}
            
            <!-- Manual Input Tab -->
            {#if activeTab === 'manual'}
              <div class="space-y-4">
                <p class="text-gray-600">Masukkan nomor resi atau QR data secara manual:</p>
                <form on:submit|preventDefault={handleManualInput} class="space-y-4">
                  <input
                    type="text"
                    bind:value={manualInput}
                    placeholder="EQ-2025-00001 atau QR data"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                    disabled={isProcessing}
                  />
                  <button
                    type="submit"
                    disabled={!manualInput.trim() || loadingStates.processing || isProcessing}
                    class="w-full py-3 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                  >
                    {#if loadingStates.processing || isProcessing}
                      <div class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memproses...
                      </div>
                    {:else}
                      Proses
                    {/if}
                  </button>
                </form>
              </div>
            {/if}
            
            <!-- History Tab -->
            {#if activeTab === 'history'}
              <div class="space-y-2">
                <h3 class="font-medium text-gray-900 mb-3">Scan Terakhir</h3>
                {#if recentScans.length > 0}
                  <div class="space-y-2 max-h-96 overflow-y-auto">
                    {#each recentScans as scan}
                      <div class="flex items-center justify-between p-3 {scan.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'} border rounded-lg">
                        <div>
                          <p class="font-medium {scan.success ? 'text-green-900' : 'text-red-900'}">{scan.no_resi}</p>
                          {#if scan.error}
                            <p class="text-sm text-red-600">{scan.error}</p>
                          {/if}
                        </div>
                        <span class="text-sm text-gray-600">{scan.time}</span>
                      </div>
                    {/each}
                  </div>
                {:else}
                  <p class="text-gray-500 text-center py-8">Belum ada scan</p>
                {/if}
              </div>
            {/if}
            
            <!-- Assignment Tab removed -->
          </div>
        </div>
        
        <!-- Right: Box Info & Items -->
        <div class="space-y-6">
          <!-- Current Box -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Kerdus Aktif</h2>
            
            <div class="space-y-4">
              <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                <div class="flex items-center justify-between mb-3">
                  {#if currentBox && currentBox.kode_kerdus}
                    <span class="font-mono text-lg font-semibold text-orange-900">{currentBox.kode_kerdus}</span>
                    <span class="text-sm text-orange-700">{currentBox.jumlah_terisi || 0}/{currentBox.kapasitas || 0}</span>
                  {:else}
                    <span class="font-mono text-lg font-semibold text-gray-900">Belum Ada Kerdus</span>
                    <span class="text-sm text-gray-700">0/0</span>
                  {/if}
                </div>

                {#if currentBox && currentBox.kode_kerdus}
                  <div class="w-full bg-orange-200 rounded-full h-2 mb-2">
                    <div 
                      class="bg-orange-500 h-2 rounded-full transition-all duration-300"
                      style="width: {currentBox.progress_percentage || 0}%"
                    ></div>
                  </div>

                  <div class="flex items-center justify-between text-sm">
                    <span class="text-orange-700">Sisa: {currentBox.remaining_space || 0} mushaf</span>
                    {#if currentBox.jumlah_terisi > 0}
                      <button
                        on:click={sealCurrentBox}
                        class="text-orange-700 hover:text-orange-900 font-medium"
                      >
                        Segel Box
                      </button>
                    {/if}
                  </div>
                {:else}
                  <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                    <div class="bg-gray-400 h-2 rounded-full" style="width: 0%"></div>
                  </div>
                  <div class="text-center text-gray-500">
                    <p class="text-sm">Kerdus akan dibuat saat scan pertama</p>
                  </div>
                {/if}
              </div>
            </div>
            
            <!-- All Boxes Summary -->
            <div class="space-y-2">
              <h3 class="text-sm font-medium text-gray-700">Semua Kerdus:</h3>
              <div class="grid grid-cols-2 gap-2">
                {#each allBoxes as box}
                  <div class="text-center p-2 rounded-lg border {box.is_current ? 'border-orange-300 bg-orange-50' : 'border-gray-200'}">
                    <p class="text-xs font-mono">{box.kode_kerdus ? box.kode_kerdus.split('-').pop() : 'N/A'}</p>
                    <p class="text-xs text-gray-600">{box.jumlah_terisi}/{box.kapasitas}</p>
                    <p class="text-xs {box.status === 'sealed' ? 'text-green-600' : box.status === 'filling' ? 'text-orange-600' : 'text-gray-500'}">
                      {box.status === 'sealed' ? 'Tersegel' : box.status === 'filling' ? 'Aktif' : 'Kosong'}
                    </p>
                  </div>
                {/each}
              </div>
            </div>
          </div>
        
          
          
          <!-- Packed Items in Current Box -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Item dalam Kerdus</h2>
            
            {#if packedItems.length > 0}
              <div class="space-y-2 max-h-64 overflow-y-auto">
                {#each packedItems as item}
                  <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <div>
                      <p class="font-mono text-sm">{item.pengiriman.no_resi}</p>
                      <p class="text-xs text-gray-600">{item.packed_at}</p>
                    </div>
                    <span class="text-xs bg-gray-200 px-2 py-1 rounded">#{item.urutan}</span>
                  </div>
                {/each}
              </div>
            {:else}
              <p class="text-gray-500 text-center py-4">Kerdus masih kosong</p>
            {/if}
          </div>
        </div>
      </div>
    {/if}
  </div>

  <!-- Jenis Warning Modal -->
  {#if showJenisWarningModal}
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" transition:fade>
      <div class="bg-white rounded-lg max-w-md w-full mx-4 p-6">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 13.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-medium text-gray-900">Peringatan Jenis Berbeda</h3>
          </div>
        </div>
        
        <div class="mb-6">
          <p class="text-sm text-gray-600 whitespace-pre-line">{jenisWarningMessage}</p>
        </div>
        
        <div class="flex space-x-3">
          <button 
            class="flex-1 bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition-colors"
            on:click={confirmJenisChange}>
            Ya, Lanjutkan
          </button>
          <button 
            class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors"
            on:click={cancelJenisChange}>
            Batal
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Box Full Modal -->
  {#if showBoxFullModal && currentBox}
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Kerdus Penuh!</h3>
        <p class="text-gray-600 mb-6">Kerdus {currentBox.kode_kerdus} sudah penuh. Silakan segel kerdus ini dan lanjutkan dengan kerdus baru.</p>
        <div class="flex space-x-3">
          <button
            on:click={() => { showBoxFullModal = false; router.reload(); }}
            class="flex-1 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600"
          >
            Lanjut Kerdus Baru
          </button>
          <button
            on:click={() => { showBoxFullModal = false; startScanner(); }}
            class="flex-1 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Task Complete Modal -->
  {#if showTaskCompleteModal}
    <div 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      role="button"
      tabindex="0"
      aria-label="Tutup modal"
      on:click|self={() => showTaskCompleteModal = false}
      on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showTaskCompleteModal = false; } }}
    >
      <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h3 class="text-lg font-semibold mb-2">Tugas Selesai!</h3>
          <p class="text-gray-600 mb-6">Selamat! Anda telah menyelesaikan tugas packing hari ini.</p>
          <div class="flex gap-3">
            <button
              on:click={() => showTaskCompleteModal = false}
              class="flex-1 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
            >
              Tutup
            </button>
            <button
              on:click={completeTask}
              class="flex-1 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
              Kembali ke Dashboard
            </button>
          </div>
        </div>
      </div>
    </div>
  {/if}
</AdminLayout>

<style>
  :global(#qr-reader) {
    border-radius: 12px;
    overflow: hidden;
  }
  
  :global(#qr-reader video) {
    border-radius: 12px;
  }
  
  :global(#qr-reader__dashboard) {
    background: rgba(0, 0, 0, 0.8);
    border-radius: 0 0 12px 12px;
  }
  
  :global(#qr-reader__dashboard_section) {
    background: transparent;
  }
  
  :global(#qr-reader__dashboard_section_csr) {
    text-align: center;
    padding: 8px;
  }
  
  :global(#qr-reader__dashboard_section_csr > button) {
    background: #eb3434 !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    margin: 4px !important;
    font-weight: 600 !important;
  }

  /* Mobile-specific optimizations */
  @media (max-width: 768px) {
    /* Optimize for touch */
    button {
      -webkit-tap-highlight-color: transparent;
    }
    
    /* Prevent zoom on input focus */
    :global(input), :global(select), :global(textarea) {
      font-size: 16px;
    }
  }

  /* Warehouse glove optimization */
  @media (max-width: 768px) and (min-width: 320px) {
    button {
      min-height: 44px;
      touch-action: manipulation;
    }
  }

  /* Low-end device optimizations */
  @media (max-width: 480px) {
    .animate-spin {
      animation-duration: 1s;
    }
  }
</style>
