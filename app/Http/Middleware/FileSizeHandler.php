<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Inertia\Inertia;

class FileSizeHandler
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (PostTooLargeException $e) {
            // If the request is expecting JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => '⚠️ Ukuran file melebihi batas maksimum yang diizinkan',
                    'details' => [
                        'max_filesize' => ini_get('upload_max_filesize'),
                        'max_post_size' => ini_get('post_max_size'),
                        'limits' => [
                            'foto' => '2MB',
                            'dokumen' => '5MB',
                            'total' => '10MB'
                        ]
                    ]
                ], 413);
            }

            // If it's a mushaf-request form submission, redirect back with errors
            if ($request->is('mushaf-request')) {
                return redirect()->back()->withErrors([
                    'error' => '⚠️ Ukuran file melebihi batas maksimum yang diizinkan! Total ukuran file tidak boleh melebihi 10MB. Detail batas ukuran file: Foto maksimal 2MB, dokumen maksimal 5MB. Silakan kompres atau pilih file yang lebih kecil.'
                ])->withInput($request->except(['foto_santri', 'foto_lembaga', 'file_nama_santri']));
            }
            
            // For Inertia requests, render a nice error page
            if ($request->header('X-Inertia')) {
                return Inertia::render('Errors/FileTooLarge', [
                    'maxFileSize' => ini_get('upload_max_filesize'),
                    'maxPostSize' => ini_get('post_max_size'),
                    'limits' => [
                        'foto' => '2MB',
                        'dokumen' => '5MB',
                        'total' => '10MB'
                    ]
                ])->toResponse($request);
            }
            
            // Fallback to a simple error page for non-Inertia requests
            return response()->view('errors.file-too-large', [
                'maxFileSize' => ini_get('upload_max_filesize'),
                'maxPostSize' => ini_get('post_max_size')
            ], 413);
        }
    }
}
