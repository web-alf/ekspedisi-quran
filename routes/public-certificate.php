<?php

use App\Http\Controllers\Public\CertificateDownloadController;
use Illuminate\Support\Facades\Route;

// Public certificate download routes (no auth required) - Enhanced rate limiting
Route::prefix('certificate')->name('public.certificate.')->middleware(['throttle:certificate-download', 'rate-limit-handler'])->group(function () {
    // Download certificate using secure token
    Route::get('/download/{token}', [CertificateDownloadController::class, 'download'])
        ->name('download');

    // Preview certificate in browser
    Route::get('/preview/{token}', [CertificateDownloadController::class, 'preview'])
        ->name('preview');
});
