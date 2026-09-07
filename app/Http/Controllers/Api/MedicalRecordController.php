<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class MedicalRecordController extends Controller
{
    /**
     * 1. Get List Kunjungan Pasien (Filter Tanggal, Status Assesmen, & Search)
     */
    public function getVisits(Request $request)
    {
        $date = $request->query('date');
        $search = $request->query('search');
        $patientId = $request->query('patient_id');
        $status = $request->query('status'); // 'ALL' | 'PENDING' | 'DONE'

        $query = Visit::with(['patient', 'rme.prescriptions.drug', 'creator'])
            ->orderBy('created_at', 'desc');

        // Filter Histori per Pasien (untuk side-panel EHR)
        if ($patientId) {
            $query->where('patient_id', $patientId);
        } else {
            // Filter Tanggal
            if ($date) {
                $query->whereDate('created_at', $date);
            }

            // Filter Status Assesmen Medis
            if ($status === 'PENDING') {
                $query->doesntHave('rme');
            } elseif ($status === 'DONE') {
                $query->has('rme');
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

        $visits = $request->has('page') ? $query->paginate(10) : $query->get();

        return response()->json([
            'success' => true,
            'data' => $visits
        ]);
    }

    /**
     * 2. Registrasi Kunjungan Pasien
     */
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
            'status' => 'REGISTERED',
            'created_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kunjungan pasien berhasil didaftarkan',
            'data' => $visit->load(['patient', 'doctor'])
        ], 201);
    }

    /**
     * 3. Simpan RME & E-Resep (Siap Diambil Kasir)
     */
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
                // 1. Simpan / Update Medical Record
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

                // 2. Simpan E-Resep ke Tabel `prescriptions`
                if (method_exists($record, 'prescriptions')) {
                    $record->prescriptions()->delete(); // Clear resep lama jika update

                    if (!empty($validated['prescriptions'])) {
                        foreach ($validated['prescriptions'] as $pres) {
                            $record->prescriptions()->create([
                                'drug_id' => $pres['drug_id'],
                                'quantity' => $pres['qty'],
                                'dose_instruction' => $pres['dose_instruction'],
                            ]);
                        }
                    }
                }

                // 3. Update Status Kunjungan menjadi Siap Pembayaran Kasir
                $visit->update(['status' => 'WAITING_PAYMENT']);

                return $record->load('prescriptions.drug');
            });

            return response()->json([
                'success' => true,
                'message' => 'Rekam medis & E-Resep berhasil disimpan. Tagihan siap diproses Kasir.',
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