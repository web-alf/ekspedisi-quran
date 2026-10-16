<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WarehouseAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = 'accessWarehouse'): Response
    {
        if (!auth()->check()) {
            Log::warning('Unauthenticated warehouse access attempt', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method()
            ]);
            
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = auth()->user();

        // Check if user is active
        if (!$user->is_active) {
            Log::warning('Inactive user warehouse access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip_address' => $request->ip()
            ]);
            
            auth()->logout();
            return redirect()->route('login')->with('error', 'Akun Anda tidak aktif. Hubungi administrator.');
        }

        // Check warehouse permission using Gate
        if (!Gate::allows($permission, $user)) {
            Log::warning('Unauthorized warehouse access blocked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'permission' => $permission,
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengakses fitur warehouse.'
                ], 403);
            }
            
            return redirect()->route('admin.dashboard')
                ->with('error', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses fitur warehouse.');
        }

        // Log successful access for audit trail
        Log::info('Warehouse access granted', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'permission' => $permission,
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip()
        ]);

        return $next($request);
    }
}