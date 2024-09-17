<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\DB;
use App\Notifications\ActivityNotification;

class NotificationController extends Controller
{
    
    public function getAllNotifications(Request $request)
    {
        $user = User::find(1);
        return $allNotifications = $user->notifications;

    }
}
