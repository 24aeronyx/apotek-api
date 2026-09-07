<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $patients = Patient::when($search, function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('nik', 'like', "%{$search}%")
              ->orWhere('bpjs_number', 'like', "%{$search}%");
        })
        ->orderBy('name', 'asc')
        ->paginate(10); // Menyesuaikan dengan limit tampilan frontend

        return response()->json(['success' => true, 'data' => $patients]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik'         => 'nullable|string|digits:16|unique:patients,nik',
            'bpjs_number' => 'nullable|string|digits:13|unique:patients,bpjs_number',
            'name'        => 'required|string|max:150',
            'gender'      => 'required|in:L,P',
            'birth_date'  => 'required|date',
            'phone'       => 'nullable|string|max:20',
            'address'     => 'nullable|string',
        ]);

        $patient = Patient::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil disimpan',
            'data'    => $patient
        ], 201);
    }

    public function show($id)
    {
        $patient = Patient::find($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasien tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $patient
        ]);
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::find($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasien tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'nik' => [
                'nullable',
                'string',
                'digits:16',
                Rule::unique('patients', 'nik')->ignore($patient->id),
            ],
            'bpjs_number' => [
                'nullable',
                'string',
                'digits:13',
                Rule::unique('patients', 'bpjs_number')->ignore($patient->id),
            ],
            'name'       => 'required|string|max:150',
            'gender'     => 'required|in:L,P',
            'birth_date' => 'required|date',
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string',
        ]);

        $patient->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil diperbarui',
            'data'    => $patient
        ]);
    }

    public function destroy($id)
    {
        $patient = Patient::find($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasien tidak ditemukan'
            ], 404);
        }

        $patient->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil dihapus'
        ]);
    }
}