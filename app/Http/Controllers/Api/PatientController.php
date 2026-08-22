<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

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
        ->paginate(15);

        return response()->json(['success' => true, 'data' => $patients]);
    }

    public function store(Request $request)
    {
        // Ubah 'length:16' menjadi 'digits:16' atau 'size:16'
        $validated = $request->validate([
            'nik'         => 'nullable|string|digits:16|unique:patients,nik',
            'bpjs_number' => 'nullable|string|digits:13|unique:patients,bpjs_number',
            'name'        => 'required|string|max:150',
            'gender'      => 'required|in:L,P',
            'birth_date'  => 'required|date',
            'phone'       => 'nullable|string',
            'address'     => 'nullable|string',
        ]);

        $patient = Patient::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil disimpan',
            'data'    => $patient
        ], 201);
    }
}