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
        'email',
        'mobile',
        'billing_address',
        'country_id',
        'state_id',
        'city',
        'zip',
        'balance',
        'status'        
    ];

    
    public function blockNumbers()
    {
        return $this->hasMany(BlockNumber::class);
    }
}
