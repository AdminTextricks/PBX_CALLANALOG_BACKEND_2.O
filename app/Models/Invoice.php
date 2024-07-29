<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'country_id',
        'state_id',
        'invoice_id',
        // 'payment_type',
        'invoice_currency',
        'invoice_subtotal_amount',
        'invoice_amount',
        'payment_status',
        'email_status',
    ];

    public function user()
    {
        return $this->belongsToMany(User::class);
    }

    public function Company()
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
        return $this->hasMany(ResellerPrice::class, 'company_id', 'company_id');
    }
}
