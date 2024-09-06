<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tfn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tfns';
    protected $fillable = [
        'company_id',
        'assign_by',
        'plan_id',
        'tfn_number',
        'tfn_provider',
        'tfn_group_id',
        'country_id',
        'time_condition',
        'time_condition_id',
        'activated',
        'reserved',
        'reserveddate',
        'reservedexpirationdate',
        'monthly_rate',
        'connection_charge',
        'selling_rate',
        'aleg_retail_min_duration',
        'aleg_billing_block',
        'startingdate',
        'expirationdate',
        'call_screen_action',
        'tfn_auth',
        'status',
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

    public function main_plans()
    {
        return $this->belongsTo(MainPlan::class, 'plan_id', 'id');
    }

    public function trunks()
    {
        return $this->belongsTo(Trunk::class, 'tfn_provider', 'id');
    }

    public function tfn_destinations()
    {
        return $this->hasMany(TfnDestination::class, 'tfn_id', 'id');
    }
}
