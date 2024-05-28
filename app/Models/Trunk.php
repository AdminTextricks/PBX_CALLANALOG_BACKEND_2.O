<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trunk extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'type',
        'name',
        'prefix',
        'tech',
		'is_register',
        'ip',
        'remove_prefix',
        'failover',
        'max_use',
        'if_max_use',
        'username',
        'password',
        'status'
    ];

    public function outboundcallrates()
    {
        return $this->hasMany(OutboundCallRate::class);
    }
}
