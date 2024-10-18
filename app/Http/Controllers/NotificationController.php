<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\NotificationRecipients;
use Validator;
use Carbon\Carbon;

class NotificationController extends Controller
{
    
    public function getAllNotifications(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $params = $request->params ?? "";
        $user_type = $user->roles->first()->slug;
        if (in_array($user_type, array('super-admin', 'support','noc'))) {            
            if ($params != "") {
                $data = NotificationRecipients::with('notification:id,subject,message,type,created_by,ip_address,created_at')
                    ->where('user_type', $user_type)
                    ->where(function($query) use($params) {
                        $query->orWhereHas('notification', function ($query) use ($params) {
                            $query->where('subject', 'LIKE', "%$params%");
                        })
                        ->orWhereHas('notification', function ($query) use ($params) {
                            $query->where('message', 'LIKE', "%$params%");
                        });
                    })
                    ->orderBy('id', 'DESC')						
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $data = NotificationRecipients::with('notification:id,subject,message,type,created_by,ip_address,created_at')
                ->where('user_type', $user_type)
                    ->select()->orderBy('id', 'DESC')->paginate(
                        $perPage = $perPageNo,
                        $columns = ['*'],
                        $pageName = 'page'
                    );
            }            
        } else {            
            if ($params != "") {
                $data = NotificationRecipients::with('notification:id,subject,message,type,created_by,ip_address,created_at')
                    ->where('user_id', '=',  $user->id)
                    ->where('user_type', $user_type)
                    ->where(function($query) use($params) {
                        $query->orWhereHas('notification', function ($query) use ($params) {
                            $query->where('subject', 'LIKE', "%$params%");
                        })
                        ->orWhereHas('notification', function ($query) use ($params) {
                            $query->where('message', 'LIKE', "%$params%");
                        });
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
            
                $data = NotificationRecipients::with('notification:id,subject,message,type,created_by,ip_address,created_at')
                    ->where('user_id', '=',  $user->id)
                    ->where('user_type', '=',  $user_type)
                    ->select()->orderBy('id', 'DESC')->paginate(
                        $perPage = $perPageNo,
                        $columns = ['*'],
                        $pageName = 'page'
                    );
            }            
        }
        
		if ($data->isNotEmpty()) {
			$dd = $data->toArray();
			unset($dd['links']);
			return $this->output(true, 'Success', $dd, 200);
		} else {
			return $this->output(true, 'No Record Found', []);
		}
    }

    public function updateMarkAsRead(Request $request, $id)
    {
        
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $NotificationRecipients = NotificationRecipients::find($id);
        if (is_null($NotificationRecipients)) {
            return $this->output(false, 'This Notification not exist with us. Please try again!.', [], 200);
        } else {
            $NotificationRecipients->read_at = Carbon::now();
            $NotificationRecipients->is_read = 1;
            $NotificationRecipientsRes = $NotificationRecipients->save();
            if ($NotificationRecipientsRes) {
                $resMessage = 'Notification as marked read successfully.';
                return $this->output(true, $resMessage);
            }else{
                return $this->output(false, 'Error occurred in Notification as marked read updating. Please try again!.', [], 409);
            }
        }
    }
}
