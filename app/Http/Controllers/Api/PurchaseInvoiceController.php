<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrugBatch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends Controller
{
   public function index()
{
    $invoices = PurchaseInvoice::with(['supplier', 'items.drug', 'items.batch'])
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'data'    => $invoices
    ]);
    }

    public function show($id)
    {
        $invoice = PurchaseInvoice::with(['supplier', 'items.drug', 'items.batch', 'creator'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string',
            'invoice_date'   => 'required|date',
            'ppn_type'       => 'required|in:INCLUDE,EXCLUDE,NONE',
            'items'          => 'required|array|min:1',
            'items.*.drug_id'        => 'required|exists:drugs,id',
            'items.*.batch_number'   => 'required|string',
            'items.*.expired_date'   => 'required|date',
            'items.*.qty'            => 'required|integer|min:1',
            'items.*.purchase_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $rawSubtotal = 0;
            foreach ($request->items as $item) {
                $rawSubtotal += $item['qty'] * $item['purchase_price'];
            }

            // Kalkulasi PPN 11%
            $ppnAmount = 0;
            $subtotal = $rawSubtotal;
            $grandTotal = $rawSubtotal;

            if ($request->ppn_type === 'EXCLUDE') {
                $ppnAmount = $rawSubtotal * 0.11;
                $grandTotal = $rawSubtotal + $ppnAmount;
            } elseif ($request->ppn_type === 'INCLUDE') {
                $subtotal = $rawSubtotal / 1.11;
                $ppnAmount = $rawSubtotal - $subtotal;
                $grandTotal = $rawSubtotal;
            }

            $invoice = PurchaseInvoice::create([
                'supplier_id'    => $request->supplier_id,
                'invoice_number' => $request->invoice_number,
                'invoice_date'   => $request->invoice_date,
                'ppn_type'       => $request->ppn_type,
                'subtotal'       => $subtotal,
                'ppn_amount'     => $ppnAmount,
                'grand_total'    => $grandTotal,
                'notes'          => $request->notes,
                'created_by'     => $request->user()->id,
            ]);

            foreach ($request->items as $itemData) {
                // 1. Buat Batch Stok Baru
                $batch = DrugBatch::create([
                    'drug_id'      => $itemData['drug_id'],
                    'batch_number' => $itemData['batch_number'],
                    'expired_date' => $itemData['expired_date'],
                    'stock_qty'    => $itemData['qty'],
                ]);

                // 2. Simpan Item Faktur
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'drug_id'             => $itemData['drug_id'],
                    'drug_batch_id'       => $batch->id,
                    'qty'                 => $itemData['qty'],
                    'purchase_price'      => $itemData['purchase_price'],
                    'subtotal'            => $itemData['qty'] * $itemData['purchase_price'],
                ]);

                // 3. Catat Mutasi Stok Masuk
                StockMutation::create([
                    'drug_batch_id'  => $batch->id,
                    'type'           => 'IN',
                    'qty'            => $itemData['qty'],
                    'reference_type' => 'PURCHASE_INVOICE',
                    'notes'          => 'Faktur Masuk No: ' . $request->invoice_number,
                    'created_by'     => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Faktur Pembelian berhasil disimpan', 'data' => $invoice], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}