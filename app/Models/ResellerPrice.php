<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'reseller_id',
        'company_id',
        'country_id',
        'tfn_commission_type',
        'extension_commission_type',
        'tfn_price',
        'extension_price',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function reseller(){
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
