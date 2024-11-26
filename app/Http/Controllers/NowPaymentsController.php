<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\ConfTemplate;
use App\Models\Country;
use App\Models\Extension;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\Payments;
use App\Models\RechargeHistory;
use App\Models\ResellerRechargeHistories;
use App\Models\State;
use App\Models\Tfn;
use App\Models\User;
use App\Services\NowPaymentsService;
use App\Traits\ManageNotifications;
use Carbon\Carbon;
use Validator;
use Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

class NowPaymentsController extends Controller
{
    use ManageNotifications;
    protected $nowPaymentsService;

    public function __construct(NowPaymentsService $nowPaymentsService)
    {
        $this->nowPaymentsService = $nowPaymentsService;
    }

    public function createPayment(Request $request)
    {
        $user = \Auth::user();
        $invoice_balance = Invoice::select('invoice_amount')->where('id', $request->invoice_id)->where('payment_status', '=', 'Unpaid')->first();
        if (!$invoice_balance) {
            return $this->output(false, 'The Invoice is Paid!!', 400);
        }
        $getcountry = Country::select('*')->where('id', $user->company->country_id)->first();
        $getstate = State::select('state_name')->where('id', $user->company->state_id)->first();
        $invoice_items = InvoiceItems::where('invoice_id', '=', $request->invoice_id)->get();
        if ($invoice_balance->invoice_amount === $request->payment_price) {
            foreach ($invoice_items as $item) {
                $itemType = $item['item_type'];
                $itemNumber = $item['item_number'];
                $cart = Cart::select('*')->where('item_number', '=', $itemNumber)->first();
                if (!$cart) {
                    return $this->output(false, 'Item Number ' . $itemNumber . ' is Not Added to Cart!.', 400);
                }
                if ($itemType === "TFN") {
                    $tfn_list_type = Tfn::select('*')->where('tfn_number', $itemNumber)->first();
                    if (is_null($tfn_list_type)) {
                        return $this->output(false, 'This Tfn Number ' . $itemNumber . ' dose not belongs to us or is currently in process.', 400);
                    }
                } else {
                    $extnumber = Extension::select('*')->where('name', '=', $itemNumber)->first();
                    if (is_null($extnumber)) {
                        return $this->output(false, 'This Extension Number ' . $itemNumber . ' dose not belongs to us or is currently in process', 400);
                    }
                }
            }
            $validator = Validator::make($request->all(), [
                'payment_price' => 'required|numeric|min:1',
                'currency' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            try {
                $price_currency = 'usd';
                $price_amount = $request->payment_price;
                $updatedInvoiceNumber = str_replace('#', '', $request->invoice_number);
                $orderId = $updatedInvoiceNumber  . 'UID' . $user->id;

                $pay_currency = 'usdttrc20';
                $paymentAPI = $this->nowPaymentsService->createPayment($price_currency, $price_amount, $orderId, $pay_currency, $user->company->email);
                if ($paymentAPI) {
                    $paymentId = $paymentAPI['payment_id'];
                    $paymentUrl = $paymentAPI['pay_address'];
                    // $invoice_items = InvoiceItems::where('invoice_id', '=', $request->invoice_id)->get();
                    foreach ($invoice_items as $item) {
                        $itemNumbers[] = $item['item_number'];
                        $itemTypes[] = $item['item_type'];
                    }
                    $payment_data = Payments::create([
                        'company_id' => $user->company_id,
                        'invoice_id'  => $request->invoice_id,
                        'ip_address' => $request->ip(),
                        'invoice_number'  => $request->invoice_number,
                        'order_id'        => $request->invoice_number . '-UID-' . $user->id,
                        'item_numbers'    => implode(', ', $itemNumbers),
                        'payment_type'    => 'Crypto Payment',
                        'payment_by'      => 'Company',
                        'payment_currency' => $request->currency ?? 'USD',
                        'transaction_id'  => $paymentId,
                        'stripe_charge_id' => '',
                        'status' => 0,
                    ]);

                    $qrCode = new QrCode($paymentUrl);
                    $writer = new PngWriter();
                    $result = $writer->write($qrCode);
                    $qrCodePath = public_path('qr_codes/' . $paymentId . '.png');
                    $result->saveToFile($qrCodePath);
                    return response()->json([
                        'payment' =>  $paymentAPI,
                        'qr_code_url' => asset('qr_codes/' . $paymentId . '.png'),
                        'items' => $request->items,
                        'invoice_id' => $request->invoice_id,
                        'invoice_number' => $request->invoice_number
                    ]);
                } else {
                    DB::rollback();
                    return response()->json(['error' => 'Payment creation failed.'], 500);
                }
            } catch (\Exception $e) {
                DB::rollback();
                \Log::error('Payment creation failed: ' . $e->getMessage());
                return response()->json(['error' => 'Payment creation failed.'], 500);
            }
        } else {
            return $this->output(false, 'Oops! Something Went Wrong. Mismatch values', 409);
        }
    }


    public function checkPaymentStatus(Request $request, $paymentId)
    {
        $user = \Auth::user();
        $itemNumbers = [];
        $itemTypes = [];
        $nowPayment_charge_id = Str::random(30);
        $NowPaymentData = $this->nowPaymentsService->getPaymentStatus($paymentId);
        try {
            if ($NowPaymentData && $NowPaymentData['payment_status'] == "partially_paid") {
                //partially_paid - it shows that the customer sent the less than the actual price. Appears when the funds have arrived in your wallet.
                $response['crypto_payment_status'] = $NowPaymentData['payment_status'];
                return $this->output(true, 'Oops! Something Went Wrong. Mismatch values', $response, 200);
            } elseif ($NowPaymentData && $NowPaymentData['payment_status'] == "finished") {
                DB::beginTransaction();
                $payment = Payments::where('transaction_id', '=', $paymentId)->first();
                $invoice_items = InvoiceItems::where('invoice_id', '=', $payment->invoice_id)->get();
                foreach ($invoice_items as $item) {
                    $itemNumbers[] = $item['item_number'];
                    $itemTypes[] = $item['item_type'];
                }
                if (is_null($payment)) {
                    DB::rollback();
                    return $this->output(false, 'Something Went Wrong. please try again', 400);
                } else {

                    //Payments Table Update
                    $payment->payment_price = $NowPaymentData['pay_amount'];
                    $payment->status = 1;
                    $payment->save();

                    foreach ($invoice_items as $item) {
                        $itemType = $item['item_type'];
                        $itemNumber = $item['item_number'];
                        if ($itemType === "TFN") {
                            $numbers_list_tfn = Tfn::where('tfn_number', $itemNumber)->first();
                            if ($numbers_list_tfn && $numbers_list_tfn->expirationdate != "") {
                                if ($numbers_list_tfn->company_id != $user->company->id && $numbers_list_tfn->reserved != '1') {
                                    DB::rollback();
                                    return $this->output(false, 'Mismatch in TFN values.', 400);
                                }
                                $value = "Renew";
                                $currentDate = Carbon::now();
                                $targetDate = Carbon::parse($numbers_list_tfn->expirationdate);
                                $daysDifference = $currentDate->diffInDays($targetDate, false);
                                if ($daysDifference <= 3 && $daysDifference >= 1) {
                                    $newDate = date('Y-m-d H:i:s', strtotime('+' . (30 + $daysDifference) . ' days'));
                                    $startDate = date('Y-m-d H:i:s', strtotime('+' . $daysDifference . ' days'));
                                } elseif ($daysDifference > 3) {
                                    $newDate = date('Y-m-d H:i:s', strtotime('+' . (30 + $daysDifference) . ' days'));
                                    $startDate = date('Y-m-d H:i:s', strtotime('+' . $daysDifference . ' days'));
                                } else {
                                    $newDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                                    $startDate = date('Y-m-d H:i:s');
                                }

                                $numbers_list_tfn->update([
                                    'company_id' => $numbers_list_tfn->company_id,
                                    'assign_by' => $user->id,
                                    'activated' => '1',
                                    'startingdate' => $startDate,
                                    'expirationdate' => $newDate,
                                    'status' => 1,
                                ]);
                            } else {
                                $value = "Purchase";
                                $numbers_list_tfn->update([
                                    'company_id' => $user->company->id,
                                    'assign_by' => $user->id,
                                    'activated' => '1',
                                    'startingdate' => date('Y-m-d H:i:s'),
                                    'expirationdate' => date('Y-m-d H:i:s', strtotime('+29 days')),
                                    'status' => 1,
                                ]);
                            }
                        } else {
                            $numbers_list = Extension::where('name', $itemNumber)->first();
                            if ($numbers_list && $numbers_list->expirationdate != NULL) {

                                if ($numbers_list->company_id != $user->company->id) {
                                    DB::rollback();
                                    return $this->output(false, 'Mismatch in Extension values.', 400);
                                }
                                $value = "Renew";
                                $currentDate = Carbon::now();
                                $targetDate = Carbon::parse($numbers_list->expirationdate);
                                $daysDifference = $currentDate->diffInDays($targetDate, false);
                                if ($daysDifference <= 3 && $daysDifference >= 1) {
                                    $newDate = date('Y-m-d H:i:s', strtotime('+' . (30 + $daysDifference) . ' days'));
                                    $startDate = date('Y-m-d H:i:s', strtotime('+' . $daysDifference . ' days'));
                                } elseif ($daysDifference > 3) {
                                    $newDate = date('Y-m-d H:i:s', strtotime('+' . (30 + $daysDifference) . ' days'));
                                    $startDate = date('Y-m-d H:i:s', strtotime('+' . $daysDifference . ' days'));
                                } else {
                                    $newDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                                    $startDate = date('Y-m-d H:i:s');
                                    // In Expired case we need to Update web or softphone template to webrtc_template_url or softphone_template_url 
                                    $webrtc_template_url = config('app.webrtc_template_url');
                                    $softphone_template_url = config('app.softphone_template_url');
                                    if ($numbers_list->sip_temp == 'WEBRTC') {
                                        $addExtensionFile = $webrtc_template_url;
                                    } else {
                                        $addExtensionFile = $softphone_template_url;
                                    }
                                    $ConfTemplate = ConfTemplate::select()->where('template_id', $numbers_list->sip_temp)->first();
                                    $this->addExtensionInConfFile($numbers_list->name, $addExtensionFile, $numbers_list->secret, $user->company->account_code, $ConfTemplate->template_contents);
                                    /* $server_flag = config('app.server_flag');
                                    if ($server_flag == 1) {
                                        $shell_script = config('app.shell_script');
                                        $result = shell_exec('sudo ' . $shell_script);
                                        Log::error('Extension Update File Transfer Log : ' . $result);
                                        
                                        $this->sipReload();
                                    } */
                                    //// End Template transfer code
                                }
                                $numbers_list->update([
                                    'company_id'  => $numbers_list->company_id,
                                    'startingdate' => $startDate,
                                    'expirationdate' => $newDate,
                                    'host' => 'dynamic',
                                    'sip_temp' => $numbers_list->sip_temp,
                                    'status' => 1,
                                ]);
                            } else {

                                // In Creating or Purchase case we need to Write web or softphone template to webrtc_template_url or softphone_template_url 
                                $webrtc_template_url = config('app.webrtc_template_url');
                                $addExtensionFile = $webrtc_template_url;
                                $ConfTemplate = ConfTemplate::select()->where('template_id', 'WEBRTC')->first();
                                $this->addExtensionInConfFile($numbers_list->name, $addExtensionFile, $numbers_list->secret, $user->company->account_code, $ConfTemplate->template_contents);
                                /* $server_flag = config('app.server_flag');
                                if ($server_flag == 1) {
                                    $shell_script = config('app.shell_script');
                                    $result = shell_exec('sudo ' . $shell_script);
                                    Log::error('Extension Update File Transfer Log : ' . $result);
                                    
                                    $this->sipReload();
                                } */
                                //// End Template transfer code
                                $value = "Purchase";
                                $numbers_list->update([
                                    'company_id' => $user->company->id,
                                    'startingdate' => date('Y-m-d H:i:s'),
                                    'expirationdate' => date('Y-m-d H:i:s', strtotime('+29 days')),
                                    'host' => 'dynamic',
                                    'sip_temp' => 'WEBRTC',
                                    'status' => 1,
                                ]);
                            }
                        }

                        Cart::where('item_number', $itemNumber)->delete();
                    }

                    $server_flag = config('app.server_flag');
                    if ($server_flag == 1) {
                        $shell_script = config('app.shell_script');
                        $result = shell_exec('sudo ' . $shell_script);
                        Log::error('Extension Update File Transfer Log : ' . $result);
                        
                        $this->sipReload();
                    }
                    $invoiceItem = InvoiceItems::where('item_number', $itemNumber)->where('invoice_id', $request->invoice_id)->first();
                    if ($invoiceItem) {
                        $invoiceItem->item_category = $value;
                        $invoiceItem->save();
                    } else {
                        DB::rollback();
                        return $this->output(false, 'Invoice item not found.', 400);
                    }
                    $invoice_update = Invoice::select('*')->where('id', $payment->invoice_id)->first();
                    if (!$invoice_update) {
                        DB::rollback();
                        return $this->output(false, 'Invoice not found.', 400);
                    } else {
                        $invoice_update->payment_status = "Paid";
                        $invoice_data = $invoice_update->save();
                        if ($invoice_data) {
                            if ($user->company->parent_id > 1) {
                                $this->ResellerCommissionCalculate($user, $invoice_items, $payment->invoice_id, $payment->payment_price);
                            }

                            // $mailsend = $this->pdfmailSend($user, $itemNumbers, $NowPaymentData['price_amount'], $payment->invoice_id, $invoice_update->invoice_number, $itemTypes);
                            // if ($mailsend) {
                            //     DB::commit();
                            // } else {
                            //     DB::rollBack();
                            // }
                        }
                    }
                    DB::commit();
                    $response['crypto_payment_status'] = $NowPaymentData['payment_status'];
                    $response['payment'] = $payment->toArray();


                    /**
                     *  Notification code
                     */
                    $subject = 'Crypto Payment';
                    $message = 'A new payment has been done by company: ' . $user->company->company_name . ' / ' . $user->company->email;
                    $type = 'info';
                    $notifyUserType = ['super-admin', 'support', 'noc'];
                    $notifyUser = array();
                    if ($user->role_id == 6) {
                        $message = 'A new payment for Add to Wallet has been done by user: ' . $user->name . ' / ' . $user->email;
                        $notifyUserType[] = 'admin';
                        $CompanyUser = User::where('company_id', $user->company_id)
                            ->where('role_id', 4)->first();
                        if ($CompanyUser->company->parent_id > 1) {
                            $notifyUserType[] = 'reseller';
                            $notifyUser['reseller'] = $CompanyUser->company->parent_id;
                        }
                        $notifyUser['admin'] = $CompanyUser->id;
                    }

                    if ($user->role_id == 4 && $user->company->parent_id > 1) {
                        $notifyUserType[] = 'reseller';
                        $notifyUser['reseller'] = $user->company->parent_id;
                    }

                    $res = $this->addNotification($user, $subject, $message, $type, $notifyUserType, $notifyUser);
                    if (!$res) {
                        Log::error('Notification not created when user role: ' . $user->role_id . ' in CheckPaymentStatus method.');
                    }
                    /**
                     * End of Notification code
                     */

                    return $this->output(true, 'Payment successfully.', $response, 200);
                }
            } else {
                $pstatus['crypto_payment_status'] = $NowPaymentData['payment_status'];
                return $this->output(true, 'Payment Status ' . $pstatus['crypto_payment_status'], $pstatus, 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Payment failed: ' . $e->getMessage());
            return response()->json(['error' => 'Payment failed.'], 500);
        }
    }


    public function nowPaymentsAddToWallet(Request $request)
    {
        $user = \Auth::user();
        $getcountry = Country::select('*')->where('id', $user->company->country_id)->first();
        $getstate = State::select('state_name')->where('id', $user->company->state_id)->first();
        $validator = Validator::make($request->all(), [
            "amount" => 'required',

        ]);
        try {
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            $invoicetable_id = DB::table('invoices')->max('id');
            if (!$invoicetable_id) {
                $invoice_id = '#INV/' . date("Y") . '/W0001';
            } else {
                $invoice_id = "#INV/" . date('Y') . "/W00" . ($invoicetable_id + 1);
            }
            $updatedInvoiceNumber = str_replace('#', '', $invoice_id);
            $price_currency = 'usd';
            $price_amount = $request->amount;
            $orderId = $updatedInvoiceNumber . 'UID' . $user->id;
            $pay_currency = 'usdttrc20';
            $paymentAPIW = $this->nowPaymentsService->createPayment($price_currency, $price_amount, $orderId, $pay_currency, $user->company->email);
            if (is_null($paymentAPIW)) {
                DB::rollback();
                return response()->json(['error' => 'Payment creation failed.'], 500);
            } else {
                $paymentId = $paymentAPIW['payment_id'];
                $paymentUrl = $paymentAPIW['pay_address'];

                $createinvoice = Invoice::create([
                    'company_id'              => $user->company->id,
                    'country_id'              => $user->company->country_id,
                    'state_id'                => $user->company->state_id,
                    'invoice_id'              => $invoice_id,
                    'invoice_currency'        => 'USD',
                    'invoice_subtotal_amount' => $price_amount,
                    'invoice_amount'          => $price_amount,
                    'payment_status'          => 'Unpaid',
                ]);
                $payment_data = Payments::create([
                    'company_id' => $user->company_id,
                    'invoice_id'  => $createinvoice->id,
                    'ip_address' => $request->ip(),
                    'invoice_number'  => $createinvoice->invoice_id,
                    'order_id'        => $createinvoice->invoice_id . '-UID-' . $user->id,
                    'item_numbers'    => 0,
                    'payment_type'    => 'Crypto Payment',
                    'payment_by'      => 'Company',
                    'payment_currency' => $request->currency ?? 'USD',
                    'payment_price' =>   $price_amount,
                    'transaction_id'  => $paymentId,
                    'stripe_charge_id' => '',
                    'status' => 0,
                ]);
                $qrCode = new QrCode($paymentUrl);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $qrCodePath = public_path('qr_codes/' . $paymentId . '.png');
                $result->saveToFile($qrCodePath);
                return response()->json([
                    'payment' =>  $paymentAPIW,
                    'qr_code_url' => asset('qr_codes/' . $paymentId . '.png'),
                    'invoice_id' => $createinvoice->id,
                    'invoice_number' => $createinvoice->invoice_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Wallet Payment creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Wallet Payment creation failed.'], 500);
        }
    }

    public function nowPaymentsWalletcheckPaymentsStatus(Request $request, $paymentId)
    {
        $user = \Auth::user();
        $NowPaymentData = $this->nowPaymentsService->getPaymentStatus($paymentId);
        // $paid_amount = 0;
        try {
            if ($NowPaymentData && $NowPaymentData['payment_status'] == "partially_paid" || $NowPaymentData['payment_status'] == "finished") {
                // if ($NowPaymentData && $NowPaymentData['payment_status'] == "waiting") {
                // if (isset($NowPaymentData['actually_paid']) && $NowPaymentData['actually_paid'] !== $NowPaymentData['pay_amount']) {
                //     $paid_amount = $NowPaymentData['actually_paid'];
                // }
                $paid_amount = $NowPaymentData['actually_paid'];
                $nowPayment_charge_id = Str::random(30);
                DB::beginTransaction();

                $payment = Payments::where('transaction_id', '=', $paymentId)->first();
                if (is_null($payment)) {
                    DB::rollback();
                    return $this->output(false, 'Something Went Wrong. please try again', 400);
                } else {
                    // Payments Table Update
                    // $payment->payment_price = $NowPaymentData['pay_amount'];
                    $payment->status = 1;
                    $payment->save();
                    $companydata = Company::where('id', '=', $user->company_id)->first();
                    if (is_null($companydata)) {
                        DB::rollback();
                        return $this->output(false, 'Company not found.', 400);
                    } else {
                        //Recharge History Update::
                        $balance_total_data = $companydata->balance + $paid_amount + $paid_amount * 0.05;
                        $added_balance_data = $paid_amount + $paid_amount * 0.05;
                        $added_balance = number_format($added_balance_data, 2, '.', '');
                        $balance_total = number_format($balance_total_data, 2, '.', '');
                        $rechargeHistory_data = RechargeHistory::create([

                            'company_id' => $companydata->id,
                            'user_id' => $user->id,
                            'invoice_id' => $payment->invoice_id,
                            'invoice_number' => $payment->invoice_number,
                            'current_balance' => $companydata->balance,
                            'added_balance'   => $added_balance,
                            'total_balance'   => $balance_total,
                            'currency'        => 'USDT',
                            'payment_type'    => 'Crypto',
                            'recharged_by'    => 'Self'
                        ]);
                        if (!$rechargeHistory_data) {
                            DB::rollback();
                            return $this->output(false, 'Failed to Create Recharge History!!.', 400);
                        } else {

                            $companydata->balance = $balance_total;
                            $companydata->save();
                            DB::commit();
                        }
                    }
                    $invoice_update = Invoice::select('*')->where('id', $payment->invoice_id)->first();
                    if (!$invoice_update) {
                        DB::rollback();
                        return $this->output(false, 'Invoice not found.', 400);
                    } else {
                        // $invoice_update->payment_type =  'Cryto Payment';
                        $invoice_update->payment_status = "Paid";
                        $invoice_update->save();
                        DB::commit();
                        $total_aamount = $NowPaymentData['price_amount'] + $paid_amount * 0.05 . ' Added to Wallet';
                        $item_number = $NowPaymentData['price_amount'];
                        $item_numbers[] = $item_number;
                        $itemTpyes[] = 'Wallet Payment';
                        // $mailsend = $this->pdfmailSend($user, $item_numbers, $total_aamount, $invoice_update->id, $invoice_update->invoice_id, $itemTpyes);
                        // if ($mailsend) {
                        //     DB::commit();
                        // } else {
                        //     DB::rollBack();
                        // }
                    }
                    DB::commit();
                    $response['payment'] = $payment->toArray();
                    $response['crypto_payment_status'] = $NowPaymentData['payment_status'];

                    /**
                     *  Notification code
                     */
                    $subject = 'Crypto Payment';
                    $message = 'A new payment for Add to Wallet has been done by company: ' . $user->company->company_name . ' / ' . $user->company->email;
                    $type = 'info';
                    $notifyUserType = ['super-admin', 'support', 'noc'];
                    $notifyUser = array();
                    if ($user->role_id == 6) {
                        $message = 'A new payment for Add to Wallet has been done by user: ' . $user->name . ' / ' . $user->email;
                        $notifyUserType[] = 'admin';
                        $CompanyUser = User::where('company_id', $user->company_id)
                            ->where('role_id', 4)->first();
                        if ($CompanyUser->company->parent_id > 1) {
                            $notifyUserType[] = 'reseller';
                            $notifyUser['reseller'] = $CompanyUser->company->parent_id;
                        }
                        $notifyUser['admin'] = $CompanyUser->id;
                    }

                    if ($user->role_id == 4 && $user->company->parent_id > 1) {
                        $notifyUserType[] = 'reseller';
                        $notifyUser['reseller'] = $user->company->parent_id;
                    }

                    $res = $this->addNotification($user, $subject, $message, $type, $notifyUserType, $notifyUser);
                    if (!$res) {
                        Log::error('Notification not created when user role: ' . $user->role_id . ' in nowPaymentsWalletcheckPaymentsStatus method.');
                    }
                    /**
                     * End of Notification code
                     */
                    return $this->output(true, 'Amount Credit in Wallet.', $response, 200);
                }
            } else {

                $pstatus['crypto_payment_status'] = $NowPaymentData['payment_status'];
                return $this->output(true, 'Payment Status ' .  $NowPaymentData['payment_status'], $pstatus, 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Payment failed: ' . $e->getMessage());
            return response()->json(['error' => 'Payment failed.'], 500);
        }
    }
    public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number, $itemTpyes)
    {
        $email = $user->company->email;
        $data['title'] = 'Invoice From Callanalog';
        $data['item_numbers'] = $item_numbers;
        $data['item_types'] = $itemTpyes;
        $data['price'] = $price_mail;
        $data['invoice_number'] = $invoice_number;

        if ($invoice_id) {
            try {
                Mail::send('invoice', ['data' => $data], function ($message) use ($data, $email) {
                    $message->to($email)->subject($data['title']);
                });

                $invoice_update_email = Invoice::find($invoice_id);
                if ($invoice_update_email) {
                    $invoice_update_email->email_status = 1;
                    $invoice_update_email->save();
                }
                return $this->output(true, 'Email sent successfully!');
            } catch (\Exception $e) {
                \Log::error('Error sending email: ' . $e->getMessage());
                return $this->output(false, 'Error occurred while sending the email.');
            }
        } else {
            return $this->output(false, 'Error occurred in Invoice creation. The PDF file does not exist or the path is incorrect.');
        }
    }

    public function refundPayment(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $paymentId = $request->input('payment_id');
        $amount = $request->input('amount');

        $refundResponse = $this->nowPaymentsService->refundPayment($paymentId, $amount);

        if (!$refundResponse['status']) {
            return response()->json(['error' => $refundResponse['message']], 400);
        }

        return response()->json([
            'message' => 'Refund successful',
            'refund' => $refundResponse,
        ]);
    }



    // Reseller Payment Section Start ::::
    public function ResellernowPaymentsaddToWallet(Request $request)
    {
        $user = \Auth::user();
        $getcountry = Country::select('*')->where('id', $user->country_id)->first();
        $getstate = State::select('state_name')->where('id', $user->state_id)->first();
        $validator = Validator::make($request->all(), [
            "amount" => 'required',

        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }


        try {
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            $price_currency = 'usd';
            $price_amount = $request->amount;
            $orderId = rand(0, 99999);
            $pay_currency = 'usdttrc20';
            $paymentAPIW = $this->nowPaymentsService->createPayment($price_currency, $price_amount, $orderId, $pay_currency, $user->email);
            if (is_null($paymentAPIW)) {
                DB::rollback();
                return response()->json(['error' => 'Payment creation failed.'], 500);
            } else {
                $paymentId = $paymentAPIW['payment_id'];
                $paymentUrl = $paymentAPIW['pay_address'];

                $payment_data = ResellerRechargeHistories::create([
                    'user_id' => $user->id,
                    // 'ip_address' => $request->ip(),
                    'old_balance' => $user->reseller_wallets->balance,
                    // 'added_balance'   => $request->amount,
                    // 'total_balance'   => $user->reseller_wallets->balance + $request->amount,
                    'currency'        => 'USD',
                    'payment_type'    => 'Crypto',
                    'transaction_id'  => $paymentId,
                    'stripe_charge_id' => '',
                    'recharged_by'    => 'Self',
                    'status' => 0,
                ]);
                $qrCode = new QrCode($paymentUrl);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $qrCodePath = public_path('qr_codes/' . $paymentId . '.png');
                $result->saveToFile($qrCodePath);
                return response()->json([
                    'payment' =>  $paymentAPIW,
                    'qr_code_url' => asset('qr_codes/' . $paymentId . '.png'),
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Wallet Payment creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Wallet Payment creation failed.'], 500);
        }
    }

    public function ReselletnowPaymentsWalletcheckPaymentsStatus(Request $request, $paymentId)
    {
        $user = \Auth::user();
        $NowPaymentData = $this->nowPaymentsService->getPaymentStatus($paymentId);
        // $paid_amount = 0;
        try {
            if ($NowPaymentData && $NowPaymentData['payment_status'] == "partially_paid" || $NowPaymentData['payment_status'] == "finished") {
                // if ($NowPaymentData && $NowPaymentData['payment_status'] == "waiting") {
                // if (isset($NowPaymentData['actually_paid']) && $NowPaymentData['actually_paid'] !== $NowPaymentData['pay_amount']) {
                //     $paid_amount = $NowPaymentData['actually_paid'];
                // }
                $paid_amount = $NowPaymentData['actually_paid'];
                $nowPayment_charge_id = Str::random(30);
                DB::beginTransaction();

                $payment = ResellerRechargeHistories::where('transaction_id', '=', $paymentId)->first();
                if (is_null($payment)) {
                    DB::rollback();
                    return $this->output(false, 'Something Went Wrong. please try again', 400);
                } else {
                    // Payments Table Update
                    // $payment->payment_price = $NowPaymentData['pay_amount'];
                    $total_balance =  $payment->old_balance + $paid_amount + $paid_amount * 0.05;
                    $added_balance_data = $paid_amount + $paid_amount * 0.05;
                    $added_balance = number_format($added_balance_data, 2, '.', '');
                    $balance_total = number_format($total_balance, 2, '.', '');

                    $payment->added_balance = $added_balance;
                    $payment->total_balance  =  $balance_total;
                    $payment->status = 1;
                    $payment->save();

                    $response['payment'] = $payment->toArray();
                    $response['crypto_payment_status'] = $NowPaymentData['payment_status'];
                    DB::commit();
                    /**
                     *  Notification code
                     */
                    $subject = 'Crypto Payment';
                    $message = 'A payment for Add to Wallet has been done by reseller: ' . $user->name . '/' . $user->email;
                    $type = 'info';
                    $notifyUserType = ['super-admin', 'support', 'noc'];
                    $notifyUser = array();
                    $res = $this->addNotification($user, $subject, $message, $type, $notifyUserType, $notifyUser);
                    if (!$res) {
                        Log::error('Notification not created when user role: ' . $user->role_id . ' inReselletnowPaymentsWalletcheckPaymentsStatus method.');
                    }
                    /**
                     * End of Notification code
                     */
                    return $this->output(true, 'Amount Credit in Wallet.', $response, 200);
                }
            } else {

                $pstatus['crypto_payment_status'] = $NowPaymentData['payment_status'];
                return $this->output(true, 'Payment Status ' .  $NowPaymentData['payment_status'], $pstatus, 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Payment failed: ' . $e->getMessage());
            return response()->json(['error' => 'Payment failed.'], 500);
        }
    }

    // Reseller Payment Section END ::::
}
