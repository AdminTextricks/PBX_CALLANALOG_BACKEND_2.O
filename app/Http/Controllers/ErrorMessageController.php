<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\DB;
use App\Models\ErrorMessage;

class ErrorMessageController extends Controller
{
    public function getAllHangupCauseList(Request $request)
    {
        $user = \Auth::user();
		$perPageNo = isset($request->perpage) ? $request->perpage : 10;
		$params = $request->params ?? "";
        
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $data = ErrorMessage::select('hangup_cause.*', 'error_messages.error_message')
                            ->with('company:id,company_name,email')
                            ->join('error_messages', 'hangup_cause.error_code', '=', 'error_messages.error_code')
                            ->orderBy('hangup_cause.id', 'DESC')
			    			->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            if ($data->isNotEmpty()) {
                $dd = $data->toArray();
                unset($dd['links']);
                return $this->output(true, 'Success', $dd, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        }else{
            return $this->output(false,'You are not authorize user.', [], 409);
        }
    }
}
