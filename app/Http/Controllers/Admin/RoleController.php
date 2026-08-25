<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request)
    {
        // Check permission
        if (! auth()->user()->can('roles.read')) {
            abort(403, 'Unauthorized: Role management is restricted to super administrators only.');
        }

        $query = Role::with('permissions')
            ->withCount('users');

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('display_name', 'like', '%'.$request->search.'%');
            });
        }

        $roles = $query->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        // Check permission
        if (! auth()->user()->can('roles.create')) {
            abort(403, 'Unauthorized: You do not have permission to create roles.');
        }

        $permissions = Permission::all()->groupBy('category');

        return Inertia::render('Admin/Roles/Create', [
            'permissions' => $permissions,
            'categories' => $this->getPermissionCategories(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        // Check permission
        if (! auth()->user()->can('roles.create')) {
            abort(403, 'Unauthorized: You do not have permission to create roles.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => Str::kebab($request->name),
            'display_name' => $request->display_name,
            'guard_name' => 'web',
        ]);

        if ($request->permissions) {
            $role->givePermissionTo($request->permissions);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role berhasil dibuat.');
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role)
    {
        // Check permission
        if (! auth()->user()->can('roles.update')) {
            abort(403, 'Unauthorized: You do not have permission to edit roles.');
        }

        $role->load('permissions');

        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        // Determine if role is a system role
        $systemRoles = ['super-admin', 'customer-service', 'warehouse', 'supervisor', 'courier'];
        $isSystemRole = in_array($role->name, $systemRoles);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'users_count' => $role->users()->count(),
                'is_system_role' => $isSystemRole,
            ],
            'permissions' => $permissions,
            'categories' => $this->getPermissionCategories(),
        ]);
    }

    /**
     * Update the role.
     */
    public function update(Request $request, Role $role)
    {
        // Check permission
        if (! auth()->user()->can('roles.update')) {
            abort(403, 'Unauthorized: You do not have permission to update roles.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'display_name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->update([
            'name' => Str::kebab($request->name),
            'display_name' => $request->display_name,
        ]);

        // Sync permissions
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role berhasil diupdate.');
    }

    /**
     * Remove the role.
     */
    public function destroy(Role $role)
    {
        // Check permission
        if (! auth()->user()->can('roles.delete')) {
            abort(403, 'Unauthorized: You do not have permission to delete roles.');
        }

        // Prevent deletion of system roles
        $systemRoles = ['super-admin', 'customer-service', 'warehouse', 'supervisor', 'courier'];
        if (in_array($role->name, $systemRoles)) {
            return back()->withErrors([
                'error' => 'Role sistem tidak dapat dihapus. Role ini penting untuk fungsi aplikasi.',
            ]);
        }

        // Check if role has users
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return back()->withErrors([
                'error' => "Role tidak dapat dihapus karena masih digunakan oleh {$usersCount} user. Silakan hapus atau transfer user tersebut terlebih dahulu.",
            ]);
        }

        // Safe to delete
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    /**
     * Get permission categories with display names
     */
    private function getPermissionCategories(): array
    {
        return [
            'users' => 'User Management',
            'wakif' => 'Wakif Management',
            'shipments' => 'Shipment Management',
            'mushaf_requests' => 'Mushaf Requests',
            'qr' => 'QR Code Management',
            'certificates' => 'Certificate Management',
            'certificate_templates' => 'Certificate Templates',
            'dashboard' => 'Dashboard Access',
            'reports' => 'Reports & Analytics',
            'system' => 'System Administration',
            'inventory' => 'Inventory Management',
            'deliveries' => 'Delivery Operations',
            'customer' => 'Customer Operations',
            'audit' => 'Audit & Logs',
        ];
    }
}
