<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'subject',
        'message',
        'type',
        'created_by',
        'ip_address',
    ];
    public function notificationRecipients()
    {
        return $this->hasMany(NotificationRecipients::class);
    }
}
