<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\Queue;
use App\Models\Extension;
use Validator;
use Carbon\Carbon;

class QueueController extends Controller
{
    public function __construct(){

    }

    public function addQueue(Request $request)
    {
		try {
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'country_id'    => 'required|numeric|exists:countries,id', 
				'company_id'    => 'required|numeric|exists:companies,id',
				'name'        	=> 'required|unique:queues',
				'queue_name'    => 'required|string|max:200',
				'description'   => 'nullable|string|max:250',
				'strategy'      => 'required|string',
				'timeout'      	=> 'required|numeric',
				'musiconhold'   => 'required|string',
			],[
                'country_id'    => 'The country field is required.',
                'company_id'    => 'The company field is required.',
            ]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$user = \Auth::user();
			if(!is_null($user)){
				$Queue = Queue::where('name', $request->ringno)
							->where('country_id', $request->country_id)
							->where('company_id', $request->company_id)
							->first();
				if(!$Queue){
					$Queue = Queue::create([
							'country_id'    => $request->country_id,
							'company_id'    => $request->company_id,						
							'name'	    	=> $request->name,							
							'queue_name'	=> $request->queue_name,							
							'strategy' 	    => $request->strategy,
							'timeout' 	    => $request->timeout,
							'description' 	=> $request->description,
							'musiconhold' 	=> $request->musiconhold,
						]);
					$response = $Queue->toArray();
					DB::commit();
					return $this->output(true, 'Queue added successfully.', $response);
				}else{
					DB::commit();
					return $this->output(false, 'This Queue already exist with us.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Adding Queue : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

	public function getAllQueue(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$Queue_id = $request->id ?? NULL;
			if ($Queue_id) {
				$data = Queue::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $Queue_id)->get();
			} else {
                $data = Queue::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
			}
		} else {
            $Queue_id = $request->id ?? NULL;
			if ($Queue_id) {
				$data = Queue::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select()
					->where('id', $Queue_id)
					->where('company_id', '=',  $user->company_id)
					->get();
			} else {
				if ($params != "") {
					$data = Queue::with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Queue::with('company:id,company_name,email,mobile')
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

	public function changeQueueStatus(Request $request, $id)
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
			$Queue = Queue::find($id);
			if(is_null($Queue)){
				DB::commit();
				return $this->output(false, 'This Queue not exist with us. Please try again!.', [], 409);
			}else{				
				$Queue->status = $request->status;
				$QueuesRes = $Queue->save();
				if($QueuesRes){
					$Queue = Queue::where('id', $id)->first();        
					$response = $Queue->toArray();
					DB::commit();
					return $this->output(true, 'Queue status updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Queue Updating. Please try again!.', [], 200);
				}				
			}
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('Error occurred in Queue Updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

	public function getAllActiveQueue(Request $request)
    {
		$user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
				$data = Queue::select('company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('status', 1)->get();
		}else{
			$data = Queue::select('company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context')
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
				$data = Queue::select('*')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('country_id', $country_id)
            			->where('company_id', $company_id)
						->where('status', 1)->get();
		}else{
			$data = Queue::select('*')
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

	public function updateQueue(Request $request, $id)
	{
		try { 
			DB::beginTransaction(); 
			$Queue = Queue::find($id);		
			if(is_null($Queue)){
				DB::commit();
				return $this->output(false, 'This Queue not exist with us. Please try again!.', [], 409);
			}
			else
			{
				$validator = Validator::make($request->all(), [
					'country_id'    => 'required|numeric|exists:countries,id',
					'company_id'	=> 'required|numeric|exists:companies,id',	
					'name'	    	=> 'required|unique:queues,name,'.$Queue->id,
					'strategy'	    => 'required|string|max:200',
					'timeout'	    => 'required|numeric',
					'description'   => 'required|string|max:200',
					'queue_name'	=> 'required',
					'musiconhold'   => 'required|string',
				],[
					'country_id'    => 'The country field is required.',
                	'company_id'    => 'The company field is required.',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}
				
				$QueueOld = Queue::where('name', $request->name)
							->where('company_id', $request->company_id)
							->where('country_id', $request->country_id)
							->where('id','!=', $id)
							->first();
				if(!$QueueOld){					
					$Queue->queue_name  = $request->queue_name;
					$Queue->strategy    = $request->strategy;
					$Queue->timeout    	= $request->timeout;
					$Queue->description = $request->description;
					$Queue->musiconhold = $request->musiconhold;
					$QueuesRes          = $Queue->save();
					if($QueuesRes){
						$Queue = Queue::where('id', $id)->first();        
						$response = $Queue->toArray();
						DB::commit();
						return $this->output(true, 'Queue updated successfully.', $response, 200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Queue Updating. Please try again!.', [], 200);
					}					
				}else{
					DB::commit();
					return $this->output(false, 'This Queue already exist with us.',[], 409);
				}			
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Queue updating : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function deleteQueue(Request $request, $id)
    {
        try {  
            DB::beginTransaction();            
            $Queue = Queue::where('id', $id)->first();
            if($Queue){
                $resdelete = $Queue->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Queue deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Queue not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Queue Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
