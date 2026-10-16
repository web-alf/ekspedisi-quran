<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak kiriman GANDA yang identik dalam beberapa detik.
 *
 * Klik ganda "Simpan" pada input donasi, tambah Al-Qur'an, tambah item wakaf, atau
 * "Import" mengirim dua request yang sama persis. Kunci baris donatur hanya membuat
 * keduanya berurutan — request kedua tetap dianggap donasi baru yang sah, sehingga item,
 * resi, dan QR-nya tergandakan (untuk impor: seluruh isi berkas).
 *
 * Sidik jari request = pengguna (atau IP bila tamu) + rute + isi form + isi berkas.
 * `Cache::add` bersifat atomik (driver database memakai primary key), jadi dari dua
 * request yang datang bersamaan hanya satu yang lolos. Kunci dilepas lagi bila request
 * pertama GAGAL (galat validasi / server), supaya petugas bisa langsung mengulang
 * kiriman yang sama setelah memperbaikinya.
 */
class CegahKirimGanda
{
    public function handle(Request $request, Closure $next, int $detik = 10): Response
    {
        $kunci = $this->sidikJari($request);

        if (! Cache::add($kunci, true, $detik)) {
            $pesan = 'Data yang sama baru saja dikirim dan sedang/sudah diproses. Periksa daftar sebelum mengirim ulang.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 409);
            }

            return back()->with('error', $pesan);
        }

        $response = $next($request);

        if ($this->gagal($request, $response)) {
            Cache::forget($kunci);
        }

        return $response;
    }

    private function sidikJari(Request $request): string
    {
        $berkas = collect(Arr::dot($request->allFiles()))
            ->filter(fn ($f) => $f instanceof UploadedFile && $f->isValid())
            ->map(fn (UploadedFile $f) => hash_file('sha256', $f->getRealPath()))
            ->all();

        $isi = $request->except(['_token', '_method']);
        ksort($isi);

        return 'kirim-ganda:'.hash('sha256', implode('|', [
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
            $request->method(),
            $request->path(),
            json_encode($isi),
            json_encode($berkas),
        ]));
    }

    private function gagal(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return true;
        }

        // Inertia/form biasa: galat validasi dikembalikan sebagai redirect + errors yang
        // di-flash. Yang dilihat hanya flash BARU dari request ini — flash request
        // sebelumnya masih ada di sesi selama satu request dan bukan tanda gagal.
        if (! $request->hasSession()) {
            return false;
        }

        $flashBaru = (array) $request->session()->get('_flash.new', []);

        return in_array('errors', $flashBaru, true) || in_array('error', $flashBaru, true);
    }
}
