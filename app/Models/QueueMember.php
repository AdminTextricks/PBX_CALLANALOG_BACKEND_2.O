<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'uniqueid',
        'queue_id',
        'membername',
        'queue_name',
        'interface',
        'penalty',
        'paused',
        'wrapuptime',
        'status',
    ];
}
