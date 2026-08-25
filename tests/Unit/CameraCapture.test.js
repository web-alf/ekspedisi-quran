import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, fireEvent, waitFor, screen } from '@testing-library/svelte';
import CameraCapture from '../../resources/js/Components/CameraCapture.svelte';

describe('CameraCapture Component', () => {
    let mockStream;
    let mockTrack;

    beforeEach(() => {
        // Setup mock video stream with stable track reference
        mockTrack = { stop: vi.fn() };
        mockStream = {
            getTracks: vi.fn(() => [mockTrack])
        };

        navigator.mediaDevices.getUserMedia.mockResolvedValue(mockStream);
    });

    afterEach(() => {
        vi.clearAllMocks();
        // Restore navigator.mediaDevices if mutated by individual tests
        if (!navigator.mediaDevices) {
            Object.defineProperty(navigator, 'mediaDevices', {
                value: {
                    getUserMedia: vi.fn(),
                    enumerateDevices: vi.fn(() => Promise.resolve([]))
                },
                writable: true,
                configurable: true
            });
        }
    });

    describe('Component Rendering', () => {
        it('renders camera capture component', () => {
            const { container } = render(CameraCapture);
            expect(container).toBeTruthy();
        });

        it('shows camera button when camera is available', async () => {
            const { getByText } = render(CameraCapture);
            await waitFor(() => {
                expect(getByText('Buka Kamera')).toBeTruthy();
            });
        });

        it('shows gallery upload option when allowFileUpload is true', () => {
            const { getByText } = render(CameraCapture, {
                props: { allowFileUpload: true }
            });
            expect(getByText('Pilih dari Galeri')).toBeTruthy();
        });

        it('hides gallery upload when allowFileUpload is false', () => {
            const { queryByText } = render(CameraCapture, {
                props: { allowFileUpload: false }
            });
            expect(queryByText('Pilih dari Galeri')).toBeFalsy();
        });
    });

    describe('Camera Functionality', () => {
        it('starts camera when button is clicked', async () => {
            const { getByText } = render(CameraCapture);

            const button = getByText('Buka Kamera');
            await fireEvent.click(button);

            await waitFor(() => {
                expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith({
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false
                });
            });
        });

        it('shows video element when camera is active', async () => {
            const { getByText, container } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                const video = container.querySelector('video');
                expect(video).toBeTruthy();
            });
        });

        it('shows close button when camera is active', async () => {
            const { getByText } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                expect(getByText('✕ Tutup')).toBeTruthy();
            });
        });

        it('stops camera when close button is clicked', async () => {
            const { getByText } = render(CameraCapture);

            // Start camera
            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(getByText('✕ Tutup')).toBeTruthy());

            // Stop camera
            const stopButton = getByText('✕ Tutup');
            await fireEvent.click(stopButton);

            await waitFor(() => {
                expect(mockStream.getTracks()[0].stop).toHaveBeenCalled();
            });
        });

        it('handles camera permission denied gracefully', async () => {
            const err = new Error('Permission denied');
            err.name = 'NotAllowedError';
            navigator.mediaDevices.getUserMedia.mockRejectedValue(err);

            const { getByText } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                expect(getByText(/Izin kamera ditolak/)).toBeTruthy();
            });
        });
    });

    describe('Photo Capture', () => {
        it('captures photo when capture button is clicked', async () => {
            const onCapture = vi.fn();
            const { getByText, container } = render(CameraCapture, {
                props: { onCapture }
            });

            // Start camera
            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(container.querySelector('video')).toBeTruthy());

            // Find and click capture button (the circular button)
            const captureButton = container.querySelector('.w-16.h-16');
            await fireEvent.click(captureButton);

            await waitFor(() => {
                expect(onCapture).toHaveBeenCalled();
            });
        });

        it('adds captured photo to preview', async () => {
            const { getByText, container } = render(CameraCapture, {
                props: {
                    showPreview: true
                }
            });

            // Start camera and capture
            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(container.querySelector('video')).toBeTruthy());

            const captureButton = container.querySelector('.w-16.h-16');
            await fireEvent.click(captureButton);

            await waitFor(() => {
                expect(getByText(/Foto Dokumentasi/)).toBeTruthy();
            });
        });

        it('respects maxPhotos limit', async () => {
            const { getByText, container } = render(CameraCapture, {
                props: {
                    maxPhotos: 2,
                    capturedPhotos: [
                        { id: 1, dataUrl: 'data:image/jpeg;base64,test1' },
                        { id: 2, dataUrl: 'data:image/jpeg;base64,test2' }
                    ]
                }
            });

            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(container.querySelector('video')).toBeTruthy());

            // Capture button should be disabled
            const captureButton = container.querySelector('.w-16.h-16');
            expect(captureButton.disabled).toBe(true);
        });

        it('shows photo counter', async () => {
            const { getByText } = render(CameraCapture, {
                props: {
                    capturedPhotos: [
                        { id: 1, dataUrl: 'data:image/jpeg;base64,test' }
                    ],
                    maxPhotos: 5
                }
            });

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                expect(getByText('📸 1/5 Foto')).toBeTruthy();
            });
        });
    });

    describe('Camera Switching', () => {
        it('shows switch camera button when camera is active', async () => {
            const { getByText, container } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                const switchButton = container.querySelector('[title="Ganti kamera"]');
                expect(switchButton).toBeTruthy();
            });
        });

        it('switches between front and back camera', async () => {
            const { getByText, container } = render(CameraCapture);

            // Start with back camera (environment)
            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => {
                expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith(
                    expect.objectContaining({
                        video: expect.objectContaining({
                            facingMode: 'environment'
                        })
                    })
                );
            });

            // Switch camera
            const switchButton = container.querySelector('[title="Ganti kamera"]');
            await fireEvent.click(switchButton);

            await waitFor(() => {
                expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith(
                    expect.objectContaining({
                        video: expect.objectContaining({
                            facingMode: 'user'
                        })
                    })
                );
            });
        });
    });

    describe('File Upload', () => {
        it('handles file selection from gallery', async () => {
            const { container } = render(CameraCapture, {
                props: { allowFileUpload: true }
            });

            const input = container.querySelector('input[type="file"]');

            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', {
                value: [file]
            });

            await fireEvent.change(input);

            // Should process the file
            await waitFor(() => {
                expect(container.querySelector('img')).toBeTruthy();
            });
        });

        it('validates file size (max 10MB)', async () => {
            const onCapture = vi.fn();
            const { container } = render(CameraCapture, {
                props: { allowFileUpload: true, onCapture }
            });

            const input = container.querySelector('input[type="file"]');

            // Create a mock file larger than 10MB
            const largeFile = new File(['x'.repeat(11 * 1024 * 1024)], 'large.jpg', {
                type: 'image/jpeg'
            });

            Object.defineProperty(largeFile, 'size', {
                value: 11 * 1024 * 1024
            });

            Object.defineProperty(input, 'files', {
                value: [largeFile]
            });

            await fireEvent.change(input);

            // Should show error toast
            await waitFor(() => {
                // File should be rejected
                expect(onCapture).not.toHaveBeenCalled();
            });
        });

        it('validates file type', async () => {
            const { container } = render(CameraCapture, {
                props: { allowFileUpload: true }
            });

            const input = container.querySelector('input[type="file"]');

            const invalidFile = new File(['test'], 'test.txt', { type: 'text/plain' });
            Object.defineProperty(input, 'files', {
                value: [invalidFile]
            });

            await fireEvent.change(input);

            // Should reject non-image files
            await waitFor(() => {
                const images = container.querySelectorAll('img');
                expect(images.length).toBe(0);
            });
        });
    });

    describe('Photo Management', () => {
        it('shows preview of captured photos', () => {
            const photos = [
                { id: 1, dataUrl: 'data:image/jpeg;base64,test1', timestamp: new Date().toISOString() },
                { id: 2, dataUrl: 'data:image/jpeg;base64,test2', timestamp: new Date().toISOString() }
            ];

            const { container } = render(CameraCapture, {
                props: {
                    capturedPhotos: photos,
                    showPreview: true
                }
            });

            const images = container.querySelectorAll('img');
            expect(images.length).toBeGreaterThanOrEqual(2);
        });

        it('can delete individual photo', async () => {
            const photos = [
                { id: 1, dataUrl: 'data:image/jpeg;base64,test1', timestamp: new Date().toISOString() }
            ];

            const { container } = render(CameraCapture, {
                props: {
                    capturedPhotos: photos,
                    showPreview: true
                }
            });

            // Find delete button
            const deleteButton = container.querySelector('button[title="Hapus foto"]');
            expect(deleteButton).toBeTruthy();
        });

        it('shows correct photo count indicator', () => {
            const { getByText } = render(CameraCapture, {
                props: {
                    capturedPhotos: [
                        { id: 1, dataUrl: 'data:image/jpeg;base64,test1' },
                        { id: 2, dataUrl: 'data:image/jpeg;base64,test2' }
                    ],
                    maxPhotos: 5,
                    showPreview: true
                }
            });

            expect(getByText(/2\/5/)).toBeTruthy();
        });
    });

    describe('Props and Events', () => {
        it('calls onCapture callback when photo is captured', async () => {
            const onCapture = vi.fn();
            const { getByText, container } = render(CameraCapture, {
                props: { onCapture }
            });

            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(container.querySelector('video')).toBeTruthy());

            const captureButton = container.querySelector('.w-16.h-16');
            await fireEvent.click(captureButton);

            await waitFor(() => {
                expect(onCapture).toHaveBeenCalled();
                expect(onCapture).toHaveBeenCalledWith(expect.any(Array));
            });
        });

        it('binds capturedPhotos prop correctly', () => {
            const photos = [
                { id: 1, dataUrl: 'data:image/jpeg;base64,test', timestamp: new Date().toISOString() }
            ];
            const { container } = render(CameraCapture, {
                props: { capturedPhotos: photos, showPreview: true }
            });

            // Preview should render the passed photos
            expect(container.querySelectorAll('img').length).toBeGreaterThanOrEqual(1);
        });
    });

    describe('Cleanup', () => {
        it('stops camera on component destroy', async () => {
            const { getByText, unmount } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(mockStream.getTracks).toBeDefined());

            unmount();

            expect(mockTrack.stop).toHaveBeenCalled();
        });

        it('stops camera on beforeunload event', async () => {
            const { getByText } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));
            await waitFor(() => expect(mockStream.getTracks).toBeDefined());

            // Simulate beforeunload
            window.dispatchEvent(new Event('beforeunload'));

            expect(mockTrack.stop).toHaveBeenCalled();
        });
    });

    describe('Accessibility', () => {
        it('has proper aria labels', () => {
            const { container } = render(CameraCapture);
            const buttons = container.querySelectorAll('button');

            buttons.forEach(button => {
                // Each button should have text or aria-label
                expect(
                    button.textContent.length > 0 || button.getAttribute('aria-label')
                ).toBeTruthy();
            });
        });

        it('video element has aria-label', async () => {
            const { getByText, container } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                const video = container.querySelector('video');
                expect(video.getAttribute('aria-label')).toBeTruthy();
            });
        });
    });

    describe('Error Handling', () => {
        it('shows error message when camera fails', async () => {
            navigator.mediaDevices.getUserMedia.mockRejectedValue(
                new Error('Camera not available')
            );

            const { getByText } = render(CameraCapture);

            await fireEvent.click(getByText('Buka Kamera'));

            await waitFor(() => {
                const errorMessage = getByText(/Camera not available/);
                expect(errorMessage).toBeTruthy();
            });
        });

        it('handles no camera device gracefully', () => {
            // Mock no camera available
            Object.defineProperty(navigator, 'mediaDevices', {
                value: undefined,
                writable: true
            });

            const { queryByText } = render(CameraCapture);

            // Should show warning message
            expect(queryByText(/Kamera tidak tersedia/)).toBeTruthy();
        });
    });
});
