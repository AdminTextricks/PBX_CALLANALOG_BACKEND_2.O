<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerPaymentHistories extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'currency',
        'amount',
        'item_numbers',
        'status',
    ];
}
