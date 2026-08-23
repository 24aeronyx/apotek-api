<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PurchaseInvoiceController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login']);

// Protected (Membutuhkan Token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & User Profile
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Master Obat & Stok Batch (FEFO)
    Route::get('/drugs', [DrugController::class, 'index']);
    Route::post('/drugs', [DrugController::class, 'store']);
    Route::post('/drugs/{id}/batches', [DrugController::class, 'addBatch']);

    Route::get('/stock-opnames', [StockOpnameController::class, 'index']);
    Route::post('/stock-opnames', [StockOpnameController::class, 'store']);

    // Master Pasien
    Route::get('/patients', [PatientController::class, 'index']);
    Route::post('/patients', [PatientController::class, 'store']);

    // Kunjungan & Rekam Medis Elektronik (RME)
    Route::get('/visits', [MedicalRecordController::class, 'getVisits']);
    Route::post('/visits', [MedicalRecordController::class, 'createVisit']);
    Route::post('/visits/{id}/rme', [MedicalRecordController::class, 'storeRme']);

    // Penjualan Kasir / POS
    Route::get('/sales', [SalesController::class, 'index']);
    Route::get('/sales/{id}', [SalesController::class, 'show']);
    Route::post('/sales', [SalesController::class, 'store']);
    Route::post('/sales/{id}/return', [SalesController::class, 'returnSale']);

    Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index']);
    Route::get('/purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show']);
    Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);

    Route::apiResource('/doctors', DoctorController::class);

    Route::apiResource('/users', UserController::class);
    
});