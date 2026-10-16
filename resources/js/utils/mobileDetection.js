/**
 * Mobile Device Detection and Optimization Utilities
 * Optimized for warehouse scanner applications
 */

export class MobileDetection {
  static isSmallScreen() {
    return window.innerWidth <= 768;
  }

  static isTouch() {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  }

  static isLowEndDevice() {
    // Check for low memory or slow connection
    const memory = navigator.deviceMemory;
    const connection = navigator.connection;
    
    return (
      (memory && memory <= 2) || // <= 2GB RAM
      (connection && (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g'))
    );
  }

  static isMobile() {
    const userAgent = navigator.userAgent.toLowerCase();
    return /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile/.test(userAgent);
  }

  static isAndroid() {
    return /android/i.test(navigator.userAgent);
  }

  static isIOS() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
  }

  static hasVibration() {
    return 'vibrate' in navigator;
  }

  static getOptimalQRBoxSize() {
    const screenWidth = window.innerWidth;
    const screenHeight = window.innerHeight;
    const minDimension = Math.min(screenWidth, screenHeight);
    
    // Calculate optimal QR box size based on screen size
    if (minDimension <= 360) {
      return { width: 200, height: 200 }; // Small phones
    } else if (minDimension <= 480) {
      return { width: 250, height: 250 }; // Medium phones
    } else if (minDimension <= 768) {
      return { width: 300, height: 300 }; // Large phones/small tablets
    } else {
      return { width: 350, height: 350 }; // Tablets/desktop
    }
  }

  static getOptimalFPS() {
    const isLowEnd = this.isLowEndDevice();
    const connection = navigator.connection;
    
    if (isLowEnd) {
      return 5; // Very low FPS for low-end devices
    } else if (connection && connection.effectiveType === '3g') {
      return 6; // Moderate FPS for 3G
    } else {
      return 8; // Standard FPS for good devices
    }
  }

  static getOptimalVideoConstraints() {
    // HTML5-QRCode expects simple camera config for the first parameter
    // Complex constraints should be passed in the second parameter (config object)
    return { facingMode: "environment" };
  }

  static getOptimalVideoConfig() {
    // Return the detailed video constraints for the config object
    const isLowEnd = this.isLowEndDevice();
    const isSmall = this.isSmallScreen();
    
    if (isLowEnd) {
      return {
        videoConstraints: {
          facingMode: { ideal: "environment" },
          width: { min: 480, ideal: 640, max: 720 },
          height: { min: 320, ideal: 480, max: 540 }
        }
      };
    } else if (isSmall) {
      return {
        videoConstraints: {
          facingMode: { ideal: "environment" },
          width: { min: 640, ideal: 1280, max: 1280 },
          height: { min: 480, ideal: 720, max: 720 }
        }
      };
    } else {
      return {
        videoConstraints: {
          facingMode: { ideal: "environment" },
          width: { min: 640, ideal: 1280, max: 1920 },
          height: { min: 480, ideal: 720, max: 1080 }
        }
      };
    }
  }

  static vibrate(pattern = [100]) {
    if (this.hasVibration()) {
      try {
        navigator.vibrate(pattern);
      } catch (e) {
        console.debug('Vibration not supported or failed');
      }
    }
  }

  static showBrightnessHint() {
    if (this.isMobile()) {
      return "💡 Tip: Naikkan kecerahan layar untuk hasil scan yang lebih baik";
    }
    return null;
  }

  static getMemoryInfo() {
    if ('memory' in performance) {
      const memory = performance.memory;
      return {
        used: Math.round(memory.usedJSHeapSize / 1024 / 1024),
        total: Math.round(memory.totalJSHeapSize / 1024 / 1024),
        limit: Math.round(memory.jsHeapSizeLimit / 1024 / 1024),
        percentage: Math.round((memory.usedJSHeapSize / memory.totalJSHeapSize) * 100)
      };
    }
    return null;
  }

  static isMemoryPressure() {
    const memInfo = this.getMemoryInfo();
    return memInfo && memInfo.percentage > 80;
  }

  static requestWakeLock() {
    if ('wakeLock' in navigator) {
      return navigator.wakeLock.request('screen').catch(() => {
        console.debug('Wake lock not available');
        return null;
      });
    }
    return Promise.resolve(null);
  }
}

export class PerformanceMonitor {
  constructor() {
    this.startTime = performance.now();
    this.checkInterval = null;
    this.callbacks = new Set();
  }

  startMonitoring(intervalMs = 10000) {
    this.checkInterval = setInterval(() => {
      const memInfo = MobileDetection.getMemoryInfo();
      const isSlowDevice = MobileDetection.isLowEndDevice();
      
      const metrics = {
        memory: memInfo,
        isSlowDevice,
        uptime: Math.round((performance.now() - this.startTime) / 1000),
        timestamp: new Date().toISOString()
      };

      this.callbacks.forEach(callback => {
        try {
          callback(metrics);
        } catch (e) {
          console.error('Performance monitor callback error:', e);
        }
      });
    }, intervalMs);
  }

  stopMonitoring() {
    if (this.checkInterval) {
      clearInterval(this.checkInterval);
      this.checkInterval = null;
    }
  }

  addCallback(callback) {
    this.callbacks.add(callback);
  }

  removeCallback(callback) {
    this.callbacks.delete(callback);
  }
}