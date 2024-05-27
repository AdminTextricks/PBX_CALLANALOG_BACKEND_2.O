<?php

namespace App\Http\Controllers;

use App\Models\Trunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class TrunkController extends Controller
{
    public function __construct(){

    }
    public function addTrunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'          => 'required|in:Inbound,Outbound',
            'name'          => 'required|unique:trunks',
            'prefix'        => 'required|max:250',
            'tech'          => 'required|max:250',
            'ip'            => 'required|ip',
			'is_register'   => 'required',
            'remove_prefix' => 'nullable',
            'failover'      => 'nullable',
            'max_use'       => 'nullable',
            'if_max_use'    => 'nullable',
            'username'      => 'nullable|max:250',
            'password'      => 'nullable|max:250',
        ],[
            'name.unique'  => 'This Trunk name is already registered. Please try with different trunk.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();
            $Trunk = Trunk::where('name', $request->name)->first();        
            if(!$Trunk){
                $Trunk = Trunk::create([
                    'type'      => $request->type,
                    'name'      => $request->name,
                    'prefix'	=> $request->prefix,
                    'tech'      => $request->tech,
                    'is_register'   => $request->is_register,
                    'ip'            => $request->ip,
                    'remove_prefix' => $request->remove_prefix,
                    'failover'      => $request->failover,
                    'max_use' 	    => $request->max_use,
                    'if_max_use' 	=> $request->if_max_use,
                    'username'      => $request->username,
                    'password'      => $request->password,
                    'status' 	    => isset($request->status) ? $request->status : 1,
                ]);
                
                $response 	= $Trunk->toArray();               
                DB::commit();
                return $this->output(true, 'Trunk added successfully.', $response);
            }else{
                DB::commit();
                return $this->output(false, 'This Trunk is already register with us.');
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }


    public function getAllTrunk(Request $request)
    {
        //$user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $trunk_id = $request->id ?? NULL;
        if($trunk_id){            
            $Trunks_data = Trunk::select('*') 
                            ->where('id', $trunk_id)->get();;
        }else{
            $Trunks_data = Trunk::select('*') 
                                ->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
        }
        if ($Trunks_data->isNotEmpty()) {
            $Trunks_dd = $Trunks_data->toArray();
            unset($Trunks_dd['links']);
            return $this->output(true, 'Success', $Trunks_dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getActiveOutboundTrunk(Request $request)
    {
        //$user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $trunk_id = $request->id ?? NULL;
        if($trunk_id){            
            $Trunks_data = Trunk::select('*') 
                            ->where('id', $trunk_id)
                            ->where('type', 'Outbound')->get();
        }else{
            $Trunks_data = Trunk::select('*')
                            ->where('type', 'Outbound') 
                            ->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
        }
        if ($Trunks_data->isNotEmpty()) {
            $Trunks_dd = $Trunks_data->toArray();
            unset($Trunks_dd['links']);
            return $this->output(true, 'Success', $Trunks_dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllActiveTrunks(Request $request)
    {
        //$user = \Auth::user();        
        $Trunks = Trunk::select()->where('status', '=', 1)->get();        
        if ($Trunks->isNotEmpty()) {
            return $this->output(true, 'Success', $Trunks->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }


    public function changeTrunkStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}

		$Trunk = Trunk::find($id);
		if (is_null($Trunk)) {
			return $this->output(false, 'This Trunk not exist with us. Please try again!.', [], 200);
		} else {			
            $Trunk->status = $request->status;
            $TrunkRes = $Trunk->save();
            if ($TrunkRes) {
                $Trunk = Trunk::where('id', $id)->first();
                $response = $Trunk->toArray();
                return $this->output(true, 'Trunk updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in Trunk Updating. Please try again!.', [], 200);
            }			
		} 
	}

    public function updateTrunk(Request $request, $id)
	{
		$Trunk = Trunk::find($id);
		if (is_null($Trunk)) {
			return $this->output(false, 'This Trunks not exist with us. Please try again!.', [], 404);
		} else {
			$validator = Validator::make($request->all(), [
                'type'          => 'required|in:Inbound,Outbound',
                'name'          => 'required|unique:trunks,name,'.$Trunk->id,
                'prefix'        => 'required|max:250',
                'tech'          => 'required|max:250',
                'is_register'   => 'required',
                'ip'            => 'required|ip',
                'remove_prefix' => 'required',
                'failover'      => 'nullable|exists:trunks,id',
                'max_use'       => 'required',
                'if_max_use'    => 'required',
                'username'      => 'required|max:250',
                'password'      => 'required|max:250',
            ],[
                'name.unique' => 'This Trunk name is already registered. Please try with different trunk.',
            ]);
            if ($validator->fails()){
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
			//$user = $request->user();
			$Trunk->type        = $request->type;
			$Trunk->name 	    = $request->name;
			$Trunk->prefix	    = $request->prefix;
			$Trunk->tech	    = $request->tech;
			$Trunk->is_register = $request->is_register;
			$Trunk->ip          = $request->ip;
			$Trunk->remove_prefix   = $request->remove_prefix;
			$Trunk->failover 	    = $request->failover;			
			$Trunk->max_use 	    = $request->max_use;			
			$Trunk->if_max_use 	    = $request->if_max_use;			
			$Trunk->username 	    = $request->username;			
			$Trunk->password 	    = $request->password;			
			$TrunkRes 			    = $Trunk->save();

			if ($TrunkRes) {
				$Trunk = Trunk::where('id', $id)->first();
				$response = $Trunk->toArray();
				return $this->output(true, 'Trunks updated successfully.', $response, 200);
			} else {
				return $this->output(false, 'Error occurred in Trunks Updating. Please try again!.', [], 200);
			}
		}
	}

    public function deleteTrunk(Request $request, $id){
        try {  
            DB::beginTransaction();            
            $Trunk = Trunk::where('id', $id)->first();
            if($Trunk){
                $resdelete = $Trunk->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Trunks removing. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Trunks not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
