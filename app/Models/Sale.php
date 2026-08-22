<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false; // Karena di migration hanya menggunakan created_at

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}