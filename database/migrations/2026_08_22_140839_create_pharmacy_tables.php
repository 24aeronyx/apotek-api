<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Patient Master
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 16)->unique()->nullable();
            $table->string('bpjs_number', 13)->unique()->nullable();
            $table->string('satusehat_patient_id', 100)->nullable();
            $table->string('name', 150);
            $table->enum('gender', ['L', 'P']);
            $table->date('birth_date');
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        // 2. Drug Categories & Drugs Master
        Schema::create('drug_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        });

        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('drug_categories')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('kfa_code', 100)->nullable(); // Kemenkes Standard Code
            $table->string('unit', 50);
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Drug Batches (FEFO Ready)
        Schema::create('drug_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->string('batch_number', 100);
            $table->date('expired_date');
            $table->integer('stock_qty')->default(0);
            $table->timestamps();
        });

        // 4. Stock Mutations Log
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_batch_id')->constrained('drug_batches')->restrictOnDelete();
            $table->enum('type', ['IN', 'OUT', 'ADJUSTMENT']);
            $table->integer('qty');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. Visits & RME
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_number', 50)->unique();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->timestamp('visit_date')->useCurrent();
            $table->enum('status', ['WAITING', 'IN_EXAM', 'COMPLETED', 'CANCELLED'])->default('WAITING');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->string('icd10_code', 20)->nullable();
            $table->string('satusehat_encounter_id', 100)->nullable();
            $table->timestamps();
        });

        // 6. POS Sales & Sale Items
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->enum('payment_method', ['CASH', 'QRIS', 'DEBIT', 'TRANSFER', 'BPJS'])->default('CASH');
            $table->string('satusehat_medication_dispense_id', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->decimal('pay_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs');
            $table->foreignId('drug_batch_id')->constrained('drug_batches');
            $table->integer('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('stock_mutations');
        Schema::dropIfExists('drug_batches');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('drug_categories');
        Schema::dropIfExists('patients');
    }
};