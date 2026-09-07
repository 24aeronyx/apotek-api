<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrugBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMutation;
use App\Models\Visit;
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

    /**
     * 1. Get E-Resep Pasien yang Menunggu Pembayaran (Untuk POS Kasir)
     */
    public function pendingPrescriptions()
    {
        try {
            $pendingVisits = Visit::with([
                'patient',
                'rme.prescriptions.drug'
            ])
                // Hanya ambil visit yang memiliki resep di medical_records
                ->whereHas('rme.prescriptions')
                // RME yang selesai diperiksa menunggu pembayaran di POS.
                ->where('status', 'WAITING_PAYMENT')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pendingVisits
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error Pending Prescriptions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. Process Transaksi Penjualan Kasir (POS)
     */
    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'nullable|exists:patients,id',
            'visit_id' => 'nullable|exists:visits,id',
            'payment_method' => 'required|in:CASH,QRIS,DEBIT,TRANSFER,BPJS',
            'discount_amount' => 'nullable|numeric|min:0',
            'pay_amount' => 'nullable|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.drug_id' => 'required|exists:drugs,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $sale = DB::transaction(function () use ($request) {
                // 1. Buat Header Transaksi
                $sale = Sale::create([
                    'invoice_number' => 'INV/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -5)),
                    'visit_id' => $request->visit_id,
                    'patient_id' => $request->patient_id,
                    'cashier_user_id' => auth()->id() ?? 1,
                    'payment_method' => $request->payment_method,
                    'total_amount' => 0,
                    'discount_amount' => $request->discount_amount ?? 0,
                    'final_amount' => 0,
                    'pay_amount' => $request->pay_amount ?? 0,
                    'change_amount' => $request->change_amount ?? 0,
                    'status' => 'PAID',
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
                            'sale_id' => $sale->id,
                            'drug_id' => $item['drug_id'],
                            'drug_batch_id' => $batch['batch_id'],
                            'qty' => $batch['qty_taken'],
                            'unit_price' => $item['unit_price'],
                            'subtotal' => $batch['qty_taken'] * $item['unit_price'],
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

                // 4. Update Status Kunjungan jika Transaksi Berasal dari RME / Poliklinik
                if ($request->visit_id) {
                    Visit::where('id', $request->visit_id)->update(['status' => 'COMPLETED']);
                }

                return $sale->load(['saleItems.drug', 'saleItems.drugBatch']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi penjualan berhasil disimpan',
                'data' => $sale,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses transaksi: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 3. Riwayat Penjualan
     */
    public function index()
    {
        $sales = Sale::with(['cashier', 'patient', 'saleItems.drug'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    /**
     * 4. Detail Penjualan / Cetak Ulang Struk
     */
    public function show($id)
    {
        $sale = Sale::with(['cashier', 'patient', 'saleItems.drug', 'saleItems.drugBatch'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    /**
     * 5. Fitur Retur Transaksi Penjualan (Kembalikan Stok ke Batch Asal)
     */
    public function returnSale(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $sale = Sale::with('saleItems')->findOrFail($id);

            if (isset($sale->status) && $sale->status === 'RETURNED') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi ini sudah pernah diretur sebelumnya!',
                ], 400);
            }

            // Kembalikan stok obat ke batch asal
            foreach ($sale->saleItems as $item) {
                if ($item->drug_batch_id) {
                    $batch = DrugBatch::find($item->drug_batch_id);
                    if ($batch) {
                        $batch->stock_qty += $item->qty;
                        $batch->save();

                        // Catat Riwayat Mutasi Stok Retur
                        StockMutation::create([
                            'drug_batch_id' => $batch->id,
                            'type' => 'IN',
                            'qty' => $item->qty,
                            'reference_type' => 'SALE_RETURN',
                            'notes' => 'Retur Transaksi: ' . $sale->invoice_number . ' (' . $request->reason . ')',
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }
                }
            }

            // Update Status Transaksi Penjualan & Status Visit jika ada
            if (isset($sale->status)) {
                $sale->status = 'RETURNED';
            }
            $sale->notes = 'DIRETUR: ' . $request->reason;
            $sale->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retur transaksi berhasil diselesaikan & stok obat telah dikembalikan!',
                'data' => $sale,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses retur: ' . $e->getMessage(),
            ], 500);
        }
    }
}