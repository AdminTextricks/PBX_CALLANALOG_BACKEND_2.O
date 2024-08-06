<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'parent_id',
        'plan_id',
        'company_name',
        'account_code',
        'email',
        'mobile',
        'billing_address',
        'country_id',
        'state_id',
        'city',
        'zip',
        'balance',
        'inbound_permission',
        'status'
    ];


    public function blockNumbers()
    {
        return $this->hasMany(BlockNumber::class);
    }

    /* public function userDocuments()
    {
        return $this->hasMany(UserDocuments::class);
    } */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
    public function user_plan()
    {
        return $this->belongsTo(MainPlan::class, 'plan_id');
    }

    public function main_prices()
    {
        return $this->hasOne(MainPrice::class, 'reseller_id', 'parent_id');
    }
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'parent_id');
    }
}
