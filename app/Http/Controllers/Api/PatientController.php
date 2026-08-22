<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    // List & Pencarian Pasien (By Name / NIK / No BPJS)
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

    // Registrasi Pasien Baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik'         => 'nullable|string|length:16|unique:patients,nik',
            'bpjs_number' => 'nullable|string|length:13|unique:patients,bpjs_number',
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