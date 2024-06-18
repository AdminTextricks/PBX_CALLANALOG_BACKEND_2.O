<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Validator;

class PermissionController extends Controller
{
    public function __construct(){

    }

    public function getUserAndRolePermission(Request $request)
    {
        $user_permissions = array();
        $role_permissions = array();
        $user = \Auth::user();
        $slug = \Auth::user()->roles[0]->slug;
        $role = Role::where('slug',$slug)->first();
        if ($request->user()->hasRole('super-admin')) {
            //$allPermission = Permission::all();            
            $allPermission = Permission::select('id','name','slug','permission_group')->get(); 
            //$role_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$allPermission); 
            $response['role_permissions'] = $allPermission->toArray();
            //$response['role_permissions'] = $role_permissions;
        }else{            
            $userPermissions = $user->permissions()->get(); 
            //$user_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$userPermissions); 
            /***** Role Permissions */           
            $rolePermissions =  $role->permissions()->get();        
            //$role_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$rolePermissions);     
            $response['role_permissions'] = $rolePermissions;   
            $response['user_permissions'] = $userPermissions;                
        }
        return $this->output(true, 'Role Permissions and User permissions.', $response, 200);
    }

    public function getRolePermissions(Request $request){
        $slug = $request->slug ?? NULL;
        if ($slug) {
            //$roles = Role::select()->where('slug', $slug)->get();
            $role = Role::select()->where('slug',$slug)->first();
            if ($role) {
                $role_permissions = array();
                $permissions =  $role->permissions()->get();
                $role_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$permissions);
                //$response['role_permissions'] = $role_permissions;    
                $response = $role_permissions;            
                return $this->output(true, 'Role Permissions.', $response, 200);
            }else{
                return $this->output(false, 'User Role not exist.', [], 409);            
            }
        }else{
            $role_permissions = array();            
            $roles = Role::whereNotIn('slug', ['super-admin'])->get();
            foreach($roles as $key => $role){
                $permissions =  $role->permissions()->get();
                $role_permissions[] = array('role_id'=>$role->id, 'role'=>$role->name, 'permissions'=>$permissions);                
            }           
            //$response['role_permissions'] = $role_permissions;
            $response = $role_permissions;
            return $this->output(true, 'Roles Permissions.', $response, 200);
        }
    }

    public function getUserPermissions(Request $request)
    {
        if(isset($request->user_id)){
            $user_id = $request->user_id ?? NULL;            
            $user = User::where('id', $user_id)->first();
            if($user){
                $user_permissions =  DB::table('users_permissions')->where('user_id', $user_id)->count();
                                
                if($user_permissions){
                    $user_permission[] = array('Name' => $user->name, 'Email' => $user->email, 'Permissions'=>$user->permissions()->get()); 
                    $response['user_permissions'] = $user_permission;
                    return $this->output(true, 'User Permissions.', $response, 200);
                }else{
                    return $this->output(true, 'No Record Found', [], 200);
                }            
            }else{
                return $this->output(false, 'User not exist.', [], 409);         
            }
        }else{
            $user = \Auth::user();
            if(in_array($user->roles->first()->slug, array('super-admin', 'admin'))){
                $user_permissions = array();
                if ($request->user()->hasRole('super-admin')) {
                    $users_permissions =  DB::table('users_permissions')
                                        ->select('user_id')->groupBy('user_id')->get()->toArray();            
                    foreach($users_permissions as $key => $user){                
                        $userObj = User::where('id', $user->user_id)->first();
                        $user_permissions[] = array('Name' => $userObj->name, 'Email' => $userObj->email, 'Permissions'=>$userObj->permissions()->get());
                    }
                }

                if ($request->user()->hasRole('admin')) {
                    $users =  User::where('company_id', $user->company_id)->get();
                    foreach($users as $key => $userObj){                
                        $user_permissions[] = array('Name' => $userObj->name, 'Email' => $userObj->email, 'Permissions'=>$userObj->permissions()->get());
                    }
                }
                $response['user_permissions'] = $user_permissions;
                return $this->output(true, 'User Permissions.', $response, 200);
            }else{
                return $this->output(false, 'You are not authorized user.');
            }
        }

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

    public function updateUserPermissions(Request $request){
        //$slug = $request->slug ?? NULL;
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric|exists:users,id',
            'permission' => 'required|string',
        ],[
            'user_id' =>  'Selected user not exist with us.'
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $user_id = $request->user_id ?? NULL;
        $permission = $request->permission ?? NULL;
        $user = User::where('id', $user_id)->first();
        if($user){
            $user->permissions()->detach(); 
            $permission_arr = explode(',', $permission);        
            foreach($permission_arr as $key => $permission){
                $user->permissions()->attach($permission);
            }       
            $user_permissions = $user->permissions()->get(); 
            $response['user_permissions'] = $user_permissions;
            return $this->output(true, 'User Permissions has been updated successfully.', $response, 200);
            
        }else{
            return $this->output(false, 'You entered role not exist.', [], 409);
        }
    }
}
