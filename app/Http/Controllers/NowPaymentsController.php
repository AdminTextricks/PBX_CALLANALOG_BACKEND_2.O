<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\Country;
use App\Models\Extension;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\Payments;
use App\Models\State;
use App\Models\Tfn;
use App\Services\NowPaymentsService;
use Validator;
use Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

class NowPaymentsController extends Controller
{
    protected $nowPaymentsService;

    public function __construct(NowPaymentsService $nowPaymentsService)
    {
        $this->nowPaymentsService = $nowPaymentsService;
    }

    public function createPayment(Request $request)
    {
        $user = \Auth::user();
        $invoice_balance = Invoice::select('invoice_amount')->where('id', $request->invoice_id)->where('payment_status', '=', 'Unpaid')->first();
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
                    if ($tfn_list_type && $tfn_list_type->company_id != 0) {
                        return $this->output(false, 'Tfn Number ' . $itemNumber . ' is already Purchased.', 400);
                    }
                } else {
                    $extnumber = Extension::select('*')->where('name', '=', $itemNumber)->first();
                    if ($extnumber && $extnumber->status == 1) {
                        return $this->output(false, 'Extension Number ' . $itemNumber . ' is already Purchased.', 400);
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
                $orderId = $request->invoice_number . '-UID-' . $user->id;
                $pay_currency = 'usddtrc20';
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
            } elseif ($NowPaymentData && $NowPaymentData['payment_status'] == "waiting") {
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
                            $numbers_list = Tfn::where('tfn_number', $itemNumber)->first();
                            if ($numbers_list) {
                                $numbers_list->company_id = $user->company->id;
                                $numbers_list->assign_by = $user->id;
                                $numbers_list->activated = '1';
                                $numbers_list->startingdate = date('Y-m-d H:i:s');
                                $numbers_list->expirationdate = date('Y-m-d H:i:s', strtotime('+29 days'));
                                $numbers_list->save();
                            } else {
                                DB::rollback();
                                return $this->output(false, 'Tfn Number ' . $itemNumber . ' not found.', 400);
                            }
                        } else {
                            $numbers_list = Extension::where('name', $itemNumber)->first();
                            if ($numbers_list) {
                                $numbers_list->startingdate = date('Y-m-d H:i:s');
                                $numbers_list->expirationdate = date('Y-m-d H:i:s', strtotime('+29 days'));
                                $numbers_list->host = 'dynamic';
                                $numbers_list->sip_temp = 'WEBRTC';
                                $numbers_list->status = 1;
                                $numbers_list->save();
                            } else {
                                DB::rollback();
                                return $this->output(false, 'Extension Number ' . $itemNumber . ' not found.', 400);
                            }
                        }

                        Cart::where('item_number', $itemNumber)->delete();
                    }

                    $invoice_update = Invoice::select('*')->where('id', $request->invoice_id)->first();
                    if (!$invoice_update) {
                        DB::rollback();
                        return $this->output(false, 'Invoice not found.', 400);
                    } else {
                        // $invoice_update->payment_type =  'Cryto Payment';
                        $invoice_update->payment_status = "Paid";
                        $invoice_update->save();

                        $mailsend = $this->pdfmailSend($user, $itemNumbers, $NowPaymentData['price_amount'], $request->invoice_id, $invoice_update->invoice_number, $itemTypes);
                        if ($mailsend) {
                            DB::commit();
                        } else {
                            DB::rollBack();
                        }
                    }
                    DB::commit();
                    $response['crypto_payment_status'] = $NowPaymentData['payment_status'];
                    $response['payment'] = $payment->toArray();
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
            $createinvoice = Invoice::create([
                'company_id'              => $user->company->id,
                'country_id'              => $user->company->country_id,
                'state_id'                => $user->company->state_id,
                'invoice_id'              => $invoice_id,
                'invoice_currency'        => 'USD',
                'invoice_subtotal_amount' => $request->amount,
                'invoice_amount'          => $request->amount,
                'payment_status'          => 'Unpaid',
            ]);


            $price_currency = 'usd';
            $price_amount = $request->amount;
            $orderId = $createinvoice->invoice_id . '-UID-' . $user->id;
            $pay_currency = 'usddtrc20';
            $payment = $this->nowPaymentsService->createPayment($price_currency, $price_amount, $orderId, $pay_currency, $user->company->email);
            $paymentId = $payment['payment_id'];
            $paymentUrl = $payment['pay_address'];
            $qrCode = new QrCode($paymentUrl);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrCodePath = public_path('qr_codes/' . $paymentId . '.png');
            $result->saveToFile($qrCodePath);
            return response()->json([
                'payment' =>  $payment,
                'qr_code_url' => asset('qr_codes/' . $paymentId . '.png'),
                'invoice_id' => $createinvoice->id,
                'invoice_number' => $createinvoice->invoice_number
            ]);
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

        if ($NowPaymentData && $NowPaymentData['payment_status'] == "partially_paid" || $NowPaymentData['payment_status'] == "finished") {
            // if ($NowPaymentData && $NowPaymentData['payment_status'] == "waiting") {
            if (isset($NowPaymentData['actually_paid']) && $NowPaymentData['actually_paid'] !== $NowPaymentData['pay_amount']) {
                $paid_amount = $NowPaymentData['pay_amount'];
            }
            $nowPayment_charge_id = Str::random(30);
            DB::beginTransaction();
            $payment = Payments::create([
                'company_id' => $user->company_id,
                'invoice_id'  => $request->invoice_id,
                'ip_address' => $request->ip(),
                'invoice_number'  => $request->invoice_number,
                'order_id'        => $request->invoice_number . '-UID-' . $user->id,
                'item_numbers'    => 0,
                'payment_type'    => 'Crypto Payment',
                'payment_currency' => $request->currency ?? 'USD',
                'payment_price' =>   $NowPaymentData['pay_amount'],
                'transaction_id'  => $paymentId,
                'stripe_charge_id' => $nowPayment_charge_id,
                'status' => 1,
            ]);
            $companydata = Company::where('id', '=', $user->company_id)->first();
            if (is_null($companydata)) {
                DB::rollback();
                return $this->output(false, 'Company not found.', 400);
            } else {
                $balance_total = $companydata->balance + $paid_amount * 0.05;
                $companydata->balance = $paid_amount + $balance_total;
                $companydata->save();
            }
            $invoice_update = Invoice::select('*')->where('id', $request->invoice_id)->first();
            if (!$invoice_update) {
                DB::rollback();
                return $this->output(false, 'Invoice not found.', 400);
            } else {
                // $invoice_update->payment_type =  'Cryto Payment';
                $invoice_update->payment_status = "Paid";
                $invoice_update->save();

                $total_aamount = $NowPaymentData['price_amount'] + $paid_amount * 0.05 . ' Added to Wallet';
                $item_number = $NowPaymentData['price_amount'];
                $item_numbers[] = $item_number;
                $itemTpyes[] = 'Wallet Payment';
                $mailsend = $this->pdfmailSend($user, $item_numbers, $total_aamount, $invoice_update->id, $invoice_update->invoice_id, $itemTpyes);
                if ($mailsend) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            }
            DB::commit();
            $response['payment'] = $payment->toArray();
            return $this->output(true, 'Amount Credit in Wallet.', $response, 200);
        } else {
            $pstatus = $NowPaymentData['payment_status'];
            return $this->output(true, 'Payment Status ' . $pstatus, $pstatus, 200);
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
}
