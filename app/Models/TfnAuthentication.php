<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TfnAuthentication extends Model
{
    use HasFactory;

    protected $fillable = [
        'tfn_id',
        'authentication_type',
        'auth_digit',
    ];


    public function tfn()
    {
        return $this->belongsTo(Tfn::class, 'tfn_id', 'id');
    }
}
