<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'ppn_type',
        'subtotal',
        'ppn_amount',
        'grand_total',
        'notes',
        'created_by',
    ];

    // Relasi ke Supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    // Relasi ke User / Petugas Input
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relasi ke Item Barang / Detail Obat
    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }
}