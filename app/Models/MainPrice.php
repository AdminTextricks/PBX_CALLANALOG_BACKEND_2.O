<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainPrice extends Model
{
    use HasFactory;
	
	protected $fillable = [
        'id',
        'reseller_id',
        'country_id',
        'price_for',
        'tfn_price',
        'extension_price',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'reseller_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
