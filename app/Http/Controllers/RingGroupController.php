<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\RingGroup;
use Validator;

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
			DB::beginTransaction(); 
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
            Log::error('Error in Adding Ring Group : ' . $e->getMessage());
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllRingGroup(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if ($request->user()->hasRole('super-admin')) {
			$RingGroup_id = $request->id ?? NULL;
			if ($RingGroup_id) {
				$data = RingGroup::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $RingGroup_id)->get();
			} else {				
                $data = RingGroup::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
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
					->get();
			} else {
				if ($params != "") {
					$data = RingGroup::with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = RingGroup::with('company:id,company_name,email,mobile')
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
            LOG::error('Error in status updating : '  . $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllActiveRingGroup(Request $request)
    {
		$user = \Auth::user();
		if ($request->user()->hasRole('super-admin')) {
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

    public function updateRingGroup(Request $request, $id){
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
            LOG::error('Error in updating ring group : '. $e->getMessage());
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
            LOG::error('Error in Deleting ring group : '. $e->getMessage());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}