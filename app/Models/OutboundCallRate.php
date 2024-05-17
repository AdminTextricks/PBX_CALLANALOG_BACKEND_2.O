<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundCallRate extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'tariff_id',
        'trunk_id',
        'country_prefix',
        'selling_rate',
        'init_block',
        'billing_block',
        'start_date',
        'stop_date',
        'status'        
    ];
}
