<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OneGoUser;
use App\Models\Company;
use App\Models\User;
use App\Models\Cart;
use App\Models\Tfn;
use App\Models\RingGroup;
use App\Models\RingMember;
use App\Models\ConfTemplate;
use App\Models\Extension;
use App\Models\Invoice;
use App\Models\ResellerWallet;
use App\Models\InvoiceItems;
use App\Models\Payments;
use App\Models\TfnDestination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Mail;
use Carbon\Carbon;

class OneGoUserController extends Controller
{
    public function getOneGoUser(Request $request){
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
		$params = $request->params ?? "";
        /*
			$data = OneGoUser::select('*')
                    ->with('parent:id,name,email')
					->with('user:id,name,email')
					->with('company:id,parent_id,company_name,email,mobile')
                    ->with('country:id,country_name')->get();
                    */

            $data = OneGoUser::select('one_go_user_steps.*',DB::raw("GROUP_CONCAT(extensions.name) as extension_name,agent_name"))
                    ->with('parent:id,name,email')
					->with('user:id,name,email')
					//->with('company:id,parent_id,company_name,email,mobile,plan_id,billing_address,country_id')
                    ->with('company.country:id,country_name,phone_code')
                    ->with('country:id,country_name')
                    ->with('tfn:id,tfn_number')
                    ->with('ring:id,ringno')
                    //->with('invoice')
                    ->with('invoice.invoice_items')
                    ->leftjoin("extensions",DB::raw("FIND_IN_SET(extensions.id,one_go_user_steps.extension_id)"),">",DB::raw('0'))
                    ->groupBy("one_go_user_steps.id")
                    ->orderBy('one_go_user_steps.id', 'DESC')
			    	->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page'); 

		if($data->isNotEmpty()){
            $dd = $data->toArray();
			unset($dd['links']);
			return $this->output(true, 'Success', $dd);
		}else{
			return $this->output(true, 'No Record Found', []);
		}
    }
    public function reserveTFN(Request $request)
    {
        try {
            DB::beginTransaction();
            $perPageNo = isset($request->perpage) ? $request->perpage : 25;
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|numeric|exists:companies,id',
                'user_id' => 'required|numeric|exists:users,id',
                'country_id' => 'required|numeric|exists:countries,id',
                'user_type' => 'required|string|in:Reseller,Company',
                'parent_id' => 'required_if:user_type,Reseller',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            //$Company = Company::find($request->company_id);
            $parent_id = '';
            $price_for = $request->user_type;
            $parent_id = $request->parent_id;
            $type = 'Toll Free';
            $starting_digits = '';
            $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $parent_id, 'TFN');
            if ($item_price_arr['Status'] == 'true') {
                $item_price = $item_price_arr['TFN_price'];
                $company = Company::where('id', $request->company_id)->first();
                $inbound_trunk = explode(',', $company->inbound_permission);

                $tfnNumber = Tfn::where('country_id', $request->country_id)
                            ->where('company_id', 0)
                            ->where('assign_by', 0)
                            ->whereIn('tfn_provider', $inbound_trunk)
                            ->where('activated', '0')
                            ->where('reserved', '0')
                            ->where('status', 1)
                            ->first();
                if ($tfnNumber) {
                    $tfnNumber->reserved = '1';
                    $tfnNumber->reserveddate = date('Y-m-d H:i:s');
                    $tfnNumber->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));                    
                    $tfnNumber_res = $tfnNumber->save();
                    if ($tfnNumber_res) {
                        $addCart = Cart::create([
                            'company_id'    => $request->company_id,
                            'country_id'    => $request->country_id,
                            'item_id'       => $tfnNumber->id,
                            'item_number'   => $tfnNumber->tfn_number,
                            'item_type'     => 'TFN',
                            'item_price'    => $item_price,
                        ]);
                        DB::table('one_go_user_steps')
                            ->where('company_id', $request->company_id)
                            ->where('user_id', $request->user_id)
                            ->update([
                                'country_id'    => $request->country_id,
                                'tfn_id'        => $tfnNumber->id,
                                'step_no'       => '2',
                                'updated_at'    => Carbon::now(),
                            ]);

                        $tfnNumberRR = Tfn::where('id', $tfnNumber->id)->first();
                        DB::commit();
                        return $this->output(true, 'Success', $tfnNumberRR->toArray(), 200);
                    } else {
                        DB::commit();
                        return $this->output(false, 'Error occurred in reserving TFN for you. Please try after some time.');
                    }
                } else {
                    DB::commit();
                    return $this->output(false, 'TFN not available for this country', []);
                }
            } else {
                DB::commit();
                return $this->output(false, $item_price_arr['Message']);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in creating user by One-GO : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }


    public function createExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id'=> 'required|numeric|exists:companies,id',
            'user_id'   => 'required|numeric|exists:users,id',
            'country_id'=> 'required|numeric|exists:countries,id',
            'name.*'    => 'required|unique:extensions,name',
            'agent_name'=> 'required|max:150',
            'secret'    => 'required',
            'user_type' => 'required|string|in:Reseller,Company',
            'parent_id' => 'required_if:user_type,Reseller',
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
                $parent_id = '';
                $price_for = $request->user_type;
                if ($request->user_type == 'Reseller') {
                    $parent_id = $request->parent_id;
                }
                $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $parent_id, 'Extension');
                if ($item_price_arr['Status'] == 'true') {
                    $item_price = $item_price_arr['Extension_price'];
                    if (is_array($extension_name)) {
                        $TotalItemPrice = $item_price * count($extension_name);
                        $item_ids = [];
                        $status = '0';
                        $startingdate = $expirationdate = $host = NULL;
                        $sip_temp = 'WEBRTC';
                        if ($Company->plan_id == 2) {
                            $status = '1';
                            $startingdate = Carbon::now();
                            $expirationdate = $startingdate->addDays(179);
                            $host = 'dynamic';
                            $sip_temp = 'WEBRTC';
                            $payment_status = 'Free';
                        }

                        foreach ($extension_name as $item) {
                            $callbackextension = str_pad(rand(1, 9999), 4, "0", STR_PAD_LEFT);
                            $data = [
                                'country_id' => $request->country_id,
                                'company_id' => $request->company_id,
                                'name' => $item,
                                'callbackextension' => $callbackextension,
                                'account_code' => $Company->account_code,
                                'agent_name' => $request->agent_name,
                                'callgroup' => '0',
                                'callerid' => '',
                                'secret' => $request->secret,
                                'barge' => '0',
                                'recording' => '1',
                                'mailbox' => '0',
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
                            $Extension = Extension::create($data);
                            $response = $Extension->toArray();
                            $item_ids[] = $Extension->id;
                            
                            $addExtensionFile = config('app.webrtc_template_url');
                            $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
                            $this->addExtensionInConfFile($item, $addExtensionFile, $request->secret, $Company->account_code, $ConfTemplate->template_contents);

                            if ($Company->plan_id == 1) {
                                $addCart = Cart::create([
                                    'company_id'    => $request->company_id,
                                    'country_id'    => $request->country_id,
                                    'item_id'       => $Extension->id,
                                    'item_number'   => $item,
                                    'item_type'     => 'Extension',
                                    'item_price'    => $item_price,
                                ]);
                            }
                        }

                        /* $server_flag = config('app.server_flag');
                        if ($Company->plan_id == 2 && $server_flag == 1) {
                            $shell_script = config('app.shell_script');
                            $result = shell_exec('sudo ' . $shell_script);
                            Log::error('Extension File Transfer Log : ' . $result);
                            $this->sipReload();
                        } */
                        //$item_ids['total_extension'] = count($item_ids);
                        $steps_result = DB::table('one_go_user_steps')
                            ->where('company_id', $request->company_id)
                            ->where('user_id', $request->user_id)
                            ->update([
                                'extension_id' => implode(',', $item_ids),
                                'step_no' => '3',
                                'updated_at' => Carbon::now(),
                            ]);
                        if($steps_result){
                            DB::commit();
                            return $this->output(true, 'Extension added successfully.', $item_ids);
                        }else{
                            DB::rollback();
                            return $this->output(false, 'Error occurred in creating Extension.', [], 409);
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

    public function addRingGroup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'country_id'=> 'required|numeric|exists:countries,id',
                'company_id'=> 'required|numeric|exists:companies,id',
                'user_id'   => 'required|numeric|exists:users,id',
                'ringno'    => 'required|unique:ring_groups',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            DB::beginTransaction();
            $RingGroup = RingGroup::create([
                'country_id'=> $request->country_id,
                'company_id'=> $request->company_id,
                'ringno'    => $request->ringno,
                'strategy'  => 'ringall',
                'ringtime'  => '60',
            ]);

            $steps_result = DB::table('one_go_user_steps')
                ->where('company_id', $request->company_id)
                ->where('user_id', $request->user_id)
                ->update([
                    'ring_id' => $RingGroup->id,
                    'step_no' => '4',
                    'updated_at' => Carbon::now(),
                ]);
            if($steps_result){
                $response = $RingGroup->toArray();
                DB::commit();
                return $this->output(true, 'Ring Group added successfully.', $response);
            }else{
                DB::rollback();
                return $this->output(false, 'Error occurred in creating Ring Group.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in Ring Group Inserting : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function createInvoice(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
        //    'oneGoUser_id' => 'required|numeric|exists:one_go_user_steps,id',
            'company_id'=> 'required|numeric|exists:companies,id', 
            'user_id'   => 'required|numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        try {
            DB::beginTransaction();
            $data = OneGoUser::select('one_go_user_steps.*',DB::raw("GROUP_CONCAT(extensions.name) as extension_name"))
                    ->with('parent:id,name,email')
					->with('user:id,name,email')
					->with('company:id,email,parent_id,country_id,state_id,plan_id')
                    ->with('country:id,country_name')
                    ->with('tfn:id,tfn_number')
                    ->with('ring:id,ringno')
                    ->leftjoin("extensions",DB::raw("FIND_IN_SET(extensions.id,one_go_user_steps.extension_id)"),">",DB::raw('0'))
                    ->groupBy("one_go_user_steps.id")
                    //->where('one_go_user_steps.id', $request->oneGoUser_id)
                    ->where('one_go_user_steps.company_id', $request->company_id)
                    ->where('one_go_user_steps.user_id', $request->user_id)
                    ->first(); 
            
            if($data){
                $oneGoUser = $data->toArray();

                if(!empty($oneGoUser['parent_id']) && !empty($oneGoUser['company_id']) && !empty($oneGoUser['user_id']) && !empty($oneGoUser['country_id']) && !empty($oneGoUser['tfn_id']) && !empty($oneGoUser['extension_id']) && !empty($oneGoUser['ring_id']))
                {
                    $itemArray = array();
                    $parent_id = $oneGoUser['parent_id'];
                    $price_for = 'Company';
                    if ($parent_id > 1){
                        $price_for = 'Reseller';
                    }
                    $tfn_price_arr = $this->getItemPrice($oneGoUser['company_id'],$oneGoUser['country_id'], $price_for, $parent_id, 'TFN');                
                    
                    if ($tfn_price_arr['Status'] == 'true') {
                        $extension_price_arr = $this->getItemPrice($oneGoUser['company_id'],$oneGoUser['country_id'], $price_for, $parent_id, 'Extension');
                        
                        if ($extension_price_arr['Status'] == 'true') { 
                            $tfn_price = $tfn_price_arr['TFN_price'];
                            $extension_price    = $extension_price_arr['Extension_price'];

                            $extension_arr      = explode(',',$oneGoUser['extension_name']);
                            $extensions_amount  = $extension_price * count($extension_arr);
                            $invoice_amount_main= $extensions_amount + $tfn_price;
                            
                            $invoice_amount     = number_format($invoice_amount_main, 2, '.', '');

                            $invoicetable_id = DB::table('invoices')->max('id');
                            if (!$invoicetable_id) {
                                $invoice_id = '#INV/' . date('Y') . '/00001';
                            } else {
                                $invoice_id = "#INV/" . date('Y') . "/000" . ($invoicetable_id + 1);
                            }

                            $createinvoice = Invoice::create([
                                'company_id'    => $oneGoUser['company_id'],
                                'country_id'    => $oneGoUser['company']['country_id'],
                                'state_id'      => $oneGoUser['company']['state_id'],
                                'invoice_id'    => $invoice_id,
                                'invoice_currency'  => 'USD',
                                'invoice_subtotal_amount' => $invoice_amount,
                                'invoice_amount'    => $invoice_amount,
                                'payment_status'    => 'Unpaid',                            
                            ]);

                            $InvoiceItems[] = InvoiceItems::create([
                                'country_id'    => $oneGoUser['country_id'],
                                'invoice_id'    => $createinvoice->id,
                                'item_type'     => 'TFN',
                                'item_id'       => $oneGoUser['tfn_id'],
                                'item_number'   => $oneGoUser['tfn']['tfn_number'],
                                'item_price'    => $tfn_price,
                                'item_category' => 'Purchase',
                            ]);

                            foreach ($extension_arr as $extension) {
                                
                                $tfninvoicenumberExt = Extension::select('name','id')->where('name', $extension)->first();
                                $itemNumber = $tfninvoicenumberExt->name;
                                $itemId = $tfninvoicenumberExt->id;

                                $InvoiceItems[] = InvoiceItems::create([
                                    'country_id'    => $oneGoUser['country_id'],
                                    'invoice_id'    => $createinvoice->id,
                                    'item_type'     => 'Extension',
                                    'item_id'       => $itemId,
                                    'item_number'   => $itemNumber,
                                    'item_price'    => $extension_price,
                                    'item_category' => ($oneGoUser['company']['plan_id'] == 2) ? 'Free' : 'Purchase',
                                ]);
                            }

                            $steps_result = DB::table('one_go_user_steps')
                                    ->where('company_id', $oneGoUser['company_id'])
                                    ->where('user_id', $oneGoUser['user_id'])
                                    ->update([
                                        'invoice_id' => $createinvoice->id,
                                        'step_no' => '5',
                                        'updated_at' => Carbon::now(),
                                    ]);

                            if($steps_result){
                                $response['invoice'] = $createinvoice->toArray();
                                $response['invoice']['invoice_items'] = $InvoiceItems;
                                DB::commit();
                                return $this->output(true, 'Invoice Created Successfully!!.', $response);
                            }else{
                                DB::rollback();
                                return $this->output(false, 'Error occurred in creating Invoice.', [], 409);
                            }                        
                        }else{
                            DB::commit();
                            return $this->output(false, 'Extension price not available for this country. Please contact with support team.');
                        }
                    }else{
                        DB::commit();
                        return $this->output(false, 'TFN price not available for this country. Please contact with support team.');
                    }
                }else{
                    DB::commit();
                    return $this->output(false, 'Some error occurred in above steps. Please try again.', [], 409);
                }
            }else{
                DB::commit();
                return $this->output(false, 'One-Go-User data not available. Please try again.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            Log::error('Error in creating One-Go-User invoice : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
    
    public function addExtensionInConfFile($extensionName, $conf_file_path, $secret, $account_code, $template_contents)
    {
        // Add new user section
        $register_string = "\n[$extensionName]\nusername=$extensionName\nsecret=$secret\naccountcode=$account_code\n$template_contents\n";
        //$webrtc_conf_path = "/var/www/html/callanalog/admin/webrtc_template.conf";
        file_put_contents($conf_file_path, $register_string, FILE_APPEND | LOCK_EX);
        //echo "Registration successful. The SIP user $nname has been added to the webrtc_template.conf file.";        
    }

    public function createOneGoPayment(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            /* 'oneGoUser_id' => 'required|numeric|exists:one_go_user_steps,id',
            'invoice_id'   => 'required|numeric|exists:one_go_user_steps,invoice_id', */
            'company_id'=> 'required|numeric|exists:companies,id', 
            'user_id'   => 'required|numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        try {
            DB::beginTransaction();

            $data = OneGoUser::select('one_go_user_steps.*')
                    ->with('parent:id,name,email')
					->with('user:id,name,email')
					->with('company:id,email,parent_id,country_id,state_id,plan_id')
                    ->with('country:id,country_name')
                    ->with('tfn:id,tfn_number')
                    ->with('ring:id,ringno') 
                    ->with('invoice.invoice_items')
                    ->groupBy("one_go_user_steps.id")
                    ->where('one_go_user_steps.company_id', $request->company_id)
                    ->where('one_go_user_steps.user_id', $request->user_id)
                    ->first(); 
            if($data){
                
                $ipAddress = $request->ip();
                $oneGoUser = $data->toArray();
                if(!empty($oneGoUser['payment_id']) && $oneGoUser['step_no'] == 6){
                    DB::commit();
                    return $this->output(false, 'Payment is already done.');
                }else{
                    $ring_id = $oneGoUser['ring_id'];
                    $plan_id = $oneGoUser['company']['plan_id'];
                    $invoice = $oneGoUser['invoice'];
                    $invoice_id = $oneGoUser['invoice']['id'];
                    $invoice_number = $oneGoUser['invoice']['invoice_id'];
                    $order_id       = $invoice_number.'-UID-'.$request->user_id;
                    $invoice_items = $oneGoUser['invoice']['invoice_items'];
                    $payment_price = 0; $item_numbers = '';
                    foreach($invoice_items as $key => $invoice_item){
                        if($invoice_item['item_category'] != 'Free'){
                            $payment_price += $invoice_item['item_price'];
                        }
                        $item_numbers .= $invoice_item['item_number'].',';
                    }
                    $item_number_str =  rtrim($item_numbers,',');
                
                    $ResellerWallet = ResellerWallet::select()->where('user_id',$oneGoUser['parent_id'])->first(); 
                    if($ResellerWallet){
                        if($payment_price < $ResellerWallet->balance){

                            $balance = $ResellerWallet->balance - $payment_price;
                            $reseller_res = ResellerWallet::where('user_id', $oneGoUser['parent_id'])->update([
                                'balance' => $balance,
                            ]);
                            if($reseller_res){
                                $payment = Payments::create([
                                        'company_id'        => $oneGoUser['company']['id'],
                                        'invoice_id'        => $invoice_id,
                                        'ip_address'        => $ipAddress,
                                        'invoice_number'    => $invoice_number,
                                        'order_id'          => $order_id,
                                        'item_numbers'      => $item_number_str,
                                        'payment_type'      => 'Wallet Payment',
                                        'payment_currency'  => 'USD',
                                        'payment_price'     => $payment_price,
                                        'transaction_id'    => time(),
                                        'status'            => '1',
                                    ]);
                                if($payment){
                                    $startingdate = Carbon::now();
                                    $expirationdate = $startingdate->addDays(29);
                                    foreach($invoice_items as $key => $invoice_item){
                                        if($invoice_item['item_type'] == 'TFN'){
                                            $Tfn = Tfn::where('tfn_number',$invoice_item['item_number'])->update([
                                                    'company_id'    => $oneGoUser['company']['id'],
                                                    'assign_by'     => $user->id,
                                                    'activated'     => '1',
                                                    'startingdate'  => Carbon::now(),
                                                    'expirationdate'=> $expirationdate,
                                                    'status'        => '1',
                                                ]);
                                        }else{
                                            $Extension = Extension::where('name', $invoice_item['item_number'])->update([
                                                    'startingdate' => Carbon::now(),
                                                    'expirationdate'=> $expirationdate,
                                                    'host'          => 'dynamic',
                                                    'status'        => '1',
                                                ]);
                                            $RingMember = RingMember::create([
                                                    'ring_id'   => $ring_id,
                                                    'extension' => $invoice_item['item_number'],
                                                ]);
                                        } 
                                        
                                        Cart::where('item_number',$invoice_item['item_number'])->delete();
                                    }

                                    $TfnDestination = TfnDestination::create([
                                                'company_id'    => $oneGoUser['company']['id'],
                                                'tfn_id'        => $oneGoUser['tfn_id'],
                                                'destination_type_id'   => '6',
                                                'destination_id'        => $ring_id,
                                                'priority'              => 1,
                                            ]);
                                    if($TfnDestination){
                                        $server_flag = config('app.server_flag');
                                        if ($server_flag == 1) {
                                            $shell_script = config('app.shell_script');
                                            $result = shell_exec('sudo ' . $shell_script);
                                            Log::error('Extension File Transfer Log : ' . $result);
                                            $this->sipReload();
                                        }
                                        //$item_ids['total_extension'] = count($item_ids);
                                        $steps_result = DB::table('one_go_user_steps')
                                            ->where('company_id', $request->company_id)
                                            ->where('user_id', $request->user_id)
                                            ->update([
                                                'payment_id' => $payment->id,
                                                'step_no' => '6',
                                                'updated_at' => Carbon::now(),
                                            ]);
                                        if($steps_result){
                                            DB::commit();
                                            $response = $payment->toArray();
                                            return $this->output(true, 'Payment done successfully.',  $response);
                                        }else{
                                            DB::rollback();
                                            return $this->output(false, 'Error occurred in One-Go status updating.', [], 409);
                                        }
                                    }else{
                                        DB::rollback();
                                        return $this->output(false, 'Error occurred in set destination for TFN.', [], 409);
                                    }
                                }else{
                                    DB::rollback();
                                    return $this->output(false, 'Error occurred in One-Go payment saving.', [], 409);
                                }
                            }else{
                                DB::rollback();
                                return $this->output(false, 'Error occurred in amount deducting!.', [], 409);
                            }
                        }else{
                            DB::commit();
                            return $this->output(false, 'Reseller account has insufficient balance!.', [], 409);
                        }
                    }else{
                        DB::commit();
                        return $this->output(false, 'Reseller wallet account not exist!.', [], 409); 
                    }
                }
            }else{
                DB::commit();
                return $this->output(false, 'One-Go-User data not available. Please try again.', [], 409);
            }
           

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in creating One-Go-User payment : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }


    public function deleteOneGoUser(Request $request, $id)
    {
        try {  
            DB::beginTransaction();
            $OneGoUser = OneGoUser::where('id', $id)->first();
            if($OneGoUser){
                
                if(!empty($OneGoUser['invoice_id']) ){
                    InvoiceItems::where('invoice_id', $OneGoUser['invoice_id'])->delete();
                    Invoice::where('id',$OneGoUser['invoice_id'])->delete();
                }

                if(!empty($OneGoUser['ring_id']) ){
                    RingMember::where('ring_id',$OneGoUser['ring_id'])->delete();
                    RingGroup::where('id',$OneGoUser['ring_id'])->delete();
                }

                if(!empty($OneGoUser['extension_id']) ){
                    $extensionId_arr = explode(',',$OneGoUser['extension_id']);
                    foreach($extensionId_arr as $ext_id){
                        Extension::findOrFail($ext_id)->delete();
                        Cart::where('item_id',$ext_id)->delete();
                    }
                }
                if(!empty($OneGoUser['tfn_id']) ){
                    $TfnObj = Tfn::find($OneGoUser['tfn_id']);
                    if($TfnObj->company_id == 0 && $TfnObj->assign_by == 0 && $TfnObj->activated == '0' && $TfnObj->reserved == '1'){
                        Cart::where('item_id',$OneGoUser['tfn_id'])->delete();

                        $TfnObj->reserved = '0';
                        $TfnObj->reserveddate = NULL;
                        $TfnObj->reservedexpirationdate = NULL;   
                        $TfnObjRes     = $TfnObj->save();
                        if(!$TfnObjRes){                           
                            DB::rollBack();
                            return $this->output(false, 'Error occurred in tfn Updating. Please try again!.', [], 200);
                        }
                    }
                }
               
                $resdelete = $OneGoUser->delete();
                if ($resdelete) {
                    if(!empty($OneGoUser['user_id']) ){
                        DB::table('users_roles')->where('user_id',$OneGoUser['user_id'])->delete();
                        User::where('id',$OneGoUser['user_id'])->delete();
                    }
                    if(!empty($OneGoUser['company_id']) ){
                        DB::table('user_registered_servers')->where('company_id', $OneGoUser['company_id'])->delete();
                        Company::where('id',$OneGoUser['company_id'])->delete();
                        
                    }
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in One-Go-User deleting. Please try again!.', [], 209); 
                }
            }else{
                DB::commit();
                return $this->output(false,'One-Go-User not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in One-Go-User Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}