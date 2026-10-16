/**
 * QR Scanner Utilities with Dynamic Loading and Robust Fallbacks
 * Provides production-ready QR scanner initialization with dynamic imports to prevent QR_CODE enum issues
 * Priority: html5-qrcode (dynamic) → file upload
 */

export class QRScannerManager {
    constructor() {
        this.scanner = null;
        this.scannerType = null; // 'html5-qrcode'
        this.isInitialized = false;
        this.hasLibraryIssues = false;
        this.Html5QrcodeScanner = null;
        this.Html5QrcodeScanType = null;
        this.Html5QrcodeSupportedFormats = null;
        this.libraryLoaded = false;
    }

    /**
     * Dynamically load html5-qrcode library with compatibility testing
     */
    async loadLibrary() {
        if (this.libraryLoaded && this.Html5QrcodeScanner) {
            return { 
                Html5QrcodeScanner: this.Html5QrcodeScanner, 
                Html5QrcodeScanType: this.Html5QrcodeScanType,
                Html5QrcodeSupportedFormats: this.Html5QrcodeSupportedFormats
            };
        }

        try {
            console.debug('Dynamically loading html5-qrcode library...');
            
            const module = await import('html5-qrcode');
            
            // Verify required exports are available
            if (!module.Html5QrcodeScanner) {
                throw new Error('Html5QrcodeScanner not found in library');
            }

            this.Html5QrcodeScanner = module.Html5QrcodeScanner;
            this.Html5QrcodeScanType = module.Html5QrcodeScanType;
            this.Html5QrcodeSupportedFormats = module.Html5QrcodeSupportedFormats;

            // Verify QR_CODE enum is available
            if (!this.Html5QrcodeSupportedFormats || !this.Html5QrcodeSupportedFormats.QR_CODE) {
                console.warn('Html5QrcodeSupportedFormats.QR_CODE not available, using fallback');
                // Create a fallback enum
                this.Html5QrcodeSupportedFormats = {
                    QR_CODE: 0,
                    DATA_MATRIX: 1,
                    UPC_A: 2,
                    UPC_E: 3,
                    UPC_EAN_EXTENSION: 4,
                    EAN_8: 5,
                    EAN_13: 6,
                    CODE_128: 7,
                    CODE_93: 8,
                    CODE_39: 9,
                    CODABAR: 10,
                    ITF: 11,
                    RSS_14: 12,
                    RSS_EXPANDED: 13,
                    PDF_417: 14,
                    AZTEC: 15,
                    MAXICODE: 16
                };
            }

            // Test library compatibility with QR_CODE enum
            const compatibility = await this.testLibraryCompatibility();
            if (!compatibility.success) {
                throw new Error(`Library compatibility test failed: ${compatibility.error}`);
            }

            this.libraryLoaded = true;
            console.debug('html5-qrcode library loaded and verified successfully');
            
            return { 
                Html5QrcodeScanner: this.Html5QrcodeScanner, 
                Html5QrcodeScanType: this.Html5QrcodeScanType,
                Html5QrcodeSupportedFormats: this.Html5QrcodeSupportedFormats
            };

        } catch (error) {
            console.error('Failed to load QR scanner library:', error);
            this.hasLibraryIssues = true;
            throw error;
        }
    }

    /**
     * Test library compatibility to avoid QR_CODE enum errors
     */
    async testLibraryCompatibility() {
        try {
            // Create a hidden test element
            const testElement = document.createElement('div');
            testElement.id = 'qr-compatibility-test-' + Date.now();
            testElement.style.display = 'none';
            testElement.style.position = 'absolute';
            testElement.style.top = '-9999px';
            testElement.style.left = '-9999px';
            document.body.appendChild(testElement);

            return new Promise((resolve) => {
                const cleanup = () => {
                    try {
                        if (document.body.contains(testElement)) {
                            document.body.removeChild(testElement);
                        }
                    } catch (e) {
                        // Ignore cleanup errors
                    }
                };

                const timeout = setTimeout(() => {
                    cleanup();
                    resolve({ success: true, error: null }); // Timeout = success (no immediate error)
                }, 2000);

                try {
                    // Test instantiation with minimal config
                    const testScanner = new this.Html5QrcodeScanner(
                        testElement.id,
                        {
                            fps: 1,
                            qrbox: { width: 100, height: 100 },
                            disableFlip: true,
                            rememberLastUsedCamera: false,
                            supportedScanTypes: this.Html5QrcodeScanType ? [this.Html5QrcodeScanType.SCAN_TYPE_CAMERA] : undefined,
                            experimentalFeatures: {
                                useBarCodeDetectorIfSupported: false
                            }
                        },
                        false
                    );

                    // If we got here without QR_CODE error, the library is compatible
                    clearTimeout(timeout);
                    cleanup();
                    resolve({ success: true, error: null });

                } catch (instantError) {
                    clearTimeout(timeout);
                    cleanup();
                    
                    // Check for specific QR_CODE enum errors
                    if (instantError.message?.includes('QR_CODE') || 
                        instantError.message?.includes('Cannot read properties of undefined')) {
                        resolve({ success: false, error: instantError.message });
                    } else {
                        // Other errors are not library compatibility issues
                        resolve({ success: true, error: null });
                    }
                }
            });

        } catch (error) {
            return { 
                success: !error.message?.includes('QR_CODE') && !error.message?.includes('Cannot read properties of undefined'), 
                error: error.message 
            };
        }
    }

    /**
     * Test camera availability and permissions
     */
    async testCameraCompatibility() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return { success: false, error: 'Camera API not available' };
            }

            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { min: 320, ideal: 640, max: 1280 },
                    height: { min: 240, ideal: 480, max: 720 }
                }
            });

            // Stop the stream immediately after testing
            stream.getTracks().forEach(track => track.stop());
            return { success: true, error: null };

        } catch (error) {
            let message = 'Camera access failed';
            if (error.name === 'NotAllowedError') {
                message = 'Camera permission denied';
            } else if (error.name === 'NotFoundError') {
                message = 'No camera found';
            } else if (error.name === 'NotSupportedError') {
                message = 'Camera not supported';
            }
            
            return { success: false, error: message };
        }
    }

    /**
     * Get optimal configuration based on device capabilities
     */
    getOptimalConfig(isMobile = false, isLowEnd = false) {
        const baseConfig = {
            fps: isMobile ? (isLowEnd ? 6 : 10) : 12,
            qrbox: isMobile ? 
                (isLowEnd ? { width: 200, height: 200 } : { width: 250, height: 250 }) :
                { width: 300, height: 300 },
            rememberLastUsedCamera: true,
            disableFlip: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: false // Better compatibility
            },
            requestPermission: true,
            showTorchButtonIfSupported: true,
            showZoomSliderIfSupported: false
        };

        // Add supported formats if available (this prevents QR_CODE enum errors)
        if (this.Html5QrcodeSupportedFormats && this.Html5QrcodeSupportedFormats.QR_CODE !== undefined) {
            baseConfig.formatsToSupport = [this.Html5QrcodeSupportedFormats.QR_CODE];
        }

        // Add mobile-specific optimizations
        if (isMobile) {
            baseConfig.videoConstraints = {
                facingMode: "environment",
                width: { 
                    min: 320, 
                    ideal: isLowEnd ? 640 : 1280, 
                    max: isLowEnd ? 1280 : 1920 
                },
                height: { 
                    min: 240, 
                    ideal: isLowEnd ? 480 : 720, 
                    max: isLowEnd ? 720 : 1080 
                }
            };

            if (isLowEnd) {
                baseConfig.aspectRatio = 1.0;
                baseConfig.fps = 6;
            }
        } else {
            // Desktop specific settings
            baseConfig.videoConstraints = {
                width: { min: 480, ideal: 1280, max: 1920 },
                height: { min: 360, ideal: 720, max: 1080 }
            };
        }

        // Add supported scan types if available
        if (this.Html5QrcodeScanType) {
            baseConfig.supportedScanTypes = [this.Html5QrcodeScanType.SCAN_TYPE_CAMERA];
        }

        return baseConfig;
    }

    /**
     * Initialize scanner with dynamic loading and fallbacks
     */
    async initialize(elementId, onSuccess, onError, options = {}) {
        const { isMobile = false, isLowEnd = false } = options;

        try {
            // Step 1: Load library dynamically
            await this.loadLibrary();

            // Step 2: Test camera availability
            const cameraTest = await this.testCameraCompatibility();
            if (!cameraTest.success) {
                throw new Error(cameraTest.error);
            }

            // Step 3: Get optimal configuration
            const config = this.getOptimalConfig(isMobile, isLowEnd);

            console.debug('Initializing html5-qrcode with config:', config);

            // Step 4: Create scanner instance
            this.scanner = new this.Html5QrcodeScanner(elementId, config, false);
            this.scannerType = 'html5-qrcode';

            // Step 5: Render with timeout protection
            return new Promise((resolve, reject) => {
                const renderTimeout = setTimeout(() => {
                    reject(new Error('Scanner initialization timeout'));
                }, 10000);

                try {
                    this.scanner.render(
                        (decodedText) => {
                            clearTimeout(renderTimeout);
                            this.isInitialized = true;
                            resolve(this.scanner);
                            onSuccess(decodedText);
                        },
                        (error) => {
                            // Check for library-specific errors
                            if (error.includes('QR_CODE') || 
                                error.includes('Cannot read properties of undefined') ||
                                error.includes('ZXing')) {
                                clearTimeout(renderTimeout);
                                this.hasLibraryIssues = true;
                                reject(new Error('Library error: ' + error));
                                return;
                            }

                            // Handle other scanner errors through the provided error handler
                            if (onError) {
                                onError(error);
                            }
                        }
                    );
                } catch (renderError) {
                    clearTimeout(renderTimeout);
                    reject(renderError);
                }
            });

        } catch (error) {
            console.error('Scanner initialization failed:', error);
            this.hasLibraryIssues = true;
            throw error;
        }
    }

    /**
     * Initialize with retry logic and fallback configurations
     */
    async initializeWithRetries(elementId, onSuccess, onError, options = {}) {
        const maxRetries = 2;
        const retryConfigs = [
            // First try: Normal configuration
            { ...options },
            // Second try: Low-end configuration
            { ...options, isLowEnd: true }
        ];

        for (let attempt = 0; attempt < maxRetries; attempt++) {
            try {
                console.log(`Scanner initialization attempt ${attempt + 1}/${maxRetries}`);
                
                return await this.initialize(
                    elementId, 
                    onSuccess, 
                    onError, 
                    retryConfigs[attempt]
                );
                
            } catch (error) {
                console.warn(`Scanner attempt ${attempt + 1} failed:`, error);
                
                // If this is the last attempt, provide detailed error info
                if (attempt === maxRetries - 1) {
                    console.error('All scanner attempts failed. Error details:', {
                        lastError: error.message,
                        hasLibraryIssues: this.hasLibraryIssues,
                        userAgent: navigator.userAgent,
                        isSecureContext: window.isSecureContext
                    });
                    
                    throw new Error(`Scanner failed after ${maxRetries} attempts: ${error.message}`);
                }
                
                // Wait before next retry
                await new Promise(resolve => setTimeout(resolve, 1000 * (attempt + 1)));
            }
        }
    }

    /**
     * Clean up scanner resources
     */
    async cleanup() {
        if (!this.scanner) return;

        try {
            if (this.scanner && typeof this.scanner.clear === 'function') {
                await this.scanner.clear();
            }

            // Stop all media tracks manually
            if (navigator.mediaDevices) {
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    for (const device of devices) {
                        if (device.kind === 'videoinput') {
                            try {
                                const stream = await navigator.mediaDevices.getUserMedia({ 
                                    video: { deviceId: device.deviceId } 
                                });
                                stream.getTracks().forEach(track => {
                                    track.stop();
                                    console.debug('Stopped media track:', track.label);
                                });
                            } catch (e) {
                                // Ignore errors for inactive streams
                            }
                        }
                    }
                } catch (e) {
                    // Ignore enumeration errors
                }
            }
            
        } catch (err) {
            console.warn('Scanner cleanup warning:', err);
        } finally {
            // Always reset state
            this.scanner = null;
            this.scannerType = null;
            this.isInitialized = false;
        }
    }

    /**
     * Check if scanner has known library issues
     */
    hasKnownLibraryIssues() {
        return this.hasLibraryIssues;
    }

    /**
     * Get current scanner instance
     */
    getScanner() {
        return this.scanner;
    }

    /**
     * Check if scanner is initialized and ready
     */
    isReady() {
        return this.isInitialized && this.scanner !== null;
    }
}

// Export singleton instance
export const qrScannerManager = new QRScannerManager();

// Export utility functions
export function createQRScannerManager() {
    return new QRScannerManager();
}

export default QRScannerManager;