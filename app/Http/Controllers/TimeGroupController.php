<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use Validator;
use Carbon\Carbon;

class TimeGroupController extends Controller
{
    public function __construct(){

    }

    public function addTimeGroup(Request $request)
    {
		try {
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'company_id'        => 'required|numeric|exists:companies,id',
				'name'              => 'required|string|unique:time_groups',
				'time_to_start'     => 'required|date_format:H:i',
				'time_to_finish'    => 'required|date_format:H:i',
				'week_day_start'    => 'nullable|string',
				'week_day_finish'   => 'required_with:week_day_start',
                'month_day_start'   => 'nullable|string',
                'month_day_finish'  => 'required_with:month_day_start',
                'month_start'       => 'nullable|string',
                'month_finish'      => 'required_with:month_start',
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}			
			
            $TimeGroup = TimeGroup::create([
                    'company_id'        => $request->company_id,
                    'name'	            => $request->name,
                    'time_to_start'	    => $request->time_to_start,
                    'time_to_finish' 	=> $request->time_to_finish,
                    'week_day_start' 	=> $request->week_day_start,
                    'week_day_finish' 	=> $request->week_day_finish,
                    'month_day_start' 	=> $request->month_day_start,
                    'month_day_finish' 	=> $request->month_day_finish,
                    'month_start' 	    => $request->month_start,
                    'month_finish' 	    => $request->month_finish,
                ]);
            if($TimeGroup){
                $response = $TimeGroup->toArray();
                DB::commit();
                return $this->output(true, 'Time Group added successfully.', $response);		
            }else{
                DB::commit();
                return $this->output(false, 'Error occurred in Adding Time Group.');
            }			
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Adding Time Group : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllTimeGroup(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$TimeGroup_id = $request->id ?? NULL;
			if ($TimeGroup_id) {
				$data = TimeGroup::select()
                        ->with('company:id,company_name,email,mobile')
                        ->where('id', $TimeGroup_id)->orderBy('id', 'DESC')->get();
			} else {
				if ($params != "") {
					$data = TimeGroup::select()
							->with('company:id,company_name,email,mobile')
							->orWhere('name', 'LIKE', "%$params%")
							->orWhereHas('company', function ($query) use ($params) {
								$query->where('company_name', 'like', "%{$params}%");
							})
							->orWhereHas('company', function ($query) use ($params) {
								$query->where('email', 'like', "%{$params}%");
							})
							->orderBy('id', 'DESC')
							->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				}else{
					$data = TimeGroup::select()
							->with('company:id,company_name,email,mobile')
							->orderBy('id', 'DESC')
							->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				}                
			}
		} else {
            $TimeGroup_id = $request->id ?? NULL;
			if ($TimeGroup_id) {
				$data = TimeGroup::with('company:id,company_name,email,mobile')
                   ->select()
					->where('id', $TimeGroup_id)
					->where('company_id', '=',  $user->company_id)
					->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = TimeGroup::select()
						->with('company:id,company_name,email,mobile')	
                       ->where('company_id', '=',  $user->company_id)
						->where(function($query) use($params) {
							$query->where('name', 'like', "%{$params}%");							
						})
						->orderBy('id', 'DESC')
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = TimeGroup::with('company:id,company_name,email,mobile')
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


    public function getTimeGroupByCompany(Request $request, $company_id)
    {
		$query = TimeGroup::select('id','name');
		if ($company_id != '') {
            $query->where('company_id', $company_id);
        } 
		$data = $query->orderBy('id', 'DESC')->get();
		
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}


    public function updateTimeGroup(Request $request, $id)
	{
		try { 
			DB::beginTransaction(); 
			$TimeGroup = TimeGroup::find($id);		
			if(is_null($TimeGroup)){
				DB::commit();
				return $this->output(false, 'This Time Group not exist with us. Please try again!.', [], 409);
			}
			else
			{
				$validator = Validator::make($request->all(), [
					'company_id'	    => 'required|numeric|exists:companies,id',	
					'name'	    	    => 'required|unique:time_groups,name,'.$TimeGroup->id,
					'time_to_start'	    => 'required|date_format:H:i',
					'time_to_finish'	=> 'required|date_format:H:i',
					'week_day_start'    => 'nullable|string|max:200',
					'week_day_finish'	=> 'required_with:week_day_start',
					'month_day_start'   => 'nullable|string',
					'month_day_finish'  => 'required_with:month_day_start',
					'month_start'       => 'nullable|string',
					'month_finish'      => 'required_with:month_start',	
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}
											
                $TimeGroup->name            = $request->name;
                $TimeGroup->time_to_start   = $request->time_to_start;
                $TimeGroup->time_to_finish  = $request->time_to_finish;
                $TimeGroup->week_day_start  = $request->week_day_start;
                $TimeGroup->week_day_finish = $request->week_day_finish;
                $TimeGroup->month_day_start = $request->month_day_start;
                $TimeGroup->month_day_finish= $request->month_day_finish;
                $TimeGroup->month_start     = $request->month_start;
                $TimeGroup->month_finish    = $request->month_finish;
                $TimeGroupsRes              = $TimeGroup->save();
                if($TimeGroupsRes){
                    $TimeGroup = TimeGroup::where('id', $id)->first();        
                    $response = $TimeGroup->toArray();
                    DB::commit();
                    return $this->output(true, 'TimeGroup updated successfully.', $response, 200);
                }else{
                    DB::commit();
                    return $this->output(false, 'Error occurred in TimeGroup Updating. Please try again!.', [], 200);
                }			
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Time Group updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}


    public function deleteTimeGroup(Request $request, $id)
    {
        try {  
            DB::beginTransaction();            
            $TimeGroup = TimeGroup::where('id', $id)->first();
            if($TimeGroup){
				$resdelete = $TimeGroup->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Time Group deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Time Group not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Time Group Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
