<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Normalize roles to handle inconsistent naming conventions
        $normalizedRoles = array_map(function ($role) {
            return $this->normalizeRoleName($role);
        }, $roles);

        // Check if user has any of the required roles using Spatie
        $hasRequiredRole = false;
        foreach ($normalizedRoles as $role) {
            if ($user->hasRole($role)) {
                $hasRequiredRole = true;
                break;
            }
        }

        // Fallback: check legacy role field if exists
        if (! $hasRequiredRole && isset($user->role)) {
            $userRole = $this->normalizeRoleName($user->role);
            $hasRequiredRole = in_array($userRole, $normalizedRoles);
        }

        if ($hasRequiredRole) {
            return $next($request);
        }

        // If user doesn't have required role, redirect or abort
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Required roles: '.implode(', ', $roles),
            ], 403);
        }

        // Redirect based on user's actual role
        return $this->redirectBasedOnRole($user);
    }

    /**
     * Normalize role names to handle inconsistent naming conventions
     */
    private function normalizeRoleName($role)
    {
        $roleMap = [
            'super_admin' => RoleEnum::SUPER_ADMIN->value,
            'super-admin' => RoleEnum::SUPER_ADMIN->value,
            'cs' => RoleEnum::CUSTOMER_SERVICE->value,
            'customer-service' => RoleEnum::CUSTOMER_SERVICE->value,
            'customer_service' => RoleEnum::CUSTOMER_SERVICE->value,
            'warehouse' => RoleEnum::WAREHOUSE->value,
            'gudang' => RoleEnum::WAREHOUSE->value,
            'supervisor' => RoleEnum::SUPERVISOR->value,
            'courier' => RoleEnum::COURIER->value,
            'kurir' => RoleEnum::COURIER->value,
        ];

        return $roleMap[$role] ?? $role;
    }

    /**
     * Redirect user based on their role
     */
    private function redirectBasedOnRole($user)
    {
        // Get first role from Spatie or fallback to legacy role field
        $userRole = null;

        if ($user->getRoleNames()->isNotEmpty()) {
            $userRole = $user->getRoleNames()->first();
        } elseif (isset($user->role)) {
            $userRole = $this->normalizeRoleName($user->role);
        }

        switch ($userRole) {
            case RoleEnum::SUPER_ADMIN->value:
                return redirect()->route('admin.dashboard');
            case RoleEnum::WAREHOUSE->value:
                return redirect()->route('admin.warehouse.dashboard');
            case RoleEnum::SUPERVISOR->value:
                return redirect()->route('admin.supervisor.warehouse-monitor');
            case RoleEnum::CUSTOMER_SERVICE->value:
                return redirect()->route('admin.dashboard');
            case RoleEnum::COURIER->value:
                return redirect()->route('admin.dashboard');
            default:
                return redirect()->route('login')->with('error', 'Unauthorized access.');
        }
    }
}
