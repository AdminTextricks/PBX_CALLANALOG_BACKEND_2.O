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
        'tfn_number',
        'country_id',
        'total_amount',
        'commission_amount',
        'call_type',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'reseller_id', 'id');
    }
}
