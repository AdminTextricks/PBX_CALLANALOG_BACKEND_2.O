<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\Conference;
use Validator;
use Carbon\Carbon;
class ConferenceController extends Controller
{
    public function __construct(){

    }

    public function addConference(Request $request)
    {
		try {
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'country_id'=> 'required|numeric|exists:countries,id', 
				'company_id'=> 'required|numeric|exists:companies,id',
				'confno'    => 'required|unique:conferences',
				'conf_name' => 'required|string|max:200',
				'pin'       => 'required|numeric',
				'adminpin'  => 'required|string|max:20',
				'maxusers'  => 'required|numeric',
			],[
                'country_id'    => 'The country field is required.',
                'company_id'    => 'The company field is required.',
            ]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$user = \Auth::user();
			if(!is_null($user)){
				$Conference = Conference::where('confno', $request->confno)
							->where('country_id', $request->country_id)
							->where('company_id', $request->company_id)
							->first();
				if(!$Conference){
					$Conference = Conference::create([
							'country_id'    => $request->country_id,
							'company_id'    => $request->company_id,
							'confno'	    => $request->confno,
							'conf_name'	    => $request->conf_name,
							'pin' 	        => $request->pin,
							'adminpin' 	    => $request->adminpin,
							'maxusers' 	    => $request->maxusers,
						]);
					$response = $Conference->toArray();
					DB::commit();
					return $this->output(true, 'Conference added successfully.', $response);
				}else{
					DB::commit();
					return $this->output(false, 'This Conference already exist with us.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Adding Conference : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllConference(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$Conference_id = $request->id ?? NULL;
			if ($Conference_id) {
				$data = Conference::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $Conference_id)->get();
			} else {
                $data = Conference::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
			}
		} else {
            $Conference_id = $request->id ?? NULL;
			if ($Conference_id) {
				$data = Conference::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select()
					->where('id', $Conference_id)
					->where('company_id', '=',  $user->company_id)
					->get();
			} else {
				if ($params != "") {
					$data = Conference::with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Conference::with('company:id,company_name,email,mobile')
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

    public function changeConferenceStatus(Request $request, $id)
	{
		try { 
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'status' => 'required',
			]);
			if ($validator->fails()){
				DB::commit();
				return $this->output(false, $validator->errors()->first(), [], 409);
			}		
			$Conference = Conference::find($id);
			if(is_null($Conference)){
				DB::commit();
				return $this->output(false, 'This Conference not exist with us. Please try again!.', [], 409);
			}else{				
				$Conference->status = $request->status;
				$ConferencesRes = $Conference->save();
				if($ConferencesRes){
					$Conference = Conference::where('id', $id)->first();        
					$response = $Conference->toArray();
					DB::commit();
					return $this->output(true, 'Conference status updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Conference Updating. Please try again!.', [], 200);
				}				
			}
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('Error occurred in Conference Updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllActiveConference(Request $request)
    {
		$user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
				$data = Conference::select('id','company_id','country_id','confno','conf_name','pin', 'adminpin', 'maxusers')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('status', 1)->get();
		}else{
			$data = Conference::select('id','company_id','country_id','confno','conf_name','pin', 'adminpin', 'maxusers')
					->with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->where('company_id', '=',  $user->company_id)
					->where('status', 1)->get();
		}

		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}

    public function getAllActiveByCompanyAndCountry(Request $request, $country_id, $company_id)
    {
		$user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
				$data = Conference::select('*')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('country_id', $country_id)
            			->where('company_id', $company_id)
						->where('status', 1)->get();
		}else{
			$data = Conference::select('*')
					->with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->where('company_id', '=',  $user->company_id)
					->where('country_id', $country_id)            		
					->where('status', 1)->get();
		}

		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}
}
