<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'country_id',
        'commission_type',
        'product',
        'price',
        'status',
    ];
}
