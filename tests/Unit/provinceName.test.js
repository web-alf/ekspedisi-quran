import { describe, it, expect } from 'vitest';
import { canonicalProvince, provinceFromGeoJson } from '../../resources/js/utils/provinceName.js';

describe('provinceName', () => {
    it('menyatukan ejaan provinsi yang berbeda', () => {
        expect(canonicalProvince('DI YOGYAKARTA'))
            .toBe(canonicalProvince('DAERAH ISTIMEWA YOGYAKARTA'));
        expect(canonicalProvince('KEPULAUAN BANGKA BELITUNG'))
            .toBe(canonicalProvince('BANGKA BELITUNG'));
        expect(canonicalProvince('DI. ACEH')).toBe('ACEH');
        expect(canonicalProvince('PROBANTEN')).toBe('BANTEN');
        expect(canonicalProvince('NUSATENGGARA BARAT')).toBe('NUSA TENGGARA BARAT');
    });

    it('tidak menyatukan provinsi yang memang berbeda', () => {
        // Jebakan paling berbahaya: Riau vs Kepulauan Riau adalah dua provinsi berbeda
        expect(canonicalProvince('RIAU')).not.toBe(canonicalProvince('KEPULAUAN RIAU'));
        expect(canonicalProvince('PAPUA')).not.toBe(canonicalProvince('PAPUA BARAT'));
        expect(canonicalProvince('JAWA BARAT')).not.toBe(canonicalProvince('JAWA TENGAH'));
    });

    it('menerima berbagai format input', () => {
        expect(canonicalProvince('  jawa   tengah  ')).toBe('JAWA TENGAH');
        expect(canonicalProvince('Provinsi Jawa Barat')).toBe('JAWA BARAT');
        expect(canonicalProvince('JAWA-TIMUR')).toBe('JAWA TIMUR');
    });

    it('mengembalikan string kosong untuk input kosong', () => {
        expect(canonicalProvince(null)).toBe('');
        expect(canonicalProvince(undefined)).toBe('');
        expect(canonicalProvince('')).toBe('');
        expect(canonicalProvince('   ')).toBe('');
    });

    it('membaca nama provinsi dari properti GeoJSON apa pun', () => {
        expect(provinceFromGeoJson({ Propinsi: 'DAERAH ISTIMEWA YOGYAKARTA' })).toBe('DI YOGYAKARTA');
        expect(provinceFromGeoJson({ name: 'JAWA TENGAH' })).toBe('JAWA TENGAH');
        expect(provinceFromGeoJson({ provinsi: 'Jawa Barat' })).toBe('JAWA BARAT');
        expect(provinceFromGeoJson({})).toBe('');
    });

    it('mencocokkan data produksi dengan nama di peta', () => {
        // Kasus nyata: 2 provinsi ini sebelumnya tidak pernah muncul di peta
        const dataProduksi = ['DI YOGYAKARTA', 'KEPULAUAN BANGKA BELITUNG'];
        const namaPeta = ['DAERAH ISTIMEWA YOGYAKARTA', 'BANGKA BELITUNG'];

        dataProduksi.forEach((data, i) => {
            expect(canonicalProvince(data)).toBe(canonicalProvince(namaPeta[i]));
        });
    });
});
