<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RechargeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'invoice_id', 'invoice_number', 'current_balance', 'added_balance', 'total_balance', 'currency', 'recharged_by',
    ];
}
