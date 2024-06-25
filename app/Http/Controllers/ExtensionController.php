<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\VoiceMail;
use App\Models\Company;
use App\Models\Cart;
use App\Models\ConfTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Carbon\Carbon;

class ExtensionController extends Controller
{
    public function __construct(){

    }

    public function generateExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'extension_digit'    => 'nullable|numeric|between:5,10',
            'extension_number'   => 'required|numeric|max:50',
        ],[
            'extension_number'  => 'Total number of extention required.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        try { 
            DB::beginTransaction();
            $extension_digit = !empty($request->extension_digit) ? $request->extension_digit : 7;
            $extension_number = $request->extension_number;
            $number_array = $final_array =  array();
            while(1) {
                // generate unique random number 
                $numberArr = array('5'=>'99999','6'=>'999999','7'=>'9999999','8'=>'99999999','9'=>'999999999','10'=>'9999999999');
                $randomNumber = rand(1000, $numberArr[$extension_digit]);
                // pad the number with zeros (if needed)
                $paded = str_pad($randomNumber, $extension_digit, '0', STR_PAD_RIGHT);   
                $number_array[] = $paded;
                $number_array = array_unique($number_array);               
                
                if(count($number_array) > $extension_number-1) 
                {
                    $final_array = $this->checkExtensionsInDB($number_array);
                    if(count($final_array) > $extension_number-1) {   
                        break;
                    }else{                     
                        $number_array = $final_array;
                        continue;
                    }
                }
            }
            //print_r($number_array);            
            //print_r($final_array);exit;
            $response = implode(',',$final_array);
            DB::commit();
            return $this->output(true, 'Extensions generated successfully.', $response);
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }

    protected function checkExtensionsInDB(array $number_array)
    {
        $existExtension = Extension::select(['name'])->whereIn('name', $number_array)->get();            
        $exitArray = $existExtension->toArray();  
        $exitArray = array_column($exitArray, 'name');
        //print_r($exitArray);
        return $final_array = array_diff($number_array,$exitArray);
    }

    public function addExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id'        => 'required|numeric',
            'company_id'        => 'required|numeric',
            'name.*'            => 'required|unique:extensions,name',
            'callbackextension' => 'required|max:50',            
            'accountcode'       => 'required|max:50',
            'agent_name'        => 'required|max:150',
            'callgroup'         => 'required', // Outbound call yes or no
            'callerid'          => 'required_if:callgroup,1',                                    
            'secret'            => 'required',
            'barge'             => 'required', //Yes ro no(0,1)
            'mailbox'           => 'required', //voice mail yes or no
            'voice_email'       => 'required_if:mailbox,1',
        ],[
            'name'  => 'This Extension is already exist with us. Please try with different.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();         
            $input = $request->all();
            //$extension_name = explode(',',$input['extension_name']);
            $extension_name = $input['name'];
            $Company = Company::where('id', $request->company_id)->first();
            $reseller_id = '';
            if($Company->parent_id > 1){
                $price_for = 'Reseller';
                $reseller_id = $Company->parent_id;
            }else{
                $price_for = 'Company';
            }            
            $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $reseller_id, 'Extension');
            if($item_price_arr['Status'] == 'true'){
                $item_price = $item_price_arr['Extension_price'];
                if (is_array($extension_name)) {
                    $VoiceMail = $ids = [];
                    $status = '0';
                    $startingdate = $expirationdate = $host = $sip_temp = '';                    
                    if($Company->plan_id == 2){
                        $status = '1';
                        $startingdate = Carbon::now();
                        $expirationdate =  $startingdate->addDays(180);
                        $host = 'dynamic';
                        $sip_temp = 'WEBRTC';
                    }
                    foreach ($extension_name as $item) {
                        $data = $Cart = [];
                        $data = [
                            'country_id'        => $request->country_id,
                            'company_id'        => $request->company_id,
                            'name'	            => $item,
                            'callbackextension' => $request->callbackextension,
                            'accountcode'       => $request->accountcode,
                            'agent_name'        => $request->agent_name,
                            'callgroup'         => $request->callgroup,
                            'callerid' 	        => $request->callerid,
                            'secret' 	        => $request->secret,
                            'barge'             => $request->barge,
                            'mailbox'           => $request->mailbox,
                            'regexten'          => $item,
                            'startingdate'      => $startingdate,
                            'expirationdate'    => $expirationdate,
                            'fromdomain'        => 'NULL',
                            'amaflags'          => 'billing',
                            'canreinvite'       => 'no',
                            'context'           => 'callanalog',
                            'dtmfmode'          => 'RFC2833',
                            'host'              => $host,
                            'sip_temp'          => $sip_temp,
                            'insecure'          => 'port,invite',
                            'language'          => 'en',
                            'nat'               => 'force_rport,comedia',
                            'qualify'           => 'yes',
                            'rtptimeout'        => '60',
                            'rtpholdtimeout'    => '300',
                            'type'              => 'friend',
                            'username'          => $item, 
                            'disallow'          => 'ALL',
                            'allow'             => 'g729,g723,ulaw,gsm',
                            'created_at'        => Carbon::now(),
                            'updated_at'        => Carbon::now(),
                            'status'            => $status,
                        ];
                        $id = DB::table('extensions')->insertGetId($data);
                        $ids[] = $id;
                        if($Company->plan_id == 1){
                            $Cart = [
                                'company_id'        => $request->company_id,
                                'item_id'           => $id,
                                'item_number'       => $item,
                                'item_type'         => 'Extension',
                                'item_price'        => $item_price,
                            ];
                            $cartIds = DB::table('carts')->insertGetId($Cart);
                        }else{
                            $webrtc_template_url = config('app.webrtc_template_url');
                            $addExtensionFile = $webrtc_template_url;
                            $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                            $this->addExtensionInConfFile($request->name, $addExtensionFile, $request->secret, $Company->account_code,  $ConfTemplate->template_contents);
                        }
                        if($request->mailbox == '1'){
                            array_push($VoiceMail, [
                                'company_id'=> $request->company_id,
                                'context'   => 'default',
                                'mailbox'   => $item,
                                'fullname'  => $request->agent_name,
                                'email'     => $request->voice_email,
                                'timezone'  => 'central',
                                'attach'    => 'yes',
                                'review'    => 'no',
                                'operator'  => 'no',
                                'envelope'  => 'no',
                                'sayduration'   => 'no',
                                'saydurationm'  => '1',
                                'sendvoicemail' => 'no',
                                'nextaftercmd'  => 'yes',
                                'forcename'     => 'no',
                                'forcegreetings'=> 'no',
                                'hidefromdir'   => 'yes',
                                'created_at'    => Carbon::now(),
                                'updated_at'    => Carbon::now(),
                            ]);
                        }
                    }
                    // $Extensions = Extension::insert($data);
                    if($request->mailbox == '1'){
                        $VoiceMail = VoiceMail::insert($VoiceMail);            
                    }                    
                    $response 	= count($ids);//$Extensions;//->toArray();
                    DB::commit();
                    return $this->output(true, 'Extension added successfully.', $response);
                }else{
                    DB::commit();
                    return $this->output(false, 'Wrong extension value format.');
                }
            }else{
                DB::commit();
                return $this->output(false, $item_price_arr['Message']);
            } 
        } catch(\Exception $e)
        {
            DB::rollback();
            Log::error('Error in Extensions Inserting : ' . $e->getMessage());
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Error Occurred in adding extensions. Please try with different extension', [], 406);
            //throw $e; 
        }
    }


    public function getAllExtensions(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
        //echo $user->company_id;
		//if ($request->user()->hasRole('super-admin')) {
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$Extension_id = $request->id ?? NULL;
			if ($Extension_id) {
				$data = Extension::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $Extension_id)
                        ->orderBy('id', 'DESC')->get();
			} else {				
                $data = Extension::select('id','country_id', 'company_id','callbackextension', 'agent_name', 'name','host','expirationdate','status','secret','sip_temp')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->orderBy('id', 'DESC')
                        ->paginate(
                        $perPage = $perPageNo,
                        $columns = ['*'],
                        $pageName = 'page'
                    );
			}
		} else {
            $Extension_id = $request->id ?? NULL;
			if ($Extension_id) {
				$data = Extension::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select()
					->where('id', $Extension_id)
					->where('company_id', '=',  $user->company_id)
                    ->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = Extension::with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						//->orWhere('did_number', 'LIKE', "%$params%")
                        ->orderBy('id', 'DESC')
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Extension::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->select()->orderBy('id', 'DESC')
                        ->paginate(
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

    public function updateExtension(Request $request, $id)
    {
        try { 
			DB::beginTransaction(); 
            $webrtc_template_url = config('app.webrtc_template_url');
            $softphone_template_url = config('app.softphone_template_url');
			$Extension = Extension::find($id);		
			if(is_null($Extension)){
				DB::commit();
				return $this->output(false, 'This Extension not exist with us. Please try again!.', [], 409);
			}else{
				$validator = Validator::make($request->all(), [
                    'country_id'    => 'required|numeric',
                    'company_id'    => 'required|numeric',
                    'name'          => 'required|unique:extensions,name,'.$Extension->id,
                    'callbackextension' => 'required',
					'agent_name'    => 'required',					
					'secret'	    => 'required',
					'barge'	        => 'required|in:0,1',
                    'mailbox'       => 'required|in:0,1',
                    'voice_email'   => 'required_if:mailbox,1',
                    'callgroup'     => 'required|in:0,1',
                    'callerid'      => 'required_if:callgroup,1',
                    'sip_temp'      => 'required|in:WEBRTC,SOFTPHONE',
				]);
				if ($validator->fails()){
					return $this->output(false, $validator->errors()->first(), [], 409);
				}				
                $Company = Company::where('id', $request->company_id)->first();
                
				$ExtensionOld = Extension::where('country_id', $request->country_id)
							->where('company_id', $request->company_id)
                            ->where('name', $request->name)
							->where('id','!=', $id)
							->first();
				if(!$ExtensionOld){
                    $VoiceMail = VoiceMail::where('mailbox', $request->name)->first();
                    if($VoiceMail){
                        $VoiceMail->delete();
                    }
                    if($request->mailbox  == 1){
                        $VoiceMail = VoiceMail::create([
                            'company_id'=> $request->company_id,
                            'context'   => 'default',
                            'mailbox'   => $request->name,
                            'fullname'  => $request->agent_name,
                            'email'     => $request->voice_email,
                            'timezone'  => 'central',
                            'attach'    => 'yes',
                            'review'    => 'no',
                            'operator'  => 'no',
                            'envelope'  => 'no',
                            'sayduration'   => 'no',
                            'saydurationm'  => '1',
                            'sendvoicemail' => 'no',
                            'nextaftercmd'  => 'yes',
                            'forcename'     => 'no',
                            'forcegreetings'=> 'no',
                            'hidefromdir'   => 'yes',
                            'created_at'    => Carbon::now(),
                            'updated_at'    => Carbon::now(),
                        ]);
                    }
                    
                    if($Extension->sip_temp != $request->sip_temp){
                        if($request->sip_temp == 'WEBRTC'){
                            $addExtensionFile = $webrtc_template_url;
                            $removeExtensionFile = $softphone_template_url;
                        }else{
                            $addExtensionFile = $softphone_template_url;
                            $removeExtensionFile = $webrtc_template_url;
                        }
                        $ConfTemplate = ConfTemplate::select()->where('template_id', $request->sip_temp)->first();
                        $this->addExtensionInConfFile($request->name, $addExtensionFile, $request->secret, $Company->account_code,  $ConfTemplate->template_contents);
                        $this->removeExtensionFromConfFile($request->name, $removeExtensionFile);
                    }

					$Extension->callbackextension = $request->callbackextension;
					$Extension->agent_name  = $request->agent_name;
					$Extension->secret      = $request->secret;
					$Extension->barge       = $request->barge;
					$Extension->mailbox     = $request->mailbox;
					$Extension->callgroup   = $request->callgroup;
                    if($request->callgroup  == 1){
                        $Extension->callerid  = $request->callerid;    
                    }
                    $Extension->sip_temp    = $request->sip_temp;					
					$ExtensionRes           = $Extension->save();
					if($ExtensionRes){
						$ExtensionUpdated = Extension::where('id', $id)->first();        
						$response = $ExtensionUpdated->toArray();                       
						DB::commit();
						return $this->output(true, 'Extension updated successfully.', $response, 200);
					}else{
						DB::commit();
						return $this->output(false, 'Error occurred in Extension Updating. Please try again!.', [], 200);
					}
				}else{
					DB::commit();
					return $this->output(false, 'This Extension already exist with us.',[], 409);
				}
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
	}

    protected function addExtensionInConfFile($extensionName, $conf_file_path, $secret, $account_code, $template_contents){
        // Add new user section
        $register_string = "\n[$extensionName]\nusername=$extensionName\nsecret=$secret\naccountcode=$account_code\n$template_contents\n";
        //$webrtc_conf_path = "/var/www/html/callanalog/admin/webrtc_template.conf";
        file_put_contents($conf_file_path, $register_string, FILE_APPEND | LOCK_EX);
        //echo "Registration successful. The SIP user $nname has been added to the webrtc_template.conf file.";        
    }

    protected function removeExtensionFromConfFile($extensionName, $conf_file_path){
        // Remove user section
			//$conf_file_path = "webrtc_template.conf";
			$lines = file($conf_file_path);
			$output = '';
			$found = false;
			foreach ($lines as $line) {
				if (strpos($line, "[$extensionName]") !== false) {
					$found = true;
					continue;
				}
				if ($found && strpos($line, "[") === 0) {
					$found = false;
				}
				if (!$found) {
					$output .= $line;
				}
			}
			file_put_contents($conf_file_path, $output, LOCK_EX);
			//echo "Registration removed. The SIP user $nname has been removed from the webrtc_template.conf file.";
    }

}
