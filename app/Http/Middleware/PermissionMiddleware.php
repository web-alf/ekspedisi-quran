<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        // Check if user is authenticated
        if (! auth()->check()) {
            return redirect('/login');
        }

        $user = auth()->user();

        // Check if user is active
        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        // Check if user has any of the required permissions using Spatie
        if (! empty($permissions)) {
            // Flatten permissions array and split by pipe (|) for OR logic
            $flatPermissions = [];
            foreach ($permissions as $permission) {
                // If permission contains pipe, split it (e.g., "dashboard.view|dashboard.analytics")
                if (str_contains($permission, '|')) {
                    $flatPermissions = array_merge($flatPermissions, explode('|', $permission));
                } else {
                    $flatPermissions[] = $permission;
                }
            }

            // Super-admin lolos middleware ini tanpa bergantung pada isi tabel
            // permissions. Penting karena hasAnyPermission() membaca DB langsung dan
            // TIDAK melewati Gate::before, sehingga tanpa cabang ini super-admin bisa
            // terkunci bila suatu permission belum di-seed.
            if ($user->hasRole(RoleEnum::SUPER_ADMIN->value)) {
                return $next($request);
            }

            if (! $user->hasAnyPermission($flatPermissions)) {
                // Log unauthorized access attempt
                \Log::warning('Unauthorized access attempt', [
                    'user_id' => $user->id,
                    'user_roles' => $user->roles->pluck('name'),
                    'required_permissions' => $permissions,
                    'parsed_permissions' => $flatPermissions,
                    'user_permissions' => $user->getAllPermissions()->pluck('name'),
                    'route' => $request->route()->getName(),
                    'url' => $request->url(),
                    'ip' => $request->ip(),
                ]);

                abort(403, 'Insufficient permissions');
            }
        }

        return $next($request);
    }
}
