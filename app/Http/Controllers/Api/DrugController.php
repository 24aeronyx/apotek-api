<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class DrugController extends Controller
{
    // 1. Ambil Semua Data Obat beserta Sisa Stok (FEFO)
    public function index()
    {
        $drugs = Drug::with([
            'category',
            'batches' => function ($q) {
                $q->where('stock_qty', '>', 0)->orderBy('expired_date', 'asc');
            }
        ])
            // ->where('is_active', true) <-- Hapus atau comment baris ini
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($drug) {
                $drug->total_stock = (int) $drug->batches->sum('stock_qty');
                return $drug;
            });

        return response()->json([
            'success' => true,
            'data' => $drugs
        ]);
    }
    // 2. Tambah Obat Baru + Batch Awal
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:drugs,code',
            'name' => 'required|string',
            'category_id' => 'nullable|exists:drug_categories,id',
            'kfa_code' => 'nullable|string', // Kesiapan SATUSEHAT
            'unit' => 'required|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'batch_number' => 'required|string',
            'expired_date' => 'required|date',
            'initial_stock' => 'required|integer|min:1',
        ]);

        try {
            $drug = DB::transaction(function () use ($validated) {
                // Buat Master Obat
                $drug = Drug::create([
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'category_id' => $validated['category_id'] ?? null,
                    'kfa_code' => $validated['kfa_code'] ?? null,
                    'unit' => $validated['unit'],
                    'purchase_price' => $validated['purchase_price'],
                    'selling_price' => $validated['selling_price'],
                ]);

                // Buat Batch Stok Pertama
                $batch = DrugBatch::create([
                    'drug_id' => $drug->id,
                    'batch_number' => $validated['batch_number'],
                    'expired_date' => $validated['expired_date'],
                    'stock_qty' => $validated['initial_stock'],
                ]);

                // Log Mutasi Stok
                StockMutation::create([
                    'drug_batch_id' => $batch->id,
                    'type' => 'IN',
                    'qty' => $validated['initial_stock'],
                    'reference_type' => 'INITIAL_STOCK',
                    'notes' => 'Stok awal pendaftaran obat',
                    'created_by' => auth()->id(),
                ]);

                return $drug->load('batches');
            });

            return response()->json([
                'success' => true,
                'message' => 'Obat & batch stok awal berhasil ditambahkan',
                'data' => $drug
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah obat: ' . $e->getMessage()
            ], 500);
        }
    }

    // 3. Tambah Batch Stok Baru ke Obat yang Sudah Ada (Restock)
    public function addBatch(Request $request, $drugId)
    {
        $validated = $request->validate([
            'batch_number' => 'required|string',
            'expired_date' => 'required|date',
            'stock_qty' => 'required|integer|min:1',
        ]);

        $drug = Drug::findOrFail($drugId);

        $batch = DB::transaction(function () use ($drug, $validated) {
            $batch = DrugBatch::create([
                'drug_id' => $drug->id,
                'batch_number' => $validated['batch_number'],
                'expired_date' => $validated['expired_date'],
                'stock_qty' => $validated['stock_qty'],
            ]);

            StockMutation::create([
                'drug_batch_id' => $batch->id,
                'type' => 'IN',
                'qty' => $validated['stock_qty'],
                'reference_type' => 'RESTOCK',
                'notes' => 'Penambahan stok/batch baru',
                'created_by' => auth()->id(),
            ]);

            return $batch;
        });

        return response()->json([
            'success' => true,
            'message' => 'Batch baru berhasil ditambahkan',
            'data' => $batch
        ], 201);
    }

    // 4. Update Data Obat & Status Active / Non-Active
    public function update(Request $request, $id)
    {
        $drug = Drug::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|unique:drugs,code,' . $drug->id,
            'name' => 'required|string',
            'category_id' => 'nullable|exists:drug_categories,id',
            'kfa_code' => 'nullable|string',
            'unit' => 'required|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        try {
            $drug->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? $drug->category_id,
                'kfa_code' => $validated['kfa_code'] ?? $drug->kfa_code,
                'unit' => $validated['unit'],
                'purchase_price' => $validated['purchase_price'],
                'selling_price' => $validated['selling_price'],
                'min_stock' => $validated['min_stock'] ?? $drug->min_stock,
                'is_active' => $validated['is_active'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data obat berhasil diperbarui',
                'data' => $drug
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data obat: ' . $e->getMessage()
            ], 500);
        }
    }
}