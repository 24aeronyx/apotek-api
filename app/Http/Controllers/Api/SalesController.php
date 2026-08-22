<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\FefoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesController extends Controller
{
    protected $fefoService;

    public function __construct(FefoService $fefoService)
    {
        $this->fefoService = $fefoService;
    }

    // Process Transaksi Penjualan Kasir (POS)
    public function store(Request $request)
    {
        $request->validate([
            'patient_id'         => 'nullable|exists:patients,id',
            'visit_id'           => 'nullable|exists:visits,id',
            'payment_method'     => 'required|in:CASH,QRIS,DEBIT,TRANSFER,BPJS',
            'discount_amount'    => 'nullable|numeric|min:0',
            'items'              => 'required|array|min:1',
            'items.*.drug_id'    => 'required|exists:drugs,id',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $sale = DB::transaction(function () use ($request) {
                // 1. Buat Header Transaksi
                $sale = Sale::create([
                    'invoice_number'  => 'INV/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -5)),
                    'visit_id'        => $request->visit_id,
                    'patient_id'      => $request->patient_id,
                    'cashier_user_id' => auth()->id() ?? 1,
                    'payment_method'  => $request->payment_method,
                    'total_amount'    => 0,
                    'discount_amount' => $request->discount_amount ?? 0,
                    'final_amount'    => 0,
                ]);

                $totalAmount = 0;

                // 2. Iterasi Barang & Eksekusi Potong Stok FEFO
                foreach ($request->items as $item) {
                    $subtotal = $item['qty'] * $item['unit_price'];
                    $totalAmount += $subtotal;

                    $allocatedBatches = $this->fefoService->deductStock(
                        $item['drug_id'],
                        $item['qty'],
                        auth()->id() ?? 1,
                        $sale->id
                    );

                    foreach ($allocatedBatches as $batch) {
                        SaleItem::create([
                            'sale_id'       => $sale->id,
                            'drug_id'       => $item['drug_id'],
                            'drug_batch_id' => $batch['batch_id'],
                            'qty'           => $batch['qty_taken'],
                            'unit_price'    => $item['unit_price'],
                            'subtotal'      => $batch['qty_taken'] * $item['unit_price'],
                        ]);
                    }
                }

                // 3. Hitung Akhir Transaksi
                $discount = $request->discount_amount ?? 0;
                $finalAmount = max(0, $totalAmount - $discount);

                $sale->update([
                    'total_amount' => $totalAmount,
                    'final_amount' => $finalAmount,
                ]);

                return $sale->load(['saleItems.drug', 'saleItems.drugBatch']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi penjualan berhasil disimpan',
                'data'    => $sale,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses transaksi: ' . $e->getMessage(),
            ], 400);
        }
    }

    // Riwayat Penjualan
    public function index()
    {
        $sales = Sale::with(['cashier', 'patient'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $sales,
        ]);
    }
}