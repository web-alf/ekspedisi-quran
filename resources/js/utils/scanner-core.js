import { writable, get } from 'svelte/store';
import { tick } from 'svelte';
import { Html5QrcodeScanner } from 'html5-qrcode';

/**
 * Creates a reactive scanner store with full lifecycle management.
 * Extracts duplicated scanner logic from BoxScanner, ScanStatus, and Packing.
 */
export function createScanner(elementId = 'qr-reader', options = {}) {
    const {
        fps = 10,
        qrbox = { width: 250, height: 250 },
        videoConstraints = {
            facingMode: 'environment',
            width: { ideal: 1280, min: 640 },
            height: { ideal: 720, min: 480 },
        },
        onSuccess = () => {},
        onFailure = () => {},
        onError = () => {},
        useBarcodeDetector = false,
    } = options;

    const store = writable({
        scanning: false,
        isInitializing: false,
        permissionGranted: false,
    });

    let scanner = null;
    let elementCheckInterval = null;

    function update(partial) {
        store.update((s) => ({ ...s, ...partial }));
    }

    async function requestPermission() {
        if (!navigator.mediaDevices?.getUserMedia) {
            const msg = 'Browser tidak mendukung akses kamera';
            onError(msg);
            return Promise.reject(new Error(msg));
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                },
            });
            stream.getTracks().forEach((track) => track.stop());
            update({ permissionGranted: true });
            await new Promise((resolve) => setTimeout(resolve, 500));
        } catch (err) {
            let errorMsg;
            if (err.name === 'NotAllowedError') {
                errorMsg = 'Izin kamera ditolak. Silakan refresh halaman dan izinkan akses kamera.';
            } else if (err.name === 'NotFoundError') {
                errorMsg = 'Kamera tidak ditemukan. Pastikan perangkat memiliki kamera.';
            } else if (err.name === 'NotReadableError') {
                errorMsg = 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi lain dan coba lagi.';
            } else {
                errorMsg = `Tidak dapat mengakses kamera: ${err.message}`;
            }
            update({ permissionGranted: false });
            onError(errorMsg);
            throw err;
        }
    }

    function init() {
        const state = get(store);
        if (state.isInitializing || state.scanning) {
            return;
        }

        update({ isInitializing: true });

        const checkElement = () => {
            const el = document.getElementById(elementId);
            if (!el) {
                setTimeout(() => {
                    const retryEl = document.getElementById(elementId);
                    if (!retryEl) {
                        onError('Scanner element tidak ditemukan. Silakan refresh halaman.');
                        update({ isInitializing: false });
                        return;
                    }
                    startScanner(retryEl);
                }, 1000);
                return;
            }
            startScanner(el);
        };

        checkElement();
    }

    function startScanner(el) {
        cleanup();
        setTimeout(() => {
            tryHtml5Scanner().catch(() => tryDirectCamera());
        }, 500);
    }

    async function tryHtml5Scanner() {
        return new Promise((resolve, reject) => {
            try {
                scanner = new Html5QrcodeScanner(
                    elementId,
                    {
                        fps,
                        qrbox,
                        rememberLastUsedCamera: true,
                        videoConstraints,
                        showTorchButtonIfSupported: true,
                        showZoomSliderIfSupported: false,
                        disableFlip: false,
                    },
                    false,
                );

                scanner.render(
                    (decodedText, decodedResult) => {
                        update({ scanning: false });
                        cleanup();
                        onSuccess(decodedText, decodedResult);
                    },
                    (error) => onFailure(error),
                );

                update({ scanning: true, isInitializing: false });
                startElementCheck();

                setTimeout(() => {
                    const videoElement = document.querySelector(`#${elementId} video`);
                    if (videoElement) {
                        if (videoElement.paused) {
                            videoElement.play().catch(() => {});
                        }
                        resolve();
                    } else {
                        if (scanner) {
                            scanner.clear();
                            scanner = null;
                        }
                        update({ scanning: false, isInitializing: false });
                        stopElementCheck();
                        reject(new Error('Video element not created'));
                    }
                }, 3000);
            } catch (renderError) {
                update({ scanning: false, isInitializing: false });
                stopElementCheck();
                reject(renderError);
            }
        });
    }

    async function tryDirectCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: videoConstraints,
            });
            const qrReaderElement = document.getElementById(elementId);
            if (qrReaderElement) {
                qrReaderElement.innerHTML = `
                    <div style="position: relative; background: #000; border-radius: 12px; overflow: hidden;">
                        <video
                            id="direct-camera-video"
                            autoplay
                            playsinline
                            muted
                            style="width: 100%; height: 300px; object-fit: cover; display: block;"
                        ></video>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 2px solid #10b981; width: 200px; height: 200px; border-radius: 8px; pointer-events: none;"></div>
                        <div style="position: absolute; top: 12px; left: 12px; background: rgba(16, 185, 129, 0.9); color: white; padding: 6px 12px; border-radius: 6px; font-size: 14px; font-weight: 600;">🔍 Direct Camera Scanner</div>
                        <div style="position: absolute; bottom: 12px; right: 12px; background: rgba(0, 0, 0, 0.8); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;" id="camera-status">Active</div>
                    </div>
                `;
            }

            const videoElement = document.getElementById('direct-camera-video');
            if (videoElement) {
                videoElement.srcObject = stream;
                await videoElement.play();
                await startQRDetectionOnVideo(videoElement);
            }

            update({ scanning: true, isInitializing: false });
            startElementCheck();
        } catch (err) {
            onError(`Camera tidak dapat diakses: ${err.message}`);
            update({ scanning: false, isInitializing: false });
        }
    }

    async function startQRDetectionOnVideo(videoElement) {
        try {
            let detector = null;
            let useNativeDetector = false;

            if (useBarcodeDetector && 'BarcodeDetector' in window) {
                try {
                    detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                    useNativeDetector = true;
                } catch (e) {
                    useNativeDetector = false;
                }
            }

            let isDetecting = false;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            let html5QrCode = null;

            if (!useNativeDetector) {
                const { Html5Qrcode } = await import('html5-qrcode');
                let tempDiv = document.getElementById('temp-qr-detector');
                if (!tempDiv) {
                    tempDiv = document.createElement('div');
                    tempDiv.id = 'temp-qr-detector';
                    tempDiv.style.display = 'none';
                    document.body.appendChild(tempDiv);
                }
                html5QrCode = new Html5Qrcode('temp-qr-detector', { verbose: false });
                videoElement.__html5QrCode = html5QrCode;
            }

            const detectQR = async () => {
                if (isDetecting || !get(store).scanning || videoElement.paused || videoElement.readyState < 2) {
                    return;
                }
                isDetecting = true;

                try {
                    if (useNativeDetector && detector) {
                        const codes = await detector.detect(videoElement);
                        if (codes && codes.length > 0) {
                            const value = codes[0].rawValue;
                            if (value) {
                                update({ scanning: false });
                                cleanup();
                                onSuccess(String(value), null);
                            }
                        }
                    } else if (html5QrCode) {
                        const vw = videoElement.videoWidth;
                        const vh = videoElement.videoHeight;
                        if (!vw || !vh) {
                            isDetecting = false;
                            return;
                        }
                        const targetW = Math.min(640, vw);
                        const scale = targetW / vw;
                        const targetH = Math.round(vh * scale);
                        canvas.width = targetW;
                        canvas.height = targetH;
                        ctx.drawImage(videoElement, 0, 0, targetW, targetH);

                        await new Promise((resolve) => {
                            canvas.toBlob(async (blob) => {
                                if (!blob || !get(store).scanning) {
                                    resolve();
                                    return;
                                }
                                try {
                                    const file = new File([blob], 'frame.jpg', { type: 'image/jpeg' });
                                    const qrResult = await html5QrCode.scanFile(file, false);
                                    update({ scanning: false });
                                    cleanup();
                                    onSuccess(qrResult, null);
                                } catch (e) {
                                    // No QR found, ignore
                                } finally {
                                    resolve();
                                }
                            }, 'image/jpeg', 0.5);
                        });
                    }
                } catch (error) {
                    // Ignore transient errors
                } finally {
                    isDetecting = false;
                }
            };

            const detectionInterval = setInterval(detectQR, useNativeDetector ? 250 : 500);
            videoElement.qrDetectionInterval = detectionInterval;
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('QR detection setup failed:', error);
        }
    }

    function cleanup() {
        try {
            if (scanner && typeof scanner.clear === 'function') {
                scanner.clear();
                scanner = null;
            }

            const directVideo = document.getElementById('direct-camera-video');
            if (directVideo) {
                try {
                    if (directVideo.srcObject) {
                        directVideo.srcObject.getTracks().forEach((track) => track.stop());
                    }
                    if (directVideo.qrDetectionInterval) {
                        clearInterval(directVideo.qrDetectionInterval);
                    }
                    if (directVideo.__html5QrCode) {
                        directVideo.__html5QrCode.clear().catch(() => {});
                        directVideo.__html5QrCode = null;
                    }
                    directVideo.srcObject = null;
                } catch (err) {
                    // ignore
                }
            }

            const tempDiv = document.getElementById('temp-qr-detector');
            if (tempDiv) {
                tempDiv.remove();
            }

            const qrReaderElement = document.getElementById(elementId);
            if (qrReaderElement) {
                qrReaderElement.innerHTML = '';
            }
        } catch (err) {
            // eslint-disable-next-line no-console
            console.warn('Scanner cleanup warning:', err);
        } finally {
            scanner = null;
            update({ scanning: false, isInitializing: false });
            stopElementCheck();
        }
    }

    async function restart() {
        onError(null);
        if (get(store).permissionGranted) {
            setTimeout(async () => {
                await tick();
                const qrReaderElement = document.getElementById(elementId);
                if (qrReaderElement && !get(store).isInitializing && !get(store).scanning) {
                    init();
                } else if (!get(store).isInitializing && !get(store).scanning) {
                    onError('Scanner element tidak tersedia. Silakan refresh halaman.');
                }
            }, 500);
        } else {
            await requestPermission();
            if (get(store).permissionGranted) {
                init();
            }
        }
    }

    function startElementCheck() {
        if (elementCheckInterval) {
            return;
        }
        elementCheckInterval = setInterval(() => {
            const qrReaderElement = document.getElementById(elementId);
            if (!qrReaderElement && get(store).scanning) {
                update({ scanning: false, isInitializing: false });
                stopElementCheck();
            }
        }, 3000);
        setTimeout(() => {
            stopElementCheck();
        }, 60000);
    }

    function stopElementCheck() {
        if (elementCheckInterval) {
            clearInterval(elementCheckInterval);
            elementCheckInterval = null;
        }
    }

    return {
        subscribe: store.subscribe,
        requestPermission,
        init,
        start() {
            if (get(store).permissionGranted) {
                init();
            } else {
                requestPermission().then(() => init());
            }
        },
        cleanup,
        restart,
    };
}
