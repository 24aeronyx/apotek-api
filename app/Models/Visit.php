<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi ke Pasien
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // Relasi ke Hasil Rekam Medis (RME / SOAP)
    public function rme()
    {
        return $this->hasOne(MedicalRecord::class, 'visit_id');
    }

    // Relasi ke Petugas/User Pembuat Registrasi
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicalRecord()
    {
        // Sesuaikan foreign key jika di tabel medical_records nama kolomnya visit_id
        return $this->hasOne(MedicalRecord::class, 'visit_id');
    }
}