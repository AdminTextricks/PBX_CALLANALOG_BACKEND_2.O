<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRegisteredServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'company_id',
        'domain',
        'sip_port',
    ];

    public function server(){
        return $this->belongsTo(Server::class);
    }
   
}
