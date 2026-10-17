/**
 * Menu "Penempatan Gudang" (16/10/2026): untuk super-admin & manager gudang, BUKAN SPV.
 * SPV di produksi ikut memegang role manager-repacking (jadi izin `gudang.penempatan`
 * ikut terbawa) — menu disembunyikan lewat `excludeRoles`, server menolak lewat
 * User::bolehAturPenempatanGudang().
 */
import { it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const nav = readFileSync(resolve(__dirname, '../../resources/js/Layouts/AdminLayout.svelte'), 'utf8')
    .replace(/<!--[\s\S]*?-->/g, '');

it('menu Penempatan Gudang ada di menu gudang dan menu admin, dikecualikan untuk SPV', () => {
    const entri = nav.match(/label: 'Penempatan Gudang',\s*route: '\/admin\/penempatan-gudang',\s*requiredPermissions: \['gudang\.penempatan'\],\s*excludeRoles: \['spv-gudang'\]/g);

    expect(entri).toHaveLength(2);
});

it('filter menu menghormati excludeRoles kecuali untuk super-admin', () => {
    expect(nav).toContain("if (item.excludeRoles && !peranNama.includes('super-admin') && item.excludeRoles.some(r => peranNama.includes(r)))");
    expect(nav).toContain('getNavigationItems(userPermissions, peranNama)');
});
