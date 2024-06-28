<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RingMember extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'ring_id',
        'extension',
    ];

}
