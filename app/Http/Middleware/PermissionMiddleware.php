<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
