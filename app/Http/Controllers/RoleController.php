<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Validator;

class RoleController extends Controller
{
    public function __construct(){

    }
	
	/**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
	public function getRoles(Request $request){
		$role_id = $request->id ?? NULL;
		if($role_id){
			$data = Role::select('id', 'name', 'description', 'status')->where('id', $role_id)->get();	
		}else{
			$data = Role::select('id', 'name', 'description', 'status')->get();
		}		
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray(), 200);
		}else{
			return $this->output(true, 'No Record Found', []);
		}
    }
	
	public function getAllActiveRole(Request $request){
		$data = Role::select('id', 'name', 'description', 'status')->where('status', 1)->get();	
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}
	
	public function addRole(Request $request){ 
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255|unique:roles',
            'description' => 'nullable|string|max:255'
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        //$user = $request->user();
        $userRole = Role::where('name', $request->name)->first();
        if(!$userRole){
           $userRole = Role::create([
                'name'	=> $request->name,
                'description' => $request->description,
				'slug' => $request->slug,
                'status' => isset($request->status) ? $request->status : 1,
            ]);
            $response = $userRole->toArray();
            return $this->output(true, 'User Role added successfully.', $response);
        }else{
            return $this->output(false, 'This Role already exist with us. Please choose another tilte to add same role.');
        }
    }
	
	
	public function changeStatus(Request $request, $id){
		if($id > 6){
			$validator = Validator::make($request->all(), [
				'status' => 'required',
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$role = Role::find($id);
			if(is_null($role)){
				return $this->output(false, 'This Role not exist with us. Please try again!.', [], 200);
			}else{
				$role->status = $request->status;
				$roleRes = $role->save();
				if($roleRes){
					$role = Role::where('id', $id)->first();        
					$response = $role->toArray();
					return $this->output(true, 'User Role updated successfully.', $response, 200);
				}else{
					return $this->output(false, 'Error occurred in Role Updating. Please try again!.', [], 409);
				}
			}
		}else{
			return $this->output(false, 'Role can\'t be edited!.', [], 409);
		}
    }
	
	public function updateRole(Request $request, $id){
		if($id > 6){
			$role = Role::find($id);
			if(is_null($role)){
				return $this->output(false, 'This Role not exist with us. Please try again!.', [], 404);
			}else{
				$validator = Validator::make($request->all(), [
					'name' => 'required|max:255|unique:roles,name,'.$role->id,
					'description' => 'nullable|string|max:255',
					'status' => 'nullable',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}
				$role->name = $request->name;
				$role->description = $request->description;
				$role->status = $request->status;
				$roleRes = $role->save();
				if($roleRes){
					$role = Role::where('id', $id)->first();        
					$response = $role->toArray();
					return $this->output(true, 'User Role updated successfully.', $response, 200);
				}else{
					return $this->output(false, 'Error occurred in Role Updating. Please try again!.', [], 409);
				}
			} 
		}else{
			return $this->output(false, 'Role can\'t be edited!.', [], 409);
		}

	}

}
