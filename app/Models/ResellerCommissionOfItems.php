<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerCommissionOfItems extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'reseller_id',
        'invoice_id',
        'no_of_items',
        'total_amount',
        'commission_amount',
    ];
}
