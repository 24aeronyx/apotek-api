<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Exception;

class DoctorController extends Controller
{
    // List Semua Dokter
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = User::whereIn('role', ['doctor', 'dokter'])
            ->orderBy('name', 'asc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $doctors = $query->get();

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
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => 'doctor',
                'phone'    => $validated['phone'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dokter berhasil ditambahkan',
                'data'    => $doctor
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah dokter: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update Data Dokter
    public function update(Request $request, $id)
    {
        $doctor = User::whereIn('role', ['doctor', 'dokter'])->findOrFail($id);

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

            // Jika ada perubahan password
            if ($request->filled('password')) {
                $doctor->update(['password' => Hash::make($request->password)]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data dokter berhasil diperbarui',
                'data'    => $doctor
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
            $doctor = User::whereIn('role', ['doctor', 'dokter'])->findOrFail($id);
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