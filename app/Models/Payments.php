<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'ip_address',
        'invoice_number',
        'order_id',
        'item_numbers',
        'payment_type',
        'payment_by',
        'payment_currency',
        'payment_price',
        'transaction_id',
        'stripe_charge_id',
        'status'
    ];
}
