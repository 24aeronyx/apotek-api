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
    // 1. Get List Kunjungan Pasien (Dengan Filter Tanggal & Pencarian Multi-Atribut)
    // 1. Get List Kunjungan Pasien (Dengan Filter Tanggal & Pencarian)
    public function getVisits(Request $request)
    {
        $date = $request->query('date');
        $search = $request->query('search');
        $patientId = $request->query('patient_id');

        $query = Visit::with(['patient', 'rme', 'creator'])
            ->orderBy('created_at', 'desc');

        // Jika filter spesifik per pasien (untuk histori RME samping)
        if ($patientId) {
            $query->where('patient_id', $patientId);
        } else {
            // Filter Tanggal (Hanya jika parameter date dikirim dan tidak kosong)
            if ($date) {
                $query->whereDate('created_at', $date);
            }

            // Filter Pencarian Multi-Atribut
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('visit_number', 'ilike', "%{$search}%")
                        ->orWhere('chief_complaint', 'ilike', "%{$search}%")
                        ->orWhereHas('patient', function ($p) use ($search) {
                            $p->where('name', 'ilike', "%{$search}%")
                                ->orWhere('nik', 'ilike', "%{$search}%")
                                ->orWhere('bpjs_number', 'ilike', "%{$search}%")
                                ->orWhere(DB::raw("CAST(id AS TEXT)"), 'ilike', "%{$search}%");
                        });
                });
            }
        }

        $visits = $query->get();

        return response()->json([
            'success' => true,
            'data' => $visits
        ]);
    }

    // 2. Buat Kunjungan Baru / Registrasi Antrean
    // 1. Buat Kunjungan Baru dengan Dokter Tujuan
    public function createVisit(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'nullable|exists:users,id',
            'chief_complaint' => 'nullable|string',
        ]);

        $visit = Visit::create([
            'visit_number' => 'VIS/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -4)),
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $validated['doctor_id'] ?? null,
            'chief_complaint' => $validated['chief_complaint'] ?? 'Pemeriksaan Kesehatan Umum',
            'status' => 'WAITING',
            'created_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kunjungan pasien berhasil didaftarkan',
            'data' => $visit->load(['patient', 'doctor'])
        ], 201);
    }

    public function storeRme(Request $request, $visitId)
    {
        $validated = $request->validate([
            'subjective' => 'required|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'icd10_code' => 'nullable|string',
            'prescriptions' => 'nullable|array',
            'prescriptions.*.drug_id' => 'required|exists:drugs,id',
            'prescriptions.*.qty' => 'required|numeric|min:1',
            'prescriptions.*.dose_instruction' => 'required|string',
        ]);

        $visit = Visit::findOrFail($visitId);

        try {
            $rme = DB::transaction(function () use ($visit, $validated) {
                // 1. Simpan / Update Rekam Medis
                $record = MedicalRecord::updateOrCreate(
                    ['visit_id' => $visit->id],
                    [
                        'patient_id' => $visit->patient_id,
                        'doctor_user_id' => auth()->id() ?? 1,
                        'subjective' => $validated['subjective'],
                        'objective' => $validated['objective'] ?? null,
                        'assessment' => $validated['assessment'] ?? null,
                        'plan' => $validated['plan'] ?? null,
                        'icd10_code' => $validated['icd10_code'] ?? null,
                    ]
                );

                // 2. Simpan E-Resep jika dikirim dari frontend
                if (!empty($validated['prescriptions'])) {
                    // Hapus resep lama jika ini adalah update
                    if (method_exists($record, 'prescriptions')) {
                        $record->prescriptions()->delete();
                        foreach ($validated['prescriptions'] as $pres) {
                            $record->prescriptions()->create([
                                'drug_id' => $pres['drug_id'],
                                'quantity' => $pres['qty'],
                                'dose_instruction' => $pres['dose_instruction'],
                            ]);
                        }
                    }
                }

                // 3. Update Status Kunjungan Selesai
                $visit->update(['status' => 'COMPLETED']);

                return $record;
            });

            return response()->json([
                'success' => true,
                'message' => 'Rekam Medis Elektronik (RME) berhasil disimpan',
                'data' => $rme
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan RME: ' . $e->getMessage()
            ], 500);
        }
    }
}