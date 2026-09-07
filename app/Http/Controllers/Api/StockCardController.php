<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\StockMutation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class StockCardController extends Controller
{
    public function pdf(Request $request)
    {
        $response = $this->index($request);
        abort_unless($response->getStatusCode() === 200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        abort_if(empty($payload['drug']), 422, 'Pilih obat untuk mencetak kartu stok.');

        return Pdf::loadView('reports.stock-card-pdf', ['payload' => $payload])->setPaper('a4', 'landscape')->download('kartu-stok-' . $payload['drug']['code'] . '.pdf');
    }

    public function index(Request $request)
    {
        $drugId = $request->query('drug_id');
        $search = trim((string) $request->query('search', ''));

        if (!$drugId) {
            $drugs = Drug::with('batches')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%")
                            ->orWhere('kfa_code', 'ilike', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->get()
                ->map(fn (Drug $drug) => [
                    'id' => $drug->id,
                    'code' => $drug->code,
                    'name' => $drug->name,
                    'unit' => $drug->unit,
                    'total_stock' => (int) $drug->batches->sum('stock_qty'),
                ]);

            return response()->json(['success' => true, 'data' => $drugs]);
        }

        $drug = Drug::with('batches')->findOrFail($drugId);
        $from = $request->query('from');
        $to = $request->query('to');
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $baseQuery = StockMutation::whereHas('drugBatch', fn ($query) => $query->where('drug_id', $drug->id));
        $openingBalance = (clone $baseQuery)
            ->when($fromDate, fn ($query) => $query->where('created_at', '<', $fromDate))
            ->get()
            ->sum(fn (StockMutation $mutation) => $this->signedQty($mutation));

        $mutations = (clone $baseQuery)
            ->with(['drugBatch', 'creator'])
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->where('created_at', '<=', $toDate))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $balance = $openingBalance;
        $rows = $mutations->map(function (StockMutation $mutation) use (&$balance) {
            $signedQty = $this->signedQty($mutation);
            $balance += $signedQty;

            return [
                'id' => $mutation->id,
                'created_at' => $mutation->created_at,
                'batch_number' => $mutation->drugBatch?->batch_number ?? '-',
                'expired_date' => $mutation->drugBatch?->expired_date,
                'type' => $mutation->type,
                'qty_in' => $signedQty > 0 ? abs($signedQty) : 0,
                'qty_out' => $signedQty < 0 ? abs($signedQty) : 0,
                'balance' => $balance,
                'reference_type' => $mutation->reference_type,
                'reference_id' => $mutation->reference_id,
                'notes' => $mutation->notes,
                'created_by' => $mutation->creator?->name ?? 'System',
            ];
        })->values();

        return response()->json([
            'success' => true,
            'drug' => [
                'id' => $drug->id,
                'code' => $drug->code,
                'name' => $drug->name,
                'unit' => $drug->unit,
                'current_stock' => (int) $drug->batches->sum('stock_qty'),
            ],
            'summary' => [
                'opening_balance' => $openingBalance,
                'total_in' => $rows->sum('qty_in'),
                'total_out' => $rows->sum('qty_out'),
                'closing_balance' => $rows->last()['balance'] ?? $openingBalance,
            ],
            'data' => $rows,
        ]);
    }

    private function signedQty(StockMutation $mutation): int
    {
        return in_array($mutation->type, ['IN', 'ADJUSTMENT'], true)
            ? abs((int) $mutation->qty)
            : -abs((int) $mutation->qty);
    }
}
