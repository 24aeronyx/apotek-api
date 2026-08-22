<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class MedicalRecordController extends Controller
{
    // Buat Kunjungan Baru
    public function createVisit(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $visit = Visit::create([
            'visit_number' => 'VIS/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -4)),
            'patient_id'   => $validated['patient_id'],
            'status'       => 'WAITING',
            'created_by'   => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kunjungan pasien berhasil didaftarkan',
            'data'    => $visit->load('patient')
        ], 201);
    }

    // Input Hasil RME Pasien (SOAP & ICD-10)
    public function storeRme(Request $request, $visitId)
    {
        $validated = $request->validate([
            'subjective' => 'required|string', // Anamnesis / Keluhan
            'objective'  => 'nullable|string', // Pemeriksaan Fisik
            'assessment' => 'nullable|string', // Diagnosa Verbal
            'plan'       => 'nullable|string', // Rencana Penanganan / Resep
            'icd10_code' => 'nullable|string', // Kode ICD-10 untuk BPJS/SATUSEHAT
        ]);

        $visit = Visit::findOrFail($visitId);

        try {
            $rme = DB::transaction(function () use ($visit, $validated) {
                $record = MedicalRecord::updateOrCreate(
                    ['visit_id' => $visit->id],
                    [
                        'patient_id'     => $visit->patient_id,
                        'doctor_user_id' => auth()->id(),
                        'subjective'     => $validated['subjective'],
                        'objective'      => $validated['objective'] ?? null,
                        'assessment'     => $validated['assessment'] ?? null,
                        'plan'           => $validated['plan'] ?? null,
                        'icd10_code'     => $validated['icd10_code'] ?? null,
                    ]
                );

                // Update status kunjungan selesai diperiksa
                $visit->update(['status' => 'COMPLETED']);

                return $record;
            });

            return response()->json([
                'success' => true,
                'message' => 'Rekam Medis Elektronik (RME) berhasil disimpan',
                'data'    => $rme
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan RME: ' . $e->getMessage()
            ], 500);
        }
    }
}