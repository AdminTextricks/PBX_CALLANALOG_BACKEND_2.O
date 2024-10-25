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
        'payment_type',
        'payment_by',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
