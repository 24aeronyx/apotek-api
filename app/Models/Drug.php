<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi ke Kategori Obat
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relasi ke Batch Stok Obat
    public function batches()
    {
        return $this->hasMany(DrugBatch::class);
    }
}