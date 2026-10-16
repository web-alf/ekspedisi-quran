/**
 * Scanner Memory Leak Test Utility
 * 
 * Test script to validate memory management improvements in QR scanner components.
 * Run this in browser console during scanner operations to monitor memory usage.
 */

class ScannerMemoryTester {
  constructor() {
    this.memorySnapshots = [];
    this.isRunning = false;
    this.intervalId = null;
    this.startTime = null;
  }

  /**
   * Start memory monitoring
   * @param {number} intervalMs - Monitoring interval in milliseconds
   */
  startMonitoring(intervalMs = 5000) {
    if (this.isRunning) {
      console.warn('Memory monitoring already running');
      return;
    }

    if (typeof performance === 'undefined' || !performance.memory) {
      console.error('Performance memory API not available');
      return;
    }

    this.isRunning = true;
    this.startTime = Date.now();
    this.memorySnapshots = [];

    console.log('🔍 Starting scanner memory monitoring...');
    console.log('📊 Monitoring interval:', intervalMs + 'ms');

    this.intervalId = setInterval(() => {
      this.takeMemorySnapshot();
    }, intervalMs);

    // Take initial snapshot
    this.takeMemorySnapshot();
  }

  /**
   * Stop memory monitoring
   */
  stopMonitoring() {
    if (!this.isRunning) {
      console.warn('Memory monitoring not running');
      return;
    }

    this.isRunning = false;
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }

    console.log('🛑 Stopped scanner memory monitoring');
    this.generateReport();
  }

  /**
   * Take a memory snapshot
   */
  takeMemorySnapshot() {
    const memoryInfo = performance.memory;
    const timestamp = Date.now();
    const elapsed = this.startTime ? timestamp - this.startTime : 0;

    const snapshot = {
      timestamp,
      elapsed,
      usedJSHeapSize: memoryInfo.usedJSHeapSize,
      totalJSHeapSize: memoryInfo.totalJSHeapSize,
      jsHeapSizeLimit: memoryInfo.jsHeapSizeLimit,
      usedMB: (memoryInfo.usedJSHeapSize / 1024 / 1024).toFixed(2),
      totalMB: (memoryInfo.totalJSHeapSize / 1024 / 1024).toFixed(2),
      limitMB: (memoryInfo.jsHeapSizeLimit / 1024 / 1024).toFixed(2),
      usagePercentage: ((memoryInfo.usedJSHeapSize / memoryInfo.totalJSHeapSize) * 100).toFixed(2)
    };

    this.memorySnapshots.push(snapshot);

    // Log current memory usage
    console.log(`📈 Memory [${Math.round(elapsed / 1000)}s]: ${snapshot.usedMB}MB/${snapshot.totalMB}MB (${snapshot.usagePercentage}%)`);

    // Detect memory leaks
    this.detectMemoryLeaks();
  }

  /**
   * Detect potential memory leaks
   */
  detectMemoryLeaks() {
    if (this.memorySnapshots.length < 3) return;

    const recent = this.memorySnapshots.slice(-3);
    const memoryGrowth = recent[2].usedJSHeapSize - recent[0].usedJSHeapSize;
    const timeSpan = recent[2].elapsed - recent[0].elapsed;
    const growthRate = memoryGrowth / timeSpan; // bytes per ms

    // Alert if memory is growing rapidly (>1MB per minute)
    const growthPerMinute = growthRate * 60000;
    if (growthPerMinute > 1024 * 1024) {
      console.warn('🚨 POTENTIAL MEMORY LEAK DETECTED!');
      console.warn(`📈 Memory growing at ${(growthPerMinute / 1024 / 1024).toFixed(2)}MB/minute`);
    }

    // Alert if memory usage exceeds 80%
    const currentUsage = parseFloat(recent[2].usagePercentage);
    if (currentUsage > 80) {
      console.warn('⚠️ HIGH MEMORY USAGE:', currentUsage + '%');
    }
  }

  /**
   * Generate memory usage report
   */
  generateReport() {
    if (this.memorySnapshots.length === 0) {
      console.log('No memory data collected');
      return;
    }

    const first = this.memorySnapshots[0];
    const last = this.memorySnapshots[this.memorySnapshots.length - 1];
    const duration = (last.elapsed / 1000).toFixed(0);
    const memoryChange = last.usedJSHeapSize - first.usedJSHeapSize;
    const memoryChangeMB = (memoryChange / 1024 / 1024).toFixed(2);

    console.log('\n🔍 SCANNER MEMORY REPORT');
    console.log('========================');
    console.log(`📊 Duration: ${duration} seconds`);
    console.log(`📈 Initial memory: ${first.usedMB}MB`);
    console.log(`📊 Final memory: ${last.usedMB}MB`);
    console.log(`🔄 Memory change: ${memoryChangeMB}MB`);
    console.log(`📈 Peak usage: ${Math.max(...this.memorySnapshots.map(s => parseFloat(s.usagePercentage)))}%`);

    // Detect overall trend
    if (memoryChange > 10 * 1024 * 1024) { // > 10MB growth
      console.log('🚨 POTENTIAL MEMORY LEAK: Significant memory growth detected');
    } else if (memoryChange < -5 * 1024 * 1024) { // > 5MB reduction
      console.log('✅ GOOD: Memory usage decreased (cleanup working)');
    } else {
      console.log('✅ STABLE: Memory usage remained stable');
    }

    // Export data for analysis
    console.log('\n📋 Raw data (copy for analysis):');
    console.table(this.memorySnapshots.map(s => ({
      'Time (s)': Math.round(s.elapsed / 1000),
      'Used (MB)': s.usedMB,
      'Total (MB)': s.totalMB,
      'Usage (%)': s.usagePercentage
    })));
  }

  /**
   * Test scanner start/stop cycles
   * @param {number} cycles - Number of cycles to test
   * @param {number} intervalMs - Interval between cycles
   */
  async testScannerCycles(cycles = 10, intervalMs = 3000) {
    console.log(`🔄 Testing ${cycles} scanner start/stop cycles...`);
    
    for (let i = 1; i <= cycles; i++) {
      console.log(`🔄 Cycle ${i}/${cycles}`);
      
      // Simulate scanner start
      console.log('▶️ Starting scanner...');
      this.takeMemorySnapshot();
      
      await this.sleep(intervalMs / 2);
      
      // Simulate scanner stop
      console.log('⏹️ Stopping scanner...');
      this.takeMemorySnapshot();
      
      await this.sleep(intervalMs / 2);
    }
    
    console.log('✅ Scanner cycle test completed');
    this.generateReport();
  }

  /**
   * Test continuous scanning scenario
   * @param {number} durationMs - Test duration in milliseconds
   */
  async testContinuousScanning(durationMs = 60000) {
    console.log(`📱 Testing continuous scanning for ${durationMs / 1000} seconds...`);
    
    this.startMonitoring(2000); // Check every 2 seconds
    
    // Simulate scan events
    const scanInterval = setInterval(() => {
      console.log('📷 Simulating QR scan...');
      // Force some memory allocation to simulate scan processing
      const dummyData = new Array(1000).fill('scan_data_' + Math.random());
      setTimeout(() => {
        // Release reference
        dummyData.length = 0;
      }, 100);
    }, 5000);
    
    await this.sleep(durationMs);
    
    clearInterval(scanInterval);
    this.stopMonitoring();
  }

  /**
   * Sleep utility
   * @param {number} ms 
   */
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Get current memory status
   */
  getCurrentMemoryStatus() {
    if (typeof performance === 'undefined' || !performance.memory) {
      return 'Performance memory API not available';
    }

    const memoryInfo = performance.memory;
    return {
      used: (memoryInfo.usedJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
      total: (memoryInfo.totalJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
      limit: (memoryInfo.jsHeapSizeLimit / 1024 / 1024).toFixed(2) + 'MB',
      percentage: ((memoryInfo.usedJSHeapSize / memoryInfo.totalJSHeapSize) * 100).toFixed(2) + '%'
    };
  }
}

// Create global instance for easy console access
if (typeof window !== 'undefined') {
  window.scannerMemoryTester = new ScannerMemoryTester();
  
  console.log('🔧 Scanner Memory Tester loaded!');
  console.log('📋 Available commands:');
  console.log('  scannerMemoryTester.startMonitoring() - Start memory monitoring');
  console.log('  scannerMemoryTester.stopMonitoring() - Stop and generate report');
  console.log('  scannerMemoryTester.getCurrentMemoryStatus() - Get current memory status');
  console.log('  scannerMemoryTester.testScannerCycles(10) - Test 10 start/stop cycles');
  console.log('  scannerMemoryTester.testContinuousScanning(60000) - Test 60s continuous scanning');
}

export default ScannerMemoryTester;