<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\LOG;
use Illuminate\Validation\Rules\File;
use App\Models\Ivr;
use Validator;

class IvrController extends Controller
{
    public function __construct(){

    }

    public function addIvr(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id'    => 'required|numeric',
            'country_id'    => 'required|numeric',
            'input_auth_type'=> 'required|numeric',
            'name'          => 'required|string|max:255|unique:ivrs',
            'description'   => 'nullable|string',
            'ivr_media_id'  => 'required|numeric',
            'timeout'       => 'nullable|string',
            
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        
        $Ivr = Ivr::where('name', $request->name)->first();
        if (!$Ivr) {
            $Ivr = Ivr::create([
                'company_id'    => $request->company_id,
                'country_id'    => $request->country_id,
                'name'          => $request->name,
                'input_auth_type'=> $request->input_auth_type,
                'description'   => $request->description,
                'ivr_media_id'  => $request->ivr_media_id,
                'timeout'       => $request->timeout,                
            ]);
            $response = $Ivr->toArray();                
            return $this->output(true, 'IVR added successfully.', $response, 200);            
        }else{
            return $this->output(false, 'IVR with the same name is already exists. please choose other name.', [], 409);
        }                
    }


    public function changeIVRStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}
		$Ivr = Ivr::find($id);
		if (is_null($Ivr)) {
			return $this->output(false, 'This Ivr not exist with us. Please try again!.', [], 200);
		} else {
			if($Ivr->company_id == $request->user()->company_id || $request->user()->hasRole('super-admin')){
				$Ivr->status = $request->status;
				$IvrRes = $Ivr->save();
				if ($IvrRes) {
					$Ivr = Ivr::where('id', $id)->first();
					$response = $Ivr->toArray();
					return $this->output(true, 'Ivr status updated successfully.', $response, 200);
				} else {
					return $this->output(false, 'Error occurred in Ivr Updating. Please try again!.', [], 409);
				}
			}else{
				return $this->output(false, 'Sorry! You are not authorized to change status.', [], 209);
			}
		}
	}

    public function updateIvr(Request $request, $id)
	{
		$Ivr = Ivr::find($id);
		if (is_null($Ivr)) {
			return $this->output(false, 'This Ivr not exist with us. Please try again!.', [], 404);
		} else {
            $validator = Validator::make($request->all(), [
                'name'          => 'required|string|max:255|unique:ivrs,name,' . $Ivr->id, 
                'input_auth_type'=> 'required|numeric',
                'country_id'    => 'required|numeric',
                'description'   => 'nullable|string',
                'ivr_media_id'  => 'required|numeric',
                'timeout'       => 'nullable|string',
            ]);
			if ($validator->fails()) {
				return $this->output(false, $validator->errors()->first(), [], 409);
			}else{

                $Ivr->name  = $request->name;
                $Ivr->country_id  = $request->country_id;
                $Ivr->input_auth_type = $request->input_auth_type;
                $Ivr->description = $request->description;
                $Ivr->ivr_media_id = $request->ivr_media_id;
                $Ivr->timeout = $request->timeout;
                $IvrRes = $Ivr->save();
                if ($IvrRes) {
                    $Ivr = Ivr::where('id', $id)->first();
                    $response = $Ivr->toArray();
                    return $this->output(true, 'Ivr updated successfully.', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in Ivr Updating. Please try again!.', [], 209);
                }
            }
        }
	} 

    public function getAllActiveIvrList(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $Ivr = Ivr::where('status',   1)->get();
        } else {
            $Ivr = Ivr::where('status',   1)->where('company_id', $request->user()->company_id)->get();
        }
        if (is_null($Ivr)) {
            return $this->output(false, 'No Recode found', [], 200);   
        } else {
            $IvrRes = $Ivr->toArray();
            return $this->output(true, 'Success',   $IvrRes, 200);
        }
                        
    }


    public function getAllIvrList(Request $request)
    {
        $user = \Auth::user();
		$perPageNo = isset($request->perpage) ? $request->perpage : 10;
		$params = $request->params ?? "";
        $Ivr_id = $request->id ?? NULL;
        
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$Ivr_id = $request->id ?? NULL;
			if ($Ivr_id) {
				$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
				    	    ->select()->where('id', $Ivr_id)->get();
			} else {
				if ($params != "") {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->where('name', 'LIKE', "%$params%")
		    				->orWhere('file_ext', 'LIKE', "%$params%")
			    			->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
					    	->select()->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
				}
			}
		} else {
			$Ivr_id = $request->id ?? NULL;
			if ($Ivr_id) {
				$data = Ivr::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
					    ->select()->where('id', $Ivr_id)->get();
			} else {
				if ($params != "") {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->where('company_id', '=',  $user->company_id)
                            ->orWhere('name', 'LIKE', "%$params%")
                            ->orWhere('file_ext', 'LIKE', "%$params%")
                            ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->where('company_id', '=',  $user->company_id)
                            ->select()->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
				}
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
