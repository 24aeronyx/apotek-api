<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Exception;

class UserController extends Controller
{
    public function accessOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'roles' => Role::with('permissions')->orderBy('name')->get(),
                'permissions' => Permission::orderBy('name')->get(),
            ],
        ]);
    }

    public function updateAccess(Request $request, $id)
    {
        $validated = $request->validate([
            'roles' => 'present|array',
            'roles.*' => 'string|exists:roles,name',
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $user = User::findOrFail($id);
            $user->syncRoles($validated['roles']);
            $user->syncPermissions($validated['permissions']);

            return response()->json([
                'success' => true,
                'message' => 'Hak akses pengguna berhasil diperbarui',
                'data' => $user->load(['roles', 'permissions']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui hak akses: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
            $role->syncPermissions($validated['permissions']);

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dibuat',
                'data' => $role->load('permissions'),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat role: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $id,
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diperbarui',
            'data' => $role->load('permissions'),
        ]);
    }

    public function destroyRole($id)
    {
        $role = Role::findOrFail($id);
        if (in_array(strtolower($role->name), ['admin', 'super admin'])) {
            return response()->json(['success' => false, 'message' => 'Role administrator tidak dapat dihapus'], 400);
        }

        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role berhasil dihapus']);
    }

    public function index(Request $request)
{
    $search = $request->query('search');
    $role   = $request->query('role');
    $perPage = $request->query('per_page', 10); // 10 data per halaman

    $query = User::with('roles')->orderBy('name', 'asc');

    if ($role) {
        $query->role($role);
    }

    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('email', 'ilike', "%{$search}%")
              ->orWhere('username', 'ilike', "%{$search}%");
        });
    }

    // Menggunakan paginate() menggantikan get()
    $users = $query->paginate($perPage);

    return response()->json([
        'success' => true,
        'data'    => $users
    ]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => User::with(['roles', 'permissions'])->findOrFail($id),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'username' => 'nullable|string|unique:users,username',
            'password' => 'required|string|min:6',
            'role'     => 'required|string',
            'phone'    => 'nullable|string',
        ]);

        try {
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'username'  => $validated['username'] ?? strstr($validated['email'], '@', true),
                'password'  => Hash::make($validated['password']),
                'phone'     => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            // Assign Role dari Spatie Permission (Otomatis buat role jika belum ada)
            Role::firstOrCreate(['name' => $validated['role'], 'guard_name' => 'web']);
            $user->assignRole($validated['role']);

            return response()->json([
                'success' => true,
                'message' => 'Pengguna berhasil ditambahkan',
                'data'    => $user->load('roles')
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $id,
            'role'      => 'required|string',
            'phone'     => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $user->update([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'] ?? null,
                'is_active' => $request->has('is_active') ? $request->is_active : $user->is_active,
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            // Sync Role Spatie Permission
            Role::firstOrCreate(['name' => $validated['role'], 'guard_name' => 'web']);
            $user->syncRoles([$validated['role']]);

            return response()->json([
                'success' => true,
                'message' => 'Data pengguna berhasil diperbarui',
                'data'    => $user->load('roles')
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menghapus akun Anda sendiri'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pengguna berhasil dihapus'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengguna: ' . $e->getMessage()
            ], 500);
        }
    }
}