<?php

namespace App\Http\Controllers;

use App\Traits\ManageNotifications;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\Server;
use App\Models\Trunk;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use Validator;
use Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;
use Crypt;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{
    use ManageNotifications;
    public function __construct() {}

    public function getUser(Request $request)
    {
        $user_id = $request->id ?? NULL;
        $params = $request->params ?? "";
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $dataQuery = User::select()
                ->with('company:id,company_name')
                ->with('user_role:id,name')
                ->with('country:id,country_name')
                ->with('state:id,state_name,state_code');

            if ($params != "") {
                $dataQuery = $dataQuery->orWhereHas('company', function ($query) use ($params) {
                    $query->where('company_name', 'like', "%{$params}%");
                })
                    ->orWhereHas('country', function ($query) use ($params) {
                        $query->where('country_name', 'like', "%{$params}%");
                    })
                    ->orWhere('email', 'LIKE', "%$params%")
                    ->orWhere('mobile', 'LIKE', "%$params%")
                    ->orWhere('name', 'LIKE', "%$params%");
            }
            if ($user_id) {
                $data = $dataQuery->where('id', $user_id)->first();
            } else {
                $data = $dataQuery->orderBy('id', 'DESC')->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
        } elseif ($user->roles->first()->slug == 'admin') {
            $dataQuery = User::select()
                ->with('company:id,company_name')
                ->with('user_role:id,name')
                ->with('country:id,country_name')
                ->with('state:id,state_name,state_code')
                ->where('company_id', $user->company_id);
            if ($params != "") {
                $dataQuery->where(function ($query) use ($params) {
                    $query->where('name', 'like', "%{$params}%")
                        ->orWhere('mobile', 'LIKE', "%$params%")
                        ->orWhere('email', 'LIKE', "%$params%")
                        ->orWhereHas('country', function ($query) use ($params) {
                            $query->where('country_name', 'like', "%{$params}%");
                        });
                });
                /*
                $dataQuery = $dataQuery->whereHas('company', function ($query) use ($params) {
                    $query->where('company_name', 'like', "%{$params}%");
                });
                $dataQuery = $dataQuery->orWhere('email', 'LIKE', "%$params%")
                        ->orWhere('mobile', 'LIKE', "%$params%")
                        ->orWhere('name', 'LIKE', "%$params%"); 
                        */
            }
            if ($user_id) {
                $data = $dataQuery->where('id', $user_id)->first();
            } else {
                $data = $dataQuery->orderBy('id', 'DESC')->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
        } else {
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
        $data = User::select('id','name')
                ->where('status', 1)
                ->whereIn('role_id', [3,2])->get();
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }



    public function getAllResellerUsers(Request $request)
    {
        $user_id = $request->id ?? NULL;
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $dataQuery = User::select()
                ->with('company:id,company_name')
                ->with('user_role:id,name')
                ->with('country:id,country_name')
                ->with('state:id,state_name,state_code')
                ->where('role_id', 5);

            if ($user_id) {
                $data = $dataQuery->where('id', $user_id)->first();
            } else {
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
        } else {
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
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }




    /**
     * Change User Status.
     *
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $User = User::find($id);
        if (is_null($User)) {
            return $this->output(false, 'This user not exist with us. Please try again!.', [], 200);
        } else {
            $User->status = $request->status;
            $UserRes = $User->save();
            if ($UserRes) {
                $User = User::where('id', $id)->first();
                $response = $User->toArray();
                return $this->output(true, 'User updated successfully.', $response, 200);
            } else {
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
            'name'             => 'required|max:255',
            'email'         => 'required|email|max:255|unique:users|unique:companies',
            'mobile'        => 'required|numeric|unique:users',
            'account_code'  => 'required|max:500|unique:companies',
            'address'        => 'required|max:500',
            'country_id'    => 'required',
            'state_id'        => 'required',
            'city'            => 'required',
            'zip'            => 'required',
            'password'         => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'plan_id'       => 'Plan type is required!',
            'parent_id'     => 'parent ID is required.',
            'email.unique'  => 'This email ID is already registered. Please try with different email ID.',
            'mobile.unique' => 'This mobile number is already registered. Please try with different mobile number.',
            'company_name.unique' => 'This Company name is already registered. Please try with different Company name.',
            'account_code.unique' => 'This mobile number is already registered. Please try with different mobile number.',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try {
            DB::beginTransaction();
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                $Trunk = Trunk::select('id')->where('status', 1)->where('type', 'Inbound')->pluck('id');
                $Trunk_ids = '';
                foreach ($Trunk as $key => $Trunk_id) {
                    $Trunk_ids .= $Trunk_id . ',';
                }
                $company = Company::create([
                    'plan_id'       => $request->plan_id,
                    'parent_id'     => $request->parent_id,
                    'company_name'    => $request->company_name,
                    'account_code'  => $request->account_code,
                    'email'            => $request->email,
                    'mobile'           => $request->mobile,
                    'billing_address' => $request->address,
                    'country_id'     => $request->country_id,
                    'state_id'         => $request->state_id,
                    'city'             => $request->city,
                    'zip'             => $request->zip,
                    'inbound_permission' => rtrim($Trunk_ids, ','),
                ]);
                //dd($company);
                $user = User::create([
                    'company_id' => $company->id,
                    //'account_code' => $request->account_code,
                    'name'         => $request->name,
                    'email'     => $request->email,
                    'mobile'     => $request->mobile,
                    'password'     => Hash::make($request->password),
                    'address'     => $request->address,
                    'country_id' => $request->country_id,
                    'state_id'     => $request->state_id,
                    'city'         => $request->city,
                    'zip'         => $request->zip,
                    'role_id'     => '4', //$request->role_id,
                ]);
                DB::table('users_roles')->insert([
                    'user_id'   => $user->id,
                    'role_id'   => 4,
                ]);
                $Server = false;
                $user_registered =  DB::table('user_registered_servers')
                    ->select(['id', 'server_id'])
                    ->orderBy('id', 'DESC')
                    ->limit(1)->first();
                if ($user_registered) {
                    $Server =   Server::select('id', 'domain', 'ip')
                        ->where('status', '=', 1)
                        ->where('id', '>', $user_registered->server_id)
                        ->limit(1)->get()->toArray();
                }
                if (!$Server) {
                    $ServerObj = Server::select('id', 'domain', 'ip')->where('status', '=', 1)->orderBy('id', 'ASC')->first();
                    $Server = $ServerObj->toArray();
                    DB::table('user_registered_servers')->insert([
                        'server_id'   => $Server['id'],
                        'company_id'   => $company->id,
                        'domain'   => $Server['ip'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                } else {
                    DB::table('user_registered_servers')->insert([
                        'server_id'   => $Server[0]['id'],
                        'company_id'   => $company->id,
                        'domain'   => $Server[0]['ip'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                $this->sendOtp($user); //OTP SEND
                $ipAddress = $request->ip();
                $token         =  $user->createToken('Callanalog-API')->plainTextToken;
                $useragent = $request->header('User-Agent');
                DB::table('personal_access_tokens')
                    ->where('token', hash('sha256', explode('|', $token)[1]))  // Look up the token by its hashed value
                    ->update(['ip_address' => $ipAddress, 'user_agent' => $useragent]);
                $response     = $user->toArray();
                $response['token'] = $token;
                DB::commit();

                $subject = 'Company Registration'; 
                $message = 'A new company has been registered: '.$request->company_name.' / '. $request->email; 
                $type = 'info';
                $notifyUserType = ['super-admin', 'support', 'noc'];
                $res = $this->addNotification($user, $subject, $message, $type, $notifyUserType);
                if(!$res){
                    Log::error('Notification not created when user role '.$user->role_id.' in company registration');
                }

                return $this->output(true, 'User registered successfully.', $response);
            } else {
                DB::commit();
                return $this->output(false, 'This email id already register with us. Please choose another email to register or login with same email.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }

    public function createUser(Request $request)
    {
        $AuthUser = \Auth::user();
        $validator = Validator::make($request->all(), [
            //'company_id'=> 'required|max:500|exists:companies,id',
            'company_id' => 'required_if:role_id,6|exists:companies,id',
            'name'      => 'required|max:255',
            'email'     => 'required|email|max:255|unique:users|unique:companies',
            'mobile'    => 'required|numeric|unique:users|unique:companies',
            'address'    => 'required|max:500',
            'country_id' => 'required',
            'state_id'    => 'required',
            'city'        => 'required',
            'zip'        => 'required',
            'role_id'    => 'required|numeric|in:2,3,5,6',
            'password'     => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            //'password' 	=> 'required|confirmed',
            //'account_code'  => 'required|max:500|unique:users', 
        ], [
            'company_id' => 'The company field is required when you are creating company user',
            'role_id' => 'The selected role is invalid!',
            'email.unique' => 'This email ID is already registered. Please try with different email ID.',
            'mobile.unique' => 'This mobile number is already registered. Please try with different mobile number.',
            'company_name.unique' => 'This company name is already registered. Please try with different company name.',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try {
            DB::beginTransaction();
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                $random_pass = Str::random(10);
                $user = User::create([
                    'company_id'    => $request->company_id,
                    //'account_code'  => $request->account_code,
                    'name'          => $request->name,
                    'email'         => $request->email,
                    'mobile'        => $request->mobile,
                    'password'      => Hash::make($request->password),
                    'address'       => $request->address,
                    'country_id'    => $request->country_id,
                    'state_id'      => $request->state_id,
                    'city'          => $request->city,
                    'zip'           => $request->zip,
                    'is_verified'   => 1,
                    'is_verified_doc' => 1,
                    'role_id'       => $request->role_id,
                    'status'        => isset($request->status) ? $request->status : 1,
                ]);

                DB::table('users_roles')->insert([
                    'user_id'   => $user->id,
                    'role_id'   => $request->role_id,
                ]);
                if ($request->role_id === "5") {
                    DB::table('reseller_wallets')->insert([
                        'user_id'   => $user->id,
                        'balance'   => 0,
                    ]);
                }
                $this->sendPassword($user, $request->password); //PASSWORD SEND
                $response = $user->toArray();
                DB::commit();

                /**
                 *  Notification code
                 */
                $subject = 'New User Created'; 
                $message = 'User name: '.$request->name.' has been Created'; 
                $type = 'info';
                $notifyUserType = ['super-admin', 'support', 'noc'];
                $notifyUser = array();
                if($request->role_id == 6){
                    $notifyUserType[] = 'admin';
                    $CompanyUser = User::where('company_id', $request->company_id)
                                    ->where('role_id', 4)->first();
                    /* if($CompanyUser->company->parent_id > 1 ){
                        $notifyUserType[] = 'reseller';
                        $notifyUser['reseller'] = $CompanyUser->company->parent_id;
                    } */
                    $notifyUser['admin'] = $CompanyUser->id; 
                }

                $res = $this->addNotification($AuthUser, $subject, $message, $type, $notifyUserType, $notifyUser);
                if(!$res){
                    Log::error('Notification not created when user role: '.$AuthUser->role_id.'  Create new user.');
                }
                /**
                 * End of Notification code
                 */
                return $this->output(true, 'User registered successfully. Please find the login details on user email ID.', $response);
            } else {
                DB::commit();
                return $this->output(false, 'This email id already register with us. Please choose another email to register or login with same email.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in User Creating : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong in user creation, Please try after some time.', [], 409);
            //throw $e;
        }
    }


    public function sendPassword($user, $random_pass)
    {

        if ($random_pass) {
            $data['email'] = $user->email;
            $data['title'] = 'Mail Password';
            $data['body'] = 'Your password is:- ' . $random_pass;
            /* Mail::send('mailVerification',['data'=>$data],function($message) use ($data){
				$message->to($data['email'])->subject($data['title']);
			}); */
            dispatch(new \App\Jobs\SendEmailJob($data));
        } else {
            return $this->output(false, 'Error occurred in Password creation. Try after some time.');
        }
    }

    public function sendOtp($user)
    {
        $otp = rand(100000, 999999);
        $time = time();
        $newOTP = EmailVerification::updateOrCreate(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'otp' => $otp,
                'created_at' => $time
            ]
        );
        if ($newOTP) {
            $data['email'] = $user->email;
            $data['title'] = 'Mail Verification';
            $data['body'] = 'Your OTP is:- ' . $otp;
            /* Mail::send('mailVerification',['data'=>$data],function($message) use ($data){
				$message->to($data['email'])->subject($data['title']);
			}); */
            dispatch(new \App\Jobs\SendEmailJob($data));
        } else {
            return $this->output(false, 'Error occurred in OTP creation. Try after some time.');
        }
    }


    public function verifyEmailIdByOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|exists:email_verifications',
            'otp' => 'required|numeric|digits:6'
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $user = User::where('email', $request->email)->first();
        $otpData = EmailVerification::where('otp', $request->otp)->first();
        if (!$otpData) {
            return $this->output(false, 'You entered wrong OTP.', [], 409);
        } else {
            $currentTime = time();
            $time = $otpData->created_at;
            if ($currentTime >= $time && $time >= $currentTime - (300 + 5)) { //5 Min
                User::where('id', $user->id)->update([
                    'email_verified_at' => now(),
                    'is_verified' => 1,
                    'status' => 1
                ]);
                Company::where('id', $user->company_id)->update([
                    'status' => 1
                ]);
                return $this->output(true, 'Mail has been verified.', [], 202);
            } else {
                return $this->output(false, 'Your OTP has been Expired!', [], 410);
            }
        }
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|exists:email_verifications'
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $user = User::where('email', $request->email)->first();
        $otpData = EmailVerification::where('email', $request->email)->first();
        $currentTime = time();
        $time = $otpData->created_at;
        if ($currentTime >= $time && $time >= $currentTime - (300 + 2)) { //2 Min
            return $this->output(false, 'Please try after 2 Minutes.');
        } else {
            $this->sendOtp($user); //OTP SEND
            return $this->output(true, 'OTP has been sent.');
        }
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $user = User::with('country:id,country_name,phone_code,currency_symbol')
            ->with('state:id,state_name')
            ->with('roles')
            ->with('userDocuments')
            ->with('reseller_wallets:id,user_id,balance')
            ->with(['company', 'company.user_plan:id,name'])
            ->where('email', $request->email)->first();
        if ($user) {
            if ($user->is_verified == 1) {

                if ((isset($user->company->status) && $user->company->status == 1) || in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc', 'reseller'))) {
                    if ($user->status == 1) {
                        if (Hash::check($request->password, $user->password)) {
                            $ipAddress = $request->ip();
                            $token =  $user->createToken('Callanalog API')->plainTextToken;
                            $useragent = $request->header('User-Agent');
                            DB::table('personal_access_tokens')
                                ->where('token', hash('sha256', explode('|', $token)[1]))  // Look up the token by its hashed value
                                ->update(['ip_address' => $ipAddress, 'user_agent' => $useragent]);
                            $response = $user->toArray();
                            $response['token'] = $token;
                            return $this->output(true, 'Login successfull', $response);
                        } else {
                            return $this->output(false, 'Invalid password!', [], 409);
                        }
                    } else {
                        return $this->output(false, 'Your account has been suspended. Please contact with support.', [], 423);
                    }
                } else {
                    return $this->output(false, 'Your company account has been suspended. Please contact with support.', [], 423);
                }
            } else {
                return $this->output(false, 'Email Id is not verifie!', [], 403);
            }
        } else {
            return $this->output(false, 'Email Id dose not exist!', [], 404);
        }
    }

    public function getProfile(Request $request)
    {
        return $request->user();
    }

    /* public function logout(Request $request)
    {
        //$request->user()->tokens()->delete();  // delete all tokens
        $request->user()->currentAccessToken()->delete();
        return $this->output(true, 'You have been successfully logged out!');
    } */
    public function logout(Request $request)
    {
        //$request->user()->tokens()->delete();  // delete all tokens
        $encryptedToken = $request->bearerToken();
        if (!$encryptedToken) {
            return response()->json(['error' => 'Token not provided'], 401);
        }
        $token = PersonalAccessToken::findToken($encryptedToken);
        $token->delete();
        return $this->output(true, 'You have been successfully logged out!');
    }

    public function passwordChange(Request $request)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'current_password'  => 'required',
                'password'             => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
                //'password' => 'required|confirmed',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            if (!Hash::check($request->current_password, auth()->user()->password)) {
                return $this->output(false, "Current Password Doesn't match!", [], 409);
            }
            User::whereId(auth()->user()->id)->update([
                'password' => Hash::make($request->password)
            ]);
            DB::commit();
            return $this->output(true, 'Password has updated successfully');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in self change password : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong in chnage password, Please try after some time.', [], 409);
            //throw $e;
        }
    }

    public function changePasswordBySuperadmin(Request $request)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'user_id'   => 'required',
                'password'     => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
                //'password' => 'required|confirmed',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            $AuthUser = \Auth::user();
            if (in_array($AuthUser->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $user = User::where('id', $request->user_id)->first();
                if ($user) {
                    $user->whereId($request->user_id)->update([
                        'password' => Hash::make($request->password)
                    ]);
                    DB::commit();
                    return $this->output(true, 'Password has updated successfully');
                } else {
                    DB::commit();
                    return $this->output(false, 'User dose not exist with us!', [], 404);
                }
            } else {
                DB::commit();
                return $this->output(false, 'You are not authorized user.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in change password By Auth(supperadmin, NOC, Support): ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong in change password, Please try after some time.', [], 409);
        }
    }

    public function updateUser(Request $request, $id)
    {
        $User = User::find($id);
        if (is_null($User)) {
            return $this->output(false, 'This User not exist with us. Please try again!.', [], 404);
        } else {
            $validator = Validator::make($request->all(), [
                'name'      => 'required|string',
                'mobile'    => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:9',
                'address'   => 'required|string|max:255',
                'country_id' => 'required|numeric',
                'state_id'  => 'required|numeric',
                'city'      => 'required|string|max:150',
                'zip'       => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:6',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            $User->name     = $request->name;
            $User->mobile   = $request->mobile;
            $User->address     = $request->address;
            $User->country_id = $request->country_id;
            $User->state_id = $request->state_id;
            $User->city     = $request->city;
            $User->zip      = $request->zip;
            $User->status     = isset($request->status) ? $request->status : 0;
            $UsersRes         = $User->save();

            if ($UsersRes) {
                $User = User::where('id', $id)->first();
                $response = $User->toArray();
                return $this->output(true, 'User updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in User Updating. Please try again!.', [], 200);
            }
        }
    }

    public function liveCallHangUp(Request $request)
    {
        $channel = $request->channel ?? NULL;

        $host = '85.195.76.161';
        $port = '5038';
        $username = 'TxuserGClanlg';
        $password = 'l3o9zMP3&X[k2+';
        $command = "Action: Logoff\r\n\r\n";
        $socket = fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            echo "Error connecting to Asterisk Manager Interface: $errstr ($errno)\n";
            exit(1);
        }
        fwrite($socket, "Action: Login\r\n");
        fwrite($socket, "Username: $username\r\n");
        fwrite($socket, "Secret: $password\r\n\r\n");
        fwrite($socket, "Action: Hangup\r\n");
        fwrite($socket, "Channel: $channel\r\n\r\n");
        fwrite($socket, $command);
        fclose($socket);

       // DB::table('live_calls')->where('agent_channel', $channel)->delete();
        
        return true;
    }

    public function getAllResellerlist(Request $request)
    {
        $user = \Auth::user();
        $params = $request->get('params', "");
        $reseller_id = $request->get('id', null);
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;

        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            if ($reseller_id) {
                $getAllReseller = User::with('reseller_wallets:user_id,balance')->where('id', $reseller_id)->where('role_id', '=', '5')->first();
            } else {
                if ($params !== "") {
                    return $getAllReseller = User::select('*')->with('country:id,country_name,iso3')->with('reseller_wallets:user_id,balance')
                        ->where('role_id', '=', '5')
                        ->where(function ($query) use ($params) {
                            $query->where('email', 'LIKE', "%$params%")
                                ->orWhere('name', 'LIKE', "%$params%")
                                ->orWhere('mobile', 'LIKE', "%$params%")
                                ->orWhereHas('country', function ($query) use ($params) {
                                    $query->where('country_name', 'like', "%{$params}%");
                                });
                        })
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                }
            }

            if (!is_null($getAllReseller)) {
                $dd = $getAllReseller->toArray();
                unset($dd['links']);
                return $this->output(true, 'Success', $dd, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized.', [], 403);
        }
    }

    public function getCompanyUserslist(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, ['admin'])) {
            $data = User::select('id','name','email')
                    ->where('company_id', $user->company_id)
                    ->where('status', 1)
                    ->where('role_id', 6)
                    ->get();
            if ($data->isNotEmpty()) {
                return $this->output(true, 'Success', $data->toArray());
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        }else{
            return $this->output(false, 'Sorry! You are not authorized.', [], 403);  
        }
    }
}
