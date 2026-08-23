<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi ke Model Visit
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    // Relasi ke Model Patient
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }
}