<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
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
    public function addServer(Request $request)
    { 
		try { 
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'name'	    => 'required',
				'port'	    => 'nullable',
				'ip'	    => 'required',
				'user_name'	=> 'required',
				'secret'	=> 'required',
				'ami_port'	=> 'required',
				'barge_url'	=> 'required',
				//'domain'    => 'required|regex:/^(?:[a-z0-9](?:[a-z0-9-æøå]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/isu',				
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$user = \Auth::user();
			if(!is_null($user)){
				$Server = Server::where('ip', $request->ip)
							->first();
				if(!$Server){
					$Server = Server::create([
							'name'	    => $request->name,
							'ip'        => $request->ip,
							'port'      => isset($request->port) ? $request->port : 5060,
							//'domain'	=> $request->domain,
							'user_name'	=> $request->user_name,
							'secret'	=> $request->secret,
							'ami_port'	=> $request->ami_port,
							'barge_url'	=> $request->barge_url,
							'status' 	=> isset($request->status) ? $request->status : '1',
						]);
					$response = $Server->toArray();
					DB::commit();
					return $this->output(true, 'Server added successfully.', $response);
				}else{
					DB::commit();
					return $this->output(false, 'This Server already exist with us.');
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


    public function getAllServers(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";

		if ($request->user()->hasRole('super-admin')) {
			$server_id = $request->id ?? NULL;
			if ($server_id) {
				$data = Server::select()->where('id', $server_id)->get();
			} else {				
                $data = Server::select()->paginate(
                        $perPage = $perPageNo,
                        $columns = ['*'],
                        $pageName = 'page'
                    );
			}
		} else {
			return $this->output(false, 'You are not authorized user.');
		}

		if ($data->isNotEmpty()) {
			$dd = $data->toArray();
			unset($dd['links']);
			return $this->output(true, 'Success', $dd, 200);
		} else {
			return $this->output(true, 'No Record Found', []);
		}
	}


    public function changeServerStatus(Request $request, $id)
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
			$Server = Server::find($id);
			if(is_null($Server)){
				DB::commit();
				return $this->output(false, 'This Server not exist with us. Please try again!.', [], 409);
			}else{				
				$Server->status = $request->status;
				$ServersRes = $Server->save();
				if($ServersRes){
					$Server = Server::where('id', $id)->first();        
					$response = $Server->toArray();
					DB::commit();
					return $this->output(true, 'Server status updated successfully.', $response, 200);
				}else{
					DB::commit();
					return $this->output(false, 'Error occurred in Server Updating. Please try again!.', [], 200);
				}				
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function updateServer(Request $request, $id)
    {
		try { 
			DB::beginTransaction(); 
			$Server = Server::find($id);		
			if(is_null($Server)){
				DB::commit();
				return $this->output(false, 'This Server not exist with us. Please try again!.', [], 409);
			}else{
				$validator = Validator::make($request->all(), [
					'name'      => 'required',
					'ip'	    => 'required|unique:servers,ip,'.$Server->id,
					'port'		=> 'nullable',
					'user_name'	=> 'required',
					'secret'	=> 'required',
					'ami_port'	=> 'required',
					'barge_url'	=> 'required',
					//'domain'	=> 'required|regex:/^(?:[a-z0-9](?:[a-z0-9-æøå]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/isu',					
					'status'	=> 'nullable',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}
				
				$ServerOld = Server::where('ip', $request->ip)
							->where('id','!=', $id)
							->first();
				if(!$ServerOld){
					$Server->name   	= $request->name;
					$Server->ip     	= $request->ip;
					$Server->port   	= $request->port;
					//$Server->domain 	= $request->domain;
					$Server->user_name 	= $request->user_name;
					$Server->secret 	= $request->secret;
					$Server->ami_port 	= $request->ami_port;
					$Server->barge_url 	= $request->barge_url;
					$ServersRes     	= $Server->save();
					if($ServersRes){
						$Server = Server::where('id', $id)->first();        
						$response = $Server->toArray();
						DB::commit();
						return $this->output(true, 'Server updated successfully.', $response, 200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Server Updating. Please try again!.', [], 200);
					}
				}else{
					DB::commit();
					return $this->output(false, 'This Server already exist with us.',[], 409);
				}
			}
		} catch (\Exception $e) {
			DB::rollback();
			Log::error('Error in Server Updating : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
	}


	public function getAllActiveServers(Request $request)
    {
        $Server = Server::select()->where('status', '=', 1)->get();        
        if ($Server->isNotEmpty()) {
            return $this->output(true, 'Success', $Server->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }


	public function deleteServer(Request $request, $id)
	{
        try {
            DB::beginTransaction();            
            $Server = Server::where('id', $id)->first();
            if($Server){
                $resdelete = $Server->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Server removing. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Server not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
