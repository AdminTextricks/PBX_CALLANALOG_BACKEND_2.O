<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class NotificationRecipients extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'notification_id',
        'user_id',
        'user_type',
        'is_read',
        'read_at',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}
