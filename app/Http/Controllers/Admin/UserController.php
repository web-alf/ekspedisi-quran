<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Sertifikat;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        // Check permission using Spatie
        if (! auth()->user()->can('users.read')) {
            abort(403, 'Unauthorized: User management is restricted to super administrators only.');
        }
        $query = User::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        // Role filter
        if ($request->role) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $users = $query->with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate(PerPage::resolve($request))
            ->withQueryString();

        // Transform users to include proper role information
        $users->getCollection()->transform(function ($user) {
            $user->spatie_role = $user->roles->first()?->name;
            $user->role_display = $user->getRoleDisplayAttribute();

            return $user;
        });

        return Inertia::render('Admin/Users/Index', [
            'perPage' => PerPage::resolve($request),
            'perPageOptions' => PerPage::OPTIONS,
            'users' => $users,
            'filters' => $request->only('search', 'role', 'status'),
            'roles' => Role::all()->mapWithKeys(function ($role) {
                return [$role->name => $role->display_name ?? $role->name];
            }),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        // Check permission
        if (! auth()->user()->can('users.create')) {
            abort(403, 'Unauthorized: You do not have permission to create users.');
        }

        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::all()->mapWithKeys(function ($role) {
                return [$role->name => $role->display_name ?? $role->name];
            }),
            'managerList' => User::daftarManager(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        // Check permission
        if (! auth()->user()->can('users.create')) {
            abort(403, 'Unauthorized: You do not have permission to create users.');
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'exists:roles,name'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Atasan hanya masuk akal untuk akun lapangan (kurir). Manager tentu tidak
        // membawahi manager lain di sini, dan role lain tidak diatur lewat jalur
        // ini sama sekali — jadi penanda "di bawah siapa" cukup satu tingkat.
        $atasan = $request->integer('manager_id') ?: null;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        // Assign role using Spatie
        $user->assignRole($request->role);

        // Penugasan atasan dilakukan SETELAH role terpasang, supaya bisa
        // diperiksa: hanya kurir yang punya atasan.
        if ($atasan !== null) {
            $user->forceFill(['manager_id' => $this->atasanSah($atasan, $request->role)])->save();
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    /**
     * Show the form for editing the user.
     */
    public function edit(User $user)
    {
        // Check permission
        if (! auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized: You do not have permission to edit users.');
        }

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => Role::all()->mapWithKeys(function ($role) {
                return [$role->name => $role->display_name ?? $role->name];
            }),
            'managerList' => User::daftarManager(),
            'auth' => [
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                    'role' => auth()->user()->role,
                ],
            ],
        ]);
    }

    /**
     * Update the user.
     */
    public function update(Request $request, User $user)
    {
        // Check permission
        if (! auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized: You do not have permission to update users.');
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'exists:roles,name'],
            'is_active' => ['boolean'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Prevent admin from deactivating themselves
        if ($user->id === auth()->id() && $request->has('is_active') && ! $request->is_active) {
            return back()->withErrors(['error' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        $data = $request->only(['name', 'email', 'is_active']);

        // Only update password if provided
        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Update role using Spatie
        $user->syncRoles([$request->role]);

        // Penanda atasan ikut berubah bersama role: kurir boleh punya atasan,
        // role lain tidak. Termasuk saat role DIUBAH dari kurir ke yang lain —
        // tanpa baris ini, sisa `manager_id` lama akan menggantung dan pengguna
        // tetap masuk lingkup manager meski bukan kurir lagi.
        $user->forceFill([
            'manager_id' => $this->atasanSah($request->integer('manager_id') ?: null, $request->role),
        ])->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diupdate.');
    }

    /**
     * Atasan yang sah untuk sebuah role.
     *
     * Hanya kurir yang punya atasan: dialah yang muatannya masuk lingkup manager.
     * Menyimpan atasan pada role lain hanya akan menyesatkan — misalnya supervisor
     * yang tidak pernah muncul di daftar muatan mana pun. Role non-kurir selalu
     * dikembalikan sebagai null, sehingga memindahkan seseorang keluar dari kurir
     * sekaligus melepas keterkaitannya.
     */
    private function atasanSah(?int $managerId, string $role): ?int
    {
        if ($managerId === null || $role !== RoleEnum::COURIER->value) {
            return null;
        }

        return User::whereKey($managerId)->exists() ? $managerId : null;
    }

    /**
     * Remove the user.
     */
    public function destroy(User $user)
    {
        // Check permission
        if (! auth()->user()->can('users.delete')) {
            abort(403, 'Unauthorized: You do not have permission to delete users.');
        }
        // Prevent admin from deleting themselves
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        // Check if user has related data and build detailed message
        $relatedData = [];

        if ($count = $user->pengiriman()->count()) {
            $relatedData[] = "$count pengiriman";
        }

        if ($count = $user->donatur()->count()) {
            $relatedData[] = "$count donatur";
        }

        if ($count = $user->wakafBatches()->count()) {
            $relatedData[] = "$count wakaf batch";
        }

        if ($count = $user->dailyPackingTasks()->count()) {
            $relatedData[] = "$count tugas packing";
        }

        if ($count = $user->packingBoxesCreated()->count()) {
            $relatedData[] = "$count kerdus";
        }

        // Check for Sertifikat (certificates generated by user)
        $certificateCount = Sertifikat::where('generated_by', $user->id)->count();
        if ($certificateCount > 0) {
            $relatedData[] = "$certificateCount sertifikat";
        }

        if (! empty($relatedData)) {
            $dataList = implode(', ', $relatedData);

            return back()->withErrors([
                'error' => "User tidak dapat dihapus karena masih memiliki data terkait: {$dataList}. ".
                          'Silakan hapus atau transfer data tersebut terlebih dahulu.',
            ]);
        }

        // Safe to delete - no related data
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * Toggle user status (for PATCH requests from table)
     */
    public function patch(Request $request, User $user)
    {
        // Check permission
        if (! auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized: You do not have permission to update users.');
        }
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        // Prevent admin from deactivating themselves
        if ($user->id === auth()->id() && ! $request->is_active) {
            return back()->withErrors(['error' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        $user->update([
            'is_active' => $request->is_active,
        ]);

        $status = $request->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "User berhasil {$status}.");
    }
}
