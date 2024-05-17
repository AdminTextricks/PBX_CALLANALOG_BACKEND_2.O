<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
	
	protected $fillable = [
        'id',
        'client_name',
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
}
