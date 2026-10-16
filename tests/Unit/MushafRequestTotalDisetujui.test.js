import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, fireEvent, waitFor } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import MushafRequestShow from '../../resources/js/Pages/Admin/MushafRequest/Show.svelte';

/**
 * Apakah "Total Disetujui" di kartu Detail Permintaan ikut berubah setelah
 * jumlah (A5/A6/IQRA) disimpan?
 */

let patchMock;

vi.mock('@inertiajs/svelte', () => ({
    router: {
        patch: (...args) => patchMock(...args),
        post: vi.fn(),
        get: vi.fn(),
        visit: vi.fn()
    },
    useForm: (data = {}) => {
        const store = writable(data);
        return Object.assign(store, {
            errors: {}, processing: false, reset: vi.fn(), patch: vi.fn(), post: vi.fn()
        });
    },
    page: writable({
        props: { auth: { user: { permissions: ['mushaf-requests.update', 'mushaf-requests.read'] } } }
    }),
    usePage: () => ({ props: {} })
}));

const requestDengan = (over = {}) => ({
    id: 1,
    no_request: 'REQ-2026-00001',
    nama_lembaga: 'Yayasan Uji',
    status: 'approved',
    jumlah_mushaf: 60,
    jumlah_mushaf_a5: 60,
    jumlah_mushaf_a6: 0,
    jumlah_iqra: 0,
    jumlah_mushaf_approved: null,
    jumlah_mushaf_a5_approved: 0,
    jumlah_mushaf_a6_approved: 0,
    jumlah_iqra_approved: 0,
    jenis_mushaf_diminta: ['A5'],
    approved_breakdown: {
        mushaf: 60, iqra: 0, total: 60, a5: 60, a6: 0,
        a5_requested: 60, a6_requested: 0, iqra_requested: 0, total_requested: 60
    },
    ...over
});

/** Ambil angka "Total Disetujui" dari kartu Detail Permintaan (bukan modal). */
function totalDiKartu(container) {
    const kandidat = Array.from(container.querySelectorAll('dl'))
        .find((dl) => dl.textContent.includes('Total Disetujui'));

    if (!kandidat) {
        return { teks: '(kartu Detail Permintaan tidak ditemukan)', angka: null };
    }

    const teks = kandidat.textContent.replace(/\s+/g, ' ').trim();
    const m = teks.match(/Total Disetujui(.*)$/);
    const angka = m ? m[1].match(/(\d+)/g) : null;

    return {
        teks: teks.slice(-180),
        angka: angka ? Number(angka[angka.length - 1]) : null
    };
}

function renderPage(request) {
    return render(MushafRequestShow, {
        props: {
            mushafRequest: request,
            donaturList: [],
            auth: { user: { permissions: ['mushaf-requests.update', 'mushaf-requests.read'] } },
            errors: {},
            flash: {}
        }
    });
}

beforeEach(() => {
    patchMock = vi.fn();
});

describe('Total Disetujui di kartu Detail Permintaan', () => {
    it('menampilkan total awal dari approved_breakdown', () => {
        const out = renderPage(requestDengan());
        const kartu = totalDiKartu(out.container);

        console.log('TOTAL AWAL:', kartu.angka, '|', kartu.teks);
        expect(kartu.angka).toBe(60);
    });

    it('ikut berubah setelah simpan berhasil (server mengembalikan angka baru)', async () => {
        const out = renderPage(requestDengan());

        // Buka modal & ubah jumlah
        await fireEvent.click(out.getByText('Edit Jumlah'));
        const inputs = out.container.querySelectorAll('input[type="number"]');
        await fireEvent.input(inputs[0], { target: { value: '10' } });
        await fireEvent.input(inputs[1], { target: { value: '0' } });
        await fireEvent.input(inputs[2], { target: { value: '5' } });

        console.log('TOTAL DI KARTU saat modal masih terbuka:', totalDiKartu(out.container).angka);

        // Simpan
        await fireEvent.click(out.getByText('Simpan Perubahan'));

        console.log('patch dipanggil:', patchMock.mock.calls.length);
        const [url, payload, options] = patchMock.mock.calls[0] || [];
        console.log('  url     :', url);
        console.log('  payload :', JSON.stringify(payload));
        console.log('  has onSuccess:', typeof options?.onSuccess);

        // Server mengembalikan props baru: total 15
        await options.onSuccess({
            props: {
                mushafRequest: requestDengan({
                    jumlah_mushaf_approved: 15,
                    jumlah_mushaf_a5_approved: 10,
                    jumlah_iqra_approved: 5,
                    approved_breakdown: {
                        mushaf: 10, iqra: 5, total: 15, a5: 10, a6: 0,
                        a5_requested: 60, a6_requested: 0, iqra_requested: 0, total_requested: 60
                    }
                })
            }
        });

        await waitFor(() => {
            const kartu = totalDiKartu(out.container);
            console.log('TOTAL DI KARTU setelah simpan:', kartu.angka, '|', kartu.teks);
            expect(kartu.angka).toBe(15);
        });
    });

    it('payload yang dikirim hanya pecahan, tanpa kolom total lama', async () => {
        const out = renderPage(requestDengan());
        await fireEvent.click(out.getByText('Edit Jumlah'));
        const inputs = out.container.querySelectorAll('input[type="number"]');
        await fireEvent.input(inputs[0], { target: { value: '10' } });
        await fireEvent.input(inputs[2], { target: { value: '5' } });
        await fireEvent.click(out.getByText('Simpan Perubahan'));

        const [, payload] = patchMock.mock.calls[0];
        console.log('PAYLOAD:', JSON.stringify(payload, null, 2));

        expect(payload).not.toHaveProperty('jumlah_mushaf_approved');
        expect(payload.jumlah_mushaf_a5_approved).toBe(10);
        expect(payload.jumlah_iqra_approved).toBe(5);
    });
});
