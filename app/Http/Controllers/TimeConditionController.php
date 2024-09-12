<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TfnDestination;
use App\Models\TimeCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use Validator;
use Carbon\Carbon;

class TimeConditionController extends Controller
{
    public function __construct(){

    }

    public function addTimeCondition(Request $request)
    {
		try {
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'company_id'        => 'required|numeric|exists:companies,id',
                'country_id'        => 'required|numeric|exists:countries,id',
				'name'              => 'required|string|unique:time_conditions',
				'time_group_id'     => 'required|numeric|exists:time_groups,id',
				'time_zone'                     => 'required|string',
				'tc_match_destination_type'     => 'required|string',
				'tc_match_destination_id'       => 'required_with:tc_match_destination_type',
                'tc_non_match_destination_type' => 'required|string',
                'tc_non_match_destination_id'   => 'required_with:tc_non_match_destination_type',
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}			
			
            $TimeCondition = TimeCondition::create([
                    'company_id'        => $request->company_id,
                    'country_id'        => $request->country_id,
                    'name'	            => $request->name,
                    'time_group_id'	    => $request->time_group_id,
                    'time_zone' 	    => $request->time_zone,
                    'tc_match_destination_type' 	=> $request->tc_match_destination_type,
                    'tc_match_destination_id' 	    => $request->tc_match_destination_id,
                    'tc_non_match_destination_type' => $request->tc_non_match_destination_type,
                    'tc_non_match_destination_id' 	=> $request->tc_non_match_destination_id,
                ]);
            if($TimeCondition){
                $response = $TimeCondition->toArray();
                DB::commit();
                return $this->output(true, 'Time Condition added successfully.', $response);		
            }else{
                DB::commit();
                return $this->output(false, 'Error occurred in Adding Time Condition.');
            }			
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Adding Time Condition: ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }


    public function getAllTimeCondition(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$TimeCondition_id = $request->id ?? NULL;
			if ($TimeCondition_id) {
				$data = TimeCondition::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $TimeCondition_id)->orderBy('id', 'DESC')->get();
			} else {
				if ($params != "") {
					$data = TimeCondition::select()
							->with('company:id,company_name,email,mobile')
							->with('country:id,country_name')
							->orWhere('name', 'LIKE', "%$params%")
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
				}else{
					$data = TimeCondition::select()
							->with('company:id,company_name,email,mobile')
							->with('country:id,country_name')
							->orderBy('id', 'DESC')
							->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				}
                
			}
		} else {
            $TimeCondition_id = $request->id ?? NULL;
			if ($TimeCondition_id) {
				$data = TimeCondition::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select()
					->where('id', $TimeCondition_id)
					->where('company_id', '=',  $user->company_id)
					->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = TimeCondition::select()
						->with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->where(function($query) use($params) {
							$query->where('name', 'like', "%{$params}%")
							->orWhereHas('country', function ($query) use ($params) {
								$query->where('country_name', 'like', "%{$params}%");
							});
						})
						->orderBy('id', 'DESC')
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = TimeCondition::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->select()
						->orderBy('id', 'DESC')
						->paginate(
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

    public function changeTimeConditionStatus(Request $request, $id)
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
			$TimeCondition = TimeCondition::find($id);
			if(is_null($TimeCondition)){
				DB::commit();
				return $this->output(false, 'This Time Condition not exist with us. Please try again!.', [], 409);
			}else{				
				$TimeCondition->status = $request->status;
				$TimeConditionsRes = $TimeCondition->save();
				if($TimeConditionsRes){
					$TimeCondition = TimeCondition::where('id', $id)->first();        
					$response = $TimeCondition->toArray();
					DB::commit();
					return $this->output(true, 'Time Condition status updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Time Condition Updating. Please try again!.', [], 200);
				}				
			}
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('Error occurred in Time Condition Updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

/*
    public function getAllActiveTimeCondition(Request $request)
    {
		$user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
				$data = TimeCondition::select('id','name')
						->where('status', 1)->get();
		}else{
			$data = TimeCondition::select('id','name')
					->where('company_id', '=',  $user->company_id)
					->where('status', 1)->get();
		}

		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}

    public function getTimeConditionByCompanyAndCountry(Request $request, $country_id, $company_id)
    {
		$user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$data = TimeCondition::select('id','name')
					 ->with('company:id,company_name,email,mobile')
					->with('country:id,country_name') 
					->where('country_id', $country_id)
					->where('company_id', $company_id)
					->where('status', 1)->get();
		}else{
			$data = TimeCondition::select('id','name')
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
*/

    public function getAllActiveTimeCondition(Request $request)
    {
		$query = TimeCondition::select('id','name');
		if ($request->get('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
		if ($request->get('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }  
		$data = $query->where('status', 1)->orderBy('id', 'DESC')->get();
		
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}

    public function updateTimeCondition(Request $request, $id)
	{
		try { 
			DB::beginTransaction(); 
			$TimeCondition = TimeCondition::find($id);		
			if(is_null($TimeCondition)){
				DB::commit();
				return $this->output(false, 'This Time Condition not exist with us. Please try again!.', [], 409);
			}
			else
			{
				$validator = Validator::make($request->all(), [
					'country_id'    => 'required|numeric|exists:countries,id',
					'company_id'	=> 'required|numeric|exists:companies,id',	
					'name'	    	=> 'required|unique:time_conditions,name,'.$TimeCondition->id,
					'time_group_id'     => 'required|numeric|exists:time_groups,id',
                    'time_zone'                     => 'required|string',
                    'tc_match_destination_type'     => 'required|string',
                    'tc_match_destination_id'       => 'required_with:tc_match_destination_type',
                    'tc_non_match_destination_type' => 'required|string',
                    'tc_non_match_destination_id'   => 'required_with:tc_non_match_destination_type',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}				

                $TimeCondition->name  = $request->name;
                $TimeCondition->time_group_id    = $request->time_group_id;
                $TimeCondition->time_zone    	= $request->time_zone;
                $TimeCondition->tc_match_destination_type       = $request->tc_match_destination_type;
                $TimeCondition->tc_match_destination_id         = $request->tc_match_destination_id;
                $TimeCondition->tc_non_match_destination_type   = $request->tc_non_match_destination_type;
                $TimeCondition->tc_non_match_destination_id     = $request->tc_non_match_destination_id;
                $TimeConditionsRes          = $TimeCondition->save();
                if($TimeConditionsRes){
                    $TimeCondition = TimeCondition::where('id', $id)->first();        
                    $response = $TimeCondition->toArray();
                    DB::commit();
                    return $this->output(true, 'Time Condition updated successfully.', $response, 200);
                }else{
                    DB::commit();
                    return $this->output(false, 'Error occurred in Time Condition Updating. Please try again!.', [], 200);
                }						
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Time Condition updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function deleteTimeCondition(Request $request, $id)
    {
        try {  
            DB::beginTransaction();            
            $TimeCondition = TimeCondition::where('id', $id)->first();
            if($TimeCondition){
				TfnDestination::where('destination_type_id', 10)->where('destination_id',$id)->delete();
				$resdelete = $TimeCondition->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Time Condition deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Time Condition not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Time Condition Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
