import '@testing-library/jest-dom';
import { vi } from 'vitest';

// Mock navigator.mediaDevices for camera tests
global.navigator.mediaDevices = {
    getUserMedia: vi.fn(),
    enumerateDevices: vi.fn(() => Promise.resolve([
        {
            deviceId: 'camera1',
            kind: 'videoinput',
            label: 'Front Camera',
            groupId: 'group1'
        },
        {
            deviceId: 'camera2',
            kind: 'videoinput',
            label: 'Back Camera',
            groupId: 'group2'
        }
    ]))
};

// Mock FileReader
global.FileReader = class FileReader {
    readAsDataURL() {
        this.onload({ target: { result: 'data:image/jpeg;base64,/9j/4AAQSkZJRg==' } });
    }
};

// Mock URL.createObjectURL
global.URL.createObjectURL = vi.fn(() => 'blob:mock-url');
global.URL.revokeObjectURL = vi.fn();

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
    constructor() {}
    disconnect() {}
    observe() {}
    unobserve() {}
    takeRecords() {
        return [];
    }
};

// Mock canvas for image capture
HTMLCanvasElement.prototype.getContext = vi.fn(() => ({
    drawImage: vi.fn(),
    fillRect: vi.fn(),
    clearRect: vi.fn(),
    getImageData: vi.fn(),
    putImageData: vi.fn(),
    createImageData: vi.fn(),
    setTransform: vi.fn(),
    resetTransform: vi.fn(),
    save: vi.fn(),
    restore: vi.fn(),
    scale: vi.fn(),
    rotate: vi.fn(),
    translate: vi.fn(),
    transform: vi.fn(),
    beginPath: vi.fn(),
    closePath: vi.fn(),
    moveTo: vi.fn(),
    lineTo: vi.fn(),
    bezierCurveTo: vi.fn(),
    quadraticCurveTo: vi.fn(),
    arc: vi.fn(),
    arcTo: vi.fn(),
    ellipse: vi.fn(),
    rect: vi.fn(),
    fill: vi.fn(),
    stroke: vi.fn(),
    clip: vi.fn(),
    isPointInPath: vi.fn(),
    isPointInStroke: vi.fn(),
    fillText: vi.fn(),
    strokeText: vi.fn(),
    measureText: vi.fn(),
    drawFocusIfNeeded: vi.fn(),
    scrollPathIntoView: vi.fn()
}));

HTMLCanvasElement.prototype.toDataURL = vi.fn(() =>
    'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k='
);

// Mock Image for canvas/image processing tests
global.Image = class Image {
    constructor() {
        this.width = 1920;
        this.height = 1080;
    }
    set src(value) {
        setTimeout(() => {
            if (this.onload) {
                this.onload();
            }
        }, 0);
    }
};

// Mock Audio
global.Audio = class Audio {
    constructor() {
        this.volume = 1;
    }
    play() {
        return Promise.resolve();
    }
    pause() {}
};

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation(query => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});
