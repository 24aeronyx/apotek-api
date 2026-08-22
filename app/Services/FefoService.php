<?php

namespace App\Services;

use App\Models\DrugBatch;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Exception;

class FefoService
{
    public function deductStock(int $drugId, int $requestedQty, int $userId, int $saleId): array
    {
        return DB::transaction(function () use ($drugId, $requestedQty, $userId, $saleId) {
            $batches = DrugBatch::where('drug_id', $drugId)
                ->where('stock_qty', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalAvailable = $batches->sum('stock_qty');

            if ($totalAvailable < $requestedQty) {
                throw new Exception("Stok obat ID {$drugId} tidak mencukupi. Dibutuhkan: {$requestedQty}, Tersedia: {$totalAvailable}");
            }

            $remainingToDeduct = $requestedQty;
            $allocatedBatches = [];

            foreach ($batches as $batch) {
                if ($remainingToDeduct <= 0) break;

                $deductQty = min($batch->stock_qty, $remainingToDeduct);

                $batch->decrement('stock_qty', $deductQty);

                StockMutation::create([
                    'drug_batch_id'  => $batch->id,
                    'type'           => 'OUT',
                    'qty'            => $deductQty,
                    'reference_type' => 'SALE',
                    'reference_id'   => $saleId,
                    'notes'          => 'Transaksi Penjualan Kasir',
                    'created_by'     => $userId,
                ]);

                $allocatedBatches[] = [
                    'batch_id'  => $batch->id,
                    'qty_taken' => $deductQty,
                ];

                $remainingToDeduct -= $deductQty;
            }

            return $allocatedBatches;
        });
    }
}