<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\SalesController;
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

    // Master Pasien
    Route::get('/patients', [PatientController::class, 'index']);
    Route::post('/patients', [PatientController::class, 'store']);

    // Kunjungan & Rekam Medis Elektronik (RME)
    Route::post('/visits', [MedicalRecordController::class, 'createVisit']);
    Route::post('/visits/{visitId}/rme', [MedicalRecordController::class, 'storeRme']);

    // Penjualan Kasir / POS
    Route::get('/sales', [SalesController::class, 'index']);
    Route::post('/sales', [SalesController::class, 'store']);
});