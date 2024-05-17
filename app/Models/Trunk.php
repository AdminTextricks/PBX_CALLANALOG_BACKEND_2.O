<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trunk extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'trunk_type',
        'trunk_name',
        'trunk_prefix',
        'tech',
        'trunk_ip',
        'remove_prefix',
        'failover_trunk',
        'max_use',
        'if_max_use',
        'trunk_username',
        'trunk_password',
        'status'        
    ];
}
