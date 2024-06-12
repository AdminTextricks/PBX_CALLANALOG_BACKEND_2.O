<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\VoiceMail;
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
            $data = $VoiceMail = [];             
            //$extension_name = explode(',',$input['extension_name']);
            $extension_name = $input['name'];            
            if (is_array($extension_name)) {
                foreach ($extension_name as $item) {
                    array_push($data, [
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
                        'fromdomain'        => 'NULL',
                        'amaflags'          => 'billing',
                        'canreinvite'       => 'no',
                        'context'           => 'callanalog',
                        'dtmfmode'          => 'RFC2833',
                        'host'              => 'dynamic',
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
                        'status'            => isset($request->status) ? $request->status : '1',
                    ]);
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
            }
            //print_r($data);exit;
            $Extensions = Extension::insert($data);
            if($request->mailbox == '1'){
                $VoiceMail = VoiceMail::insert($VoiceMail);            
            }
            $response 	= $Extensions;//->toArray();
            DB::commit();
            return $this->output(true, 'Extension added successfully.', $response);
            
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
		//if ($request->user()->hasRole('super-admin')) {
        if (in_array($user->roles->first()->name, array('Super Admin', 'Support','NOC'))) {
			$Extension_id = $request->id ?? NULL;
			if ($Extension_id) {
				$data = Extension::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $Extension_id)->get();
			} else {				
                $data = Extension::select()
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
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
					->get();
			} else {
				if ($params != "") {
					$data = Extension::with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Extension::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->select()->paginate(
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
}
