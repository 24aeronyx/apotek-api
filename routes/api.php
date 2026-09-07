<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PurchaseInvoiceController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\StockCardController;
use App\Http\Controllers\Api\ReportController;
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
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:view-reports');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->middleware('permission:view-reports');

    // Master Obat & Stok Batch (FEFO)
    Route::get('/drugs', [DrugController::class, 'index'])
        ->middleware('permission:view-drugs');
    Route::post('/drugs', [DrugController::class, 'store'])
        ->middleware('permission:manage-master-drugs');
    Route::put('/drugs/{drug}', [DrugController::class, 'update'])
        ->middleware('permission:manage-master-drugs');
    Route::patch('/drugs/{drug}', [DrugController::class, 'update'])
        ->middleware('permission:manage-master-drugs');

    Route::get('/stock-opnames', [StockOpnameController::class, 'index'])->middleware('permission:view-stock');
    Route::post('/stock-opnames', [StockOpnameController::class, 'store'])->middleware('permission:view-stock');
    Route::get('/stock-opnames/pdf', [StockOpnameController::class, 'pdf'])->middleware('permission:view-stock');
    Route::get('/stock-cards', [StockCardController::class, 'index'])->middleware('permission:view-stock');
    Route::get('/stock-cards/pdf', [StockCardController::class, 'pdf'])->middleware('permission:view-stock');

    // Master Pasien
    Route::apiResource('/patients', PatientController::class)
        ->middleware('permission:view-patients');

    // Kunjungan & Rekam Medis Elektronik (RME)
    Route::get('/visits', [MedicalRecordController::class, 'getVisits'])->middleware('permission:view-rme');
    Route::post('/visits', [MedicalRecordController::class, 'createVisit'])->middleware('permission:create-rme');
    Route::post('/visits/{id}/rme', [MedicalRecordController::class, 'storeRme'])->middleware('permission:create-rme');

    // Penjualan Kasir / POS
    Route::get('/sales', [SalesController::class, 'index'])->middleware('permission:process-pos-sale');
    Route::get('/sales/pending-prescriptions', [SalesController::class, 'pendingPrescriptions'])->middleware('permission:process-pos-sale');
    Route::get('/sales/{id}', [SalesController::class, 'show'])->middleware('permission:process-pos-sale');
    Route::post('/sales', [SalesController::class, 'store'])->middleware('permission:process-pos-sale');
    Route::post('/sales/{id}/return', [SalesController::class, 'returnSale'])->middleware('permission:process-pos-sale');

    Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index'])->middleware('permission:manage-stock-in');
    Route::get('/purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show'])->middleware('permission:manage-stock-in');
    Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store'])->middleware('permission:manage-stock-in');

    Route::apiResource('/suppliers', SupplierController::class);

    Route::apiResource('/doctors', DoctorController::class)
        ->middleware('permission:view-doctors');

    Route::middleware('permission:manage-users')->group(function () {
        Route::get('/users/access-options', [UserController::class, 'accessOptions']);
        Route::post('/users/roles', [UserController::class, 'storeRole']);
        Route::put('/users/roles/{id}', [UserController::class, 'updateRole']);
        Route::delete('/users/roles/{id}', [UserController::class, 'destroyRole']);
        Route::put('/users/{id}/access', [UserController::class, 'updateAccess']);
        Route::apiResource('/users', UserController::class);
    });
    
});