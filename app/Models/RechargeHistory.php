<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RechargeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'invoice_id',
        'invoice_number',
        'current_balance',
        'added_balance',
        'total_balance',
        'currency',
        'payment_type',
        'recharged_by',
    ];


    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function invoice_items()
    {
        return $this->hasMany(InvoiceItems::class, 'invoice_id', 'id');
    }

    public function countries()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function states()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function payments()
    {
        return $this->hasOne(Payments::class, 'invoice_id', 'id');
    }

    public function reseller_prices()
    {
        return $this->hasOne(ResellerPrice::class, 'company_id', 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
