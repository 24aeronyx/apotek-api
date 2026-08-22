<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrugBatch;
use App\Models\StockMutation;
use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    // Get Riwayat Stok Opname
    public function index()
    {
        $opnames = StockOpname::with(['drugBatch.drug', 'user'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $opnames]);
    }

    // Eksekusi Adjustment Stok
    public function store(Request $request)
    {
        $request->validate([
            'drug_batch_id' => 'required|exists:drug_batches,id',
            'physical_qty'  => 'required|integer|min:0',
            'reason'        => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $batch = DrugBatch::lockForUpdate()->findOrFail($request->drug_batch_id);
            $systemQty = $batch->stock_qty;
            $physicalQty = $request->physical_qty;
            $difference = $physicalQty - $systemQty;

            if ($difference === 0) {
                return response()->json(['success' => false, 'message' => 'Stok fisik sama dengan sistem, tidak ada penyesuaian.'], 400);
            }

            // 1. Simpan Record Opname
            $opname = StockOpname::create([
                'drug_batch_id'  => $batch->id,
                'system_qty'     => $systemQty,
                'physical_qty'   => $physicalQty,
                'difference_qty' => $difference,
                'reason'         => $request->reason,
                'adjusted_by'    => $request->user()->id,
            ]);

            // 2. Update Stok Batch Fisik
            $batch->stock_qty = $physicalQty;
            $batch->save();

            // 3. Catat Riwayat Mutasi Stok
            StockMutation::create([
                'drug_batch_id'  => $batch->id,
                'type'           => $difference > 0 ? 'IN' : 'OUT',
                'qty'            => abs($difference),
                'reference_type' => 'STOCK_ADJUSTMENT',
                'notes'          => 'Stok Opname: ' . $request->reason,
                'created_by'     => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stok Opname & Adjustment berhasil disesuaikan',
                'data'    => $opname
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal penyesuaian stok: ' . $e->getMessage()], 500);
        }
    }
}