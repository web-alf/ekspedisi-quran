<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Setting;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $authData = null;

        if ($user) {
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            $authData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role, // Primary role (backward compatibility)
                'role_display' => $user->role_display, // Primary role display
                'roles' => $user->roles_array, // All roles (multi-role support)
                'roles_display' => $user->roles_display, // All role displays
                'is_active' => $user->is_active,
                'permissions' => $permissions,
            ];

        }
        
        // Get basic settings for layout (cached for performance)
        $basicSettings = cache()->remember('basic_settings_for_layout', 3600, function () {
            return Setting::whereIn('key', ['app_name', 'app_logo', 'admin_logo'])
                ->where('is_active', true)
                ->pluck('value', 'key');
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authData,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
            ],
            'settings' => $basicSettings,
        ];
    }
}
