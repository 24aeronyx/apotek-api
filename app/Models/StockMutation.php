<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    protected $guarded = ['id'];

    // Matikan pengelolaan timestamps otomatis agar tidak mencari kolom updated_at
    public $timestamps = false;

    public function drugBatch()
    {
        return $this->belongsTo(DrugBatch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}