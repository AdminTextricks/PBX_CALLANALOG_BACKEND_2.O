<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tfn extends Model
{
    use HasFactory;

    protected $table = 'tfns';
    protected $fillable = [
        'company_id', 'assign_by', 'plan_id', 'tfn_number', 'tfn_provider', 'tfn_group_id', 'country_id', 'tfn_type_id', 'tfn_type_number', 'activated', 'reserved', 'reserveddate', 'reservedexpirationdate', 'monthly_rate', 'connection_charge', 'selling_rate', 'aleg_retail_min_duration', 'aleg_billing_block', 'startingdate', 'expirationdate', 'status',
    ];

    public function users()
    {
        return $this->belongsTo(User::class);
    }

    public function countries()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
    public function Company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tfn_groups()
    {
        return $this->belongsTo(TfnGroups::class, 'tfn_group_id', 'id');
    }

    public function truks()
    {
        return $this->belongsTo(Trunk::class, 'tfn_provider', 'id');
    }
}
