<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItems extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'invoice_id',
        'item_type',
        'item_number',
        'item_price',
        'item_category',
    ];
    public function user()
    {
        return $this->belongsToMany(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
