<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\Country;
use App\Models\State;
use App\Models\Permission;
use App\Models\Server;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Validator;
use Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserController extends Controller
{
    public function __construct(){

    }

    public function getUser(Request $request)
    {
        $user_id = $request->id ?? NULL;
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $user = \Auth::user();        
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $dataQuery = User::select()
                        ->with('company:id,company_name')
                        ->with('user_role:id,name')
                        ->with('country:id,country_name')
                        ->with('state:id,state_name,state_code');

            if($user_id) {
                $data = $dataQuery->where('id', $user_id)->first();
            }else{
                $data = $dataQuery->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
        }elseif($user->roles->first()->slug == 'admin'){
            $dataQuery = User::select()
                        ->with('company:id,company_name')
                        ->with('user_role:id,name')
                        ->with('country:id,country_name')
                        ->with('state:id,state_name,state_code')
                        ->where('company_id', $user->company_id);
            $data = $dataQuery->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
        }else{
            $dataQuery = User::select()
                        ->with('company:id,company_name')
                        ->with('user_role:id,name')
                        ->with('country:id,country_name')
                        ->with('state:id,state_name,state_code')
                        ->where('id', $user->id);
            $data = $dataQuery->get();
        }
        if ($data) {
            $dd = $data->toArray();
            if (is_array($dd)) {
                unset($dd['links']);
                return $this->output(true, 'Success', $dd, 200);
            }
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }
	
    /**
     * Display a listing of the Active resource.
     *
     * @return \Illuminate\Http\Response
     */

     public function getAllActiveUsers(Request $request)
     {
        $data = User::select()->where('status', 1)->get();	
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}



    public function getAllResellerUsers(Request $request)
    {
        $user_id = $request->id ?? NULL;
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $user = \Auth::user();        
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $dataQuery = User::select()
                        ->with('company:id,company_name')
                        ->with('user_role:id,name')
                        ->with('country:id,country_name')
                        ->with('state:id,state_name,state_code')
                        ->where('role_id', 5);

            if($user_id) {
                $data = $dataQuery->where('id', $user_id)->first();
            }else{
                $data = $dataQuery->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
            if ($data) {
                $dd = $data->toArray();
                if (is_array($dd)) {
                    unset($dd['links']);
                    return $this->output(true, 'Success', $dd, 200);
                }
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        }else{
            return $this->output(false, 'You are not authorized user.');
        }

        
	}


    /**
     * Display a listing of the reseller Active resource.
     *
     * @return \Illuminate\Http\Response
     */

     public function getActiveResellerUsers(Request $request)
     {
        $data = User::select()
                ->where('status', 1)
                ->where('role_id', 5)->get();	
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}




    /**
     * Change User Status.
     *
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id){
		$validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
		
        $User = User::find($id);
		if(is_null($User)){
			return $this->output(false, 'This user not exist with us. Please try again!.', [], 200);
		}else{
			$User->status = $request->status;
			$UserRes = $User->save();
			if($UserRes){
				$User = User::where('id', $id)->first();        
				$response = $User->toArray();
				return $this->output(true, 'User updated successfully.', $response, 200);
			}else{
				return $this->output(false, 'Error occurred in user updating. Please try again!.', [], 200);
			}
		}
    }
	
	public function registration(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'parent_id'     => 'required',
            'plan_id'       => 'required',
            'company_name'  => 'required|max:500|unique:companies',
            'account_code'  => 'required|max:500|unique:companies',
            'name'     		=> 'required|max:255',
            'email'         => 'required|email|max:255|unique:users|unique:companies',
            'mobile'        => 'required|string|unique:users',
			'address'		=> 'required|max:500',
			'country_id'	=> 'required',
			'state_id'		=> 'required',
			'city'			=> 'required',
			'zip'			=> 'required',
            'password' 		=> 'required|confirmed',
        ],[
            'plan_id'       => 'Plan type is required!',
            'parent_id'     => 'parent ID is required.',
            'email.unique'  => 'This email ID is already registered. Please try with different email ID.',
            'mobile.unique' => 'This mobile number is already registered. Please try with different mobile number.',
            'company_name.unique' => 'This Company name is already registered. Please try with different Company name.',
			'account_code.unique' => 'This Account code is already exist. Please try with different Account code.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();
            $user = User::where('email', $request->email)->first();        
            if(!$user){
                $company = Company::create([
                    'plan_id'       => $request->plan_id,
                    'parent_id'     => $request->parent_id,
                    'company_name'	=> $request->company_name,
                    'account_code'  => $request->account_code,
                    'email'        	=> $request->email,
                    'mobile'       	=> $request->mobile,
                    'billing_address' => $request->address,
                    'country_id' 	=> $request->country_id,
                    'state_id' 		=> $request->state_id,
                    'city' 			=> $request->city,
                    'zip' 			=> $request->zip,
                ]);
                //dd($company);
                $user = User::create([
                    'company_id' => $company->id,
                    //'account_code' => $request->account_code,
                    'name' 		=> $request->name,
                    'email' 	=> $request->email,
                    'mobile' 	=> $request->mobile,
                    'password' 	=> Hash::make($request->password),
                    'address' 	=> $request->address,
                    'country_id'=> $request->country_id,
                    'state_id' 	=> $request->state_id,
                    'city' 		=> $request->city,
                    'zip' 		=> $request->zip,
                    'role_id' 	=> '4',//$request->role_id,
                ]);
                DB::table('users_roles')->insert([
                    'user_id'   => $user->id,
                    'role_id'   => 4,
                ]);
                $Server = false;
                $user_registered =  DB::table('user_registered_servers')
                            ->select(['id','server_id'])
                            ->orderBy('id', 'DESC')
                            ->limit(1)->first();
                if($user_registered){
                    $Server =   Server::select('id','domain','ip')
                                ->where('status', '=', 1)
                                ->where('id', '>', $user_registered->server_id)
                                ->limit(1)->get()->toArray();
                }
                if(!$Server){
                    $ServerObj = Server::select('id','domain','ip')->where('status', '=', 1)->orderBy('id', 'ASC')->first();
                    $Server = $ServerObj->toArray();
                    DB::table('user_registered_servers')->insert([
                        'server_id'   => $Server['id'],
                        'company_id'   => $company->id,
                        'domain'   => $Server['domain'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }else{
                    DB::table('user_registered_servers')->insert([
                        'server_id'   => $Server[0]['id'],
                        'company_id'   => $company->id,
                        'domain'   => $Server[0]['domain'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
                
                $this->sendOtp($user);//OTP SEND
                $token 		=  $user->createToken('Callanalog-API')->plainTextToken;
                $response 	= $user->toArray();
                $response['token'] = $token;
                DB::commit();
                return $this->output(true, 'User registered successfully.', $response);
            }else{
                DB::commit();
                return $this->output(false, 'This email id already register with us. Please choose another email to register or login with same email.');
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }

    public function createUser(Request $request){

        $validator = Validator::make($request->all(), [
            //'company_id'=> 'required|max:500|exists:companies,id',
            'company_id'=> 'required_if:role_id,6|exists:companies,id',
            'name'      => 'required|max:255',
            'email'     => 'required|email|max:255|unique:users|unique:companies',
            'mobile'    => 'required|string|unique:users|unique:companies',
			'address'	=> 'required|max:500',
			'country_id'=> 'required',
			'state_id'	=> 'required',
			'city'		=> 'required',
			'zip'		=> 'required', 
			'role_id'	=> 'required|numeric|in:2,3,5,6',
            //'account_code'  => 'required|max:500|unique:users', 
        ],[
            'company_id' => 'The company field is required when you are creating company user',
            'role_id' => 'The selected role is invalid!',
            'email.unique' => 'This email ID is already registered. Please try with different email ID.',
            'mobile.unique' => 'This mobile number is already registered. Please try with different mobile number.',
            'company_name.unique' => 'This company name is already registered. Please try with different company name.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }       
        // Start transaction!
        try {       
            DB::beginTransaction();
            $user = User::where('email', $request->email)->first();        
            if(!$user){
                $random_pass = Str::random(10);
                $user = User::create([
                    'company_id'    => $request->company_id,
                    //'account_code'  => $request->account_code,
                    'name'          => $request->name,
                    'email'         => $request->email,
                    'mobile'        => $request->mobile,
                    'password'      => Hash::make($random_pass),
                    'address'       => $request->address,
                    'country_id'    => $request->country_id,
                    'state_id'      => $request->state_id,
                    'city'          => $request->city,
                    'zip'           => $request->zip,
                    'is_verified'   => 1,
                    'is_verified_doc'=> 1,
                    'role_id'       => $request->role_id,
                    'status'        => isset($request->status) ? $request->status : 1,
                ]);

                DB::table('users_roles')->insert([
                    'user_id'   => $user->id,
                    'role_id'   => $request->role_id,
                ]);

                $this->sendPassword($user, $random_pass);//PASSWORD SEND
                $response = $user->toArray();
                DB::commit();
                return $this->output(true, 'User registered successfully. Please find the login details on user email ID.', $response);
            }else{
                DB::commit();
                return $this->output(false, 'This email id already register with us. Please choose another email to register or login with same email.');
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            return $this->output(false, $e->getMessage());
            //throw $e;
        }
    }
	

	public function sendPassword($user, $random_pass){
        
		if($random_pass){
			$data['email'] = $user->email;
			$data['title'] = 'Mail Password';
			$data['body'] = 'Your password is:- '.$random_pass;
			/* Mail::send('mailVerification',['data'=>$data],function($message) use ($data){
				$message->to($data['email'])->subject($data['title']);
			}); */
            dispatch(new \App\Jobs\SendEmailJob($data));
		}else{
			return $this->output(false, 'Error occurred in Password creation. Try after some time.');
		}
    }

	public function sendOtp($user){
        $otp = rand(100000,999999);
        $time = time();
		$newOTP = EmailVerification::updateOrCreate(
				['email' => $user->email],
				['email' => $user->email,
				 'otp' => $otp,
				 'created_at' => $time]
			);
		if($newOTP){
			$data['email'] = $user->email;
			$data['title'] = 'Mail Verification';
			$data['body'] = 'Your OTP is:- '.$otp;
			/* Mail::send('mailVerification',['data'=>$data],function($message) use ($data){
				$message->to($data['email'])->subject($data['title']);
			}); */
            dispatch(new \App\Jobs\SendEmailJob($data));
		}else{
			return $this->output(false, 'Error occurred in OTP creation. Try after some time.');
		}
    }
	
	
	public function verifyEmailIdByOTP(Request $request){
		$validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|exists:email_verifications',
            'otp' => 'required|numeric|digits:6'
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $user = User::where('email',$request->email)->first();
        $otpData = EmailVerification::where('otp',$request->otp)->first();
        if(!$otpData){
			return $this->output(false, 'You entered wrong OTP.', [], 409);
        }else{
            $currentTime = time();
            $time = $otpData->created_at;
            if($currentTime >= $time && $time >= $currentTime - (300+5)){//5 Min
                User::where('id',$user->id)->update([
                    'email_verified_at' => now(),
                    'is_verified' => 1,
                    'status' => 1
                ]);
                Company::where('id',$user->company_id)->update([
                    'status' => 1
                ]);
				return $this->output(true, 'Mail has been verified.', [], 202);                
            }else{
				return $this->output(false, 'Your OTP has been Expired!', [], 410);
            }
        }
    }

    public function resendOtp(Request $request){
		$validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|exists:email_verifications'            
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $user = User::where('email',$request->email)->first();
        $otpData = EmailVerification::where('email',$request->email)->first();
        $currentTime = time();
        $time = $otpData->created_at;
        if($currentTime >= $time && $time >= $currentTime - (300+5)){//5 Min
			return $this->output(false, 'Please try after some time.');
        }else{
            $this->sendOtp($user);//OTP SEND
			return $this->output(true, 'OTP has been sent.');			
        }
    }
	
	
	public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required'
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }     
        $user = User::with('country:id,country_name,phone_code,currency_symbol')
                ->with('state:id,state_name') 
                ->with('roles')   
                ->where('email', $request->email)->first();
		if ($user) {
            if ($user->is_verified == 1) {
                if((isset($user->company->status) && $user->company->status == 1) || in_array($user->roles->first()->name, array('Super Admin', 'Support','NOC'))){
                    if($user->status == 1){                    
                        if (Hash::check($request->password, $user->password)) {
                            $token =  $user->createToken('Callanalog API')->plainTextToken;
                            $response = $user->toArray();                            
                            $response['token'] = $token;
                            return $this->output(true, 'Login successfull', $response);						
                        }else{
                            return $this->output(false, 'Invalid password!', [], 409);
                        }                        
                    } else {
                        return $this->output(false, 'Your account has been suspended. Please contact with support.', [], 423);					
                    }
                }else{
                    return $this->output(false, 'Your company account has been suspended. Please contact with support.', [], 423);                
                }
            }else{
                return $this->output(false, 'Email Id is not verifie!', [], 403);
            }
        } else {
            return $this->output(false, 'Email Id dose not exist!', [], 404);
        }        
    }

    public function getProfile(Request $request){
		return $request->user();
	}
    	
	public function logout(Request $request) {
        //$request->user()->tokens()->delete();  // delete all tokens
        $request->user()->currentAccessToken()->delete();
        return $this->output(true, 'You have been successfully logged out!');
    }

    public function passwordChange(Request $request){
		
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|confirmed',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
		
        if(!Hash::check($request->current_password, auth()->user()->password)){
            return $this->output(false, "Current Password Doesn't match!", [], 409);
        }
        User::whereId(auth()->user()->id)->update([
        'password' => Hash::make($request->password)
        ]);
        return $this->output(true, 'Password has updated successfully');
    }
	

    public function updateUser(Request $request, $id){
		$User = User::find($id);
		if(is_null($User)){
			return $this->output(false, 'This User not exist with us. Please try again!.', [], 404);
		}else{
			$validator = Validator::make($request->all(), [
				'name'      => 'required|string',
				'mobile'    => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:9',
				'address'   => 'required|string|max:255',
				'country_id'=> 'required|numeric',
				'state_id'  => 'required|numeric',
				'city'      => 'required|string|max:150',
				'zip'       => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:6',
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$User->name     = $request->name;
			$User->mobile   = $request->mobile;
			$User->address 	= $request->address;
			$User->country_id= $request->country_id;
			$User->state_id = $request->state_id;
			$User->city     = $request->city;
            $User->zip      = $request->zip;
			$User->status 	= isset($request->status) ? $request->status : 0;
			$UsersRes 		= $User->save();

			if($UsersRes){
				$User = User::where('id', $id)->first();        
				$response = $User->toArray();
				return $this->output(true, 'User updated successfully.', $response, 200);
			}else{
				return $this->output(false, 'Error occurred in User Updating. Please try again!.', [], 200);
			}
		}
	}
}
