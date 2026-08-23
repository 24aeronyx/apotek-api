<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Exception;

class DoctorController extends Controller
{
    // List Semua Dokter dari Tabel Users
    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage  = $request->query('per_page', 10);

        // Query pengguna yang memiliki role doctor/dokter/Doctor/Dokter
        $query = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['doctor', 'dokter', 'Doctor', 'Dokter']);
        })->with('roles')->orderBy('name', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $doctors = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $doctors
        ]);
    }

    // Tambah Dokter Baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone'    => 'nullable|string',
        ]);

        try {
            $doctor = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'username'  => strstr($validated['email'], '@', true),
                'password'  => Hash::make($validated['password']),
                'phone'     => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            // Assign Role 'doctor' di Spatie
            Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
            $doctor->assignRole('doctor');

            return response()->json([
                'success' => true,
                'message' => 'Dokter berhasil ditambahkan',
                'data'    => $doctor->load('roles')
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah dokter: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update Dokter
    public function update(Request $request, $id)
    {
        $doctor = User::findOrFail($id);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string',
        ]);

        try {
            $doctor->update([
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ]);

            if ($request->filled('password')) {
                $doctor->update(['password' => Hash::make($request->password)]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data dokter berhasil diperbarui',
                'data'    => $doctor->load('roles')
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Hapus Dokter
    public function destroy($id)
    {
        try {
            $doctor = User::findOrFail($id);
            $doctor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Dokter berhasil dihapus'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus dokter: ' . $e->getMessage()
            ], 500);
        }
    }
}