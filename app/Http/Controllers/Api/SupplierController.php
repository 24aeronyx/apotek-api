<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Exception;

class SupplierController extends Controller
{
    // List Supplier (Digunakan untuk Master Supplier & Dropdown Faktur)
    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        $query = Supplier::orderBy('name', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        // Jika frontend meminta semua supplier untuk dropdown, kembalikan semua
        // tanpa memaksa filter is_active (untuk kompatibilitas dengan skema DB lama)
        if ($request->has('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->get() // Mengembalikan ARRAY MURNI
            ]);
        }

        // Jika tidak ada parameter ?all=true, kembalikan paginasi (untuk Master Supplier)
        $suppliers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $suppliers
        ]);
    }

    // Tambah Supplier Baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|unique:suppliers,code',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'npwp' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        try {
            // Auto generate kode supplier jika kosong
            if (empty($validated['code'])) {
                $count = Supplier::count() + 1;
                $validated['code'] = 'SUP-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            $supplier = Supplier::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Supplier berhasil ditambahkan',
                'data' => $supplier
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah supplier: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update Supplier
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'npwp' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        try {
            $supplier->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Data supplier berhasil diperbarui',
                'data' => $supplier
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Hapus Supplier
    public function destroy($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->delete();

            return response()->json([
                'success' => true,
                'message' => 'Supplier berhasil dihapus'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus supplier: ' . $e->getMessage()
            ], 500);
        }
    }
}