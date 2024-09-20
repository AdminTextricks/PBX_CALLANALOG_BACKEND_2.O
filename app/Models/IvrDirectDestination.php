<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrDirectDestination extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'ivr_id',
        'authentication',
        'authentication_type',
        'authentication_digit',
        'destination_type_id',
        'destination_id',        
    ];
}
