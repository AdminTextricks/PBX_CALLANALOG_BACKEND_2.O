<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Validator;

class PermissionController extends Controller
{
    public function __construct(){

    }

    public function getAllPermissionByRole(Request $request){
        $slug = $request->slug ?? NULL;
        $roles = Role::select()->where('slug', $slug)->get();
        if ($roles->isNotEmpty()) {
            $role_permissions = array();
            if ($request->user()->hasRole('super-admin')) {
                $roles = Role::whereNotIn('slug', ['super-admin'])->get();
                foreach($roles as $key => $role){
                    $permissions =  $role->permissions()->get();
                    if(count($permissions) > 0){
                        $role_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$permissions);                    
                    }
                }           
                $response['role_permissions'] = $role_permissions;
            }else{
                $role = Role::where('slug',$slug)->first();
                $role_permissions = array();
                $permissions =  $role->permissions()->get();
                if(count($permissions) > 0){
                    $role_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$permissions);                                
                }
                $user_permissions = array();
                $user = \Auth::user();
                $userPermissions = $user->permissions()->get();
                if(count($userPermissions) > 0){
                    $user_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$userPermissions); 
                }
                $response['role_permissions'] = $role_permissions;
                $response['user_permissions'] = $user_permissions;
            }
            return $this->output(true, 'Role Permissions and User permissions.', $response, 200);
        }else{
            return $this->output(false, 'User Role not exist.', [], 409);            
        }
    }

    public function getAllPermissionByGroup(Request $request)
    {
        $slug = $request->slug ?? NULL;
        
        if ($request->user()->hasRole('super-admin')) {
            $userPermission = Permission::all();            
            $Gropus = Permission::select('permission_group')->distinct()->get();
            $response['groups'] = $Gropus->toArray();
            $response['role_permissions'] = $userPermission->toArray();
        }else{
            $role = Role::where('slug',$slug)->first();
            $role_permissions = $role->permissions()->get();
            $user = \Auth::user();
            $user_permissions = $user->permissions()->get();
            $response['role_permissions'] = $role_permissions;
            $response['user_permissions'] = $user_permissions;
        }
      
        return $this->output(true, 'Role Permissions and User permissions.', $response, 200);
    }

    public function updateRolePermissions(Request $request){
        //$slug = $request->slug ?? NULL;
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|numeric',
            'permission' => 'required|string',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $role_id = $request->role_id ?? NULL;
        $permission = $request->permission ?? NULL;
        $role = Role::where('id', $role_id)->first();
        if($role){
            $role->permissions()->detach(); 
            $permission_arr = explode(',', $permission);        
            foreach($permission_arr as $key => $permission){
                $role->permissions()->attach($permission);
            }       
            $role_permissions = $role->permissions()->get(); 
            $response['role_permissions'] = $role_permissions;
            return $this->output(true, 'Role Permissions has been updated successfully.', $response, 200);
            
        }else{
            return $this->output(false, 'You entered role not exist.', [], 409);
        }
    }
}
