<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\Country;
use App\Models\Extension;
use App\Models\Invoice;
use App\Models\Payments;
use App\Models\State;
use App\Models\Tfn;
use Exception;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Stripe\Stripe;
use Illuminate\Support\Str;
// use Stripe\Exception\CardException;
// use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{
    public function PayNow(Request $request)
    {
        $user = \Auth::user();
        $invoice_balance = Invoice::select('invoice_amount')->where('id', $request->invoice_id)->where('payment_status', '=', 'Unpaid')->first();
        $getcountry = Country::select('*')->where('id', $user->company->country_id)->first();
        $getstate = State::select('state_name')->where('id', $user->company->state_id)->first();

        if ($invoice_balance->invoice_amount === $request->payment_price) {
            $item_numbers_array =  $request->item_numbers;
            $item_type_array = $request->item_type;

            foreach ($item_numbers_array as $index => $itemNumber) {
                $itemType = trim($item_type_array[$index]);
                $cart = Cart::select('*')->where('item_number', '=', $itemNumber)->first();
                if (!$cart) {
                    return $this->output(false, 'Item Number ' . $itemNumber . ' is Not Added to Cart!.', 400);
                }
                if ($itemType === "TFN") {
                    $tfn_list_type = Tfn::select('*')->where('tfn_number', $itemNumber)->first();
                    if ($tfn_list_type && $tfn_list_type->reserved == 1 && $tfn_list_type->company_id != $user->company_id) {
                        return $this->output(false, 'Tfn Number ' . $itemNumber . ' is already Purchased.', 400);
                    }
                } else {
                    $extnumber = Extension::select('*')->where('name', '=', $itemNumber)->first();
                    if ($extnumber && $extnumber->status == 1) {
                        return $this->output(false, 'Extension Number ' . $itemNumber . ' is already Purchased.', 400);
                    }
                }
            }
            // return "Good";
            $validator = Validator::make($request->all(), [
                'payment_price' => 'required|numeric|min:1',
                'currency' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            $stripe = new \Stripe\StripeClient(config('stripe.stripe.secret_test'));
            try {
                // Create a customer with a payment source
                $token = 'tok_visa';
                $itmNumber = implode("-", $request->item_numbers);
                $itemTpyes = implode("-", $request->item_type);
                $customer = $stripe->customers->create([
                    'name' => $user->company->company_name,
                    'email' => $user->company->email,
                    'source' => $token
                ]);

                $paymentIntent = $stripe->paymentIntents->create([
                    'amount' => $request->payment_price * 100,
                    'currency' => $request->currency ?? 'USD',
                    'customer' => $customer->id,
                    'description' => $itmNumber,
                    'metadata' => [
                        'item_id' => $itmNumber,
                        'billing_details' => json_encode([
                            'city' => $user->company->city ?? NULL,
                            'country' => $getcountry->country_name ?? NULL,
                            'state' => $getstate->state_name ?? NULL,
                            'postal_code' => $user->company->zip ?? NULL,
                            'email' => $user->company->email,
                            'name' => $user->company->company_name,
                            'phone' => $user->company->mobile,
                        ])
                    ],
                ]);

                $charge = $stripe->charges->create([
                    'customer' => $customer->id,
                    'amount' => $request->payment_price * 100,
                    'currency' => $request->currency ?? 'USD',
                    'description' => $itmNumber,
                    'ip' => $request->ip(),
                    'metadata' => [
                        'item_id' => $itmNumber,
                    ]
                ]);

                $chargeJson = $charge->jsonSerialize();
                if ($chargeJson['amount_refunded'] == 0 && empty($chargeJson['failure_code']) && $chargeJson['paid'] == 1 && $chargeJson['captured'] == 1) {
                    DB::beginTransaction();
                    $status = 1;
                    $price_mail = $charge->amount / 100;

                    $payment = Payments::create([
                        'company_id' => $user->company_id,
                        'invoice_id'  => $request->invoice_id,
                        'ip_address' => $request->ip(),
                        'invoice_number'  => $request->invoice_number,
                        'order_id'        => $request->invoice_number . '-UID-' . $user->id,
                        'item_numbers'    => $itmNumber,
                        'payment_type'    => 'Stripe Card Payment',
                        'payment_currency' => $charge->currency,
                        'payment_price' => $charge->amount / 100,
                        'stripe_charge_id' => $chargeJson['id'],
                        'transaction_id'  => $chargeJson['balance_transaction'],
                        'status' => $status,
                    ]);

                    foreach ($item_numbers_array as $index => $itemNumber) {
                        $itemType = trim($item_type_array[$index]);
                        if ($itemType === "TFN") {
                            $numbers_list = Tfn::where('tfn_number', $itemNumber)->first();
                            if ($numbers_list) {
                                $numbers_list->assign_by = $user->id;
                                $numbers_list->plan_id = $user->company->plan_id;
                                $numbers_list->startingdate = date('Y-m-d H:i:s');
                                $numbers_list->expirationdate = date('Y-m-d H:i:s', strtotime('+30 days'));
                                $numbers_list->save();
                            } else {
                                DB::rollback();
                                return $this->output(false, 'Tfn Number ' . $itemNumber . ' not found.', 400);
                            }
                        } else {
                            $numbers_list = Extension::where('name', $itemNumber)->first();
                            if ($numbers_list) {
                                $numbers_list->status = 1;
                                $numbers_list->save();
                            } else {
                                DB::rollback();
                                return $this->output(false, 'Extension Number ' . $itemNumber . ' not found.', 400);
                            }
                        }
                        Cart::where('item_number', $itemNumber)->delete();
                        DB::commit();
                    }

                    $invoice_update = Invoice::select('*')->where('id', $request->invoice_id)->first();
                    if (!$invoice_update) {
                        DB::rollback();
                        return $this->output(false, 'Invoice not found.', 400);
                    } else {
                        $invoice_update->payment_type =  'Stripe Card Payment';
                        $invoice_update->payment_status = "Paid";
                        $mailsend = $this->pdfmailSend($user, $itmNumber, $price_mail, $request->invoice_id, $invoice_update->invoice_id, $itemTpyes);
                        if ($mailsend) {
                            DB::commit();
                        } else {
                            DB::rollBack();
                        }
                        $invoice_update->save();
                    }

                    DB::commit();
                    $response['payment'] = $payment->toArray();
                    return $this->output(true, 'Payment successfully.', $response, 200);
                } else {
                    DB::rollback();
                    return $this->output(false, 'Payment failed. Please Try Again.', null, 400);
                }
            } catch (\Stripe\Exception\CardException $e) {
                return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
            } catch (\Stripe\Exception\AuthenticationException $e) {
                return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
            } catch (\Stripe\Exception\ApiConnectionException $e) {
                return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
            }
        } else {
            return $this->output(false, 'Oops! Something Went Wrong. Mismatch values', 409);
        }
    }




    // public function pdfmailSend($user, $invoice_id, $invoiceFilePath, $fileName)
    // public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number)
    // public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number, $itemTpyes)
    // public function pdfmailSend(Request $request)
    public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number, $itemTpyes)
    {

        $user = \Auth::user();
        $email = $user->email;
        // $item_number = $request->item_numbers;
        // $itemTpye = $request->item_type;
        // $price_mail = $request->payment_price;
        // $invoice_id =  $request->invoice_id;
        // $invoice_number =  $request->invoice_number;
        // $item_numbers = explode('-', $item_number);
        // $item_types = explode('-', $itemTpye);

        $item_numbers = explode('-', $item_numbers);
        $item_types = explode('-', $itemTpyes);
        if (count($item_numbers) != count($item_types)) {
            return $this->output(false, 'Mismatched item numbers and item types count.');
        }

        $data['title'] = 'Invoice From Callanalog';
        $data['items'] = array_map(function ($number, $type) {
            return [
                'item_number' => trim($number),
                'item_type' => trim($type),
            ];
        }, $item_numbers, $item_types);
        $data['price'] = $price_mail;
        $data['invoice_number'] = $invoice_number;
        //   return $data;
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



    public function generatePDF($user, $invoice_id)
    {
        $data = [
            'title' => 'Your Payment Invoice',
            'date'  => date('m/d/Y'),
            'users' => $user
        ];
        $invoiceCreate = DB::table('payments')
            ->select('*')
            ->leftJoin('companies', 'payments.company_id', '=', 'companies.id')
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('payments.invoice_id', '=', $invoice_id)
            ->where('payments.status', '=', 1)
            ->where('invoices.payment_status', '=', 'Paid')
            ->get();

        $pdf = PDF::loadView('invoice', compact('data'));
        $fileName = "invoice_" . date('YmdHis') . ".pdf";
        // $pdfFilePath = storage_path('app/invoice_pdf/' . $fileName);
        // $pdfFilePathStorage = storage_path('app/invoice_pdf/' . $fileName);
        $pdfFilePathPublic = public_path('invoicePDF/' . $fileName);


        try {
            $pdfContent = $pdf->output();
            file_put_contents($pdfFilePathPublic, $pdfContent);
            $invoice_update = Invoice::find($invoice_id);
            if ($invoice_update) {
                $invoice_update->invoice_file = $fileName;
                $invoice_update->save();
                // $this->pdfmailSend($user, $invoice_id, $pdfFilePathPublic, $invoice_update->invoice_file);
            } else {
                return $this->output(false, 'Invoice not found.');
            }
            return $this->output(true, 'PDF generated and saved successfully.');
        } catch (\Exception $e) {
            \Log::error('Error saving PDF: ' . $e->getMessage());
            return $this->output(false, 'Error generating or saving the PDF file.');
        }
    }




    public function RefundStripePayment(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'transaction_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {

            $stripe = new \Stripe\StripeClient(config('stripe.stripe.secret_test'));
            $payments = Payments::select('*')->where('transaction_id', '=', $request->transaction_id)->where('company_id', '=', $user->company_id)->first();
            // return $payments;

            if ($payments) {
                // Get Data Fro Stripe
                $checkstripeData =  $stripe->charges->retrieve($payments->stripe_charge_id, []);
                $paid_amount_ss = $checkstripeData->amount / 100;
                if ($checkstripeData->amount_refunded == 0 && $paid_amount_ss > $request->amount) {
                    $refunds =  $stripe->refunds->create([
                        'charge' => $payments->stripe_charge_id,
                        'amount' => $request->amount,
                    ]);
                    if ($refunds) {
                        return $this->output(true, 'success', $refunds->toArray(), 200);
                    } else {
                        return $this->output(false, 'Something went wrong! Refund not Initiated', 200);
                    }
                } else {
                    return $this->output(false, 'Oops Something went wrong, Either amount refunded or amount mis matched!', 200);
                }
            } else {
                return $this->output(false, 'There is no payment using this transaction id', 400);
            }
        }
    }


    public function addToWallet(Request $request)
    {
        $user = \Auth::user();
        $getcountry = Country::select('*')->where('id', $user->company->country_id)->first();
        $getstate = State::select('state_name')->where('id', $user->company->state_id)->first();
        $validator = Validator::make($request->all(), [
            "amount" => 'required|string',

        ]);
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
        // $response['createinvoice'] = $createinvoice->toArray();

        $stripe = new \Stripe\StripeClient(config('stripe.stripe.secret_test'));
        try {
            // Create a customer with a payment source
            $token = 'tok_visa';


            $customer = $stripe->customers->create([
                'name' => $user->company->company_name,
                'email' => $user->company->email,
                'source' => $token
            ]);

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $request->amount * 100,
                'currency' => 'USD',
                'customer' => $customer->id,
                'description' => "Wallet Payments",
                'metadata' => [
                    'item_id' => "Wallet Payments",
                    'billing_details' => json_encode([
                        'city' => $user->company->city ?? NULL,
                        'country' => $getcountry->country_name ?? NULL,
                        'state' => $getstate->state_name ?? NULL,
                        'postal_code' => $user->company->zip ?? NULL,
                        'email' => $user->company->email,
                        'name' => $user->company->company_name,
                        'phone' => $user->company->mobile,
                    ])
                ],

            ]);

            // Create a charge for the customer
            $charge = $stripe->charges->create([
                'customer' => $customer->id,
                'amount' => $request->amount * 100,
                'currency' => 'USD',
                'description' => 'Wallet Payments',
                'ip' => $request->ip(),
                'metadata' => [
                    'item_id' => 'Wallet Payments',
                ]
            ]);


            // Create Invoice
            $invoiceStripe = $stripe->invoices->create([
                'customer' => $customer->id,
                'auto_advance' => false,
            ]);

            // Create Invoice Items
            $invoice_item = $stripe->invoiceItems->create([
                'customer' => $customer->id,
                'invoice' => $invoiceStripe->id,
                'amount' => $request->amount * 100,
                'currency' => 'USD',
                'description' => 'Wallet Payments',
            ]);

            $chargeJson = $charge->jsonSerialize();

            if ($chargeJson['amount_refunded'] == 0 && empty($chargeJson['failure_code']) && $chargeJson['paid'] == 1 && $chargeJson['captured'] == 1) {
                $status = 1;
                $price_mail = $charge->amount / 100;
                $item_numbers = $price_mail . 'Added to Wallet';
                $itemTpyes = $createinvoice->id;
                DB::beginTransaction();
                $payment = Payments::create([
                    'company_id' => $user->company_id,
                    'invoice_id'  => $createinvoice->id,
                    'ip_address' => $request->ip(),
                    'invoice_number'  => $createinvoice->invoice_id,
                    'order_id'        =>  $createinvoice->invoice_id . '-UID-' . $user->id,
                    'item_numbers'    => "Added to Wallet",
                    'payment_type'    => 'Added to Wallet',
                    'payment_currency' => $charge->currency,
                    'payment_price' => $charge->amount / 100,
                    'stripe_charge_id' => $chargeJson['id'],
                    'transaction_id'  => $chargeJson['balance_transaction'],
                    'status' => $status,
                ]);
                $user_payment = Company::where('id', $user->company_id)->first();
                if (!$user_payment) {
                    DB::rollback();
                    return $this->output(false, 'Company not found.');
                } else {

                    $user_payment->balance = $user_payment->balance + $request->amount;
                    $user_result = $user_payment->save();
                    DB::commit();
                }
                $invoice_update = Invoice::where('id', $createinvoice->id)->first();
                if (!$invoice_update) {
                    DB::rollback();
                    return $this->output(false, 'Invoice not found.');
                } else {
                    $invoice_update->payment_type =  'Add to Wallet Payment';
                    $invoice_update->payment_status = "Paid";
                    $this->pdfmailSend($user, $item_numbers, $price_mail, $createinvoice->id, $createinvoice->invoice_id, $itemTpyes);
                    $invoice_result = $invoice_update->save();
                    DB::commit();
                }
                $response = $payment->toArray();
                DB::commit();
                return $this->output(true, 'Payment Added To Wallet successfully.', $response, 200);
            } else {
                DB::rollback();
                return $this->output(false, 'Payment failed!!. Please Try Again.', null, 400);
            }
        } catch (\Stripe\Exception\CardException $e) {
            return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->output(false, 'Payment failed. ' . $e->getMessage(), null, 400);
        }
    }


    public function PaywithWallet(Request $request)
    {
        $user = \Auth::user();
        $invoice_balance = Invoice::select('invoice_amount')->where('id', $request->invoice_id)->where('payment_status', '=', 'Unpaid')->first();
        if ($invoice_balance->invoice_amount === $request->payment_price) {
            $balance_record = Company::select('balance')->where('id', $user->company_id)->first();
            if ($request->payment_price > 0 && $balance_record->balance > $request->payment_price) {
                $item_numbers_array =  $request->item_numbers;
                $item_type_array = $request->item_type;

                foreach ($item_numbers_array as $index => $itemNumber) {
                    $itemType = trim($item_type_array[$index]);
                    $cart = Cart::select('*')->where('item_number', '=', $itemNumber)->first();
                    if (!$cart) {
                        return $this->output(false, 'Item Number ' . $itemNumber . ' is Not Added to Cart!.', 400);
                    }
                    if ($itemType === "TFN") {
                        $tfn_list_type = Tfn::select('*')->where('tfn_number', $itemNumber)->first();
                        if ($tfn_list_type && $tfn_list_type->reserved == 1 && $tfn_list_type->company_id != $user->company_id) {
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
                $transaction_id = Str::random(10);
                $stripe_charge_id = Str::random(30);
                DB::beginTransaction();
                $itmNumberM = implode("-", $request->item_numbers);
                $itemTpyes  = implode("-", $request->item_type);
                $payment = Payments::create([
                    'company_id' => $user->company_id,
                    'invoice_id'  => $request->invoice_id,
                    'ip_address' => $request->ip(),
                    'invoice_number'  => $request->invoice_number,
                    'order_id'        =>  $request->invoice_number . '-UID-' . $user->id,
                    'item_numbers'    =>  $itmNumberM,
                    'payment_type'    => 'Wallet Payment',
                    'payment_currency' => $request->currency ?? 'USD',
                    'payment_price' => $request->payment_price,
                    'transaction_id'  => $transaction_id,
                    'stripe_charge_id' => $stripe_charge_id,
                    'status' => 1,
                ]);
                $balance_record_main = Company::where('id', $user->company_id)->first();
                if ($balance_record_main) {
                    $balance_record_main->balance = $balance_record_main->balance - $request->payment_price;
                    $balance_record_main->save();
                    DB::commit();
                } else {
                    DB::rollback();
                    return $this->output(false, 'Company Not Found.', null, 400);
                }
                foreach ($item_numbers_array as $index => $itemNumber) {
                    $itemType = trim($item_type_array[$index]);
                    if ($itemType === "TFN") {
                        $numbers_list = Tfn::where('tfn_number', $itemNumber)->first();
                        if ($numbers_list) {
                            $numbers_list->assign_by = $user->id;
                            $numbers_list->plan_id = $user->company->plan_id;
                            $numbers_list->startingdate = date('Y-m-d H:i:s');
                            $numbers_list->expirationdate = date('Y-m-d H:i:s', strtotime('+30 days'));
                            $numbers_list->save();
                        } else {
                            DB::rollback();
                            return $this->output(false, 'Tfn Number ' . $itemNumber . ' not found.', 400);
                        }
                    } else {
                        $numbers_list = Extension::where('name', $itemNumber)->first();
                        if ($numbers_list) {
                            $numbers_list->status = 1;
                            $numbers_list->save();
                        } else {
                            DB::rollback();
                            return $this->output(false, 'Extension Number ' . $itemNumber . ' not found.', 400);
                        }
                    }
                    Cart::where('item_number', $itemNumber)->delete();
                    DB::commit();
                }

                $invoice_update = Invoice::where('id', $request->invoice_id)->first();
                if (!$invoice_update) {
                    DB::rollback();
                    return $this->output(false, 'Invoice not found.');
                } else {
                    $itmNumber = implode("-", $request->item_numbers);
                    $price_mail = $request->payment_price;
                    $invoice_update->payment_type =  'Wallet Payment';
                    $invoice_update->payment_status = "Paid";
                    $this->pdfmailSend($user, $itmNumber, $price_mail, $request->invoice_id, $invoice_update->invoice_id, $itemTpyes);
                    $invoice_result = $invoice_update->save();
                }

                DB::commit();
                $response['payment'] = $payment->toArray();
                return $this->output(true, 'Payment successfully.', $response, 200);
            } else {
                DB::rollback();
                return $this->output(false, 'You have In sufficient balance in your Wallet. Please choose Pay Now Option.', null, 400);
            }
        } else {
            DB::rollback();
            return $this->output(false, 'Oops! Something Went Wrong. mismatch values', 409);
        }
    }
}