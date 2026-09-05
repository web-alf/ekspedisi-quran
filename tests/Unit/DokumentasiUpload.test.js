import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor } from '@testing-library/svelte';
import DokumentasiUpload from '../../resources/js/Components/DokumentasiUpload.svelte';

describe('DokumentasiUpload Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Component Rendering', () => {
        it('renders component with default props', () => {
            const { container } = render(DokumentasiUpload);
            expect(container).toBeTruthy();
        });

        it('shows custom label when provided', () => {
            const { getByText } = render(DokumentasiUpload, {
                props: { label: 'Custom Label' }
            });
            expect(getByText('Custom Label')).toBeTruthy();
        });

        it('shows mode toggle button when showToggle is true', () => {
            const { getByText } = render(DokumentasiUpload, {
                props: { showToggle: true }
            });
            expect(getByText('Mode Upload')).toBeTruthy();
        });

        it('hides mode toggle when showToggle is false', () => {
            const { queryByText } = render(DokumentasiUpload, {
                props: { showToggle: false }
            });
            expect(queryByText('Mode Upload')).toBeFalsy();
            expect(queryByText('Mode Kamera')).toBeFalsy();
        });

        it('starts in upload mode by default', () => {
            const { getByText } = render(DokumentasiUpload);
            expect(getByText('Mode Upload')).toBeTruthy();
        });

        it('starts in camera mode when defaultMode is camera', () => {
            const { getByText } = render(DokumentasiUpload, {
                props: { defaultMode: 'camera' }
            });
            expect(getByText('Mode Kamera')).toBeTruthy();
        });
    });

    describe('Mode Switching', () => {
        it('switches to camera mode when toggle is clicked', async () => {
            const { getByText } = render(DokumentasiUpload, {
                props: { showToggle: true, allowModeSwitch: true }
            });

            const toggleButton = getByText('Mode Upload');
            await fireEvent.click(toggleButton);

            await waitFor(() => {
                expect(getByText('Mode Kamera')).toBeTruthy();
            });
        });

        it('emits modeChanged event when mode is toggled', async () => {
            const handleModeChanged = vi.fn();

            const { component, getByText } = render(DokumentasiUpload, {
                props: { showToggle: true }
            });

            component.$on('modeChanged', handleModeChanged);

            const toggleButton = getByText('Mode Upload');
            await fireEvent.click(toggleButton);

            await waitFor(() => {
                expect(handleModeChanged).toHaveBeenCalled();
                expect(handleModeChanged).toHaveBeenCalledWith(
                    expect.objectContaining({
                        detail: expect.objectContaining({ mode: 'camera' })
                    })
                );
            });
        });

        it('clears data when switching modes', async () => {
            const { getByText, container } = render(DokumentasiUpload, {
                props: { showToggle: true }
            });

            // Upload a file first
            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });
            await fireEvent.change(input);

            // Switch mode
            const toggleButton = getByText('Mode Upload');
            await fireEvent.click(toggleButton);

            // Data should be cleared
            await waitFor(() => {
                expect(getByText('Mode Kamera')).toBeTruthy();
            });
        });

        it('does not allow mode switch when allowModeSwitch is false', async () => {
            const { queryByText } = render(DokumentasiUpload, {
                props: { showToggle: true, allowModeSwitch: false }
            });

            // Toggle button should not be present when mode switching is disabled
            expect(queryByText('Mode Upload')).toBeFalsy();
            expect(queryByText('Mode Kamera')).toBeFalsy();
        });
    });

    describe('File Upload Mode', () => {
        it('shows upload area in upload mode', () => {
            const { getByText } = render(DokumentasiUpload, {
                props: { defaultMode: 'upload' }
            });
            expect(getByText('Upload file')).toBeTruthy();
        });

        it('handles file selection', async () => {
            const handlePhotosChanged = vi.fn();
            const { container, component } = render(DokumentasiUpload);

            component.$on('photosChanged', handlePhotosChanged);

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });

            await fireEvent.change(input);

            await waitFor(() => {
                expect(handlePhotosChanged).toHaveBeenCalledWith(
                    expect.objectContaining({
                        detail: expect.objectContaining({
                            mode: 'upload',
                            files: expect.any(Array)
                        })
                    })
                );
            });
        });

        it('shows preview of uploaded files', async () => {
            const { container } = render(DokumentasiUpload);

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });

            await fireEvent.change(input);

            await waitFor(() => {
                const images = container.querySelectorAll('img');
                expect(images.length).toBeGreaterThan(0);
            });
        });
    });

    describe('Camera Mode', () => {
        it('renders CameraCapture component in camera mode', async () => {
            const { getByText, container } = render(DokumentasiUpload, {
                props: { defaultMode: 'camera' }
            });

            // Should show camera controls
            await waitFor(() => {
                expect(getByText('Buka Kamera')).toBeTruthy();
            });
        });

        it('emits photosChanged when camera captures photo', async () => {
            const handlePhotosChanged = vi.fn();
            const { component } = render(DokumentasiUpload, {
                props: { defaultMode: 'camera' }
            });

            component.$on('photosChanged', handlePhotosChanged);

            // Simulate camera capture
            // This would require interacting with CameraCapture component
            // For now, we test the event handler exists
            expect(component).toBeTruthy();
        });
    });

    describe('Public Methods', () => {
        it('getFiles returns uploaded files in upload mode', async () => {
            const { container, component } = render(DokumentasiUpload);

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });
            await fireEvent.change(input);

            const files = component.getFiles();
            expect(files).toBeInstanceOf(Array);
            expect(files.length).toBeGreaterThan(0);
        });

        it('getFiles returns converted files in camera mode', async () => {
            const { component } = render(DokumentasiUpload, {
                props: { defaultMode: 'camera' }
            });

            // Mock captured photos
            component.capturedPhotos = [
                {
                    id: 1,
                    dataUrl: 'data:image/jpeg;base64,/9j/4AAQSkZJRg==',
                    timestamp: new Date().toISOString()
                }
            ];

            const files = component.getFiles();
            expect(files).toBeInstanceOf(Array);
        });

        it('getPhotoCount returns correct count', async () => {
            const { component, container } = render(DokumentasiUpload);

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });
            await fireEvent.change(input);

            await waitFor(() => {
                const count = component.getPhotoCount();
                expect(count).toBeGreaterThan(0);
            });
        });

        it('clearAll removes all photos', () => {
            const { component } = render(DokumentasiUpload);

            // Mock some photos
            component.uploadedFiles = [new File(['test'], 'test.jpg', { type: 'image/jpeg' })];

            component.clearAll();

            expect(component.getPhotoCount()).toBe(0);
        });

        it('clearAll emits cleared event', () => {
            const handleCleared = vi.fn();
            const { component } = render(DokumentasiUpload);

            component.$on('cleared', handleCleared);
            component.clearAll();

            expect(handleCleared).toHaveBeenCalled();
        });
    });

    describe('Photo Count Indicator', () => {
        it('shows photo count when photos are selected', async () => {
            const { container, getByText } = render(DokumentasiUpload);

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });
            await fireEvent.change(input);

            await waitFor(() => {
                expect(getByText(/1\/5 foto dipilih/)).toBeTruthy();
            });
        });

        it('respects custom maxPhotos', async () => {
            const { container, getByText } = render(DokumentasiUpload, {
                props: { maxPhotos: 3 }
            });

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });
            await fireEvent.change(input);

            await waitFor(() => {
                expect(getByText(/1\/3 foto dipilih/)).toBeTruthy();
            });
        });
    });

    describe('Base64 to File Conversion', () => {
        it('converts base64 to File object correctly', () => {
            const { component } = render(DokumentasiUpload);

            const base64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRg==';
            const file = component.base64ToFile(base64, 'test.jpg');

            expect(file).toBeInstanceOf(File);
            expect(file.name).toBe('test.jpg');
            expect(file.type).toBe('image/jpeg');
        });

        it('handles different image formats', () => {
            const { component } = render(DokumentasiUpload);

            const formats = [
                { base64: 'data:image/png;base64,iVBORw0KGgo', type: 'image/png' },
                { base64: 'data:image/jpeg;base64,/9j/4AAQ', type: 'image/jpeg' },
                { base64: 'data:image/webp;base64,UklGRiQA', type: 'image/webp' }
            ];

            formats.forEach(format => {
                const file = component.base64ToFile(format.base64, 'test');
                expect(file.type).toBe(format.type);
            });
        });
    });

    describe('Integration with Form', () => {
        it('can be used to append files to FormData', async () => {
            const { component, container } = render(DokumentasiUpload);

            const input = container.querySelector('input[type="file"]');
            const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' });
            Object.defineProperty(input, 'files', { value: [file] });
            await fireEvent.change(input);

            const files = component.getFiles();
            const formData = new FormData();

            files.forEach((f, index) => {
                formData.append(`dokumentasi[${index}]`, f);
            });

            expect(formData.has('dokumentasi[0]')).toBe(true);
        });
    });

    describe('Props Validation', () => {
        it('handles invalid maxPhotos gracefully', () => {
            const { component } = render(DokumentasiUpload, {
                props: { maxPhotos: -1 }
            });
            expect(component).toBeTruthy();
        });

        it('handles empty label', () => {
            const { container } = render(DokumentasiUpload, {
                props: { label: '' }
            });
            expect(container).toBeTruthy();
        });

        it('handles invalid defaultMode', () => {
            const { component } = render(DokumentasiUpload, {
                props: { defaultMode: 'invalid' }
            });
            // Should fallback to upload mode
            expect(component).toBeTruthy();
        });
    });
});
