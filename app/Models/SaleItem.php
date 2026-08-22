<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false;

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function drugBatch()
    {
        return $this->belongsTo(DrugBatch::class);
    }
}