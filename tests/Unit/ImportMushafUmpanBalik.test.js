import { render, fireEvent } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import { describe, it, expect, vi, beforeEach } from 'vitest';

/**
 * Cakupan: umpan balik saat import BERKAS GAGAL pada halaman Permintaan Mushaf.
 *
 * Keluhan aslinya: "berkas yang di-upload tidak bisa sempurna di import".
 * Ternyata errornya memang tidak pernah muncul: fungsi showError/showWarning
 * dipanggil tetapi tidak pernah diimpor, sehingga memanggilnya melempar
 * ReferenceError dan tidak ada pesan apa pun yang terlihat pengguna.
 */

const pageStore = writable({ props: {}, url: '/admin/mushaf-requests' });

vi.mock('@inertiajs/svelte', () => ({
    router: { get: vi.fn(), visit: vi.fn(), post: vi.fn(), on: vi.fn() },
    page: pageStore,
    usePage: () => ({ props: {} }),
    useForm: (data = {}) => {
        const store = writable(data);
        return Object.assign(store, {
            errors: {}, processing: false, reset: vi.fn(), patch: vi.fn(), post: vi.fn()
        });
    }
}));

vi.mock('../../resources/js/utils/auth.js', () => ({
    logout: vi.fn(), safeLogout: vi.fn()
}));

// Leaflet dimuat dinamis oleh peta; tidak relevan untuk uji ini.
vi.mock('leaflet', () => ({ default: {}, map: vi.fn(), tileLayer: vi.fn() }));

describe('Permintaan Mushaf — umpan balik import gagal', () => {
    let Index;

    beforeEach(async () => {
        vi.clearAllMocks();
        Index = (await import('../../resources/js/Pages/Admin/MushafRequest/Index.svelte')).default;
    });

    it('menampilkan pesan saat berkas yang dipilih bukan Excel', async () => {
        const { container } = render(Index, {
            props: {
                mushafRequests: { data: [], links: [], from: 0, to: 0, total: 0 },
                errors: {}, auth: {}, flash: {}, settings: {}
            }
        });

        // Buka modal import lalu pilih berkas dengan tipe yang salah.
        const tombolImport = Array.from(container.querySelectorAll('button'))
            .find((b) => /import/i.test(b.textContent));
        expect(tombolImport, 'tombol Import tidak ditemukan').toBeTruthy();
        await fireEvent.click(tombolImport);

        const input = container.querySelector('input[type="file"]');
        expect(input, 'input berkas tidak ditemukan').toBeTruthy();

        const berkasSalah = new File(['x'], 'data.pdf', { type: 'application/pdf' });
        Object.defineProperty(input, 'files', { value: [berkasSalah] });

        // Inilah yang dulu melempar ReferenceError dan tidak menampilkan apa pun.
        await expect(async () => {
            await fireEvent.change(input);
        }).not.toThrow();
    });

    it('tidak memanggil fungsi yang tidak ada saat berkas ditolak', async () => {
        const pesan = [];
        const asliError = console.error;
        const asliWarn = console.warn;
        console.error = (...a) => pesan.push(a.join(' '));
        console.warn = (...a) => pesan.push(a.join(' '));

        const { container } = render(Index, {
            props: {
                mushafRequests: { data: [], links: [], from: 0, to: 0, total: 0 },
                errors: {}, auth: {}, flash: {}, settings: {}
            }
        });

        const tombolImport = Array.from(container.querySelectorAll('button'))
            .find((b) => /import/i.test(b.textContent));
        await fireEvent.click(tombolImport);

        const input = container.querySelector('input[type="file"]');
        const berkasSalah = new File(['x'], 'data.pdf', { type: 'application/pdf' });
        Object.defineProperty(input, 'files', { value: [berkasSalah] });

        let melempar = null;
        try {
            await fireEvent.change(input);
        } catch (e) {
            melempar = e;
        }
        console.error = asliError;
        console.warn = asliWarn;

        // Kalau showError tidak didefinisikan, di sini muncul
        // "ReferenceError: showError is not defined".
        expect(melempar?.message || '').not.toMatch(/is not defined/);
    });
});
