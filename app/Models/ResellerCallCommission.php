<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerCallCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'reseller_id',
        'company_id',
        'country_id',
        'inbound_call_commission',
        'outbound_call_commission',
        'status',
    ];

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
    /**
     * Get the user that owns the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
