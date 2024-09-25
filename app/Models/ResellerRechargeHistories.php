<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerRechargeHistories extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'old_balance',
        'added_balance',
        'total_balance',
        'currency',
        'payment_type',
        'transaction_id',
        'stripe_charge_id',
        'recharged_by',
        'status'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
