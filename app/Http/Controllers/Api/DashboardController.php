<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $selectedDate = Carbon::parse($date);

        $salesQuery = Sale::where('status', 'PAID')->whereDate('created_at', $selectedDate);
        $purchaseQuery = PurchaseInvoice::whereDate('invoice_date', $selectedDate);

        $drugs = Drug::with('batches')->orderBy('name')->get();
        $expiringBatches = $drugs->flatMap(fn ($drug) => $drug->batches
            ->where('stock_qty', '>', 0)
            ->map(fn ($batch) => [
                'id' => $batch->id,
                'drug_name' => $drug->name,
                'unit' => $drug->unit,
                'batch_number' => $batch->batch_number,
                'expired_date' => $batch->expired_date,
                'stock_qty' => $batch->stock_qty,
            ]))
            ->sortBy('expired_date')
            ->take(5)
            ->values();

        $sales = (clone $salesQuery)->latest('created_at')->limit(5)->get();
        $salesTotal = (clone $salesQuery)->sum('final_amount');
        $purchaseTotal = (clone $purchaseQuery)->sum('grand_total');

        return response()->json([
            'success' => true,
            'date' => $selectedDate->toDateString(),
            'financial' => [
                'sales_total' => (float) $salesTotal,
                'sales_count' => (clone $salesQuery)->count(),
                'purchase_total' => (float) $purchaseTotal,
                'purchase_count' => (clone $purchaseQuery)->count(),
                'indicative_net' => (float) ($salesTotal - $purchaseTotal),
            ],
            'inventory' => [
                'total_stock' => (int) $drugs->sum(fn ($drug) => $drug->batches->sum('stock_qty')),
                'product_count' => $drugs->count(),
                'low_stock_count' => $drugs->filter(fn ($drug) => $drug->batches->sum('stock_qty') <= max((int) $drug->min_stock, 10))->count(),
            ],
            'patients_count' => Patient::count(),
            'expiring_batches' => $expiringBatches,
            'recent_sales' => $sales,
        ]);
    }
}
