<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerCommissionOfCalls extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'reseller_id',
        'tfn_id',
        'country_id',
        'total_amount',
        'commission_amount',
    ];
}
