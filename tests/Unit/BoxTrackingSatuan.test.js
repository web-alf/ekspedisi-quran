import { describe, it, expect, vi } from 'vitest';
import { render, fireEvent } from '@testing-library/svelte';
import { writable } from 'svelte/store';
import BoxTrackingIndex from '../../resources/js/Pages/Admin/BoxTracking/Index.svelte';

/**
 * Poin #5 — halaman daftar Al-Qur'an selesai packing.
 *
 * Fokus: kolom satuan (pcs/doz) dan QR benar-benar tampil di tabel, dan QR
 * dipasang sebagai data URL apa adanya — BUKAN ditambah prefix
 * "data:image/png;base64," lagi (getBoxQRBase64() sudah mengembalikan data URL
 * utuh; dobel prefix membuat gambarnya tidak pernah tampil).
 */

vi.mock('@inertiajs/svelte', () => ({
    router: { get: vi.fn(), visit: vi.fn(), post: vi.fn() },
    useForm: (data = {}) => {
        const store = writable(data);
        return Object.assign(store, {
            errors: {}, processing: false, reset: vi.fn(), patch: vi.fn(), post: vi.fn()
        });
    },
    page: writable({ props: {} }),
    usePage: () => ({ props: {} })
}));

const QR_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';

const boxDengan = (over = {}) => ({
    id: 1,
    kode_kerdus: 'KB-20260930-001-A5-01',
    status: 'sealed',
    status_info: { label: 'Tersegel', bg: 'bg-green-100', text: 'text-green-800' },
    jenis_quran: "Al-Qur'an A5",
    kapasitas: 30,
    terisi: 24,
    progress_percentage: 80,
    user_name: 'Petugas Uji',
    seal_code: 'SEAL-1',
    sealed_at: '30/09/2026 10:00',
    created_at: '30/09/2026 09:00',
    satuan: { pcs: 20, doz: 1, pcs_per_doz: 20, label: '1 doz' },
    satuan_kapasitas: { pcs: 20, doz: 1, pcs_per_doz: 20, label: '1 doz' },
    satuan_keterangan: '1 doz = 20 pcs (A5)',
    qr_code_base64: QR_DATA_URL,
    ...over
});

const statsDengan = (over = {}) => ({
    total_boxes: 1, empty_boxes: 0, filling_boxes: 0, full_boxes: 0,
    sealed_boxes: 1, total_items_packed: 20,
    sealed_pcs: 20,
    sealed_per_jenis: [
        {
            jenis: 'Al-Quran Ukuran A5', kode_jenis: 'A5', jumlah_kerdus: 1,
            satuan: { pcs: 20, doz: 1, pcs_per_doz: 20, label: '1 doz' },
            keterangan: '1 doz = 20 pcs (A5)'
        }
    ],
    ...over
});

function renderHalaman(box = boxDengan(), stats = statsDengan()) {
    return render(BoxTrackingIndex, {
        props: {
            boxes: { data: [box] },
            filters: {},
            jenisQuranList: [],
            warehouseUsers: [],
            stats,
            auth: { user: { permissions: ['warehouse.boxes.view'] } }
        }
    });
}

/** Ambil elemen pertama yang teksnya cocok — teks bisa muncul di beberapa tempat. */
function getByTextSafe(text) {
    return [...document.querySelectorAll('body *')]
        .filter((el) => el.children.length === 0 && el.textContent.trim() === text)
        .at(0) ?? null;
}

describe('BoxTracking — satuan & QR', () => {
    it('menampilkan satuan isi kerdus dalam doz', () => {
        const { container } = renderHalaman();

        // Kerdus A5 penuh: 20 keping = 1 doz (bukan lusin, tapi kerdus penuh).
        const sel = [...container.querySelectorAll('tbody td')].map((td) => td.textContent.trim());
        expect(sel.some((t) => t.startsWith('1 doz'))).toBe(true);
        expect(getByTextSafe('Isi (Satuan)')).toBeTruthy();
    });

    it('menampilkan keping apa adanya bila kerdus belum penuh', () => {
        // 24 keping pada kerdus A5 (isi 20) belum penuh — disebut pcs, bukan
        // "1,2 doz" yang bikin gudang salah hitung.
        const { getByText } = renderHalaman(boxDengan({
            terisi: 24,
            satuan: { pcs: 24, doz: 1.2, pcs_per_doz: 20, label: '24 pcs' }
        }));

        expect(getByText('24 pcs')).toBeTruthy();
    });

    it('memasang QR apa adanya tanpa menggandakan prefix data URL', () => {
        const { container } = renderHalaman();

        const img = container.querySelector('img[alt^="QR KB-"]');
        expect(img).toBeTruthy();
        // Kalau prefix digandakan, src akan jadi
        // "data:image/png;base64,data:image/png;base64,..." dan gambar tidak tampil.
        expect(img.getAttribute('src')).toBe(QR_DATA_URL);
        expect(img.getAttribute('src').match(/data:image\/png;base64,/g)).toHaveLength(1);
    });

    it('menampilkan kartu ringkasan per jenis beserta keterangan isi doz', () => {
        const { getByText } = renderHalaman();

        expect(getByText("Total Al-Qur'an Selesai Packing")).toBeTruthy();
        // Kartu ringkasan = elemen pembungkus terluar yang memuat judulnya.
        const teksKartu = [...document.querySelectorAll('div')]
            .filter((d) => d.textContent.includes("Total Al-Qur'an Selesai Packing"))
            .at(0).textContent;
        // Isi 1 doz berbeda tiap ukuran, jadi harus tertulis di kartunya.
        expect(teksKartu).toContain('1 doz');
        expect(teksKartu).toContain('A5');
        expect(teksKartu).toContain('1 doz = 20 pcs (A5)');
        expect(teksKartu).toContain('1 kerdus');
    });

    it('menampilkan tombol filter "Hanya Selesai Packing"', () => {
        const { getByText } = renderHalaman();

        const tombol = getByText('Hanya Selesai Packing');
        expect(tombol).toBeTruthy();
        expect(tombol.closest('button')).toBeTruthy();
    });

    it('mengirim parameter selesai_packing saat filter dinyalakan', async () => {
        const { router } = await import('@inertiajs/svelte');
        const { getByText } = renderHalaman();

        await fireEvent.click(getByText('Hanya Selesai Packing').closest('button'));

        expect(router.get).toHaveBeenCalled();
        const [url, params] = router.get.mock.calls.at(-1);
        expect(url).toBe('/admin/box-tracking');
        expect(params.selesai_packing).toBe(1);
    });

    it('memberi keterangan bila QR kerdus belum dibuat', () => {
        const { getByText } = renderHalaman(boxDengan({ qr_code_base64: null }));

        expect(getByText('QR belum dibuat')).toBeTruthy();
    });
});
