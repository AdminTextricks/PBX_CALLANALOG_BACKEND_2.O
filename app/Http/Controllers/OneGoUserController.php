<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OneGoUser;
use App\Models\Company;
use App\Models\Cart;
use App\Models\Tfn;
use App\Models\RingGroup;
use App\Models\ConfTemplate;
use App\Models\Extension;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Mail;
use Carbon\Carbon;

class OneGoUserController extends Controller
{

    public function getOneGoUser(Request $request){
        $user = \Auth::user();
			$data = OneGoUser::select('*')
                    ->with('parent:id,name,email')
					->with('user:id,name,email')
					->with('company:id,parent_id,company_name,email,mobile')
                    ->with('country:id,country_name')->get();

		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
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
                                'tfn_id' => $tfnNumber->id,
                                'step_no' => '2.1',
                                'updated_at' => Carbon::now(),
                            ]);

                        $tfnNumberRR = Tfn::where('id', $tfnNumber->id)->first();
                        DB::commit();
                        return $this->output(true, 'Success UPDATE', $tfnNumberRR->toArray(), 200);
                    } else {
                        DB::commit();
                        return $this->output(false, 'Error occurred in reserving TFN for you. Please try after some time.');
                    }
                } else {
                    DB::commit();
                    return $this->output(true, 'TFN not available for this country', []);
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
            'country_id' => 'required|numeric',
            'company_id' => 'required|numeric',
            'name.*' => 'required|unique:extensions,name',
            'callbackextension' => 'required|integer|digits_between:2,5',
            'agent_name' => 'required|max:150',
            'secret' => 'required',
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
                        $startingdate = $expirationdate = $host = $sip_temp = NULL;
                        if ($Company->plan_id == 2) {
                            $status = '1';
                            $startingdate = Carbon::now();
                            $expirationdate = $startingdate->addDays(179);
                            $host = 'dynamic';
                            $sip_temp = 'WEBRTC';
                            $payment_status = 'Free';
                        }

                        foreach ($extension_name as $item) {
                            $data = [
                                'country_id' => $request->country_id,
                                'company_id' => $request->company_id,
                                'name' => $item,
                                'callbackextension' => $request->callbackextension,
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
                        }

                        $server_flag = config('app.server_flag');
                        if ($Company->plan_id == 2 && $server_flag == 1) {
                            $shell_script = config('app.shell_script');
                            $result = shell_exec('sudo ' . $shell_script);
                            Log::error('Extension File Transfer Log : ' . $result);
                            $this->sipReload();
                        }
                        //$item_ids['total_extension'] = count($item_ids);
                        DB::table('one_go_user_steps')
                            ->where('company_id', $request->company_id)
                            ->where('user_id', $request->user_id)
                            ->update([
                                'extension_id' => implode(',', $item_ids),
                                'step_no' => '2.2',
                                'updated_at' => Carbon::now(),
                            ]);
                        DB::commit();
                        return $this->output(true, 'Extension added successfully.', $item_ids);
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
            $response = $RingGroup->toArray();
            DB::commit();
            return $this->output(true, 'Ring Group added successfully.', $response);

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
            'country_id' => 'required|numeric|exists:countries,id',
            'company_id' => 'required|numeric|exists:companies,id',
            'items' => 'required|array',
            'items.*.item_id' => 'required|numeric',
            'items.*.item_number' => 'required|numeric',
            'items.*.item_price' => 'required',
            'items.*.item_type' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        try {
            DB::beginTransaction();

            $invoice_amount_main = array_sum(array_column($request->items, 'item_price'));
            $invoice_amount = number_format($invoice_amount_main, 2, '.', '');

            $invoicetable_id = DB::table('invoices')->max('id');
            if (!$invoicetable_id) {
                $invoice_id = '#INV/' . date('Y') . '/00001';
            } else {
                $invoice_id = "#INV/" . date('Y') . "/000" . ($invoicetable_id + 1);
            }

            $createinvoice = Invoice::create([
                'company_id' => $user->company->id,
                'country_id' => $user->company->country_id,
                'state_id' => $user->company->state_id,
                'invoice_id' => $invoice_id,
                'invoice_currency' => 'USD',
                'invoice_subtotal_amount' => $invoice_amount,
                'invoice_amount' => $invoice_amount,
                'payment_status' => 'Unpaid',
            ]);

            foreach ($request->items as $item) {
                $itemType = $item['item_type'];
                $itemId = $item['item_id'];
                $itemNumber = $item['item_number'];
                $itemPrice = $item['item_price'];

                if ($itemType == "TFN") {
                    $tfninvoicenumberTfn = Tfn::select('tfn_number')->where('tfn_number', '=', $itemNumber)->first();
                    $tfninvoicenumber = $tfninvoicenumberTfn->tfn_number;
                } else {
                    $tfninvoicenumberExt = Extension::select('name')->where('name', '=', $itemNumber)->first();
                    $tfninvoicenumber = $tfninvoicenumberExt->name;
                }

                if ($tfninvoicenumber) {

                    InvoiceItems::create([
                        'company_id' => $user->company->id,
                        'invoice_id' => $createinvoice->id,
                        'item_id' => $itemId,
                        'item_number' => $itemNumber,
                        'item_price' => $itemPrice,
                    ]);

                } else {
                    DB::rollback();
                    return $this->output(false, 'This Cart Number does not belong to us', 409);
                }
            }

            $response = $createinvoice->toArray();
            DB::commit();
            return $this->output(true, 'Invoice Created Successfully!!.', $response);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage());
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
        'payment_status'    => $payment_status,
        'email_status'      => 0,
    ]);
    $purchase_item = array();
    foreach ($item_ids as $item_id => $item) {
        $purchase_item[] = $item;
        $InvoiceItems = InvoiceItems::create([                                    
            'invoice_id'    => $Invoice->id,
            'item_type'     => 'Extension',
            'item_number'   => $item,
            'item_price'    => $item_price,
        ]);
        
        $webrtc_template_url = config('app.webrtc_template_url');
        $addExtensionFile = $webrtc_template_url;
        $ConfTemplate = ConfTemplate::select()->where('template_id', $sip_temp)->first();
        $this->addExtensionInConfFile($item, $addExtensionFile, $request->secret, $Company->account_code, $ConfTemplate->template_contents);
    }

    $emailData['title'] = 'Invoice From Callanalog';
    $emailData['item_numbers'] = $item_ids;
    $emailData['item_types'] = 'Extension';
    $emailData['price'] = $TotalItemPrice;
    $emailData['invoice_number'] = $invoice_id;
    $emailData['email'] = $Company->email;
    $emailData['email_template'] = 'invoice';
    dispatch(new \App\Jobs\SendEmailJob($emailData));
     */


    protected function addExtensionInConfFile($extensionName, $conf_file_path, $secret, $account_code, $template_contents)
    {
        // Add new user section
        $register_string = "\n[$extensionName]\nusername=$extensionName\nsecret=$secret\naccountcode=$account_code\n$template_contents\n";
        //$webrtc_conf_path = "/var/www/html/callanalog/admin/webrtc_template.conf";
        file_put_contents($conf_file_path, $register_string, FILE_APPEND | LOCK_EX);
        //echo "Registration successful. The SIP user $nname has been added to the webrtc_template.conf file.";        
    }
}