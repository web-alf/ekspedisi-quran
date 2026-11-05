/**
 * Proses Packing untuk freelancer di HP (05/11/2026):
 *   1. Tombol "Hubungi Supervisor" dulu membuka Monitor & Penugasan (403 untuk freelancer).
 *      Kini freelancer melihat nama SPV gudangnya; tautan Monitor hanya untuk pemegang izin.
 *   2. Rincian Siap Dikemas dilipat di HP supaya pemindai terlihat di bawah progres.
 *   3. Suara sukses & galat berbeda di semua perangkat — dulu HP RAM ≤ 2 GB memakai dua
 *      data-URI identik dan suara sukses/ditolak dilewati.
 *
 * Tes membaca sumber berkas (Vitest repo ini mengompilasi Svelte ke mode SSR).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const sumber = readFileSync(resolve(__dirname, '../../resources/js/Pages/Warehouse/Packing.svelte'), 'utf8');
const markup = sumber.slice(sumber.indexOf('<AdminLayout>'));
const dashboard = readFileSync(resolve(__dirname, '../../resources/js/Pages/Warehouse/Dashboard.svelte'), 'utf8');

describe('Kartu Tidak Ada Tugas', () => {
    const kartu = markup.slice(markup.indexOf('{#if noTaskMessage}'), markup.indexOf('<!-- Normal Packing Interface -->'));

    it('menampilkan tautan Monitor & Penugasan hanya bagi pemegang izinnya', () => {
        expect(sumber).toContain("includes('supervisor.warehouse.monitor')");
        expect(kartu).toMatch(/\{#if bolehMonitor\}\s*<a\s+href="\/admin\/supervisor\/warehouse-monitor"/);
        expect(kartu).not.toContain('Hubungi Supervisor');
    });

    it('menyebut nama SPV gudang bagi freelancer', () => {
        expect(sumber).toContain('export let spvGudang = [];');
        expect(kartu).toContain("{spvGudang.join(', ')}");
        expect(kartu).toContain('Hubungi SPV gudang Anda untuk mendapatkan tugas.');
    });

    it('menyebut nama SPV gudang di Dashboard Gudang', () => {
        expect(dashboard).toContain('export let spvGudang = [];');
        expect(dashboard).toContain("{spvGudang.join(', ')}");
        expect(dashboard).not.toContain('Hubungi supervisor jika ada kesalahan');
    });
});

describe('Siap Dikemas di HP', () => {
    const seksi = markup.slice(markup.indexOf('judul="Siap Dikemas'), markup.indexOf('</RingkasanSection>', markup.indexOf('judul="Siap Dikemas')));

    it('melipat rincian di bawah lg dan selalu membukanya di desktop', () => {
        expect(seksi).toMatch(/class="lg:hidden[^"]*"/);
        expect(seksi).toContain("<div class=\"{siapTerbuka ? '' : 'hidden'} lg:block\">");
        expect(sumber).toContain('let siapTerbuka = false;');
    });

    it('tetap menyebut jumlah resi dan mushaf siap saat dilipat', () => {
        const tombol = seksi.slice(seksi.indexOf('<button'), seksi.indexOf('</button>'));
        expect(tombol).toContain('siapDikemas.jumlah_resi');
        expect(tombol).toContain('siapDikemas.jumlah_eks');
        expect(tombol).toContain('aria-expanded={siapTerbuka}');
    });

    it('menaruh kotak angka dan tabel contoh di dalam bagian yang dilipat', () => {
        const lipatan = seksi.slice(seksi.indexOf("{siapTerbuka ? '' : 'hidden'}"));
        expect(lipatan).toContain('<StatTile label="Resi siap"');
        expect(lipatan).toContain('{#each siapDikemas.contoh as resi');
        expect(lipatan).toContain('{#each TAUTAN_TAHAP_LANJUT as tautan');
    });
});

describe('Suara scan', () => {
    it('memakai berkas sukses dan galat yang berbeda di semua perangkat', () => {
        expect(sumber).toContain("successSound = new Audio('/sounds/success.mp3');");
        expect(sumber).toContain("errorSound = new Audio('/sounds/error.mp3');");
        expect(sumber).not.toContain('data:audio/wav');
        expect(sumber).not.toContain('isLowEndDevice');
    });

    it('memutar suara dari awal di setiap scan', () => {
        expect(sumber).toMatch(/function putarSuara\(suara\) \{[\s\S]*?suara\.currentTime = 0;[\s\S]*?suara\.play\(\)/);
        expect(sumber.match(/putarSuara\(successSound\)/g)).toHaveLength(1);
        expect(sumber.match(/putarSuara\(errorSound\)/g)).toHaveLength(2);
    });
});
