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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'reseller_id', 'id');
    }
}
