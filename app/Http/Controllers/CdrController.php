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
						->orWhere('agent_number', 'LIKE', "%$params%")
                        ->orWhere('caller_num', 'LIKE', "%$params%")
                        ->orWhere('disposition', 'LIKE', "%$params%")
                        ->orWhere('tfn', 'LIKE', "%$params%")
                        ->orWhere('destination', 'LIKE', "%$params%")
                        ->orWhere('call_type', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('email', 'like', "%{$params}%");
                        })
                        ->orWhereHas('country', function ($query) use ($params) {
                            $query->where('country_name', 'like', "%{$params}%");
                        })
                        ->orderBy('id', 'DESC')
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
                    $data = Cdr::select('*')
                        ->with('company:id,company_name,email')
                        ->with('country:id,country_name')
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                }
			}
		} else {
            if ($params != "") {
                $data = Cdr::select('*')
                    ->with('company:id,company_name,email')	
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->where(function($query) use($params) {
                        $query->where('agent_number', 'like', "%{$params}%")
                            ->orWhere('caller_num', 'LIKE', "%$params%")
                            ->orWhere('disposition', 'LIKE', "%$params%")
                            ->orWhere('tfn', 'LIKE', "%$params%")
                            ->orWhere('destination', 'LIKE', "%$params%")
                            ->orWhere('call_type', 'LIKE', "%$params%")
                            ->orWhereHas('country', function ($query) use ($params) {
                                $query->where('country_name', 'like', "%{$params}%");
                            });
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $data = Cdr::with('company:id,company_name,email')
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->select('*')
                    ->orderBy('id', 'DESC')
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


    public function getCdrFilterList(Request $request)
	{
        $user = \Auth::user();
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
       
        if ($request->get('startDate')) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->get('startDate'))->startOfDay();
        }else{
            $startDate = Carbon::now()->startOfDay(); 
        }
        if ($request->get('endDate')) {
            $endDate = Carbon::createFromFormat('Y-m-d', $request->get('endDate'))->endOfDay();
        }else{
            $endDate = Carbon::now()->endOfDay();           
        }
       
		$query  = Cdr::select('*')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name');
                        
        if ($startDate) {
            $query->where('call_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('call_date', '<=', $endDate);
        }
        if ($request->get('caller_id')) {
            $query->where('caller_num', 'like', "%{$request->get('caller_id')}%");
        }
        if ($request->get('destination')) {
            $query->where('destination', $request->get('destination'));
        }
        if ($request->get('tfn')) {
            $query->where('tfn', $request->get('tfn'));
        }
        if ($request->get('disposition')) {
            $query->where('disposition', $request->get('disposition'));
        }
        if ($request->get('call_type')) {
            $query->where('call_type', $request->get('call_type'));
        }
        if ($request->get('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        if (!in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc','reseller'))) {
            $query->where('company_id', $user->company_id);
        }
        //return $query->ddRawSql();       
        //return $data = $query->get();
        $data = $query->orderBy('id', 'DESC')->paginate($perPageNo, ['*'], 'page');
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
						->orWhere('agent_number', 'LIKE', "%$params%")
                        ->orWhere('caller_num', 'LIKE', "%$params%")
                        ->orWhere('disposition', 'LIKE', "%$params%")
                        ->orWhere('tfn', 'LIKE', "%$params%")
                        ->orWhere('destination', 'LIKE', "%$params%")
                        ->orWhere('call_type', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('email', 'like', "%{$params}%");
                        })
                        ->orWhereHas('country', function ($query) use ($params) {
                            $query->where('country_name', 'like', "%{$params}%");
                        })
                        ->orderBy('id', 'DESC')
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
                    $data = Call::select('*')
                        ->with('company:id,company_name,email')
                        ->with('country:id,country_name')
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                }
			}
		} else {
            if ($params != "") {
                $data = Call::select('*')
                    ->with('company:id,company_name,email')	
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->where(function($query) use($params) {
                        $query->where('agent_number', 'like', "%{$params}%")
                            ->orWhere('caller_num', 'LIKE', "%$params%")
                            ->orWhere('disposition', 'LIKE', "%$params%")
                            ->orWhere('tfn', 'LIKE', "%$params%")
                            ->orWhere('destination', 'LIKE', "%$params%")
                            ->orWhere('call_type', 'LIKE', "%$params%")
                            ->orWhereHas('country', function ($query) use ($params) {
                                $query->where('country_name', 'like', "%{$params}%");
                            });
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $data = Call::with('company:id,company_name,email')
                    ->with('country:id,country_name')
                    ->where('company_id', '=',  $user->company_id)
                    ->select('*')
                    ->orderBy('id', 'DESC')
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
