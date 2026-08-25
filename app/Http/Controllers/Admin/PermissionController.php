<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request)
    {
        // Check permission
        if (!auth()->user()->can('permissions.read')) {
            abort(403, 'Unauthorized: Permission management is restricted to super administrators only.');
        }

        $query = Permission::query();
        
        // Search functionality
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Category filter
        if ($request->category) {
            $query->where('name', 'like', $request->category . '.%');
        }
        
        $permissions = $query->orderBy('name')
                            ->paginate(20)
                            ->withQueryString();

        // Group permissions by category for display
        $groupedPermissions = Permission::all()
            ->groupBy(function($permission) {
                return explode('.', $permission->name)[0];
            });

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $permissions,
            'groupedPermissions' => $groupedPermissions,
            'filters' => $request->only('search', 'category'),
            'categories' => $this->getPermissionCategories()
        ]);
    }

    /**
     * Show the form for creating a new permission.
     */
    public function create()
    {
        // Check permission
        if (!auth()->user()->can('permissions.create')) {
            abort(403, 'Unauthorized: You do not have permission to create permissions.');
        }

        return Inertia::render('Admin/Permissions/Create', [
            'categories' => $this->getPermissionCategories()
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request)
    {
        // Check permission
        if (!auth()->user()->can('permissions.create')) {
            abort(403, 'Unauthorized: You do not have permission to create permissions.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'category' => ['required', 'string']
        ]);

        Permission::create([
            'name' => $request->name,
            'guard_name' => 'web'
        ]);

        return redirect()->route('admin.permissions.index')
                        ->with('success', 'Permission berhasil dibuat.');
    }

    /**
     * Show the form for editing a permission.
     */
    public function edit(Permission $permission)
    {
        // Check permission
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized: You do not have permission to edit permissions.');
        }

        return Inertia::render('Admin/Permissions/Edit', [
            'permission' => $permission,
            'categories' => $this->getPermissionCategories()
        ]);
    }

    /**
     * Update the permission.
     */
    public function update(Request $request, Permission $permission)
    {
        // Check permission
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized: You do not have permission to update permissions.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,' . $permission->id],
        ]);

        $permission->update([
            'name' => $request->name
        ]);

        return redirect()->route('admin.permissions.index')
                        ->with('success', 'Permission berhasil diupdate.');
    }

    /**
     * Remove the permission.
     */
    public function destroy(Permission $permission)
    {
        // Check permission
        if (!auth()->user()->can('users.delete')) {
            abort(403, 'Unauthorized: You do not have permission to delete permissions.');
        }

        // Check if permission is assigned to any roles
        if ($permission->roles()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete permission that is assigned to roles.']);
        }

        $permission->delete();

        return redirect()->route('admin.permissions.index')
                        ->with('success', 'Permission berhasil dihapus.');
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
            'audit' => 'Audit & Logs'
        ];
    }
}