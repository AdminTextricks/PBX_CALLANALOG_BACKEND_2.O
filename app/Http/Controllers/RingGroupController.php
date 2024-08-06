<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\RingGroup;
use App\Models\RingMember;
use App\Models\Extension;
use Validator;
use Carbon\Carbon;
class RingGroupController extends Controller
{
    public function __construct(){

    }

    /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
    public function addRingGroup(Request $request)
    { 
		try {
			$validator = Validator::make($request->all(), [
				'country_id'    => 'required|numeric|exists:countries,id', 
				'company_id'    => 'required|numeric|exists:companies,id',
				'ringno'        => 'required|unique:ring_groups',
				'strategy'      => 'required|string|max:200',
				'ringtime'      => 'required|numeric',
				'description'   => 'nullable|string|max:250',
			],[
                'country_id'    => 'The country field is required.',
                'company_id'    => 'The company field is required.',
            ]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			DB::beginTransaction(); 
			$user = \Auth::user();
			if(!is_null($user)){
				$RingGroup = RingGroup::where('ringno', $request->ringno)
							->where('country_id', $request->country_id)
							->where('company_id', $request->company_id)
							->first();
				if(!$RingGroup){
					$RingGroup = RingGroup::create([
							'country_id'    => $request->country_id,
							'company_id'    => $request->company_id,						
							'ringno'	    => $request->ringno,							
							'strategy' 	    => $request->strategy,
							'ringtime' 	    => $request->ringtime,
							'description' 	=> $request->description,
						]);
					$response = $RingGroup->toArray();
					DB::commit();
					return $this->output(true, 'Ring Group added successfully.', $response);
				}else{
					DB::commit();
					return $this->output(false, 'This Ring Group already exist with us.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
           	Log::error('Error in Ring Group Inserting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllRingGroup(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$RingGroup_id = $request->id ?? NULL;
			if ($RingGroup_id) {
				$data = RingGroup::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $RingGroup_id)->orderBy('id', 'DESC')->get();
			} else {				
                $data = RingGroup::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->orderBy('id', 'DESC')
                        ->paginate(
                        $perPage = $perPageNo,
                        $columns = ['*'],
                        $pageName = 'page'
                    );
			}
		} else {
            $RingGroup_id = $request->id ?? NULL;
			if ($RingGroup_id) {
				$data = RingGroup::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select()
					->where('id', $RingGroup_id)
					->where('company_id', '=',  $user->company_id)
					->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = RingGroup::with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->orderBy('id', 'DESC')
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = RingGroup::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->select()->orderBy('id', 'DESC')->paginate(
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

    public function changeRingGroupStatus(Request $request, $id)
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
			$RingGroup = RingGroup::find($id);
			if(is_null($RingGroup)){
				DB::commit();
				return $this->output(false, 'This Ring Group not exist with us. Please try again!.', [], 409);
			}else{				
				$RingGroup->status = $request->status;
				$RingGroupsRes = $RingGroup->save();
				if($RingGroupsRes){
					$RingGroup = RingGroup::where('id', $id)->first();        
					$response = $RingGroup->toArray();
					DB::commit();
					return $this->output(true, 'Ring Group status updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Ring Group Updating. Please try again!.', [], 200);
				}				
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
            Log::error('Error occurred in Ring Group status updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllActiveRingGroup(Request $request)
    {
		$user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
				$data = RingGroup::select('*')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('status', 1)->get();
		}else{
			$data = RingGroup::select('*')
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
				$data = RingGroup::select('*')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('country_id', $country_id)
            			->where('company_id', $company_id)
						->where('status', 1)->get();
		}else{
			$data = RingGroup::select('*')
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

    public function updateRingGroup(Request $request, $id)
	{
		try { 
			DB::beginTransaction(); 
			$RingGroup = RingGroup::find($id);		
			if(is_null($RingGroup)){
				DB::commit();
				return $this->output(false, 'This Ring Group not exist with us. Please try again!.', [], 409);
			}
			else
			{
				$validator = Validator::make($request->all(), [
					'country_id'    => 'required|numeric|exists:countries,id',
					'company_id'	=> 'required|numeric|exists:companies,id',	
					'ringno'	    => 'required|unique:ring_groups,ringno,'.$RingGroup->id,
					'strategy'	    => 'required|string|max:200',
					'ringtime'	    => 'required|numeric',
					'description'   => 'required|string|max:200',
					'status'	    => 'nullable',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}
				
				$RingGroupOld = RingGroup::where('ringno', $request->ringno)
							->where('company_id', $request->company_id)
							->where('country_id', $request->country_id)
							->where('id','!=', $id)
							->first();
				if(!$RingGroupOld){
					/*$RingGroup->country_id  = $request->country_id;
					$RingGroup->company_id  = $request->company_id;
					$RingGroup->ringno      = $request->ringno;*/
					$RingGroup->strategy    = $request->strategy;
					$RingGroup->ringtime    = $request->ringtime;
					$RingGroup->description = $request->description;
					$RingGroupsRes          = $RingGroup->save();
					if($RingGroupsRes){
						$RingGroup = RingGroup::where('id', $id)->first();        
						$response = $RingGroup->toArray();
						DB::commit();
						return $this->output(true, 'Ring Group updated successfully.', $response, 200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Ring Group Updating. Please try again!.', [], 200);
					}					
				}else{
					DB::commit();
					return $this->output(false, 'This Ring Group already exist with us.',[], 409);
				}			
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Ring Group updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function deleteRingGroup(Request $request, $id)
    {
        try {  
            DB::beginTransaction();            
            $RingGroup = RingGroup::where('id', $id)->first();
            if($RingGroup){
				RingMember::where('ring_id', $id)->delete();
                $resdelete = $RingGroup->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Ring Group deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Ring Group not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Ring Group Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

/********   Ring Member functions ********************* */

	public function addRingMember(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'ring_id'	=> 'required|numeric|exists:ring_groups,id',
				'extension.*'	=> 'required|numeric|exists:extensions,name',				
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			DB::beginTransaction();
			$input = $request->all();
			$extension_name = $input['extension'];
			if (is_array($extension_name)) {
				$ring_members = array();
				foreach ($extension_name as $item) {
					$RingMember = RingMember::firstOrCreate ([
						'ring_id'		=> $request->ring_id,					
						'extension' 	=> $item,
					]); 
				}
				if($RingMember){
					$RingMember = RingMember::where('ring_id', $request->ring_id)->get();					
					$response = $RingMember->toArray();
					DB::commit();
					return $this->output(true, 'Ring Member updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Ring Member Updating. Please try again!.', [], 200);
				}
			}else{
				DB::commit();
				return $this->output(false, 'Wrong extension value format.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error in adding Ring Member : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function removeRingMember(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'ring_id'	=> 'required|numeric|exists:ring_groups,id',
				'extension.*'	=> 'required|numeric|exists:extensions,name',				
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			DB::beginTransaction();
			$input = $request->all();
			$extension_name = $input['extension'];
			$ring_id = $input['ring_id'];
			$RingMember = RingMember::whereIn('extension', $extension_name)
							->where('ring_id', $ring_id)->get()->toArray();
			
			if(!empty($RingMember)){
				if(is_array($extension_name)){				
					$resdelete = RingMember::whereIn('extension', $extension_name)
								->where('ring_id', $ring_id)->delete();
					if($resdelete) {
						DB::commit();
						return $this->output(true,'Success',$resdelete,200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Ring Member removing. Please try again!.', [], 209);                    
					}
				}else{
					DB::commit();
					return $this->output(false, 'Wrong extension value format.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'Ring member not exist. Please select correct value.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error in removing ring Member : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function getRingMemberByRingId(Request $request, $ring_id)
	{
		$response = array();
		$RingData = RingGroup::select()->where('id', $ring_id)->first();
		if($RingData){
			$RingData->country_id;
			$RingData->company_id;
			$RingMember = RingMember::select()->where('ring_id', $ring_id)->get(); 
			$response['Ringno'] = $RingData->ringno;
			$response['RingMember'] = $RingMember;
			$ringExtensions = array_column($RingMember->toArray(), 'extension');
			$Extensions = Extension::select('id', 'name')
						->where('company_id', $RingData->company_id)
						->where('country_id', $RingData->country_id)
                        ->whereNotIn('name', $ringExtensions)
						->where('status' , 1)
                        ->get();
			$response['Extensions'] = $Extensions;
			if ($response) {
				return $this->output(true, 'Success', $response, 200);
			} else {
				return $this->output(true, 'No Record Found', []);
			}
		}else{
			return $this->output(false,'This Ring is not exist with us. Please try again!', [],409);
		}
	}
}
