<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\VoiceMail;
use App\Models\Company;
use App\Models\Cart;
use App\Models\ConfTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Carbon\Carbon;

class ExtensionController extends Controller
{
    public function __construct()
    {

    }

    public function generateExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'extension_digit' => 'nullable|numeric|between:5,10',
            'extension_number' => 'required|numeric|max:50',
        ], [
            'extension_number' => 'Total number of extention required.',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        try {
            DB::beginTransaction();
            $extension_digit = !empty($request->extension_digit) ? $request->extension_digit : 7;
            $extension_number = $request->extension_number;
            $number_array = $final_array = array();
            while (1) {
                // generate unique random number 
                $numberArr = array('5' => '99999', '6' => '999999', '7' => '9999999', '8' => '99999999', '9' => '999999999', '10' => '9999999999');
                $randomNumber = rand(1000, $numberArr[$extension_digit]);
                // pad the number with zeros (if needed)
                $paded = str_pad($randomNumber, $extension_digit, '0', STR_PAD_RIGHT);
                $number_array[] = $paded;
                $number_array = array_unique($number_array);

                if (count($number_array) > $extension_number - 1) {
                    $final_array = $this->checkExtensionsInDB($number_array);
                    if (count($final_array) > $extension_number - 1) {
                        break;
                    } else {
                        $number_array = $final_array;
                        continue;
                    }
                }
            }
            //print_r($number_array);            
            //print_r($final_array);exit;
            $response = implode(',', $final_array);
            DB::commit();
            return $this->output(true, 'Extensions generated successfully.', $response);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in extension generating : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong in extension generating, Please try again.', [], 409);
        }
    }

    protected function checkExtensionsInDB(array $number_array)
    {
        $existExtension = Extension::select(['name'])->whereIn('name', $number_array)->get();
        $exitArray = $existExtension->toArray();
        $exitArray = array_column($exitArray, 'name');
        //print_r($exitArray);
        return $final_array = array_diff($number_array, $exitArray);
    }

    /**********    new  */
    public function createExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'required|numeric',
            'company_id' => 'required|numeric',
            'name.*' => 'required|unique:extensions,name',
            'callbackextension' => 'required|integer|digits_between:2,5',
            'agent_name' => 'required|max:150',
            'callgroup' => 'required|in:0,1', // Outbound call yes or no
            'callerid' => 'required_if:callgroup,1',
            'secret' => 'required',
            'barge' => 'required|in:0,1', //Yes ro no(0,1)
            'recording' => 'required|in:0,1', //Yes ro no(0,1)
            'mailbox' => 'required|in:0,1', //voice mail yes or no
            'voice_email' => 'required_if:mailbox,1',
            'payment_type' => 'nullable',
        ], [
            'name' => 'This Extension is already exist with us. Please try with different.',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try {
            DB::beginTransaction();
            $user = \Auth::user();
            $Company = Company::where('id', $request->company_id)->first();
            $input = $request->all();
            $extension_name = $input['name'];
            if ($Company) {
                $reseller_id = '';
                if ($Company->parent_id > 1) {
                    $price_for = 'Reseller';
                    $reseller_id = $Company->parent_id;
                } else {
                    $price_for = 'Company';
                }
                $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $reseller_id, 'Extension');
                if ($item_price_arr['Status'] == 'true') {
                    $item_price = $item_price_arr['Extension_price'];
                    if (is_array($extension_name)) {
                        $TotalItemPrice = $item_price * count($extension_name);
                        if ($Company->plan_id == 1 && in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc')) && $request->payment_type == 'Paid' && $Company->balance < $TotalItemPrice) {
                            DB::commit();
                            return $this->output(false, 'Company account has insufficient balance!');
                        } else {
                            $VoiceMail = $item_ids = $Cart = [];
                            $status = '0';
                            $startingdate = $expirationdate = $host = $sip_temp = NULL;
                            if ($Company->plan_id == 2 || in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                                $status = '1';
                                $startingdate = Carbon::now();
                                $expirationdate = ($Company->plan_id == 2) ? $startingdate->addDays(179) : $startingdate->addDays(29);
                                $host = 'dynamic';
                                $sip_temp = 'WEBRTC';
                            }

                            foreach ($extension_name as $item) {
                                $data = [];
                                $data = [
                                    'country_id' => $request->country_id,
                                    'company_id' => $request->company_id,
                                    'name' => $item,
                                    'callbackextension' => $request->callbackextension,
                                    'account_code' => $Company->account_code,
                                    'agent_name' => $request->agent_name,
                                    'callgroup' => $request->callgroup,
                                    'callerid' => $request->callerid,
                                    'secret' => $request->secret,
                                    'barge' => $request->barge,
                                    'recording' => $request->recording,
                                    'mailbox' => $request->mailbox,
                                    'regexten' => $item,
                                    'startingdate' => Carbon::now(),
                                    'expirationdate' => $expirationdate,
                                    'fromdomain' => 'NULL',
                                    'amaflags' => 'billing',
                                    'canreinvite' => 'no',
                                    'context' => 'callanalog',
                                    'dtmfmode' => 'RFC2833',
                                    'host' => $host,
                                    'sip_temp' => $sip_temp,
                                    'insecure' => 'port,invite',
                                    'language' => 'en',
                                    'nat' => 'force_rport,comedia',
                                    'qualify' => 'yes',
                                    'rtptimeout' => '60',
                                    'rtpholdtimeout' => '300',
                                    'type' => 'friend',
                                    'username' => $item,
                                    'disallow' => 'ALL',
                                    'allow' => 'g729,g723,ulaw,gsm',
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                    'status' => $status,
                                ];

                                $id = DB::table('extensions')->insertGetId($data);
                                $item_ids[$id] = $item;
                                if ($request->mailbox == '1') {
                                    array_push($VoiceMail, [
                                        'company_id' => $request->company_id,
                                        'context' => 'default',
                                        'mailbox' => $item,
                                        'fullname' => $request->agent_name,
                                        'email' => $request->voice_email,
                                        'timezone' => 'central',
                                        'attach' => 'yes',
                                        'review' => 'no',
                                        'operator' => 'no',
                                        'envelope' => 'no',
                                        'sayduration' => 'no',
                                        'saydurationm' => '1',
                                        'sendvoicemail' => 'no',
                                        'nextaftercmd' => 'yes',
                                        'forcename' => 'no',
                                        'forcegreetings' => 'no',
                                        'hidefromdir' => 'yes',
                                        'created_at' => Carbon::now(),
                                        'updated_at' => Carbon::now(),
                                    ]);
                                }
                                array_push($Cart, [
                                    'company_id' => $request->company_id,
                                    'item_id' => $id,
                                    'item_number' => $item,
                                    'item_type' => 'Extension',
                                    'item_price' => $item_price,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                ]);
                                //$cartIds = DB::table('carts')->insertGetId($Cart);  
                            }
                            // $Extensions = Extension::insert($data);
                            if ($request->mailbox == '1') {
                                $VoiceMail = VoiceMail::insert($VoiceMail);
                            }

                            if ($Company->plan_id == 1 && !in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                                $cartIds = Cart::insert($Cart);
                                $response['total_extension'] = count($item_ids);//$Extensions;//->toArray();
                                $response['Show_Cart'] = 'Yes';
                                DB::commit();
                                return $this->output(true, 'Extension added successfully in cart.', $response);
                            } else {
                                if ($Company->plan_id == 1 && in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc')) && $request->payment_type == 'Paid') {
                                    $Company = Company::where('id', $request->company_id)->first();
                                    if ($Company->balance > $TotalItemPrice) {
                                        $Company_balance = $Company->balance;
                                        $Company->balance = $Company_balance - $TotalItemPrice;
                                        if ($Company->save()) {
                                            $response['total_extension'] = count($item_ids);
                                            //$Extensions;//->toArray();
                                            $response['Show_Cart'] = 'No';
                                        } else {
                                            DB::rollback();
                                            return $this->output(false, 'Error occurred in deducting company balance.', [], 209);
                                        }
                                    } else {
                                        DB::rollback();
                                        return $this->output(false, 'Company account has insufficient balance.');
                                    }
                                }
                                /*
                                $invoicetable_id = DB::table('invoices')->max('id');
                                if (!$invoicetable_id) {
                                    $invoice_id = 'INV/' . date('Y') . '/00001';
                                } else {
                                    $invoice_id = "INV/" . date('Y') . "/000" . ($invoicetable_id + 1);
                                }
                                $Invoice = Invoice::create([
                                    'company_id'        => $request->company_id,
                                    'country_id'        => $Company->country_id,
                                    'state_id'          => $Company->state_id,
                                    'invoice_id'        => $invoice_id,
                                    'invoice_currency'  => 'USD',
                                    'invoice_subtotal_amount'   => $TotalItemPrice,
                                    'invoice_amount'    => $TotalItemPrice,
                                    'payment_status'    => $request->payment_type,
                                    'email_status'      => 0,
                                ]);
                                */
                                foreach ($item_ids as $item_id => $item) {
                                    /*
                                    $InvoiceItems = InvoiceItems::create([                                    
                                        'invoice_id'    => $Invoice->id,
                                        'item_type'     => 'Extension',
                                        'item_number'   => $item,
                                        'item_price'    => $item_price,
                                    ]);
                                    */
                                    $webrtc_template_url = config('app.webrtc_template_url');
                                    $addExtensionFile = $webrtc_template_url;
                                    $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                                    $this->addExtensionInConfFile($item, $addExtensionFile, $request->secret, $Company->account_code, $ConfTemplate->template_contents);
                                }
                                /*
                                $emailData['title'] = 'Invoice From Callanalog';
                                $emailData['item_numbers'] = $item_ids;
                                $emailData['item_types'] = 'Extension';
                                $emailData['price'] = $TotalItemPrice;
                                $emailData['invoice_number'] = $invoice_id;
                                $emailData['email'] = $Company->email;
                                dispatch(new \App\Jobs\SendEmailJob($emailData));
                                */
                                $response['total_extension'] = count($item_ids);
                                //$Extensions;//->toArray();
                                $response['Show_Cart'] = 'No';
                                
                                $server_flag = config('app.server_flag');
                                if ($server_flag == 1) {
                                    $shell_script = config('app.shell_script');
                                    $result = shell_exec('sudo ' . $shell_script);
                                    Log::error('Extension File Transfer Log : ' . $result);
                                    $this->sipReload();
                                }
                                DB::commit();
                                return $this->output(true, 'Extension added successfully.', $response);
                            }
                        }
                    } else {
                        DB::commit();
                        return $this->output(false, 'Wrong extension value format.', [], 429);
                    }
                } else {
                    DB::commit();
                    return $this->output(false, $item_price_arr['Message']);
                }
            } else {
                DB::commit();
                return $this->output(false, 'Company not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in Extensions Inserting : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Error Occurred in adding extensions. Please try again after some time.', [], 406);
        }
    }

    /************ End */
    public function addExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'required|numeric',
            'company_id' => 'required|numeric',
            'name.*' => 'required|unique:extensions,name',
            'callbackextension' => 'required|max:50',
            'agent_name' => 'required|max:150',
            'callgroup' => 'required|in:0,1', // Outbound call yes or no
            'callerid' => 'required_if:callgroup,1',
            'secret' => 'required',
            'barge' => 'required|in:0,1', //Yes ro no(0,1)
            'recording' => 'required|in:0,1', //Yes ro no(0,1)
            'mailbox' => 'required|in:0,1', //voice mail yes or no
            'voice_email' => 'required_if:mailbox,1',
        ], [
            'name' => 'This Extension is already exist with us. Please try with different.',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try {
            DB::beginTransaction();
            $user = \Auth::user();
            $input = $request->all();
            //$extension_name = explode(',',$input['extension_name']);
            $extension_name = $input['name'];
            $Company = Company::where('id', $request->company_id)->first();
            if ($Company) {
                $reseller_id = '';
                if ($Company->parent_id > 1) {
                    $price_for = 'Reseller';
                    $reseller_id = $Company->parent_id;
                } else {
                    $price_for = 'Company';
                }
                $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $reseller_id, 'Extension');
                if ($item_price_arr['Status'] == 'true') {
                    $item_price = $item_price_arr['Extension_price'];
                    if (is_array($extension_name)) {
                        $VoiceMail = $ids = $Cart = [];
                        $status = '0';
                        $startingdate = $expirationdate = $host = $sip_temp = NULL;
                        if ($Company->plan_id == 2) {
                            $status = '1';
                            $startingdate = Carbon::now();
                            $expirationdate = $startingdate->addDays(179);
                            $host = 'dynamic';
                            $sip_temp = 'WEBRTC';
                        }
                        foreach ($extension_name as $item) {
                            $data = [];
                            $data = [
                                'country_id' => $request->country_id,
                                'company_id' => $request->company_id,
                                'name' => $item,
                                'callbackextension' => $request->callbackextension,
                                'account_code' => $Company->account_code,
                                'agent_name' => $request->agent_name,
                                'callgroup' => $request->callgroup,
                                'callerid' => $request->callerid,
                                'secret' => $request->secret,
                                'barge' => $request->barge,
                                'recording' => $request->recording,
                                'mailbox' => $request->mailbox,
                                'regexten' => $item,
                                'startingdate' => Carbon::now(),
                                'expirationdate' => $expirationdate,
                                'fromdomain' => 'NULL',
                                'amaflags' => 'billing',
                                'canreinvite' => 'no',
                                'context' => 'callanalog',
                                'dtmfmode' => 'RFC2833',
                                'host' => $host,
                                'sip_temp' => $sip_temp,
                                'insecure' => 'port,invite',
                                'language' => 'en',
                                'nat' => 'force_rport,comedia',
                                'qualify' => 'yes',
                                'rtptimeout' => '60',
                                'rtpholdtimeout' => '300',
                                'type' => 'friend',
                                'username' => $item,
                                'disallow' => 'ALL',
                                'allow' => 'g729,g723,ulaw,gsm',
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                                'status' => $status,
                            ];

                            $id = DB::table('extensions')->insertGetId($data);
                            $ids[$id] = $item;
                            if ($Company->plan_id == 1) {
                                array_push($Cart, [
                                    'company_id' => $request->company_id,
                                    'item_id' => $id,
                                    'item_number' => $item,
                                    'item_type' => 'Extension',
                                    'item_price' => $item_price,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                ]);
                                //$cartIds = DB::table('carts')->insertGetId($Cart);                   
                            } else {
                                $webrtc_template_url = config('app.webrtc_template_url');
                                $addExtensionFile = $webrtc_template_url;
                                $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                                $this->addExtensionInConfFile($item, $addExtensionFile, $request->secret, $Company->account_code, $ConfTemplate->template_contents);
                            }
                            if ($request->mailbox == '1') {
                                array_push($VoiceMail, [
                                    'company_id' => $request->company_id,
                                    'context' => 'default',
                                    'mailbox' => $item,
                                    'fullname' => $request->agent_name,
                                    'email' => $request->voice_email,
                                    'timezone' => 'central',
                                    'attach' => 'yes',
                                    'review' => 'no',
                                    'operator' => 'no',
                                    'envelope' => 'no',
                                    'sayduration' => 'no',
                                    'saydurationm' => '1',
                                    'sendvoicemail' => 'no',
                                    'nextaftercmd' => 'yes',
                                    'forcename' => 'no',
                                    'forcegreetings' => 'no',
                                    'hidefromdir' => 'yes',
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                ]);
                            }
                        }
                        // $Extensions = Extension::insert($data);
                        if ($request->mailbox == '1') {
                            $VoiceMail = VoiceMail::insert($VoiceMail);
                        }
                        if ($Company->plan_id == 1) {
                            $cartIds = Cart::insert($Cart);
                        }

                        $response['total_extension'] = count($ids);//$Extensions;//->toArray();
                        $response['plan_id'] = $Company->plan_id;
                        DB::commit();
                        return $this->output(true, 'Extension added successfully.', $response);
                    } else {
                        DB::commit();
                        return $this->output(false, 'Wrong extension value format.');
                    }
                } else {
                    DB::commit();
                    return $this->output(false, $item_price_arr['Message']);
                }
            } else {
                DB::commit();
                return $this->output(false, 'Company not exist with us.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in Extensions Inserting : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Error Occurred in adding extensions. Please try again after some time.', [], 406);
        }
    }


    public function getAllExtensions(Request $request)
    {
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $params = $request->params ?? "";
        $user = \Auth::user();
        //echo $user->company_id;
        //if ($request->user()->hasRole('super-admin')) {
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $Extension_id = $request->id ?? NULL;
            if ($Extension_id) {
                $data = Extension::select()
                    ->with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
                    ->where('id', $Extension_id)
                    ->orderBy('id', 'DESC')->get();
            } else {
                if ($params != "") {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                        ->orWhere('agent_name', 'LIKE', "%$params%")
                        ->orWhere('name', 'LIKE', "%$params%")
                        ->orWhere('callbackextension', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('email', 'like', "%{$params}%");
                        })
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                } else {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                        ->orderBy('extensions.id', 'DESC')
                        ->paginate(
                            $perPage = $perPageNo,
                            $columns = ['*'],
                            $pageName = 'page'
                        );
                }
            }
        } else {
            $Extension_id = $request->id ?? NULL;
            if ($Extension_id) {
                $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                    ->with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
                    ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                    ->where('id', $Extension_id)
                    ->where('extensions.company_id', '=', $user->company_id)
                    ->orderBy('id', 'DESC')
                    ->get();
            } else {
                if ($params != "") {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                        ->where('extensions.company_id', '=', $user->company_id)
                        ->orWhere('agent_name', 'LIKE', "%$params%")
                        ->orWhere('name', 'LIKE', "%$params%")
                        ->orWhere('callbackextension', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                } else {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                        ->where('extensions.company_id', '=', $user->company_id)
                        ->orderBy('id', 'DESC')
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
            if (is_null($Extension)) {
                DB::commit();
                return $this->output(false, 'This Extension not exist with us. Please try again!.', [], 409);
            } else {
                $validator = Validator::make($request->all(), [
                    'country_id'=> 'required|numeric',
                    'company_id'=> 'required|numeric',
                    'name'      => 'required|unique:extensions,name,' . $Extension->id,
                    'callbackextension' => 'required',
                    'agent_name'=> 'required',
                    'secret'    => 'required',
                    'barge'     => 'required|in:0,1',
                    'recording' => 'required|in:0,1',
                    'mailbox'   => 'required|in:0,1',
                    'voice_email'   => 'required_if:mailbox,1',
                    'callgroup' => 'required|in:0,1',
                    'callerid'  => 'required_if:callgroup,1',
                    'sip_temp'  => 'required|in:WEBRTC,SOFTPHONE',
                    'dial_timeout'  => 'required',
                ]);
                if ($validator->fails()) {
                    return $this->output(false, $validator->errors()->first(), [], 409);
                }
                $Company = Company::where('id', $request->company_id)->first();

                $ExtensionOld = Extension::where('country_id', $request->country_id)
                    ->where('company_id', $request->company_id)
                    ->where('name', $request->name)
                    ->where('id', '!=', $id)
                    ->first();
                if (!$ExtensionOld) {
                    $VoiceMail = VoiceMail::where('mailbox', $request->name)->first();
                    if ($VoiceMail) {
                        $VoiceMail->delete();
                    }
                    if ($request->mailbox == 1) {
                        $VoiceMail = VoiceMail::create([
                            'company_id' => $request->company_id,
                            'context' => 'default',
                            'mailbox' => $request->name,
                            'fullname' => $request->agent_name,
                            'email' => $request->voice_email,
                            'timezone' => 'central',
                            'attach' => 'yes',
                            'review' => 'no',
                            'operator' => 'no',
                            'envelope' => 'no',
                            'sayduration' => 'no',
                            'saydurationm' => '1',
                            'sendvoicemail' => 'no',
                            'nextaftercmd' => 'yes',
                            'forcename' => 'no',
                            'forcegreetings' => 'no',
                            'hidefromdir' => 'yes',
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }

                    if ($Extension->sip_temp != $request->sip_temp) { 
                        if ($request->sip_temp == 'WEBRTC') {
                            $addExtensionFile = $webrtc_template_url;
                            $removeExtensionFile = $softphone_template_url;
                        } else {
                            $addExtensionFile = $softphone_template_url;
                            $removeExtensionFile = $webrtc_template_url;
                        }

                        Log::error('addExtensionFile : ' . $addExtensionFile . '  / removeExtensionFile: ' . $removeExtensionFile );

                        $ConfTemplate = ConfTemplate::select()->where('template_id', $request->sip_temp)->first();
                        $this->addExtensionInConfFile($request->name, $addExtensionFile, $request->secret, $Company->account_code, $ConfTemplate->template_contents);
                        $this->removeExtensionFromConfFile($request->name, $removeExtensionFile);

                        $server_flag = config('app.server_flag');
                        if ($server_flag == 1) {
                            $shell_script = config('app.shell_script');
                            $result = shell_exec('sudo ' . $shell_script);
                            Log::error('Extension Update File Transfer Log : ' . $result);
                            $this->sipReload();
                        }
                    }


                    $Extension->callbackextension = $request->callbackextension;
                    $Extension->agent_name = $request->agent_name;
                    $Extension->secret = $request->secret;
                    $Extension->barge = $request->barge;
                    $Extension->mailbox = $request->mailbox;
                    $Extension->callgroup = $request->callgroup;
                    $Extension->recording = $request->recording;
                    $Extension->dial_timeout = $request->dial_timeout;
                    if ($request->callgroup == 1) {
                        $Extension->callerid = $request->callerid;
                    }
                    $Extension->sip_temp = $request->sip_temp;
                    $ExtensionRes = $Extension->save();
                    if ($ExtensionRes) {


                        $ExtensionUpdated = Extension::where('id', $id)->first();
                        $response = $ExtensionUpdated->toArray();
                        DB::commit();
                        return $this->output(true, 'Extension updated successfully.', $response, 200);
                    } else {
                        DB::commit();
                        return $this->output(false, 'Error occurred in Extension Updating. Please try again!.', [], 200);
                    }
                } else {
                    DB::commit();
                    return $this->output(false, 'This Extension already exist with us.', [], 409);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in Extensions Updating : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    protected function addExtensionInConfFile($extensionName, $conf_file_path, $secret, $account_code, $template_contents)
    {
        // Add new user section
        $register_string = "\n[$extensionName]\nusername=$extensionName\nsecret=$secret\naccountcode=$account_code\n$template_contents\n";
        //$webrtc_conf_path = "/var/www/html/callanalog/admin/webrtc_template.conf";
        file_put_contents($conf_file_path, $register_string, FILE_APPEND | LOCK_EX);
        //echo "Registration successful. The SIP user $nname has been added to the webrtc_template.conf file.";        
    }

    protected function removeExtensionFromConfFile($extensionName, $conf_file_path)
    {
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

    public function getExtensionsByCountryIdAndCompanyId(Request $request, $country_id, $company_id)
    {
        $data = Extension::with('company:id,company_name,email,mobile')
            ->with('country:id,country_name')
            ->select('id', 'name', 'agent_name', 'callbackextension', 'country_id', 'company_id')
            ->where('country_id', $country_id)
            ->where('company_id', $company_id)
            ->where('status', 1)
            ->orderBy('id', 'DESC')
            ->get();

        if ($data->isNotEmpty()) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }


    protected function sipReload()
    {
        $server_ip = "85.195.76.161";
        $socket = @fsockopen($server_ip, 5038);
        $response = "";
        if (!is_resource($socket)) {
            echo "conn failed in Engconnect ";
            exit;
        }
        fputs($socket, "Action: Login\r\n");
        fputs($socket, "UserName: TxuserGClanlg\r\n");
        fputs($socket, "Secret: l3o9zMP3&X[k2+\r\n\r\n");
        fputs($socket, "Action: Command\r\n");
        fputs($socket, "Command: sip reload\r\n\r\n");
        fputs($socket, "Action: Logoff\r\n\r\n");
        while (!feof($socket))
            $response .= fread($socket, 5038);            
        fclose($socket);
        return true;
    }

    public function getSipRegistrationList(Request $request)
    {
        $user = \Auth::user();
        $server_ip = "85.195.76.161";
        $socket = @fsockopen($server_ip, 5038);
        $response = "";
        if (!is_resource($socket)) {
            echo "conn failed in Engconnect ";
            exit;
        }
        fputs($socket, "Action: Login\r\n");
        fputs($socket, "UserName: TxuserGClanlg\r\n");
        fputs($socket, "Secret: l3o9zMP3&X[k2+\r\n\r\n");
        fputs($socket, "Action: Command\r\n");
        fputs($socket, "Command: sip show peers\r\n\r\n");
        fputs($socket, "Action: Logoff\r\n\r\n");
        while (!feof($socket))
            $response .= fread($socket, 8192);
        fclose($socket); 

        $lines = explode("\n", $response);
        $data = array();
        foreach ($lines as $line) 
        {
            $line = trim($line); // Remove leading/trailing whitespace 

            if (empty($line)) {
                continue; // Skip empty lines
            }

            if (strpos($line, "OK") !== false || strpos($line, "UNREACHABLE") !== false || strpos($line, "LAGGED") !== false) {
                $columns = preg_split('/\s+/', $line);
                
                if (strpos($columns[1], "/") !== false) {
                    $columns[1] = substr($columns[1], 0, strpos($columns[1], "/"));
                }
                if(is_numeric($columns[1])){

                    if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                        $extension = Extension::with('company:id,company_name,email,mobile')
                                    ->select('id', 'name', 'agent_name', 'sip_temp','callbackextension', 'country_id', 'company_id')
                                    ->where('name', $columns[1])->first();
                    }else{
                        $extension = Extension::with('company:id,company_name,email,mobile')
                                    ->select('id', 'name', 'agent_name', 'sip_temp','callbackextension', 'country_id', 'company_id')
                                    ->where('company_id', $user->company_id)
                                    ->where('name', $columns[1])->first();
                    }

                    if ($extension != null) {
                        $clientId   = $extension->company_id;
                        $agent      = $extension->agent_name;
                        $phone_type = $extension->sip_temp;
                        $client_name= $extension->company->company_name;
                        $email      = $extension->company->email;

                        if (in_array('D', $columns)) {
                            unset($columns[3]);
                            $columns = array_values($columns);
                        }

                        if ($phone_type == 'WEBRTC') {
                            $user_type = "Web Phone";                    
                        } else {
                            $user_type = "Soft Phone";
                        }
                        if (in_array('A', $columns)) {
                            unset($columns[5]);
                            $columns = array_values($columns);                  
                        } 
                        $port = $columns[5];
                        if ($columns[5] == 0) {
                            $port = 'Unreachable';
                        }
        
                        $status = trim($columns[6] . ' ' . $columns[7] . ' ' . $columns[8]);
                        if ($port == 0 || $status == 'UNREACHABLE') {
                            if ($status == 'UNREACHABLE') {
                                $statusText = $status;
                            } else {
                                $statusText = "Out of Network";
                            }
                            $status = $statusText;
                        } else {
                            $status = 'REACHABLE<br>'.trim($columns[7] . ' ' . $columns[8]);
                        }
                        
                        $peerData = array(
                            "companyName" => $client_name,
                            "agent_name" => $agent . '<br>' . $email,
                            "userType" => $user_type,
                            "name" => $columns[1] . '/' . $columns[1],
                            "host" => $columns[2],
                            "forceport" => $columns[3],
                            "comedia" => $columns[4],
                            "port" => $port,
                            "status" => $status                   
                        );                
                        $data[] = $peerData;
                    }
                }
            }
        }
        if (count($data) > 0 ) {
            return $this->output(true, 'Success', $data, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }
}
