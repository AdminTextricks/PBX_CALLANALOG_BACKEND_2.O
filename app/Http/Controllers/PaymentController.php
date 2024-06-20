<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Invoice;
use App\Models\Payments;
use App\Models\Tfn;
use Exception;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Stripe\Stripe;
// use Stripe\Exception\CardException;
// use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{
    public function PayNow(Request $request)
    {
        $user = \Auth::user();
        $invoice_balance = Invoice::select('invoice_amount')->where('id', $request->invoice_id)->first();

        if ($invoice_balance->invoice_amount === $request->payment_price) {
            $item_numbers_array = explode('-', $request->item_numbers);
            foreach ($item_numbers_array as $item) {
                $tfn_list_type = Tfn::select('*')->where('tfn_number', $item)->first();
                if ($tfn_list_type && $tfn_list_type->reserved == 1 && $tfn_list_type->company_id != $user->company_id) {
                    return $this->output(false, 'Tfn Number ' . $item . ' is already Purchased.', 400);
                }
            }

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
                $customer = $stripe->customers->create([
                    'name' => $user->company->company_name,
                    'email' => $user->company->email,
                    'source' => $token
                ]);

                // Create a charge for the customer
                $charge = $stripe->charges->create([
                    'customer' => $customer->id,
                    'amount' => $request->payment_price * 100,
                    'currency' => $request->currency,
                    'description' => $request->item_numbers,
                    'ip' => $request->ip(),
                    'metadata' => [
                        'item_id' => $request->item_numbers,
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
                    'amount' => $request->payment_price * 100,
                    'currency' => $request->currency,
                    'description' => $request->item_numbers,
                ]);


                // // \Log::info('Invoice Created: ', $invoice->toArray());
                // // \Log::info('Finalized Invoice: ', $finalizedInvoice->toArray());

                // Create Refund
                // $ref =  $stripe->refunds->create([
                //     'charge' => 'ch_3PSvq2G71L2aH3X10CC335ff',
                //     'amount' => $request->amount,
                // ]);

                $chargeJson = $charge->jsonSerialize();
                if ($chargeJson['amount_refunded'] == 0 && empty($chargeJson['failure_code']) && $chargeJson['paid'] == 1 && $chargeJson['captured'] == 1) {

                    $status = 1;
                    $price_mail = $charge->amount / 100;
                    DB::beginTransaction();
                    $payment = Payments::create([
                        'company_id' => $user->company_id,
                        'invoice_id'  => $request->invoice_id,
                        'invoice_number'  => $request->invoice_number,
                        'order_id'        =>  $request->invoice_number . '-UID-' . $user->id,
                        'item_numbers'    => $request->item_numbers,
                        'payment_type'    => 'Stripe Card Payment',
                        'payment_currency' => $charge->currency,
                        'payment_price' => $charge->amount / 100,
                        'stripe_charge_id' => $chargeJson['id'],
                        'transaction_id'  => $chargeJson['balance_transaction'],
                        'status' => $status,
                    ]);
                    foreach ($item_numbers_array as $item) {
                        $numbers_list = Tfn::where('tfn_number', $item)->first();
                        if ($numbers_list) {
                            $numbers_list->assign_by = $user->id;
                            $numbers_list->plan_id = $user->plan_id;
                            // $numbers_list->startingdate = now();
                            // $numbers_list->expirationdate = now()->addMonth();
                            $numbers_list->startingdate = date('Y-m-d H:i:s');
                            $numbers_list->expirationdate = date('Y-m-d H:i:s', strtotime('+1 month'));
                            // $numbers_list->activated = 1;
                            // $numbers_list->status = 1;
                            $numbers_list->save();
                            Cart::where('item_number', $numbers_list->tfn_number)->delete();
                            DB::commit();
                        } else {
                            DB::rollback();
                            return $this->output(false, 'Tfn Number not found.');
                        }
                    }
                    $invoice_update = Invoice::select('*')->where('id', $request->invoice_id)->first();
                    if (!$invoice_update) {
                        DB::rollback();
                        return $this->output(false, 'Invoice not found.');
                    } else {
                        $invoice_update->payment_type =  'Stripe Card Payment';
                        $invoice_update->payment_status = "Paid";
                        // $this->generatePDF($user, $request->invoice_id);
                        // return $invoice_update;
                        $this->pdfmailSend($user, $request->item_numbers, $price_mail, $request->invoice_id, $invoice_update->invoice_id);
                        $invoice_result = $invoice_update->save();
                        DB::commit();
                    }

                    DB::commit();
                    $response['payment'] = $payment->toArray();
                    $finalizedInvoice = $invoiceStripe->jsonSerialize();
                    $finalizedInvoice = $stripe->invoices->finalizeInvoice($invoiceStripe->id);

                    // Finalize the invoice to create the invoice PDF and start the billing cycle
                    $paidInvoice = $stripe->invoices->pay($finalizedInvoice->id);

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
            DB::rollback();
            return $this->output(false, 'Oops! Something Went Wrong. mismatch values', 409);
        }
    }



    // public function pdfmailSend($user, $invoice_id, $invoiceFilePath, $fileName)
    // public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number)
    public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number)
    {
        $user = \Auth::user();
        $email = $user->email;
        $data['title']  = 'Invoice From  Callanalog';
        $data['item']   = $item_numbers;
        $data['price']  = $price_mail;
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
}
