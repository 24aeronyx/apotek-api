<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_batch_id')->constrained('drug_batches')->onDelete('cascade');
            $table->integer('system_qty'); // Stok di sistem sebelum penyesuaian
            $table->integer('physical_qty'); // Hasil hitung fisik di lapangan
            $table->integer('difference_qty'); // Selisih (physical - system)
            $table->string('reason')->nullable(); // Alasan (Obat Rusak/Hilang/Salah Catat)
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};