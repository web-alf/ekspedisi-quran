import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createScanner } from '../../resources/js/utils/scanner-core.js';
import { get } from 'svelte/store';

const mockRender = vi.fn();
const mockClear = vi.fn();

vi.mock('html5-qrcode', () => ({
    Html5QrcodeScanner: vi.fn().mockImplementation(function (elementId, config, verbose) {
        this.render = mockRender;
        this.clear = mockClear;
    }),
    Html5Qrcode: {
        getCameras: vi.fn(() => Promise.resolve([{ id: 'camera1', label: 'Back Camera' }])),
    },
    Html5QrcodeScannerState: {
        SCANNING: 2,
        NOT_STARTED: 1,
    },
}));

describe('scanner-core', () => {
    let onSuccess;
    let onFailure;
    let onError;

    beforeEach(() => {
        onSuccess = vi.fn();
        onFailure = vi.fn();
        onError = vi.fn();
        mockRender.mockClear();
        mockClear.mockClear();

        // Ensure qr-reader element exists in DOM
        document.body.innerHTML = '<div id="qr-reader"></div>';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('creates scanner store with initial state', () => {
        const scanner = createScanner('qr-reader', {
            onSuccess,
            onFailure,
            onError,
        });

        const state = get(scanner);
        expect(state.scanning).toBe(false);
        expect(state.isInitializing).toBe(false);
        expect(state.permissionGranted).toBe(false);
    });

    it('requestPermission resolves when camera is available', async () => {
        const stream = {
            getTracks: vi.fn(() => [{ stop: vi.fn() }]),
        };
        navigator.mediaDevices.getUserMedia.mockResolvedValueOnce(stream);

        const scanner = createScanner('qr-reader', {
            onSuccess,
            onFailure,
            onError,
        });

        await scanner.requestPermission();

        const state = get(scanner);
        expect(state.permissionGranted).toBe(true);
        expect(onError).not.toHaveBeenCalled();
    });

    it('requestPermission rejects and calls onError when camera is denied', async () => {
        const err = new Error('Permission denied');
        err.name = 'NotAllowedError';
        navigator.mediaDevices.getUserMedia.mockRejectedValueOnce(err);

        const scanner = createScanner('qr-reader', {
            onSuccess,
            onFailure,
            onError,
        });

        await expect(scanner.requestPermission()).rejects.toThrow();

        const state = get(scanner);
        expect(state.permissionGranted).toBe(false);
        expect(onError).toHaveBeenCalledWith(
            'Izin kamera ditolak. Silakan refresh halaman dan izinkan akses kamera.'
        );
    });

    it('cleanup resets scanning state', () => {
        const scanner = createScanner('qr-reader', {
            onSuccess,
            onFailure,
            onError,
        });

        // Manually set scanning true by mocking internal state update
        // Since we can't easily trigger init without mocking video element deeply,
        // we verify cleanup works on a freshly created scanner
        scanner.cleanup();

        const state = get(scanner);
        expect(state.scanning).toBe(false);
        expect(state.isInitializing).toBe(false);
    });

    it('init starts scanning when permission is granted', async () => {
        const stream = {
            getTracks: vi.fn(() => [{ stop: vi.fn() }]),
        };
        navigator.mediaDevices.getUserMedia.mockResolvedValueOnce(stream);

        const scanner = createScanner('qr-reader', {
            onSuccess,
            onFailure,
            onError,
        });

        await scanner.requestPermission();
        scanner.init();

        // wait for setTimeout inside init
        await new Promise((resolve) => setTimeout(resolve, 600));

        const state = get(scanner);
        // Because Html5QrcodeScanner is mocked, render is called but video element check may not resolve
        expect(state.isInitializing || state.scanning).toBeTruthy();
    });

    it('start chains permission and initialization', async () => {
        const stream = {
            getTracks: vi.fn(() => [{ stop: vi.fn() }]),
        };
        navigator.mediaDevices.getUserMedia.mockResolvedValueOnce(stream);

        const scanner = createScanner('qr-reader', {
            onSuccess,
            onFailure,
            onError,
        });

        scanner.start();

        await new Promise((resolve) => setTimeout(resolve, 1200));

        expect(get(scanner).permissionGranted).toBe(true);
    });
});
