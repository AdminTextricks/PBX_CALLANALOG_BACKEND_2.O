<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\Tfn;

class InvoiceController extends Controller
{
    public function createInvoice(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator($request->all(), [
            'invoice_id'              => 'string|max:255',
            'payment_type'            => 'string|max:255',
            'invoice_currency'        => 'string|max:255',
            'invoice_subtotal_amount' => 'string|max:255',
            'invoice_amount'          => 'string|max:255',
            'payment_status'          => 'string|max:255',
            'invoice_file'            => 'string|max:255',
            'email_status'            => 'numeric|max:10',

        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        try {
            DB::beginTransaction();

            $invoice_amount = 0;
            foreach ($request->item_price as $key => $priceCartInvoiceNumber) {
                $invoice_amount += $priceCartInvoiceNumber;
            }

            $invoicetable_id = DB::table('invoices')->max('id');
            if (!$invoicetable_id) {
                $invoice_id = '#INV/' . date("Y") . '/00001';
            } else {
                $invoice_id = "#INV/" . date('Y') . "/000" . ($invoicetable_id + 1);
            }
            // return $user->company->state_id;
            $createinvoice = Invoice::create([
                'company_id'              => $user->company->id,
                'country_id'              => $user->company->country_id,
                'state_id'                => $user->company->state_id,
                'invoice_id'              => $invoice_id,
                'invoice_currency'        => 'USD',
                'invoice_subtotal_amount' => $invoice_amount,
                'invoice_amount'          => $invoice_amount,
                'payment_status'          => 'Unpaid',
            ]);
            // $cart_type = Cart::select('item_type')->where('company_id', $user->company->id)->first();
            // return $cart_type;
            if ($request->item_type == 'TFN') {
                foreach ($request->item_number as $key => $tfncartinvoicenumber) {
                    $tfninvoicenumber = Tfn::where('tfn_number', '=', $tfncartinvoicenumber)->first();
                    if ($tfninvoicenumber) {
                        $cartinvoicenumber = Cart::where('item_number', '=', $tfncartinvoicenumber)->first();
                        if ($cartinvoicenumber) {
                            if (
                                isset($cartinvoicenumber->item_id) &&
                                isset($cartinvoicenumber->item_number) &&
                                isset($cartinvoicenumber->item_price) &&
                                $cartinvoicenumber->item_id == $request->item_id[$key] &&
                                $cartinvoicenumber->item_number == $request->item_number[$key] &&
                                $cartinvoicenumber->item_price == $request->item_price[$key]
                            ) {
                                // return $createinvoice->id;
                                $invoice_items = InvoiceItems::create([
                                    'company_id'  => $user->company->id,
                                    'invoice_id'  => $createinvoice->id,
                                    'item_id'     => $request->item_id[$key],
                                    'item_number' => $request->item_number[$key],
                                    'item_price'  => $request->item_price[$key],
                                ]);
                            } else {
                                DB::rollback();
                                return $this->output(false, 'Oops! Something Went Wrong. Tfn Number does not exist with us or values mismatch', 409);
                            }
                        } else {
                            DB::rollback();
                            return $this->output(false, 'Oops! Something Went Wrong. Cart not found', 409);
                        }
                    } else {
                        DB::rollback();
                        return $this->output(false, 'This Cart Number is not belong to us');
                    }
                }
                $response = $createinvoice->toArray();
                DB::commit();
                return $this->output(true, 'Invoice Created Successfully!!.', $response);
            } else {
                return $this->output(true, 'Please Check Item Type First', 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage());
        }
    }


    public function getInvoiceData(Request $request, $id)
    {
        $user = \Auth::user();
        $invoiceid = Invoice::find($id);
        if ($invoiceid) {
            $invoiceData = Invoice::with('invoice_items')
                ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                ->with('states:id,country_id,state_name')
                ->with('company')
                ->select('*')
                ->where('company_id', '=',  $user->company_id)
                ->where('id', $id)
                // ->where('payment_status', 'Paid')
                ->first();
            if ($invoiceData) {
                $downloadLink = 'invoice_pdf/' . $invoiceData->invoice_file;
                $invoiceData['download_link'] = $downloadLink;
                return $this->output(true, 'Success', $invoiceData->toArray(), 200);
            } else {
                return $this->output(false, 'Invoice data not found for the specified conditions.', [], 404);
            }
        } else {
            return $this->output(false, 'This invoice does not exist. Please try again!', [], 404);
        }
    }

    public function getAllInvoiceData(Request $request)
    {
        $user  = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params      = $request->params ?? "";
        $invoice_get_id = $request->id ?? NULL;

        if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with('invoice_items')
                    ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                    ->with('states:id,country_id,state_name')
                    ->with('company')
                    ->select('*')
                    ->where('payment_status', 'Paid')
                    ->where('id', $invoice_get_id);
            } else {
                if ($params !== "") {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company')
                        ->select('*')
                        ->where('payment_status', 'Paid')
                        ->orWhere('payment_type', "%$params%")
                        ->orWhere('invoice_id', "%$params%")
                        ->orWhere('invoice_amount', "%$params%")
                        ->orWhere('invoice_file', "%$params%")
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company')
                        ->select('*')
                        ->where('payment_status', 'Paid')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        } else {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with('invoice_items')
                    ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                    ->with('states:id,country_id,state_name')
                    ->with('company')
                    ->select('*')
                    ->where('company_id', $user->company_id)
                    ->where('payment_status', 'Paid')
                    ->where('id', $invoice_get_id);
            } else {
                if ($params != "") {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company')
                        ->select('*')
                        ->where('company_id', $user->company_id)
                        ->where('payment_status', 'Paid')
                        ->orWhere('payment_type', "%$params%")
                        ->orWhere('invoice_id', "%$params%")
                        ->orWhere('invoice_amount', "%$params%")
                        ->orWhere('invoice_file', "%$params%")
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company')
                        ->select('*')
                        ->where('company_id', $user->company_id)
                        ->where('payment_status', 'Paid')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        }
        if ($getinvoicedata->isNotEmpty()) {
            $response = $getinvoicedata->toArray();
            foreach ($response['data'] as $key => $invoice) {
                $downloadLink = asset('storage/app/invoice_pdf/' . $invoice['invoice_file']);
                // $downloadLink = $baseUrl . '/storage/app/public/invoice_pdf/' . $invoice['invoice_file'];
                $result['data'][$key]['download_link'] = $downloadLink;
            }
            unset($result['links']);
            return $this->output(true, 'Success', $result, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }
}
