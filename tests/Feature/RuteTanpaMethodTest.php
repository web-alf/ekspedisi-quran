<?php

use Illuminate\Support\Facades\Route;

/**
 * Setiap rute harus menunjuk method controller yang benar-benar ada.
 *
 * Di dash, `GET /admin/pengiriman-export` menunjuk `PengirimanController@export` —
 * method yang tidak pernah ada (tanpa trait juga). Setiap permintaan ke sana
 * dijawab 500 "Method ... does not exist", dan itu tampak dari luar seperti
 * halaman yang rusak/hak akses yang salah, bukan seperti salah tulis rute.
 *
 * Uji ini menjaga SELURUH rute sekaligus, bukan hanya satu kasus: rute baru yang
 * salah nama method akan langsung ketahuan sebelum sampai ke produksi. Rute
 * berbasis Closure (mis. halaman statis) dilewati — di situ tidak ada method yang
 * bisa salah nama.
 */
it('tidak ada rute yang menunjuk method controller yang tidak ada', function () {
    $rusak = [];

    foreach (Route::getRoutes() as $rute) {
        $aksi = $rute->getActionName();

        if (! is_string($aksi) || $aksi === 'Closure') {
            continue;
        }

        if (str_contains($aksi, '@')) {
            [$kelas, $method] = explode('@', $aksi, 2);
        } else {
            $kelas = $aksi;
            $method = '__invoke';
        }

        if (! class_exists($kelas) || ! method_exists($kelas, $method)) {
            $rusak[] = sprintf(
                '%s /%s -> %s (method tidak ada)',
                $rute->methods()[0],
                $rute->uri(),
                $aksi
            );
        }
    }

    expect($rusak)->toBe([]);
});
