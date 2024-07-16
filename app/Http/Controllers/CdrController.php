<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\Cdr;
use App\Models\Call;
use Validator;
use Carbon\Carbon;

class CdrController extends Controller
{
    public function __construct(){

    }

    public function getAllCdrList(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$company_id = $request->company_id ?? NULL;
			if ($company_id) {
				$data = Cdr::select('*')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('company_id', $company_id)->get();
			} else {
                if ($params != "") {
					$data = Cdr::select('*')
						->with('company:id,company_name,email')	
                        ->with('country:id,country_name')
						->orWhere('agent_name', 'LIKE', "%$params%")
                        ->orWhere('duration', 'LIKE', "%$params%")
                        ->orWhere('tfn', 'LIKE', "%$params%")
                        ->orWhere('destination', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
                    $data = Cdr::select('*')
                        ->with('company:id,company_name,email')
                        ->with('country:id,country_name')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                }
			}
		} else {
            if ($params != "") {
                $data = Cdr::select('*')
                    ->with('company:id,company_name,email')	
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->orWhere('agent_name', 'LIKE', "%$params%")
                    ->orWhere('duration', 'LIKE', "%$params%")
                    ->orWhere('tfn', 'LIKE', "%$params%")
                    ->orWhere('destination', 'LIKE', "%$params%")
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('company_name', 'like', "%{$params}%");
                    })
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $data = Cdr::with('company:id,company_name,email')
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->select('*')
                    ->paginate(
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

    public function getAllCallList(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$company_id = $request->company_id ?? NULL;
			if ($company_id) {
				$data = Call::select('*')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('company_id', $company_id)->get();
			} else {
                if ($params != "") {
					$data = Call::select('*')
						->with('company:id,company_name,email')	
                        ->with('country:id,country_name')
						->orWhere('agent_name', 'LIKE', "%$params%")
                        ->orWhere('duration', 'LIKE', "%$params%")
                        ->orWhere('tfn', 'LIKE', "%$params%")
                        ->orWhere('destination', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
                    $data = Call::select('*')
                        ->with('company:id,company_name,email')
                        ->with('country:id,country_name')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                }
			}
		} else {
            if ($params != "") {
                $data = Call::select('*')
                    ->with('company:id,company_name,email')	
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->orWhere('agent_name', 'LIKE', "%$params%")
                    ->orWhere('duration', 'LIKE', "%$params%")
                    ->orWhere('tfn', 'LIKE', "%$params%")
                    ->orWhere('destination', 'LIKE', "%$params%")
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('company_name', 'like', "%{$params}%");
                    })
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $data = Call::with('company:id,company_name,email')
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->select('*')
                    ->paginate(
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
}
