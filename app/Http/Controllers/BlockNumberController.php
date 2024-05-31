<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BlockNumber;
use App\Models\User;
use App\Models\Company;
use Validator;

class BlockNumberController extends Controller
{
    public function __construct(){

    }

    /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function getAllBlockNumber(Request $request)
	{
		$user = \Auth::user();
		$perPageNo = isset($request->perpage) ? $request->perpage : 5;
		$params = $request->params ?? "";

		if ($request->user()->hasRole('super-admin')) {
			$block_number_id = $request->id ?? NULL;
			if ($block_number_id) {
				$data = BlockNumber::with('company:id,company_name,email,mobile')					
					->select()->where('id', $block_number_id)->get();
			} else {
				if ($params != "") {
					$data = BlockNumber::with('company:id,company_name,email,mobile')							
						->where('rule_number', 'LIKE', "%$params%")
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = BlockNumber::with('company:id,company_name,email,mobile')							
						->select()->paginate(
							$perPage = $perPageNo,
							$columns = ['*'],
							$pageName = 'page'
						);
				}
			}
		} else {
			$block_number_id = $request->id ?? NULL;
			if ($block_number_id) {
				$data = BlockNumber::with('company:id,company_name,email,mobile')						
					->select()
					->where('id', $block_number_id)
					->where('company_id', '=',  $user->company_id)
					->get();
			} else {
				if ($params != "") {
					$data = BlockNumber::with('company:id,company_name,email,mobile')	
						->where('company_id', '=',  $user->company_id)
						->orWhere('digits', 'LIKE', "%$params%")
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = BlockNumber::with('company:id,company_name,email,mobile')	
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

	public function getBlockNumbersByCompany(Request $request, $company_id)
    {
		$company = Company::find($company_id);
		if(!$company){
			return $this->output(false, 'This Company not exist with us. Please try again!.', [], 409);
		}else{
			$BlockNumber = BlockNumber::select('*')
					->with('company:id,company_name,email,mobile')					
					->where('company_id', '=',  $company_id)->get();
					//->where('user_id','=', $user_id)
					//->where('status', 1)->get();
			if($BlockNumber->isNotEmpty()){
				return $this->output(true, 'Success', $BlockNumber->toArray());
			}else{
				return $this->output(true, 'No Record Found', []);
			}
		}			
	}

	public function getAllActiveBlockNumbers(Request $request)
    {
		$user = \Auth::user();
		if ($request->user()->hasRole('super-admin')) {
				$data = BlockNumber::select('*')
						->with('company:id,company_name,email,mobile')					
						->where('status', 1)->get();
		}else{
			$data = BlockNumber::select('*')
					->with('company:id,company_name,email,mobile')					
					->where('company_id', '=',  $user->company_id)
					->where('status', 1)->get();
		}

		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}

	public function addBlockNumber(Request $request)
    { 
		try { 
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'company_id'	=> 'required|numeric',
				'digits'	    => 'required|numeric',
				'subject'	    => 'required|in:prefix,phonenumber',
				'ruletype'	    => 'required|in:transfer,block',
				'blocktype'	    => 'required_if:ruletype,block,in:busy,congestion,hangup',
				'transfer_number'=> 'required_if:ruletype,transfer,numeric',
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			//$user = User::select()->where('company_id', $request->company_id)->first();
			$user = \Auth::user();
			if(!is_null($user)){
				$BlockNumber = BlockNumber::where('digits', $request->digits)
							->where('company_id', $request->company_id)
							->first();
				if(!$BlockNumber){
					$BlockNumber = BlockNumber::create([
							'digits'	    => $request->digits,
							'company_id'    => $request->company_id,						
							'subject'	    => $request->subject,					
							'ruletype'	    => $request->ruletype,
							'transfer_number' => $request->transfer_number,
							'blocktype'	    => $request->blocktype,
							'status' 	    => isset($request->status) ? $request->status : '1',
						]);
					$response = $BlockNumber->toArray();
					DB::commit();
					return $this->output(true, 'Block Number added successfully.', $response);
				}else{
					DB::commit();
					return $this->output(false, 'This Block Number already exist with us.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

	public function changeBlockNumberStatus(Request $request, $id){
		try { 
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'status' => 'required',
			]);
			if ($validator->fails()){
				DB::commit();
				return $this->output(false, $validator->errors()->first(), [], 409);
			}		
			$BlockNumber = BlockNumber::find($id);
			if(is_null($BlockNumber)){
				DB::commit();
				return $this->output(false, 'This Block Number not exist with us. Please try again!.', [], 409);
			}else{				
				$BlockNumber->status = $request->status;
				$BlockNumbersRes = $BlockNumber->save();
				if($BlockNumbersRes){
					$BlockNumber = BlockNumber::where('id', $id)->first();        
					$response = $BlockNumber->toArray();
					DB::commit();
					return $this->output(true, 'Block Number status updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Block Number Updating. Please try again!.', [], 200);
				}				
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

	public function updateBlockNumber(Request $request, $id){
		try { 
			DB::beginTransaction(); 
			$BlockNumber = BlockNumber::find($id);		
			if(is_null($BlockNumber)){
				DB::commit();
				return $this->output(false, 'This Block Number not exist with us. Please try again!.', [], 409);
			}
			else
			{
				$validator = Validator::make($request->all(), [
					'company_id'    => 'required|numeric',
					'digits'	    => 'required|numeric',	
					'subject'	    => 'required|in:prefix,phonenumber',
					'ruletype'	    => 'required|in:transfer,block',
					'blocktype'	    => 'required_if:ruletype,block,in:busy,congestion,hangup',
					'transfer_number'=> 'required_if:ruletype,transfer,numeric',
					'status'	    => 'nullable',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}
				
				$BlockNumberOld = BlockNumber::where('digits', $request->digits)
							->where('subject', $request->subject)
							->where('id','!=', $id)
							->first();
				if(!$BlockNumberOld){
					$BlockNumber->digits    = $request->digits;
					$BlockNumber->transfer_number = $request->transfer_number;
					$BlockNumber->subject   = $request->subject;
					$BlockNumber->ruletype  = $request->ruletype;
					$BlockNumber->blocktype = $request->blocktype;
					$BlockNumbersRes        = $BlockNumber->save();
					if($BlockNumbersRes){
						$BlockNumber = BlockNumber::where('id', $id)->first();        
						$response = $BlockNumber->toArray();
						DB::commit();
						return $this->output(true, 'Block Numbre updated successfully.', $response, 200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Block Number Updating. Please try again!.', [], 200);
					}					
				}else{
					DB::commit();
					return $this->output(false, 'This Block Number already exist with us.',[], 409);
				}			
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}	
	}

	public function deleteBlockNumber(Request $request, $id){
        try {  
            DB::beginTransaction();            
            $BlockNumber = BlockNumber::where('id', $id)->first();
            if($BlockNumber){
                $resdelete = $BlockNumber->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Block Number deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Block Number not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
