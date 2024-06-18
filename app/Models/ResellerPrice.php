<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'company_id',
        'country_id',
        'commission_type',
        'product',
        'price',
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
}
