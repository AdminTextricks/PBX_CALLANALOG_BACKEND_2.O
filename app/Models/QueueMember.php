<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueMember extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        // Hook into the creating event to set the custom_increment column
        static::creating(function ($model) {
            $maxValue = static::max('uniqueid');
            $model->uniqueid = $maxValue ? $maxValue + 1 : 1;
        });
    }
    protected $fillable = [
        'id',
        'uniqueid',
        'queue_id',
        'queue_name',
        'membername',
        'interface',
        'penalty',
        'paused',
        'wrapuptime',
        'status',
    ];
}
