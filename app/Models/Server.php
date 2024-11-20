<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'ip',
        'port',
        'domain',
        'user_name',
        'secret',
        'ami_port',
        'status',
    ];

}
