<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugCategory extends Model
{
    protected $guarded = ['id'];
    
    // Matikan pencarian kolom created_at & updated_at
    public $timestamps = false;

    public function drugs()
    {
        return $this->hasMany(Drug::class, 'category_id');
    }
}