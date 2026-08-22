<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrugBatch;
use App\Models\StockMutation;
use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockOpnameController extends Controller
{
    // Get Daftar Audit Stok Bulan Ini (Semua Batch Obat + Status Opname)
    public function index(Request $request)
    {
        // Ambil parameter bulan (default: bulan ini YYYY-MM)
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        $batches = DrugBatch::with(['drug', 'stockOpnames' => function ($query) use ($month) {
            $query->where(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"), $month)
                  ->latest('id');
        }])
        ->where('stock_qty', '>=', 0)
        ->get()
        ->map(function ($batch) {
            // Ambil opname terakhir di bulan berjalan jika ada
            $latestOpname = $batch->stockOpnames->first();
            
            return [
                'batch_id'       => $batch->id,
                'drug_code'      => $batch->drug->code ?? '-',
                'drug_name'      => $batch->drug->name ?? '-',
                'unit'           => $batch->drug->unit ?? '-',
                'batch_number'   => $batch->batch_number,
                'expired_date'   => $batch->expired_date,
                'system_qty'     => $batch->stock_qty,
                'is_opnamed'     => $latestOpname ? true : false,
                'last_opname'    => $latestOpname ? [
                    'id'             => $latestOpname->id,
                    'physical_qty'   => $latestOpname->physical_qty,
                    'difference_qty' => $latestOpname->difference_qty,
                    'reason'         => $latestOpname->reason,
                    'date'           => $latestOpname->created_at,
                    'user_name'      => $latestOpname->user->name ?? 'Admin',
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'current_month' => $month,
            'data' => $batches
        ]);
    }

    // Eksekusi Adjustment / Opname
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

            // 1. Simpan Record Opname
            $opname = StockOpname::create([
                'drug_batch_id'  => $batch->id,
                'system_qty'     => $systemQty,
                'physical_qty'   => $physicalQty,
                'difference_qty' => $difference,
                'reason'         => $request->reason,
                'adjusted_by'    => $request->user()->id,
            ]);

            // 2. Update Stok Fisik Batch
            $batch->stock_qty = $physicalQty;
            $batch->save();

            // 3. Catat Mutasi Stok jika ada selisih
            if ($difference !== 0) {
                StockMutation::create([
                    'drug_batch_id'  => $batch->id,
                    'type'           => $difference > 0 ? 'IN' : 'OUT',
                    'qty'            => abs($difference),
                    'reference_type' => 'STOCK_ADJUSTMENT',
                    'notes'          => 'Opname Bulanan: ' . $request->reason,
                    'created_by'     => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stok Opname berhasil dicatat!',
                'data'    => $opname
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal opname: ' . $e->getMessage()], 500);
        }
    }
}