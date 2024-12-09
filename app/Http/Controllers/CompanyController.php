<?php

namespace App\Http\Controllers;

use App\Models\RechargeHistory;
use App\Traits\ManageNotifications;
use Auth;
use Illuminate\Http\Request;

use App\Models\Company;
use App\Models\User;
use App\Models\Server;
use App\Models\UserRegisteredServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Validator;
use Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rules\Password;

class CompanyController extends Controller
{
    use ManageNotifications;
    public function __construct()
    {
    }

    public  function registrationByAdminOrReseller(Request $request)
    {
        $AuthUser = Auth::user();
        $validator = Validator::make($request->all(), [
            'parent_id'     => 'required',
            'plan_id'       => 'required',
            'company_name'  => 'required|max:500|unique:companies',
            'name'          => 'required|max:255',
            'email'         => 'required|email|max:255|unique:users|unique:companies',
            'mobile'        => 'required|string|unique:users',
            'account_code'  => 'required|max:500|unique:companies',
            'address'       => 'required|max:500',
            'country_id'    => 'required',
            'state_id'      => 'required',
            'city'          => 'required',
            'zip'           => 'required',
            'password' 		=> [
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
            // 'password'       => 'required|confirmed',
            // 'inbound_permission' => 'required',
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
                    'inbound_permission' => $request->inbound_permission ?? "1,2,3,4",
                    'status'         => '1',
                ]);
                //dd($company);
                //$random_pass = Str::random(10);
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
                    'email_verified_at' => Carbon::now(),
                    'mobile_verified_at' => Carbon::now(),
                    'is_verified' => '1',
                    'role_id'     => '4', //$request->role_id,
                    'status'     => '1',
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

                if (isset($request->one_go_user)) {
                    DB::table('one_go_user_steps')->insert([
                        'parent_id' => $company->parent_id,
                        'company_id'=> $company->id,
                        'user_id'   => $user->id,
                        'step_no'   => 1,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                    $user['user_type'] = ($request->parent_id > 1) ? 'Reseller' : 'Company';
                    $user['parent_id'] = $request->parent_id;
                }

                $this->sendPassword($user, $request->password); //PASSWORD SEND
                //$token 		=  $user->createToken('Callanalog-API')->plainTextToken;
                $response     = $user->toArray();
                //$response['token'] = $token;
                DB::commit();


                /**
                 *  Notification code
                 */
                $subject = 'New Company Created'; 
                $message = 'Company name '.$request->company_name.' / '.$request->email.' has been Created'; 
                $type = 'info';
                $notifyUserType = ['super-admin', 'support', 'noc'];
                $notifyUser = array();
                if($request->parent_id > 1 ){
                    $notifyUserType[] = 'reseller';
                    $notifyUser['reseller'] = $request->parent_id;
                }

                $res = $this->addNotification($AuthUser, $subject, $message, $type, $notifyUserType, $notifyUser);
                if(!$res){
                    Log::error('Notification not created when user role: '.$AuthUser->role_id.' in registrationByAdminOrReseller method.');
                }
                /**
                 * End of Notification code
                 */


                return $this->output(true, 'User registered successfully.', $response);
            } else {
                DB::commit();
                return $this->output(false, 'This email id already register with us. Please choose another email to register or login with same email.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            // return $this->output(false, $e->getMessage());
            Log::error('Error in company registration By admin or reseller : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Error Occurred in adding company.', [], 406);
            //throw $e; 
        }
    }

    public function getAllCompanyOLD(Request $request)
    {
        $user = \Auth::user();
        $company_id = $request->id ?? "";
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($company_id) {
                $data = Company::select('*')
                    ->with('country:id,country_name')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->where('id', $company_id)->first();
                //->where('status', 1)         
            } else {
                $data = Company::select()
                    ->with('country:id,country_name,iso3')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
        } elseif ($user->roles->first()->slug == 'reseller') {

            if ($company_id) {
                $data = Company::select('*')
                    ->with('country:id,country_name')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->where('id', $company_id)
                    ->where('parent_id', $user->id)->first();
                //->where('status', 1)         
            } else {
                $data = Company::select()
                    ->with('country:id,country_name,iso3')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->where('parent_id', $user->id)
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
        } else {
            $data = Company::select('*')
                ->with('country:id,country_name')
                ->with('state:id,state_name,state_code')
                ->with('user_plan:id,name')
                ->where('id', $user->company_id)->first();
        }

        if (!is_null($data)) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllActiveCompany(Request $request)
    {
        $data = Company::select()->where('status', 1)->get();
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray(), 200);
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
        $AuthUser = Auth::user();
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $Company = Company::find($id);
        if (is_null($Company)) {
            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 200);
        } else {
            $Company->status = $request->status;
            $companyRes = $Company->save();
            if ($companyRes) {
                if ($request->status == '0') {
                    $User = User::where('company_id', $id)
                        ->update(['status' => '0']);
                    $resMessage = 'Company and all User has been disabled successfully.';
                } else {
                    if ($request->status == '1') {
                        $User = User::where('company_id', $id)->where('role_id', 4)
                            ->orderBy('id', 'asc')->limit(1)
                            ->update(['status' => '1']);
                        $resMessage ='Company and admin User status has been activated successfully.';
                    }
                }

                /**
                 *  Notification code
                 */
                if ($request->status == '0') {
                    $subject = 'Company has disabled'; 
                    $message = 'Company '.$Company->company_name.' / '.$Company->email.' and all users has been disabled'; 
                }
                if ($request->status == '1') {
                    $subject = 'Company has activated'; 
                    $message = 'Company '.$Company->company_name.' / '.$Company->email.' has been activated'; 
                }
                
                $type = 'info';
                $notifyUserType = ['super-admin', 'support', 'noc'];
                $notifyUser = array();
                if($Company->parent_id > 1 ){
                    $notifyUserType[] = 'reseller';
                    $notifyUser['reseller'] = $Company->parent_id;
                }

                $res = $this->addNotification($AuthUser, $subject, $message, $type, $notifyUserType, $notifyUser);
                if(!$res){
                    Log::error('Notification not created when user role '.$AuthUser->role_id.' in update company status.');
                }
                /**
                 * End of Notification code
                 */

                return $this->output(true, $resMessage);
            } else {
                return $this->output(false, 'Error occurred in company status updating. Please try again!.', [], 409);
            }
        }
    }

    public function updateCompany(Request $request, $id)
    {
        $Company = Company::find($id);
        if (is_null($Company)) {
            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 404);
        } else {
            $validator = Validator::make($request->all(), [
                'billing_address'   => 'required|string|max:255',
                'country_id'    => 'required|numeric',
                'state_id'      => 'required|numeric',
                'city'          => 'required|string|max:150',
                'zip'           => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:6',
                'inbound_permission' => 'required',
                'outbound_call' => 'required',
                'tariff_id'     => 'required_if:outbound_call,1',
            ], [
                'tariff_id' => 'The tarrif field is required when outbound call is yes',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            $Company->billing_address       = $request->billing_address;
            $Company->country_id = $request->country_id;
            $Company->state_id  = $request->state_id;
            $Company->city      = $request->city;
            $Company->zip       = $request->zip;
            $Company->inbound_permission    = $request->inbound_permission;
            $Company->outbound_call         = $request->outbound_call;
            $Company->tariff_id             = ($request->outbound_call == 1) ? $request->tariff_id : NULL;
            $CompanysRes                    = $Company->save();

            if ($CompanysRes) {
                $Company = Company::where('id', $id)->first();
                $response = $Company->toArray();
                return $this->output(true, 'Company updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in Company Updating. Please try again!.', [], 409);
            }
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

    public function getBalance(Request $request, $id)
    {
        $Company = Company::find($id);
        if (is_null($Company)) {
            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 404);
        } else {
            $balance_result = Company::select('balance')->where('id', $Company->id)->first();
            return $this->output(true, 'success', $balance_result->toArray(), 200);
        }
    }

    public function getAllActiveCompanyOfReseller(Request $request, $id)
    {
        $data = Company::select('id', 'company_name', 'account_code', 'email')
            ->where('parent_id', $id)
            ->where('status', 1)->get();
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllCompany(Request $request)
    {
        $user = \Auth::user();
        $company_id = $request->id ?? "";
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $params = $request->params ?? "";
       // $type = $request->type ?? "";
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($company_id) {
                $data = Company::select('*')
                    ->with('country:id,country_name')
                    ->with('userRegisteredServer.server')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->with('user:id,company_id,name,email')
                    ->where('id', $company_id)->first();
                //->where('status', 1)         
            } elseif ($params !== "") {
                $query = Company::select()
                    ->with('country:id,country_name,iso3')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->with('userRegisteredServer.server')
                    ->with('user:id,company_id,name,email');
                    if ($request->get('type') && $request->get('type') == 'rc') {
                        $query->where(function($query) use($params) {
							$query->where('parent_id', '>', 1);
						});                        
                    }
                    if ($request->get('type') && $request->get('type') == 'nc') {
                        $query->where(function($query) use($params) {
							$query->where('parent_id', 1);
						});                        
                    }
                    $query->where(function($query) use($params) {
                        $query->where('company_name', 'LIKE', "%$params%")
                        ->orWhere('email', 'LIKE', "%$params%")
                        ->orWhere('mobile', 'LIKE', "%$params%")
                        ->orWhereHas('country', function ($query) use ($params) {
                            $query->where('country_name', 'LIKE', "%$params%");
                        });
                    });
                    $data = $query->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                    //return $query->ddRawSql();
            } else {
                $query = Company::select('*')
                    ->with('country:id,country_name')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->with('userRegisteredServer.server')
                    ->with('user:id,company_id,name,email');
                    
                    if ($request->get('type') && $request->get('type') == 'rc') {
                        $query->where(function($query) use($params) {
							$query->where('parent_id', '>', 1);
						});                        
                    }
                    if ($request->get('type') && $request->get('type') == 'nc') {
                        $query->where(function($query) use($params) {
							$query->where('parent_id', 1);
						});                        
                    }
                    $data = $query->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                   // return $data->ddRawSql();   
            }
        } elseif ($user->roles->first()->slug == 'reseller') {

            if ($company_id) {
                $data = Company::select('*')
                    ->with('country:id,country_name')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->with('user:id,company_id,name,email')
                    ->with('userRegisteredServer.server')
                    ->where('id', $company_id)
                    ->where('parent_id', $user->id)->first();
                //->where('status', 1)         
            } elseif ($params !== "") {
                $data = Company::select()
                    ->with('country:id,country_name,iso3')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->with('userRegisteredServer.server')
                    ->with('user:id,company_id,name,email')
                    ->where('parent_id', $user->id)
                    ->where(function($query) use($params) {
                        $query->where('company_name', 'LIKE', "%$params%")
                        ->orWhere('email', 'LIKE', "%$params%")
                        ->orWhere('mobile', 'LIKE', "%$params%")
                        ->orWhereHas('country', function ($query) use ($params) {
                            $query->where('country_name', 'like', "%{$params}%");
                        });
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $data = Company::select()
                    ->with('country:id,country_name,iso3')
                    ->with('state:id,state_name,state_code')
                    ->with('user_plan:id,name')
                    ->with('user:id,company_id,name,email')
                    ->with('userRegisteredServer.server')
                    ->where('parent_id', $user->id)
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }
        } else {
            $data = Company::select('*')
                ->with('country:id,country_name')
                ->with('state:id,state_name,state_code')
                ->with('user_plan:id,name')
                ->with('user:id,company_id,name,email')
                ->with('userRegisteredServer.server')
                ->where('id', $user->company_id)->first();
        }

        if (!is_null($data)) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function AddbalanceForCompanyBySuperAdmin(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|numeric',
                'amount' => 'required|numeric',
            ],  [
                'company_id' => 'Company name is Required.',
            ]);

            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $companydataforbalanceupdate = Company::where('id', '=', $request->company_id)->first();
                if (is_null($companydataforbalanceupdate)) {
                    return $this->output(true, 'Something Went Wrong!!', []);
                } else {

                    $rechargeHistory_data = RechargeHistory::create([
                        'company_id' => $request->company_id,
                        'user_id'    => $user->id,
                        'invoice_id' => 0,
                        'invoice_number' => 'Added by Admin',
                        'current_balance' => $companydataforbalanceupdate->balance,
                        'added_balance'   => $request->amount,
                        'total_balance'   => $companydataforbalanceupdate->balance + $request->amount,
                        'currency'        => 'USD',
                        'payment_type'    => 'Super Admin',
                        'recharged_by'    => 'Admin'
                    ]);
                    if (!$rechargeHistory_data) {
                        return $this->output(false, 'Failed to Create Recharge History!!.', 400);
                    } else {
                        $companydataforbalanceupdate->balance += $request->amount;
                        $resCompanyBalance = $companydataforbalanceupdate->save();
                        if ($resCompanyBalance) {

                            /**
                             *  Notification code
                             */
                            $subject = 'Add Balance'; 
                            $message = 'Wallet balance added by: '.$user->name .' ('.$user->roles->first()->slug.') to Company '.$companydataforbalanceupdate->company_name.' / '.$companydataforbalanceupdate->email;
                            $type = 'info';
                            $notifyUserType = ['super-admin', 'support', 'noc'];
                            $notifyUser = array();
                            $CompanyUser = User::where('company_id', $request->company_id)
                                        ->where('role_id', 4)->first();
                                                
                            $notifyUserType[] = 'admin';
                            $notifyUser['admin'] = $CompanyUser->id; 
                            

                            $res = $this->addNotification($user, $subject, $message, $type, $notifyUserType, $notifyUser);
                            if(!$res){
                                Log::error('Notification not created when user role: '.$user->role_id.' in AddbalanceForCompanyBySuperAdmin method.');
                            }
                            /**
                             * End of Notification code
                             */


                            return $this->output(true, 'Amount Added successfully!', $resCompanyBalance, 200);
                        } else {
                            return $this->output(false, 'Error occurred While adding Amount. Please try again!.', [], 200);
                        }
                    }
                }
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }



    public function getAllActiveCompanyOfResellersByPlanId(Request $request, $reseller_id, $plan_id)
    {
        $data = Company::select('id', 'company_name', 'account_code', 'email')
                        ->where('parent_id', $reseller_id)
                        ->where('plan_id',  $plan_id)
                        ->where('status', 1)->get();
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function changeCompanyRegisteredServer(Request $request)
    {
        try { 
            $user = \Auth::user();
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $validator = Validator::make($request->all(), [
                    'company_id'    => 'required|numeric|exists:companies,id',
                    'server_id'     => 'required||numeric|exists:servers,id',
                ]);

                if ($validator->fails()) {
                    return $this->output(false, $validator->errors()->first(), [], 409);
                } else {
                    $Server = Server::select()
                            ->where('id', $request->server_id)
                            ->where('status', '=', 1)->first(); 
                    if($Server) {
                        $UserRegisteredServer = UserRegisteredServer::select()
                                        ->where('company_id', $request->company_id)
                                        ->first();
                        if($UserRegisteredServer){
                            $UserRegisteredServer->server_id = $request->server_id;
                            $UserRegisteredServer->domain = $Server->ip;
                            $UserRegisteredServer->save();
                            $response = [];
                            return $this->output(true, 'Server updated successfully.', $response, 200);
                        }else{
                            return $this->output(false, 'Company not registered on any server. Please contact with development team!.', [], 200); 
                        }

                    }else{
                        return $this->output(false, 'Server is disabled. Please contact with development team!.', [], 200);  
                    }
                }
            } else {
                return $this->output(false, 'Unauthorized user action.', [], 403);
            }
        
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in Server Updating for company: ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
