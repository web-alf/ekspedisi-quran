<?php

use App\Http\Middleware\CegahKirimGanda;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PerformanceMonitoring;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RateLimitHandler;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\WarehouseAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->group('api-web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            // 'guest' WAJIB didaftarkan di sini: alias dari app/Http/Kernel.php
            // tidak terpakai lagi (aplikasi memakai bootstrap/app.php Laravel 12),
            // sehingga tanpa baris ini Laravel memakai middleware bawaan yang
            // melempar pengguna yang sudah login ke '/' — bukan ke beranda
            // perannya (26/10/2026: SPV membuka /login mendarat di landing page).
            'guest' => RedirectIfAuthenticated::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'warehouse' => WarehouseAccess::class,
            'rate-limit-handler' => RateLimitHandler::class,
            'performance' => PerformanceMonitoring::class,
            'cegah-kirim-ganda' => CegahKirimGanda::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            // Handle authentication exceptions for API routes
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                    'error' => 'authentication_required',
                ], 401);
            }
        });
    })->create();
