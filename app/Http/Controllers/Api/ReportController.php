<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use App\Models\Visit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function pdf(Request $request)
    {
        $type = $request->query('type', 'sales');
        $from = Carbon::parse($request->query('from', Carbon::today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', Carbon::today()->toDateString()))->endOfDay();

        if ($type === 'purchases') {
            $rows = PurchaseInvoice::with(['supplier', 'creator'])->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->latest('invoice_date')->latest('id')->get();
            $summary = ['document_count' => $rows->count(), 'total_amount' => (float) $rows->sum('grand_total')];
        } elseif ($type === 'visits') {
            $rows = Visit::with(['patient', 'doctor', 'rme.doctor'])->whereBetween('created_at', [$from, $to])->latest('created_at')->get();
            $summary = ['document_count' => $rows->count(), 'completed_count' => $rows->where('status', 'COMPLETED')->count(), 'waiting_count' => $rows->whereIn('status', ['REGISTERED', 'WAITING', 'IN_EXAM', 'WAITING_PAYMENT'])->count()];
        } else {
            $rows = Sale::with(['cashier', 'patient'])->whereBetween('created_at', [$from, $to])->latest('created_at')->get();
            $paidRows = $rows->where('status', 'PAID');
            $summary = ['document_count' => $rows->count(), 'paid_count' => $paidRows->count(), 'unpaid_count' => $rows->where('status', 'UNPAID')->count(), 'returned_count' => $rows->where('status', 'RETURNED')->count(), 'total_amount' => (float) $paidRows->sum('final_amount')];
        }

        $label = ['sales' => 'Penjualan', 'purchases' => 'Pembelian', 'visits' => 'Kunjungan'][$type] ?? 'Laporan';
        return Pdf::loadView('reports.pdf', compact('type', 'label', 'from', 'to', 'rows', 'summary'))->setPaper('a4', 'landscape')->download('laporan-' . $type . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf');
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'sales');
        $from = Carbon::parse($request->query('from', Carbon::today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', Carbon::today()->toDateString()))->endOfDay();

        if ($type === 'purchases') {
            $invoices = PurchaseInvoice::with(['supplier', 'creator'])
                ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
                ->latest('invoice_date')
                ->latest('id')
                ->get();

            return response()->json([
                'success' => true,
                'type' => 'purchases',
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'summary' => [
                    'document_count' => $invoices->count(),
                    'total_amount' => (float) $invoices->sum('grand_total'),
                ],
                'data' => $invoices,
            ]);
        }

        if ($type === 'visits') {
            $visits = Visit::with(['patient', 'doctor', 'rme.doctor'])
                ->whereBetween('created_at', [$from, $to])
                ->latest('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'type' => 'visits',
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'summary' => [
                    'document_count' => $visits->count(),
                    'completed_count' => $visits->where('status', 'COMPLETED')->count(),
                    'waiting_count' => $visits->whereIn('status', ['REGISTERED', 'WAITING', 'IN_EXAM', 'WAITING_PAYMENT'])->count(),
                ],
                'data' => $visits,
            ]);
        }

        $sales = Sale::with(['cashier', 'patient'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->get();
        $paidSales = $sales->where('status', 'PAID');

        return response()->json([
            'success' => true,
            'type' => 'sales',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'document_count' => $sales->count(),
                'paid_count' => $paidSales->count(),
                'unpaid_count' => $sales->where('status', 'UNPAID')->count(),
                'returned_count' => $sales->where('status', 'RETURNED')->count(),
                'total_amount' => (float) $paidSales->sum('final_amount'),
            ],
            'data' => $sales,
        ]);
    }
}
