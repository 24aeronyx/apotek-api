<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugBatch extends Model
{
    protected $guarded = ['id'];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function stockOpnames()
    {
        return $this->hasMany(StockOpname::class, 'drug_batch_id');
    }
}