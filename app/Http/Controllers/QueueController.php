<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\Queue;
use App\Models\QueueMember;
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
				$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $Queue_id)->orderBy('id', 'DESC')->get();
			} else {
				if ($params != "") {
					$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
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
					$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
							->with('company:id,company_name,email,mobile')
							->with('country:id,country_name')
							->orderBy('id', 'DESC')
							->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				}
                
			}
		} else {
            $Queue_id = $request->id ?? NULL;
			if ($Queue_id) {
				$data = Queue::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
					->where('id', $Queue_id)
					->where('company_id', '=',  $user->company_id)
					->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
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
					$data = Queue::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
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
				$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
						->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('status', 1)->get();
		}else{
			$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
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
			$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
					->with('company:id,company_name,email,mobile')
					->with('country:id,country_name')
					->where('country_id', $country_id)
					->where('company_id', $company_id)
					->where('status', 1)->get();
		}else{
			$data = Queue::select('id','company_id','country_id','name','queue_name','musiconhold', 'timeout', 'context','description','strategy', 'status')
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
				QueueMember::where('queue_id', $id)->delete();
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

	/*******   Manage Queue Members ********** */

	public function addQueueMember(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'queue_id'	=> 'required|numeric|exists:queues,id',
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
					$QueueMember = QueueMember::firstOrCreate ([
						'queue_id'		=> $request->queue_id,					
						'membername' 	=> $item,
						'interface'		=> 'SIP/'.$item,
					]); 
				}
				if($QueueMember){
					$QueueMember = QueueMember::where('queue_id', $request->queue_id)->get();					
					$response = $QueueMember->toArray();
					DB::commit();
					return $this->output(true, 'Queue Member updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Queue Member Updating. Please try again!.', [], 200);
				}
			}else{
				DB::commit();
				return $this->output(false, 'Wrong extension value format.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error in Adding Queue Member : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function removeQueueMember(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'queue_id'	=> 'required|numeric|exists:queues,id',
				'extension.*'	=> 'required|numeric|exists:extensions,name',				
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			DB::beginTransaction();
			$input = $request->all();
			$extension_name = $input['extension'];
			$queue_id = $input['queue_id'];
			$QueueMember = QueueMember::whereIn('membername', $extension_name)
							->where('queue_id', $queue_id)->get()->toArray();
			
			if(!empty($QueueMember)){
				if(is_array($extension_name)){				
					$resdelete = QueueMember::whereIn('membername', $extension_name)
								->where('queue_id', $queue_id)->delete();
					if($resdelete) {
						DB::commit();
						return $this->output(true,'Success',$resdelete,200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Queue Member removing. Please try again!.', [], 209);                    
					}
				}else{
					DB::commit();
					return $this->output(false, 'Wrong extension value format.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'Queue Member not exist. Please select correct value.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error in removing Queue Member : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function getQueueMemberByQueueId(Request $request, $queue_id)
	{
		$response = array();
		$QueueData = Queue::select()->where('id', $queue_id)->first();
		if($QueueData){
			$QueueData->country_id;
			$QueueData->company_id;
			$QueueMember = QueueMember::select()->where('queue_id', $queue_id)->get(); 
			$response['QueueName'] = $QueueData->name;
			$response['QueueMember'] = $QueueMember;
			$queueExtensions = array_column($QueueMember->toArray(), 'membername');
			$Extensions = Extension::select('id', 'name')
						->where('company_id', $QueueData->company_id)
						->where('country_id', $QueueData->country_id)
                        ->whereNotIn('name', $queueExtensions)
						->where('status' , 1)
                        ->get();
			$response['Extensions'] = $Extensions;
			if ($response) {
				return $this->output(true, 'Success', $response, 200);
			} else {
				return $this->output(true, 'No Record Found', []);
			}
		}else{
			return $this->output(false,'This Queue is not exist with us. Please try again!', [],409);
		}
	}
}
