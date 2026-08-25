<?php

namespace App\Services\Cache;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserCacheService extends BaseCacheService
{
    protected string $prefix = 'user';

    protected int $defaultTtl = 1800; // 30 minutes for user data

    protected array $tags = ['user', 'permissions'];

    /**
     * Get user permissions with caching
     */
    public function getUserPermissions(int $userId): array
    {
        return $this->remember("permissions_{$userId}", function () use ($userId) {
            $user = User::find($userId);
            if (! $user) {
                return [];
            }

            return $user->getAllPermissions()
                ->pluck('name')
                ->toArray();
        }, $this->defaultTtl, ['user', 'permissions', "user_{$userId}"]);
    }

    /**
     * Get user roles with caching
     */
    public function getUserRoles(int $userId): array
    {
        return $this->remember("roles_{$userId}", function () use ($userId) {
            $user = User::find($userId);
            if (! $user) {
                return [];
            }

            return $user->getRoleNames()->toArray();
        }, $this->defaultTtl, ['user', 'roles', "user_{$userId}"]);
    }

    /**
     * Get user dashboard data with caching
     */
    public function getUserDashboardData(int $userId): array
    {
        return $this->remember("dashboard_data_{$userId}", function () use ($userId) {
            $user = User::find($userId);
            if (! $user) {
                return [];
            }

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                ],
                'permissions' => $this->getUserPermissions($userId),
                'roles' => $this->getUserRoles($userId),
                'preferences' => $this->getUserPreferences($userId),
            ];
        }, 900, ['user', 'dashboard', "user_{$userId}"]); // 15 minutes
    }

    /**
     * Get user preferences with caching
     */
    public function getUserPreferences(int $userId): array
    {
        return $this->remember("preferences_{$userId}", function () {
            // For now, return default preferences
            // This can be extended when user preferences are implemented
            return [
                'theme' => 'light',
                'language' => 'id',
                'timezone' => 'Asia/Jakarta',
                'notifications' => [
                    'email' => true,
                    'push' => false,
                    'sms' => false,
                ],
                'dashboard' => [
                    'default_view' => 'overview',
                    'items_per_page' => 15,
                    'show_charts' => true,
                ],
            ];
        }, 3600, ['user', 'preferences', "user_{$userId}"]); // 1 hour
    }

    /**
     * Check if user has permission with caching
     */
    public function userHasPermission(int $userId, string $permission): bool
    {
        $permissions = $this->getUserPermissions($userId);

        return in_array($permission, $permissions);
    }

    /**
     * Check if user has any of the permissions
     */
    public function userHasAnyPermission(int $userId, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($userId);

        return ! empty(array_intersect($permissions, $userPermissions));
    }

    /**
     * Check if user has role with caching
     */
    public function userHasRole(int $userId, string $role): bool
    {
        $roles = $this->getUserRoles($userId);

        return in_array($role, $roles);
    }

    /**
     * Get all users with their basic info (cached)
     */
    public function getAllUsersBasic(): array
    {
        return $this->remember('all_users_basic', function () {
            return User::where('is_active', true)
                ->select('id', 'name', 'email', 'role', 'last_login_at')
                ->orderBy('name')
                ->get()
                ->toArray();
        }, 1800, ['user', 'list']); // 30 minutes
    }

    /**
     * Get users by role with caching
     */
    public function getUsersByRole(string $role): array
    {
        return $this->remember("users_by_role_{$role}", function () use ($role) {
            return User::where('is_active', true)
                ->role($role)
                ->select('id', 'name', 'email', 'last_login_at')
                ->orderBy('name')
                ->get()
                ->toArray();
        }, 1800, ['user', 'role', "role_{$role}"]); // 30 minutes
    }

    /**
     * Get user statistics with caching
     */
    public function getUserStats(): array
    {
        return $this->remember('user_stats', function () {
            return [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'users_by_role' => User::where('is_active', true)
                    ->selectRaw('role, COUNT(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->toArray(),
                'recent_logins' => User::whereNotNull('last_login_at')
                    ->where('last_login_at', '>=', now()->subDays(7))
                    ->count(),
            ];
        }, 900, ['user', 'stats']); // 15 minutes
    }

    /**
     * Get all available permissions with caching
     */
    public function getAllPermissions(): array
    {
        return $this->remember('all_permissions', function () {
            return Permission::orderBy('name')
                ->get(['id', 'name', 'guard_name'])
                ->groupBy(function ($permission) {
                    // Group by prefix (e.g., 'users', 'shipments', etc.)
                    $parts = explode('.', $permission->name);

                    return $parts[0] ?? 'general';
                })
                ->toArray();
        }, 3600, ['permissions', 'all']); // 1 hour
    }

    /**
     * Get all available roles with caching
     */
    public function getAllRoles(): array
    {
        return $this->remember('all_roles', function () {
            return Role::with('permissions:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'guard_name'])
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'guard_name' => $role->guard_name,
                        'permissions' => $role->permissions->pluck('name')->toArray(),
                    ];
                })
                ->toArray();
        }, 3600, ['roles', 'all']); // 1 hour
    }

    /**
     * Get user activity summary (for dashboard)
     */
    public function getUserActivitySummary(int $userId): array
    {
        return $this->remember("activity_summary_{$userId}", function () use ($userId) {
            // This would be expanded when activity tracking is implemented
            $user = User::find($userId);
            if (! $user) {
                return [];
            }

            return [
                'last_login' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
                'role' => $user->role,
                'permissions_count' => count($this->getUserPermissions($userId)),
                'account_status' => $user->is_active ? 'active' : 'inactive',
                'created_at' => $user->created_at->diffForHumans(),
            ];
        }, 900, ['user', 'activity', "user_{$userId}"]); // 15 minutes
    }

    /**
     * Get role-based navigation menu with caching
     */
    public function getRoleNavigation(string $role): array
    {
        return $this->remember("navigation_{$role}", function () use ($role) {
            // Define navigation based on role
            $navigation = match ($role) {
                'super-admin' => [
                    ['name' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '🏠'],
                    ['name' => 'Pengiriman', 'route' => 'admin.pengiriman.index', 'icon' => '📦'],
                    ['name' => 'Donatur', 'route' => 'admin.donatur.index', 'icon' => '👤'],
                    ['name' => 'Permintaan Mushaf', 'route' => 'admin.mushaf-requests.index', 'icon' => '📖'],
                    ['name' => 'Users', 'route' => 'admin.users.index', 'icon' => '👥'],
                    ['name' => 'Settings', 'route' => 'admin.settings.index', 'icon' => '⚙️'],
                ],
                'warehouse' => [
                    ['name' => 'Dashboard', 'route' => 'warehouse.dashboard', 'icon' => '🏠'],
                    ['name' => 'Packing', 'route' => 'warehouse.packing', 'icon' => '📦'],
                    ['name' => 'Scanner', 'route' => 'warehouse.scanner', 'icon' => '📱'],
                    ['name' => 'Performance', 'route' => 'warehouse.performance', 'icon' => '📊'],
                ],
                'customer-service' => [
                    ['name' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '🏠'],
                    ['name' => 'Permintaan Mushaf', 'route' => 'admin.mushaf-requests.index', 'icon' => '📖'],
                    ['name' => 'Donatur', 'route' => 'admin.donatur.index', 'icon' => '👤'],
                    ['name' => 'Tracking', 'route' => 'admin.tracking.index', 'icon' => '🔍'],
                ],
                'courier' => [
                    ['name' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '🏠'],
                    ['name' => 'Pengiriman', 'route' => 'admin.pengiriman.index', 'icon' => '🚚'],
                    ['name' => 'Tracking', 'route' => 'admin.tracking.index', 'icon' => '🔍'],
                ],
                default => [
                    ['name' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '🏠'],
                ]
            };

            return $navigation;
        }, 3600, ['navigation', "role_{$role}"]); // 1 hour
    }

    /**
     * Invalidate user cache when user data changes
     */
    public function invalidateUser(int $userId): bool
    {
        Log::info("Invalidating cache for user {$userId}");

        return $this->flushByTags(["user_{$userId}"]);
    }

    /**
     * Invalidate all user permissions cache
     */
    public function invalidatePermissions(): bool
    {
        Log::info('Invalidating permissions cache');

        return $this->flushByTags(['permissions']);
    }

    /**
     * Invalidate all user roles cache
     */
    public function invalidateRoles(): bool
    {
        Log::info('Invalidating roles cache');

        return $this->flushByTags(['roles']);
    }

    /**
     * Invalidate navigation cache for a specific role
     */
    public function invalidateRoleNavigation(string $role): bool
    {
        Log::info("Invalidating navigation cache for role {$role}");

        return $this->flushByTags(["role_{$role}"]);
    }

    /**
     * Warm user cache
     */
    public function warm(): bool
    {
        try {
            Log::info('Warming user cache...');

            // Warm permissions and roles
            $this->getAllPermissions();
            $this->getAllRoles();

            // Warm user statistics
            $this->getUserStats();

            // Warm basic users list
            $this->getAllUsersBasic();

            // Warm navigation for common roles
            $commonRoles = ['super-admin', 'warehouse', 'customer-service', 'courier'];
            foreach ($commonRoles as $role) {
                $this->getRoleNavigation($role);
                $this->getUsersByRole($role);
            }

            // Warm recent active users (last 10)
            $recentUsers = User::where('is_active', true)
                ->whereNotNull('last_login_at')
                ->orderBy('last_login_at', 'desc')
                ->limit(10)
                ->pluck('id');

            foreach ($recentUsers as $userId) {
                $this->getUserPermissions($userId);
                $this->getUserRoles($userId);
                $this->getUserActivitySummary($userId);
            }

            Log::info('User cache warmed successfully');

            return true;
        } catch (\Exception $e) {
            Log::error('User cache warming failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get detailed user cache statistics
     */
    public function getDetailedStats(): array
    {
        $baseStats = $this->getStats();

        try {
            $userStats = $this->getUserStats();

            return array_merge($baseStats, [
                'cached_users' => $userStats['total_users'],
                'active_users' => $userStats['active_users'],
                'users_by_role' => $userStats['users_by_role'],
                'permissions_cached' => count($this->getAllPermissions()),
                'roles_cached' => count($this->getAllRoles()),
            ]);
        } catch (\Exception $e) {
            return array_merge($baseStats, [
                'error' => 'Could not get detailed stats: '.$e->getMessage(),
            ]);
        }
    }
}
