<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainPrice extends Model
{
    use HasFactory;
	
	protected $fillable = [
        'id',
        'user_id',
        'country_id',
        'user_type',
        'product',
        'price',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
