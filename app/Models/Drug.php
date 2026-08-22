<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(DrugCategory::class, 'category_id');
    }

    public function batches()
    {
        return $this->hasMany(DrugBatch::class);
    }
}