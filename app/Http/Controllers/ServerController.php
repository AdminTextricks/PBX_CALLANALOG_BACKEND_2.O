<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Server;
use Validator;

class ServerController extends Controller
{
    public function __construct(){

    }

    /**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
    public function addBlockNumber(Request $request)
    { 
		try { 
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'name'	=> 'required|numeric',
				'ip'	    => 'required|numeric',
				'domain'	    => 'required|in:prefix,phonenumber',				
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			//$user = User::select()->where('company_id', $request->company_id)->first();
			$user = \Auth::user();
			if(!is_null($user)){
				$BlockNumber = Server::where('digits', $request->digits)
							->where('company_id', $request->company_id)
							->first();
				if(!$BlockNumber){
					$BlockNumber = Server::create([
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
}
