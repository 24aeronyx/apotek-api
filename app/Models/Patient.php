<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi ke Semua Kunjungan Pasien
    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    // Relasi ke Semua Histori Rekam Medis Pasien
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }
}