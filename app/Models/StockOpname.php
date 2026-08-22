<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false; // Hanya memakai created_at

    public function drugBatch()
    {
        return $this->belongsTo(DrugBatch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}