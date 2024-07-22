<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Extension;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\Tfn;

class InvoiceController extends Controller
{
    public function createInvoice1(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|array',
            'item_number' => 'required|array',
            'item_price' => 'required|array',
            'item_type' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        try {
            DB::beginTransaction();

            $invoice_amount_main = array_sum($request->item_price);
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
            // return $createinvoice;
            foreach ($request->item_number as $key => $itemNumber) {
                $itemType = $request->item_type[$key];
                $itemId = $request->item_id[$key];
                $itemPrice = $request->item_price[$key];

                if ($itemType == "TFN") {
                    $tfninvoicenumberTfn = Tfn::select('tfn_number')->where('tfn_number', '=', $itemNumber)->first();
                    $tfninvoicenumber = $tfninvoicenumberTfn->tfn_number;
                } else {
                    $tfninvoicenumberExt = Extension::select('name')->where('name', '=', $itemNumber)->first();
                    $tfninvoicenumber = $tfninvoicenumberExt->name;
                }
                // return $tfninvoicenumber;
                if ($tfninvoicenumber) {
                    $cartinvoicenumber = Cart::where('item_number', '=', $tfninvoicenumber)->first();

                    // if (!$cartinvoicenumber) {
                    //     DB::rollback();
                    //     return $this->output(false, 'Cart not found for item', ['item_number' => $itemNumber], 409);
                    // }

                    // if ($cartinvoicenumber->item_id == $itemId) {
                    //     DB::rollback();
                    //     return $this->output(false, 'Item ID mismatch', 409, [
                    //         'item_id' => $itemId,
                    //         'expected_item_id' => $cartinvoicenumber->item_id
                    //     ]);
                    // }

                    // if ($cartinvoicenumber->item_number != $itemNumber) {
                    //     DB::rollback();
                    //     return $this->output(false, 'Item Number mismatch', [
                    //         'item_number' => $itemNumber,
                    //         'expected_item_number' => $cartinvoicenumber->item_number
                    //     ], 409);
                    // }

                    // if ($cartinvoicenumber->item_price != $itemPrice) {
                    //     DB::rollback();
                    //     return $this->output(false, 'Item Price mismatch', [
                    //         'item_price' => $itemPrice,
                    //         'expected_item_price' => $cartinvoicenumber->item_price
                    //     ], 409);
                    // }
                    if (
                        $cartinvoicenumber &&
                        $cartinvoicenumber->item_id == $itemId &&
                        $cartinvoicenumber->item_number == $itemNumber &&
                        $cartinvoicenumber->item_price == $itemPrice
                    ) {
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


    public function createInvoice(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
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
                    $cartinvoicenumber = Cart::where('item_number', '=', $tfninvoicenumber)->first();

                    if (
                        $cartinvoicenumber &&
                        $cartinvoicenumber->item_id == $itemId &&
                        $cartinvoicenumber->item_number == $itemNumber &&
                        $cartinvoicenumber->item_price == $itemPrice
                    ) {
                        InvoiceItems::create([
                            'company_id' => $user->company->id,
                            'invoice_id' => $createinvoice->id,
                            'item_type' => $itemType,
                            'item_number' => $itemNumber,
                            'item_price' => $itemPrice,
                        ]);
                    } else {
                        DB::rollback();
                        return $this->output(false, 'This Cart Number does not belong to us', 409);
                    }
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
                // $downloadLink = 'invoice_pdf/' . $invoiceData->invoice_file;
                // $invoiceData['download_link'] = $downloadLink;
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

        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with('invoice_items')
                    ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                    ->with('states:id,country_id,state_name')
                    ->with('company:id,company_name,account_code,email,mobile')
                    ->with('payments')
                    ->select('*')
                    ->where('payment_status', 'Paid')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '!=', 'Added to Wallet');
                    })
                    ->where('id', $invoice_get_id);
            } else {
                if ($params !== "") {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile')
                        ->with('payments')
                        ->select('*')
                        ->where('payment_status', 'Paid')
                        ->whereHas('payments', function ($query) {
                            $query->where('payment_type', '!=', 'Added to Wallet');
                        })
                        ->orWhere('payment_type', "%$params%")
                        ->orWhere('invoice_id', "%$params%")
                        ->orWhere('invoice_amount', "%$params%")
                        ->orWhere('invoice_file', "%$params%")
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile')
                        ->with('payments')
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
                    ->with('company:id,company_name,account_code,email,mobile')
                    ->with('payments')
                    ->select('*')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '!=', 'Added to Wallet');
                    })
                    ->where('company_id', $user->company_id)
                    ->where('payment_status', 'Paid')
                    ->where('id', $invoice_get_id);
            } else {
                if ($params != "") {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile')
                        ->with('payments')
                        ->select('*')
                        ->where('company_id', $user->company_id)
                        ->where('payment_status', 'Paid')
                        ->whereHas('payments', function ($query) {
                            $query->where('payment_type', '!=', 'Added to Wallet');
                        })
                        ->orWhere('payment_type', "%$params%")
                        ->orWhere('invoice_id', "%$params%")
                        ->orWhere('invoice_amount', "%$params%")
                        ->orWhere('invoice_file', "%$params%")
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile')
                        ->with('payments')
                        ->select('*')
                        ->whereHas('payments', function ($query) {
                            $query->where('payment_type', '!=', 'Added to Wallet');
                        })
                        ->where('company_id', $user->company_id)
                        ->where('payment_status', 'Paid')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        }
        if ($getinvoicedata->isNotEmpty()) {
            $response = $getinvoicedata->toArray();
            // foreach ($response['data'] as $key => $invoice) {
            //     $downloadLink = asset('storage/app/invoice_pdf/' . $invoice['invoice_file']);
            //     // $downloadLink = $baseUrl . '/storage/app/public/invoice_pdf/' . $invoice['invoice_file'];
            //     $result['data'][$key]['download_link'] = $downloadLink;
            // }
            // unset($result['links']);
            return $this->output(true, 'Success', $response, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }
}
