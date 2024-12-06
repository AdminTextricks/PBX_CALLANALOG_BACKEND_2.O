<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\ExtensionLogs;
use App\Models\VoiceMail;
use App\Models\Company;
use App\Models\Cart;
use App\Models\ConfTemplate;
use App\Models\Invoice;
use App\Models\Tfn;
use App\Models\InvoiceItems;
use App\Models\Payments;
use App\Models\RingMember;
use App\Models\QueueMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Carbon\Carbon;

class ExtensionController extends Controller
{
    public function __construct() {}

    public function generateExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'extension_digit' => 'nullable|numeric|between:5,10',
            'extension_number' => 'required|numeric|max:80',
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
            'country_id'    => 'required|numeric|exists:countries,id',
            'company_id'    => 'required|numeric|exists:companies,id',
            'name.*' => 'required|unique:extensions,name',
            //'callbackextension' => 'required|integer|digits_between:2,5',
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
                                $payment_status = 'Free';
                            }

                            foreach ($extension_name as $item) {
                                $callbackextension = str_pad(rand(1, 9999), 4, "0", STR_PAD_LEFT);
                                $data = [];
                                $data = [
                                    'country_id' => $request->country_id,
                                    'company_id' => $request->company_id,
                                    'name' => $item,
                                    'callbackextension' => $callbackextension,
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
                                    'fromdomain' => NULL,
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
                                        'password' => '1234',
                                        'timezone' => 'central',
                                        'dialout'   => '60',
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
                                    'country_id' => $request->country_id,
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
                                $response['total_extension'] = count($item_ids); //$Extensions;//->toArray();
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
                                    $payment_status = $request->payment_type;
                                }

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
                                    'payment_status'    => $payment_status,
                                    'email_status'      => 0,
                                ]);
                                $purchase_item = array();
                                foreach ($item_ids as $item_id => $item) {
                                    $purchase_item[] = $item;
                                    $InvoiceItems = InvoiceItems::create([
                                        'invoice_id'    => $Invoice->id,
                                        'country_id'    => $request->country_id,
                                        'item_type'     => 'Extension',
                                        'item_number'   => $item,
                                        'item_price'    => $item_price,
                                        'item_category' => ($payment_status == 'Paid') ? 'Purchase' : 'Free',
                                    ]);

                                    $webrtc_template_url = config('app.webrtc_template_url');
                                    $addExtensionFile = $webrtc_template_url;
                                    $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                                    $this->addExtensionInConfFile($item, $addExtensionFile, $request->secret, $Company->account_code, $ConfTemplate->template_contents);
                                }

                                if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                                    $payment_by = 'Super Admin';
                                } else {
                                    $payment_by = 'Company';
                                }
                                $payment = Payments::create([
                                    'company_id'        => $request->company_id,
                                    'invoice_id'        => $Invoice->id,
                                    'ip_address'        => $request->ip(),
                                    'invoice_number'    => $invoice_id,
                                    'order_id'          =>  $invoice_id . '-UID-' . $request->company_id,
                                    'item_numbers'      => implode(',', $purchase_item),
                                    'payment_type'      => $payment_status,
                                    'payment_by'        => $payment_by,
                                    'payment_currency'  => 'USD',
                                    'payment_price'     => $TotalItemPrice,
                                    'stripe_charge_id'  => '',
                                    'transaction_id'    => $TotalItemPrice . '-' . time(),
                                    'status'            => 1,
                                ]);

                                /* $emailData['title']         = 'Invoice From Callanalog';
                                $emailData['item_numbers']  = $item_ids;
                                $emailData['item_types']    = 'Extension';
                                $emailData['price']         = $TotalItemPrice;
                                $emailData['invoice_number'] = $invoice_id;
                                $emailData['email']         = $Company->email;
                                $emailData['email_template'] = 'invoice';
                                dispatch(new \App\Jobs\SendEmailJob($emailData)); */

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

    public function deleteExtension(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $Extension = Extension::where('id', $id)->first();
            if ($Extension) {

                $removeExtensionFile = config('app.webrtc_template_url');
                $this->removeExtensionFromConfFile($Extension->name, $removeExtensionFile);

                $removeExtensionFile = config('app.softphone_template_url');
                $this->removeExtensionFromConfFile($Extension->name, $removeExtensionFile);

                Log::error('removeExtensionFile: ' . $removeExtensionFile);

                $server_flag = config('app.server_flag');
                if ($server_flag == 1) {
                    $shell_script = config('app.shell_script');
                    $result = shell_exec('sudo ' . $shell_script);
                    Log::error('Extension Update File Transfer Log : ' . $result);
                    $this->sipReload();
                }
                $resdelete = $Extension->delete();
                if ($resdelete) {
                    Log::error('Remove Extension From File: ' . $Extension->name);
                    Cart::where('item_id', '=', $id)->delete();
                    RingMember::where('extension', $Extension->name)->delete();
                    QueueMember::where('membername', $Extension->name)->delete();
                    DB::commit();
                    return $this->output(true, 'Success', 200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Extension deleting. Please try again!.', [], 209);
                }
            } else {
                DB::commit();
                return $this->output(false, 'Extension not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Extension Deleting : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function changeExtensionStatus(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'status' => 'required',
            ]);
            if ($validator->fails()) {
                DB::commit();
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            $Extension = Extension::find($id);
            if (is_null($Extension)) {
                DB::commit();
                return $this->output(false, 'This Extension not exist with us. Please try again!.', [], 409);
            } else {
                $Extension->status = $request->status;
                $ExtensionsRes = $Extension->save();
                if ($ExtensionsRes) {
                    $Extension = Extension::where('id', $id)->first();
                    $response = $Extension->toArray();
                    DB::commit();
                    return $this->output(true, 'Extension status updated successfully.', $response, 200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Extension Updating. Please try again!.', [], 200);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Extension Updating : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function getAllExtensions(Request $request)
    {
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $params = $request->params ?? "";
        $user = \Auth::user();
        $options = $request->get('options', null);
        $data_id = Extension::select()
            //->with('company:id,company_name,email,mobile,balance')
            ->with(['company' => function ($query) {
                $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
            }, 'company.user_plan' => function ($query) {
                $query->select('id', 'name'); // select specific fields from user_plan
            }])
            ->with('country:id,country_name');

        $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
            //->with('company:id,company_name,email,mobile,balance')
            ->with(['company' => function ($query) {
                $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
            }, 'company.user_plan' => function ($query) {
                $query->select('id', 'name'); // select specific fields from user_plan
            }])
            ->with('country:id,country_name')
            ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')->orderBy('extensions.updated_at', 'DESC');

        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $Extension_id = $request->id ?? NULL;
            if ($Extension_id) {
                $data_id->where('id', $Extension_id)->get();
            } elseif ($params != "") {
                $data->orWhere('name', 'like', "%$params%")
                    //->orWhere('callbackextension', 'LIKE', "%$params%")
                    //->orWhere('agent_name', 'LIKE', "%$params%")
                    ->orWhere('agent_name', 'like', "%$params%")
                    ->orWhere('host', 'like', "%$params%")
                    ->orWhere('sip_temp', 'like', "%$params%")
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('company_name', 'like', "%{$params}%");
                    })
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('email', 'like', "%{$params}%");
                    })
                    ->orWhereHas('country', function ($query) use ($params) {
                        $query->where('country_name', 'like', "%{$params}%");
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } elseif ($options != "") {
                if ($options == 1) {
                    $data->where('extensions.host', '=', NULL)->where('extensions.status', '=', '0');
                } elseif ($options == 2) {
                    $data->where('extensions.host', '=', 'dynamic')->where('extensions.status', '=', '1')->whereBetween('extensions.expirationdate', [Carbon::now(), Carbon::now()->addDays(3)]);
                } elseif ($options == 3) {
                    $data->where('extensions.host', '=', 'static')->where('extensions.status', '=', '0')->where('extensions.expirationdate', '<', Carbon::now());
                }
            } else {
                $data;
            }
        } else {
            $Extension_id = $request->id ?? NULL;
            if ($Extension_id) {
                $data_id->where('id', $Extension_id)
                    ->where('extensions.company_id', '=', $user->company_id)
                    ->orderBy('id', 'DESC')
                    ->get();
            } elseif ($params != "") {
                //DB::enableQueryLog();
                $data->where('extensions.company_id', '=', $user->company_id)
                    ->where(function ($query) use ($params) {
                        $query->where('name', 'like', "%{$params}%")
                            ->orWhereHas('country', function ($query) use ($params) {
                                $query->where('country_name', 'like', "%{$params}%");
                            });
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                //dd(DB::getQueryLog());
            } elseif ($options != "") {
                $data->where('extensions.company_id', '=', $user->company_id);
                if ($options == 1) {
                    $data->where('extensions.host', '=', NULL)->where('extensions.status', '=', '0');
                } elseif ($options == 2) {
                    $data->where('extensions.host', '=', 'dynamic')->where('extensions.status', '=', '1')->whereBetween('extensions.expirationdate', [Carbon::now(), Carbon::now()->addDays(3)]);
                } elseif ($options == 3) {
                    $data->where('extensions.host', '=', 'static')->where('extensions.status', '=', '0')->where('extensions.expirationdate', '<', Carbon::now());
                }
            } else {
                $data->where('extensions.company_id', '=', $user->company_id);
            }
        }
        $data_extension = $data->paginate($perPageNo);
        if ($data_extension->isNotEmpty()) {
            $dd = $data_extension->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }


    public function getAllExtensionsOLD(Request $request)
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
                    //->with('company:id,company_name,email,mobile,balance')
                    ->with(['company' => function ($query) {
                        $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
                    }, 'company.user_plan' => function ($query) {
                        $query->select('id', 'name'); // select specific fields from user_plan
                    }])
                    ->with('country:id,country_name')
                    ->where('id', $Extension_id)
                    ->orderBy('id', 'DESC')->get();
            } else {
                if ($params != "") {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        //->with('company:id,company_name,email,mobile,balance')
                        ->with(['company' => function ($query) {
                            $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
                        }, 'company.user_plan' => function ($query) {
                            $query->select('id', 'name'); // select specific fields from user_plan
                        }])
                        ->with('country:id,country_name')
                        ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                        ->orWhere('name', 'like', "%$params%")
                        ->orWhere('callbackextension', 'LIKE', "%$params%")
                        ->orWhere('agent_name', 'LIKE', "%$params%")
                        ->orWhere('host', 'LIKE', "%$params%")
                        ->orWhere('sip_temp', 'LIKE', "%$params%")

                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('email', 'like', "%{$params}%");
                        })
                        ->orWhereHas('country', function ($query) use ($params) {
                            $query->where('country_name', 'like', "%{$params}%");
                        })
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                } else {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        //->with('company:id,company_name,email,mobile,balance,plan_id')
                        ->with(['company' => function ($query) {
                            $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
                        }, 'company.user_plan' => function ($query) {
                            $query->select('id', 'name'); // select specific fields from user_plan
                        }])
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
                    //->with('company:id,company_name,email,mobile,balance')
                    ->with(['company' => function ($query) {
                        $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
                    }, 'company.user_plan' => function ($query) {
                        $query->select('id', 'name'); // select specific fields from user_plan
                    }])
                    ->with('country:id,country_name')
                    ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                    ->where('id', $Extension_id)
                    ->where('extensions.company_id', '=', $user->company_id)
                    ->orderBy('id', 'DESC')
                    ->get();
            } else {
                if ($params != "") {
                    //DB::enableQueryLog();
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        //->with('company:id,company_name,email,mobile,balance')
                        ->with(['company' => function ($query) {
                            $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
                        }, 'company.user_plan' => function ($query) {
                            $query->select('id', 'name'); // select specific fields from user_plan
                        }])
                        ->with('country:id,country_name')
                        ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox')
                        ->where('extensions.company_id', '=', $user->company_id)
                        ->where(function ($query) use ($params) {
                            $query->where('name', 'like', "%{$params}%")
                                ->orWhereHas('country', function ($query) use ($params) {
                                    $query->where('country_name', 'like', "%{$params}%");
                                });
                        })
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                    //dd(DB::getQueryLog());
                } else {
                    $data = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
                        //->with('company:id,company_name,email,mobile,balance')
                        ->with(['company' => function ($query) {
                            $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id'); // select specific fields from company
                        }, 'company.user_plan' => function ($query) {
                            $query->select('id', 'name'); // select specific fields from user_plan
                        }])
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
                    'country_id'    => 'required|numeric|exists:countries,id',
                    'company_id'    => 'required|numeric|exists:companies,id',
                    'name'          => 'required|unique:extensions,name,' . $Extension->id,
                    'callbackextension' => 'required',
                    'agent_name' => 'required',
                    'secret'    => 'required',
                    'barge'     => 'required|in:0,1',
                    'recording' => 'required|in:0,1',
                    'mailbox'   => 'required|in:0,1',
                    'voice_email'   => 'required_if:mailbox,1',
                    'callgroup' => 'required|in:0,1',
                    'callerid'  => 'required_if:callgroup,1',
                    'sip_temp'  => 'required|in:WEBRTC,SOFTPHONE',
                    'dial_timeout'  => 'required',
                ], [
                    'callbackextension' => 'Intercom number already exists.',
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
                    $Extension_intercom = Extension::where('callbackextension', $request->callbackextension)
                        ->where('company_id', $request->company_id)->where('id', '!=', $id)->first();
                    if ($Extension_intercom) {
                        DB::commit();
                        return $this->output(false, 'Intercom number already exists with us. Please try again with different!.', [], 409);
                    }
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

                    //if ($Extension->sip_temp != $request->sip_temp) { 
                    if ($request->sip_temp == 'WEBRTC') {
                        $addExtensionFile = $webrtc_template_url;
                        $removeExtensionFile = $softphone_template_url;
                    } else {
                        $addExtensionFile = $softphone_template_url;
                        $removeExtensionFile = $webrtc_template_url;
                    }

                    Log::error('addExtensionFile : ' . $addExtensionFile . '  / removeExtensionFile: ' . $removeExtensionFile);

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
                    //}


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

    // protected function addExtensionInConfFile($extensionName, $conf_file_path, $secret, $account_code, $template_contents)
    // {
    //     // Add new user section
    //     $register_string = "\n[$extensionName]\nusername=$extensionName\nsecret=$secret\naccountcode=$account_code\n$template_contents\n";
    //     //$webrtc_conf_path = "/var/www/html/callanalog/admin/webrtc_template.conf";
    //     file_put_contents($conf_file_path, $register_string, FILE_APPEND | LOCK_EX);
    //     //echo "Registration successful. The SIP user $nname has been added to the webrtc_template.conf file.";        
    // }

    // protected function removeExtensionFromConfFile($extensionName, $conf_file_path)
    // {
    //     // Remove user section
    //     //$conf_file_path = "webrtc_template.conf";
    //     $lines = file($conf_file_path);
    //     $output = '';
    //     $found = false;
    //     foreach ($lines as $line) {
    //         if (strpos($line, "[$extensionName]") !== false) {
    //             $found = true;
    //             continue;
    //         }
    //         if ($found && strpos($line, "[") === 0) {
    //             $found = false;
    //         }
    //         if (!$found) {
    //             $output .= $line;
    //         }
    //     }
    //     file_put_contents($conf_file_path, $output, LOCK_EX);
    //     //echo "Registration removed. The SIP user $nname has been removed from the webrtc_template.conf file.";
    // }

    public function getExtensionsByCountryIdAndCompanyId(Request $request, $country_id, $company_id)
    {
        $data = Extension::with('company:id,company_name,email,mobile,balance')
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


    // protected function sipReload()
    // {
    //     $server_ip = "85.195.76.161";
    //     $socket = @fsockopen($server_ip, 5038);
    //     $response = "";
    //     if (!is_resource($socket)) {
    //         echo "conn failed in Engconnect ";
    //         exit;
    //     }
    //     fputs($socket, "Action: Login\r\n");
    //     fputs($socket, "UserName: TxuserGClanlg\r\n");
    //     fputs($socket, "Secret: l3o9zMP3&X[k2+\r\n\r\n");
    //     fputs($socket, "Action: Command\r\n");
    //     fputs($socket, "Command: sip reload\r\n\r\n");
    //     fputs($socket, "Action: Logoff\r\n\r\n");
    //     while (!feof($socket))
    //         $response .= fread($socket, 5038);            
    //     fclose($socket);
    //     return true;
    // }

    public function getSipRegistrationList(Request $request)
    {
        $user = \Auth::user();
        $data = [];
        $shell_script = config('app.extension_list_script');
        $result = shell_exec('sudo ' . $shell_script);
        if(!empty($result)){
            $lines = explode("\n", $result); 
            foreach ($lines as $line) {
                $line = trim($line); // Trim whitespace and newlines
                if (empty($line)) continue; // Skip empty lines

                // Split by '|', then trim and extract AOR and User-agent separately
                $parts = explode('|', $line);
                
                // Extract AOR
                $aorPart = trim($parts[0]);
                $aor = trim(str_replace(['AOR:', '"', ','], '', $aorPart));

                // Extract User-agent
                $userAgentPart = trim($parts[1]);
                $userAgent = trim(str_replace(['User-agent:', '"', ','], '', $userAgentPart));

                // Extract Received
                $ReceivedPart = trim($parts[2]);
                $Received = trim(str_replace([ '"', ',','Received:'], '', $ReceivedPart));
                
                $SipPart = explode(':', $Received);
                $Port = explode(';',end($SipPart));

                /**** DB Data */            
                if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {

                    $extension = Extension::with('company:id,company_name,email,mobile')
                            ->with([
                                'userRegisteredServer' => function ($query) {
                                    $query->select('id', 'server_id', 'company_id')
                                        ->with('server:id,name,ip,port,domain,status');
                                }
                            ])
                            ->select('id', 'name', 'agent_name', 'sip_temp', 'callbackextension', 'country_id', 'company_id')
                            ->where('name', $aor)->first();
                }else{
                    $extension = Extension::with('company:id,company_name,email,mobile')
                            ->with([
                                'userRegisteredServer' => function ($query) {
                                    $query->select('id', 'server_id', 'company_id')
                                        ->with('server:id,name,ip,port,domain,status');
                                }
                            ])
                            ->select('id', 'name', 'agent_name', 'sip_temp', 'callbackextension', 'country_id', 'company_id')
                            ->where('company_id', $user->company_id)
                            ->where('name', $aor)->first();
                    
                }
                //return $extension->userRegisteredServer;
                /*** End DB data */
                // Add to data array
                if ($extension) {
                    $data[] = [
                        'server_name'   => $extension->userRegisteredServer->server->name,
                        'server_ip'     => $extension->userRegisteredServer->server->ip,
                        'server_port'   => $extension->userRegisteredServer->server->port,
                        'company_id'    => $extension->company_id,
                        'agent'         => $extension->agent_name,
                        'company_name'  => $extension->company->company_name,
                        'email'         => $extension->company->email,
                        'extension'     => $aor,
                        'User-agent' => $userAgent,
                        'Received'  => $SipPart[1],
                        'Port' => $Port[0],
                    ];
                }
            }
            // Output the data array
            //print_r($data);
        }
        if ($data) {
            return $this->output(true, 'Success', $data, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
        /* $server_ip = "85.195.76.161";
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
            $response .= fread($socket, 5038);
        fclose($socket);

        $lines = explode("\n", $response);
        $data = array();
        foreach ($lines as $line) {
            $line = trim($line); // Remove leading/trailing whitespace 
            if (empty($line)) {
                continue; // Skip empty lines
            }
            if (strpos($line, "OK") !== false || strpos($line, "UNREACHABLE") !== false || strpos($line, "LAGGED") !== false) {
                $columns = preg_split('/\s+/', $line);
                if (strpos($columns[1], "/") !== false) {
                    $columns[1] = substr($columns[1], 0, strpos($columns[1], "/"));
                }
                if (is_numeric($columns[1])) {

                    if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                        $extension = Extension::with('company:id,company_name,email,mobile')
                            ->select('id', 'name', 'agent_name', 'sip_temp', 'callbackextension', 'country_id', 'company_id')
                            ->where('name', $columns[1])->first();
                    } else {
                        $extension = Extension::with('company:id,company_name,email,mobile')
                            ->select('id', 'name', 'agent_name', 'sip_temp', 'callbackextension', 'country_id', 'company_id')
                            ->where('company_id', $user->company_id)
                            ->where('name', $columns[1])->first();
                    }

                    if ($extension != null) {
                        $clientId   = $extension->company_id;
                        $agent      = $extension->agent_name;
                        $phone_type = $extension->sip_temp;
                        $client_name = $extension->company->company_name;
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
                            $status = 'REACHABLE';
                        }

                        $peerData = array(
                            "companyName"   => $client_name,
                            "agent_name"    => $agent,
                            "email"         => $email,
                            "userType"      => $user_type,
                            "name"      => $columns[1] . '/' . $columns[1],
                            "host"      => $columns[2],
                            "status"    => $status,
                            "status_val" => trim($columns[7] . ' ' . $columns[8]),
                        );
                        $data[] = $peerData;
                    }
                }
            }
        } 
        if (count($data) > 0) {
            return $this->output(true, 'Success', $data, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }*/
    }

    public function getExtensionsNumberPassword(Request $request, $company_id)
    {
        try {
            $Extension  = Extension::select('country_id', 'name', 'secret')
                ->with('country:id,country_name')
                ->where('status', '1')
                ->where('host', 'dynamic')
                ->where('company_id', $company_id)->get();

            $Tfn        = Tfn::select('country_id', 'tfn_number')
                ->with('countries:id,country_name')
                ->where('status', 1)
                ->where('activated', '1')
                ->where('company_id', $company_id)->get();
            $response['Extension']  = $Extension->toArray();
            $response['Tfn']        = $Tfn->toArray();
            if ($Extension->isNotEmpty() ||  $Tfn->isNotEmpty()) {
                return $this->output(true, 'Success', $response, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } catch (\Exception $e) {
            Log::error('Error in fetching extension number and secret : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function updateExtensionsDetails(Request $request)
    {
        try {
            $webrtc_template_url = config('app.webrtc_template_url');
            $softphone_template_url = config('app.softphone_template_url');
            $user = \Auth::user();
            $validator = Validator::make($request->all(), [
                'extension'               => 'required|array',
                'extension.*.id'          => 'required|numeric|exists:extensions,id',
                'extension.*.name'        => 'required|numeric|exists:extensions,name',
                'extension.*.barge'       => 'required|numeric',
                'extension.*.recording'   => 'required|numeric',
                'extension.*.sip_temp'    => 'required|string',
                'extension.*.secret'      => 'required|string',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $input = $request->all();
                $extension = $input['extension'];
                if (is_array($extension)) {
                    $extension_details = array();
                    foreach ($extension as $data) {
                        $id = $data['id'];
                        unset($data['id']);
                        //$data = ['barge' => $data['barge'], 'recording' => $data['recording'], 'sip_temp' => $data['sip_temp'], 'secret' => $data['secret']];
                        $res = Extension::where('id', $id)->update($data);
                        $extension_details[]  = $res;

                        if ($data['sip_temp'] == 'WEBRTC') {
                            $addExtensionFile = $webrtc_template_url;
                            $removeExtensionFile = $softphone_template_url;
                        } else {
                            $addExtensionFile = $softphone_template_url;
                            $removeExtensionFile = $webrtc_template_url;
                        }

                        Log::error('addExtensionFile : ' . $addExtensionFile . '  / removeExtensionFile: ' . $removeExtensionFile);

                        $ConfTemplate = ConfTemplate::select()->where('template_id', $data['sip_temp'])->first();
                        $this->addExtensionInConfFile($data['name'], $addExtensionFile, $data['secret'], '12345', $ConfTemplate->template_contents);
                        $this->removeExtensionFromConfFile($data['name'], $removeExtensionFile);
                    }

                    $server_flag = config('app.server_flag');
                    if ($server_flag == 1) {
                        $shell_script = config('app.shell_script');
                        $result = shell_exec('sudo ' . $shell_script);
                        Log::error('Extension Update File Transfer Log : ' . $result);
                        $this->sipReload();
                    }


                    if (count($extension_details) > 0) {
                        return $this->output(true, 'Success', $extension_details, 200);
                    } else {
                        return $this->output(true, 'No Records updated', []);
                    }
                } else {
                    return $this->output(false, 'Wrong extension value format.');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in fetching extension Details : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }


    public function multipleDeleteExtension(Request $request)
    {
        try {
            $user = \Auth::user();
            $validator = Validator::make($request->all(), [
                'extension'     => 'required|array',
                'extension.*'   => 'required|numeric|exists:extensions,id',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $input = $request->all();
                $extensionsId = $input['extension'];
                if (is_array($extensionsId)) {
                    foreach ($extensionsId as $id) {
                        DB::beginTransaction();
                        $Extension = Extension::where('id', $id)->first();
                        if ($Extension) {

                            $removeExtensionFile = config('app.webrtc_template_url');
                            $this->removeExtensionFromConfFile($Extension->name, $removeExtensionFile);

                            $removeExtensionFile = config('app.softphone_template_url');
                            $this->removeExtensionFromConfFile($Extension->name, $removeExtensionFile);


                            $resdelete = $Extension->delete();
                            if ($resdelete) {
                                Log::error('Mutli delete- Remove Extension From File: ' . $Extension->name);


                                Cart::where('item_id', '=', $id)->delete();
                                RingMember::where('extension', $Extension->name)->delete();
                                QueueMember::where('membername', $Extension->name)->delete();
                                DB::commit();
                                //return $this->output(true,'Success',200);
                            } else {
                                DB::commit();
                                return $this->output(false, 'Error occurred in Extension deleting. Please try again!.', [], 209);
                            }
                        } else {
                            DB::commit();
                            return $this->output(false, 'Extension not exist with us.', [], 409);
                        }
                    }

                    Log::error('Multiple Remove Extension From File: ' . $removeExtensionFile);
                    $server_flag = config('app.server_flag');
                    if ($server_flag == 1) {
                        $shell_script = config('app.shell_script');
                        $result = shell_exec('sudo ' . $shell_script);
                        Log::error('Extension Update File Transfer Log : ' . $result);
                        $this->sipReload();
                    }
                    return $this->output(true, 'Success', 200);
                } else {
                    return $this->output(false, 'Wrong extension value format.');
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Multi Extension Deleting : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }


    public function getExtensionsByCompany(Request $request, $company_id)
    {
        $Company = Company::find($company_id);
        if (is_null($Company)) {

            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 409);
        } else {
            $data = Extension::with('company:id,company_name,email,mobile')
                ->with('country:id,country_name')
                ->select('id', 'name', 'agent_name', 'callbackextension', 'country_id', 'company_id')
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
    }

    /***  get extension list for barging */
    public function getExtensionsForBarging(Request $request)
    {
        $user = \Auth::user();
        $company_id = $request->company_id ?? NULL;
        // return $user->roles->first()->slug;
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {

            $data = Extension::select('id', 'name', 'agent_name')
                ->where('barge', '1')
                ->where('status', 1)
                ->orderBy('id', 'DESC')
                ->get();
        } else {
            $data = Extension::select('id', 'name', 'agent_name')
                ->where('company_id', $company_id)
                ->where('barge', '1')
                ->where('status', 1)
                ->orderBy('id', 'DESC')
                ->get();
        }

        if ($data->isNotEmpty()) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }


    /**********  Renew Extensions  ********** */
    public function renewExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id'    => 'required|numeric|exists:countries,id',
            'company_id'    => 'required|numeric|exists:companies,id',
            'id.*'          => 'required|exists:extensions,id',
            'payment_type'  => 'nullable',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try {
            DB::beginTransaction();
            $user = \Auth::user();
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $Company = Company::where('id', $request->company_id)->first();
                $input = $request->all();
                $extensions_id = $input['id'];
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
                        if (is_array($extensions_id)) {
                            $TotalItemPrice = $item_price * count($extensions_id);
                            if ($Company->plan_id == 1 && $request->payment_type == 'Paid' && $Company->balance < $TotalItemPrice) {
                                DB::commit();
                                return $this->output(false, 'Company account has insufficient balance!');
                            } else {
                                $item_ids = [];
                                $status = '1';
                                $host = 'dynamic';
                                $payment_status = ($Company->plan_id == 1) ? $request->payment_type : 'Free';

                                foreach ($extensions_id as $id) {
                                    $Extension = Extension::find($id);
                                    $sip_temp = $Extension->sip_temp;

                                    $startingdate = Carbon::now();
                                    if ($Company->plan_id == 2) {
                                        $expirationdate = $startingdate->addDays(179);
                                    } else {
                                        if ($startingdate > $Extension->expirationdate) {
                                            $expirationdate = $startingdate->addDays(29);
                                        } else {
                                            $dt = Carbon::create($Extension->expirationdate);
                                            $expirationdate = $dt->addDays(29);
                                        }
                                    }


                                    $Extension->startingdate    = $startingdate;
                                    $Extension->expirationdate  = $expirationdate;
                                    $Extension->host            = $host;
                                    $Extension->updated_at      = Carbon::now();
                                    $Extension->status          = $status;
                                    $ExtensionRes               = $Extension->save();

                                    if ($ExtensionRes) {
                                        if ($sip_temp == 'WEBRTC') {
                                            $addExtensionFile = config('app.webrtc_template_url');
                                        } else {
                                            $addExtensionFile = config('app.softphone_template_url');
                                        }

                                        $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                                        $this->addExtensionInConfFile($Extension->name, $addExtensionFile, $Extension->secret, $Company->account_code, $ConfTemplate->template_contents);

                                        $item_ids[$id] = $Extension->name;
                                    }
                                }

                                if ($Company->plan_id == 1 && $request->payment_type == 'Paid') {
                                    $Company = Company::where('id', $request->company_id)->first();
                                    if ($Company->balance > $TotalItemPrice) {
                                        $Company_balance = $Company->balance;
                                        $Company->balance = $Company_balance - $TotalItemPrice;
                                        if ($Company->save()) {
                                            $response['total_extension'] = count($item_ids);
                                            $response['Show_Cart'] = 'No';
                                        } else {
                                            DB::rollback();
                                            return $this->output(false, 'Error occurred in deducting company balance.', [], 209);
                                        }
                                    } else {
                                        DB::rollback();
                                        return $this->output(false, 'Company account has insufficient balance.');
                                    }
                                    $payment_status = $request->payment_type;
                                }

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
                                    'payment_status'    => $payment_status,
                                    'email_status'      => 0,
                                ]);
                                $purchase_item = array();
                                foreach ($item_ids as $item_id => $item) {
                                    $purchase_item[] = $item;
                                    $InvoiceItems = InvoiceItems::create([
                                        'invoice_id'    => $Invoice->id,
                                        'country_id'    => $request->country_id,
                                        'item_type'     => 'Extension',
                                        'item_number'   => $item,
                                        'item_price'    => $item_price,
                                        'item_category' => 'Renew',
                                    ]);
                                }
                                if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                                    $payment_by = 'Super Admin';
                                } else {
                                    $payment_by = 'Company';
                                }
                                $payment = Payments::create([
                                    'company_id'        => $request->company_id,
                                    'invoice_id'        => $Invoice->id,
                                    'ip_address'        => $request->ip(),
                                    'invoice_number'    => $invoice_id,
                                    'order_id'          => $invoice_id . '-UID-' . $request->company_id,
                                    'item_numbers'      => implode(',', $purchase_item),
                                    'payment_type'      => $payment_status,
                                    'payment_by'        => $payment_by,
                                    'payment_currency'  => 'USD',
                                    'payment_price'     => $TotalItemPrice,
                                    'stripe_charge_id'  => '',
                                    'transaction_id'    => $TotalItemPrice . '-' . time(),
                                    'status'            => 1,
                                ]);

                                /* $emailData['title']         = 'Invoice From Callanalog';
                                $emailData['item_numbers']  = $item_ids;
                                $emailData['item_types']    = 'Extension';
                                $emailData['price']         = $TotalItemPrice;
                                $emailData['invoice_number'] = $invoice_id;
                                $emailData['email']         = $Company->email;
                                $emailData['email_template'] = 'invoice';
                                dispatch(new \App\Jobs\SendEmailJob($emailData)); */

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
                                return $this->output(true, 'Extension renew successfully.', $response);
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
            } else {
                DB::commit();
                return $this->output(false, 'You are not authorized user.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in Extensions renewing : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Error Occurred in renewing extensions. Please try again after some time.', [], 406);
        }
    }

    /************ End */

    public function extensionexpDateUpdate(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'name' => 'required|numeric',
            'expirationdate' => 'required|date_format:Y-m-d',
        ], [
            'name.required' => 'Extension Number is Required',
            'expirationdate.required' => 'Expiration Date is Required'
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 400);
        }
        try {
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $dataChangeExtensions = Extension::where('name', $request->name)->first();
                if (is_null($dataChangeExtensions)) {
                    return $this->output(false, 'This Number does not exist with us. Please try again!', [], 404);
                }
                $currentDate = Carbon::today(); // Only the date part, time set to 00:00:00
                $requestExpirationDate = \Carbon\Carbon::createFromFormat('Y-m-d', $request->expirationdate);
                $sip_temp = $dataChangeExtensions->sip_temp;
                
                $Company = Company::where('id', $dataChangeExtensions->company_id)->first();

                if ($requestExpirationDate->greaterThanOrEqualTo($currentDate)) 
                { 
                    $dataChangeExtensions->expirationdate = $requestExpirationDate;
                    $dataChangeExtensions->expirationdate = $requestExpirationDate;
                    $dataChangeExtensions->host = 'dynamic';
                    $dataChangeExtensions->status = 1;
                    if ($sip_temp == 'WEBRTC') {
                        $addExtensionFile = config('app.webrtc_template_url');
                    } else {
                        $addExtensionFile = config('app.softphone_template_url');
                    }

                    $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                    $this->addExtensionInConfFile($dataChangeExtensions->name, $addExtensionFile, $dataChangeExtensions->secret, $Company->account_code, $ConfTemplate->template_contents);
                    Log::error('Write Extension into File : ' . $addExtensionFile);
                    $server_flag = config('app.server_flag');
                    if ($server_flag == 1) {
                        $shell_script = config('app.shell_script');
                        $result = shell_exec('sudo ' . $shell_script);
                        Log::error('Extension Update File Transfer Log : ' . $result);
                        $this->sipReload();
                    }
                }else{
                    $dataChangeExtensions->expirationdate = $requestExpirationDate;
                    $dataChangeExtensions->host = 'static';
                    $dataChangeExtensions->status = 0;

                    $removeExtensionFile = config('app.webrtc_template_url');
                    $this->removeExtensionFromConfFile($dataChangeExtensions->name, $removeExtensionFile);

                    $removeExtensionFile = config('app.softphone_template_url');
                    $this->removeExtensionFromConfFile($dataChangeExtensions->name, $removeExtensionFile);

                    Log::error('Multiple Remove Extension From File: ' . $removeExtensionFile);

                    $server_flag = config('app.server_flag');
                    if ($server_flag == 1) {
                        $shell_script = config('app.shell_script');
                        $result = shell_exec('sudo ' . $shell_script);
                        Log::error('Extension Update File Transfer Log : ' . $result);
                        $this->sipReload();
                    }
                }
                
                $dateData = $dataChangeExtensions->save();
                if ($dateData) {
                    $response = $dataChangeExtensions->toArray();
                    return $this->output(true, "Extension Number Date update Successfully!.", $response, 200);
                } else {
                    return $this->output(false, "Somthing went wrong. While Tfn Date update", [], 400);
                }


            } else {
                return $this->output(false, 'Sorry! You are not authorized.', [], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in Extension Number Date change : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function extensionLogin(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'name'  => 'required|numeric',
            'secret' => 'required',
        ], [
            'name.required'     => 'Extension number is required',
            'secret.required'   => 'Extension password is required'
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 400);
        }
        try {

            $Extension = Extension::select('name', 'account_code', 'secret', 'agent_name', 'host', 'sip_temp', 'company_id', 'status')
                ->with('company:id,company_name,email,mobile,status')
                ->where('name', $request->name)->first();
            if ($Extension) {
                if ($Extension->status == 1) {
                    if ((isset($Extension->company->status) && $Extension->company->status == 1)) {
                        if ($request->secret == $Extension->secret) {
                            if ($Extension->host == 'dynamic') {
                                if ($Extension->sip_temp == 'WEBRTC') {
                                    //$token =  $user->createToken('Callanalog API')->plainTextToken;
                                    $response = $Extension->toArray();
                                    //$response['token'] = $token;
                                    return $this->output(true, 'Login successfull', $response);
                                } else {
                                    return $this->output(false, 'You have configured the settings to register your extension on the softphone.', [], 423);
                                }
                            } else {
                                return $this->output(false, 'Your extension has been expired. Please contact with support.', [], 423);
                            }
                        } else {
                            return $this->output(false, 'Invalid password!', [], 409);
                        }
                    } else {
                        return $this->output(false, 'Your company account has been suspended. Please contact with support.', [], 423);
                    }
                } else {
                    return $this->output(false, 'Extension is not activated!', [], 403);
                }
            } else {
                return $this->output(false, 'Extension dose not exist!', [], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in Extension login with WEBRTC : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function getAllExtensionsLog(Request $request)
    {
        $user = \Auth::user();
        $params = $request->get('params', "");
        $log_id = $request->get('id', null);
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            if ($log_id) {
                $getextensionlog = ExtensionLogs::with('user:id,name,email')->with('company:id,parent_id,company_name,balance')->where('id', $log_id)->first();
            } elseif ($params !== "") {
                $getextensionlog = ExtensionLogs::select('*')->with('user:id,name,email')->with('company:id,parent_id,company_name,email,balance')
                    ->where(function ($query) use ($params) {
                        $query->where('extension_ip', 'LIKE', "%$params%")
                            ->orWhere('extension_name', 'LIKE', "%$params%")
                            ->orWhereHas('user', function ($query) use ($params) {
                                $query->where('name', 'like', "%{$params}%")
                                    ->orWhere('email', 'like', "%{$params}%");
                            })
                            ->orWhereHas('company', function ($query) use ($params) {
                                $query->where('company_name', 'like', "%{$params}%")
                                    ->orWhere('email', 'like', "%{$params}%");
                            });
                    })
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            } else {
                $getextensionlog = ExtensionLogs::select('*')->with('user:id,name,email')->with('company:id,parent_id,company_name,email,balance')
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
            }

            if (!is_null($getextensionlog)) {
                $dd = $getextensionlog->toArray();
                unset($dd['links']);
                return $this->output(true, 'Success', $dd, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized.', [], 403);
        }
    }


    public function extensionContactList(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'name'  => 'required|numeric|exists:extensions,name',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 400);
        }
        try {
            $Extension = Extension::select('id', 'company_id', 'name', 'callbackextension', 'country_id')->where('name', $request->name)->first();
            if ($Extension) {
                $Extensions = Extension::select('id', 'name', 'agent_name', 'callbackextension', 'country_id')
                    ->where('company_id', $Extension->company_id)
                    ->where('name', '!=', $request->name)
                    ->where('host', 'dynamic')
                    ->where('status', 1)->get();

                if (!is_null($Extensions)) {
                    $response = $Extensions->toArray();
                    return $this->output(true, 'Success', $response, 200);
                } else {
                    return $this->output(true, 'No Record Found', []);
                }
            } else {
                return $this->output(false, 'Extension is not exist!', [], 403);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in getting Extension Contact : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function getAllExtensionForsuperadmintodownloadincsv(Request $request)
    {
        $user = \Auth::user();
        $params = $request->params ?? "";
        $options = $request->get('options', null);
        $getextensions = Extension::select('extensions.id', 'extensions.country_id', 'extensions.company_id', 'callbackextension', 'agent_name', 'name', 'host', 'expirationdate', 'status', 'secret', 'sip_temp', 'callerid', 'callgroup', 'extensions.mailbox as mail_box', 'voice_mails.mailbox', 'barge', 'voice_mails.email', 'recording', 'dial_timeout')
            ->with(['company' => function ($query) {
                $query->select('id', 'company_name', 'email', 'mobile', 'balance', 'plan_id');
            }, 'company.user_plan' => function ($query) {
                $query->select('id', 'name');
            }])
            ->with('country:id,country_name')
            ->leftJoin('voice_mails', 'extensions.name', '=', 'voice_mails.mailbox');
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            if ($params != "") {
                $getextensions->orWhere('name', 'like', "%$params%")
                    ->orWhere('agent_name', 'like', "%$params%")
                    ->orWhere('host', 'like', "%$params%")
                    ->orWhere('sip_temp', 'like', "%$params%")
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('company_name', 'like', "%{$params}%");
                    })
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('email', 'like', "%{$params}%");
                    })
                    ->orWhereHas('country', function ($query) use ($params) {
                        $query->where('country_name', 'like', "%{$params}%");
                    });
            } elseif ($options != "") {
                if ($options == 1) {
                    $getextensions->where('extensions.host', '=', NULL)->where('extensions.status', '=', '0');
                } elseif ($options == 2) {
                    $getextensions->where('extensions.host', '=', 'dynamic')->where('extensions.status', '=', '1')->whereBetween('expirationdate', [Carbon::now(), Carbon::now()->addDays(3)]);
                } elseif ($options == 3) {
                    $getextensions->where('extensions.host', '=', 'static')->where('extensions.status', '=', '0')->where('extensions.expirationdate', '<', Carbon::now());
                }
            } else {
                $getextensions;
            }

            $data_extensioncsv = $getextensions->get();
            if (!is_null($data_extensioncsv)) {
                $dd = $data_extensioncsv->toArray();
                unset($dd['links']);
                return $this->output(true, 'Success', $dd, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized.', [], 403);
        }
    }

    public function extensionUnregisterFromOpenSips(Request $request, $extension_number)
    {
        $user = \Auth::user();
        try{
            $existExtension = Extension::select(['name'])->where('name', $extension_number)->get();
            $exitArray = $existExtension->toArray();
            if(!empty($exitArray)){
                $shell_script = config('app.extension_unregister_script');
                $command = 'sudo ' . escapeshellcmd($shell_script) . ' ' . escapeshellarg($extension_number);

               // echo 'Command: ' . $command; // Debug the command being executed
                $result = shell_exec($command);

                Log::error('Extension {' . $extension_number . '} unregistered Successfully : ' . $result);

                if($result){
                    return $this->output(true, 'Extension {'.$extension_number.'} unregistered Successfully. ', [], 200);
                }else{
                    return $this->output(false, 'Sorry! extension not unregistered.', [], 403);
                }
            }else{
                Log::error('Sorry! extension not exist with us');
                return $this->output(false, 'Sorry! extension not exist with us.', [], 403);
            }      
        } catch (\Exception $e) {
            Log::error('Error occurred in unregistered extension from opensips : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
