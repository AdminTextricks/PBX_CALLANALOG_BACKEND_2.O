<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Extension;
use App\Models\RechargeHistory;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\Tfn;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function createInvoice(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.country_id' => 'required|numeric',
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
                $itemCountry = $item['country_id'];
                $itemType = $item['item_type'];
                $itemId = $item['item_id'];
                $itemNumber = $item['item_number'];
                $itemPrice = $item['item_price'];

                if ($itemType == "TFN") {
                    $tfninvoicenumberTfn = Tfn::select('tfn_number')->where('tfn_number', '=', $itemNumber)->first();
                    if (!$tfninvoicenumberTfn) {
                        DB::rollback();
                        return $this->output(false, 'An error occurred!! ' . $itemNumber . 'Not Matching!!', 409);
                    } else {
                        $tfninvoicenumber = $tfninvoicenumberTfn->tfn_number;
                    }
                } else {

                    $tfninvoicenumberExt = Extension::select('name')->where('name', '=', $itemNumber)->first();
                    if (!$tfninvoicenumberExt) {
                        DB::rollback();
                        return $this->output(false, 'An error occurred!! ' . $itemNumber . 'Not Matching!!', 409);
                    } else {
                        $tfninvoicenumber = $tfninvoicenumberExt->name;
                    }
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
                            'country_id' => $itemCountry,
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

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate) {
            $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        }
        if ($toDate) {
            $toDate = \Carbon\Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        }

        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with(['invoice_items', 'countries:id,country_name,phone_code,currency,currency_symbol', 'states:id,country_id,state_name', 'company:id,company_name,account_code,email,mobile,billing_address,city,zip', 'payments'])
                    ->select('*')
                    ->where('payment_status', 'Paid')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '!=', 'Added to Wallet');
                    })
                    ->where('id', $invoice_get_id);
            } else {
                $getinvoicedata = Invoice::with(['invoice_items', 'countries:id,country_name,phone_code,currency,currency_symbol', 'states:id,country_id,state_name', 'company:id,company_name,account_code,email,mobile,billing_address,city,zip', 'payments'])
                    ->select('*')
                    ->where('payment_status', 'Paid')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '!=', 'Added to Wallet');
                    });

                if ($fromDate) {
                    $getinvoicedata->where('updated_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $getinvoicedata->where('updated_at', '<=', $toDate);
                }

                if ($params !== "") {
                    $getinvoicedata->where(function ($query) use ($params) {
                        $query->orWhere('invoice_id', 'LIKE', "%$params%")
                            ->orWhere('invoice_amount', 'LIKE', "%$params%")
                            ->orWhere('updated_at', 'LIKE', "%$params%")
                            ->orWhereHas('company', function ($subQuery) use ($params) {
                                $subQuery->where('company_name', 'LIKE', "%$params%")
                                    ->orWhere('email', 'LIKE', "%{$params}%");
                            })
                            ->orWhereHas('payments', function ($subQuery) use ($params) {
                                $subQuery->where('payment_type', 'LIKE', "%{$params}%")
                                    ->orWhere('transaction_id', 'LIKE', "%{$params}%");
                            })
                            ->orWhereHas('countries', function ($subQuery) use ($params) {
                                $subQuery->where('country_name', 'LIKE', "%{$params}%");
                            });
                    });
                }

                $getinvoicedata = $getinvoicedata->orderBy('id', 'DESC')
                    ->paginate($perPageNo, ['*'], 'page');
            }
        } else {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with(['invoice_items', 'countries:id,country_name,phone_code,currency,currency_symbol', 'states:id,country_id,state_name', 'company:id,company_name,account_code,email,mobile,billing_address,city,zip', 'payments'])
                    ->select('*')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '!=', 'Added to Wallet');
                    })
                    ->where('company_id', $user->company_id)
                    ->where('payment_status', 'Paid')
                    ->where('id', $invoice_get_id);
            } else {
                $getinvoicedata = Invoice::with(['invoice_items', 'countries:id,country_name,phone_code,currency,currency_symbol', 'states:id,country_id,state_name', 'company:id,company_name,account_code,email,mobile,billing_address,city,zip', 'payments'])
                    ->select('*')
                    ->where('company_id', $user->company_id)
                    ->where('payment_status', 'Paid')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '!=', 'Added to Wallet');
                    });

                if ($fromDate) {
                    $getinvoicedata->where('updated_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $getinvoicedata->where('updated_at', '<=', $toDate);
                }

                if ($params !== "" || $request->has('from_date') || $request->has('to_date')) {
                    $getinvoicedata->where(function ($query) use ($params) {
                        $query->orWhere('invoice_id', 'LIKE', "%$params%")
                            ->orWhere('invoice_amount', 'LIKE', "%$params%")
                            ->orWhere('updated_at', 'LIKE', "%$params%")
                            ->orWhereHas('company', function ($subQuery) use ($params) {
                                $subQuery->where('company_name', 'LIKE', "%$params%")
                                    ->orWhere('email', 'LIKE', "%{$params}%");
                            })
                            ->orWhereHas('payments', function ($subQuery) use ($params) {
                                $subQuery->where('payment_type', 'LIKE', "%{$params}%")
                                    ->orWhere('transaction_id', 'LIKE', "%{$params}%");
                            })
                            ->orWhereHas('countries', function ($subQuery) use ($params) {
                                $subQuery->where('country_name', 'LIKE', "%{$params}%");
                            });
                    });
                }

                $getinvoicedata = $getinvoicedata->orderBy('id', 'DESC')
                    ->paginate($perPageNo, ['*'], 'page');
            }
        }

        if ($getinvoicedata->isNotEmpty()) {
            $response = $getinvoicedata->toArray();
            return $this->output(true, 'Success', $response, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }


    public function getRechargehistoryInvoiceData_old(Request $request)
    {
        $user  = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params      = $request->params ?? "";
        $invoice_get_id = $request->id ?? NULL;
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        if ($fromDate) {
            $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        }
        if ($toDate) {
            $toDate = \Carbon\Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        }
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with('invoice_items')
                    ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                    ->with('states:id,country_id,state_name')
                    ->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                    ->with('payments')
                    ->select('*')
                    ->where('payment_status', 'Paid')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '=', 'Added to Wallet');
                    })
                    ->where('id', $invoice_get_id);
            } else {
                if ($params !== "" || $request->has('from_date') || $request->has('to_date')) {
                    $getinvoicedata = Invoice::with([
                        'invoice_items',
                        'countries:id,country_name,phone_code,currency,currency_symbol',
                        'states:id,country_id,state_name',
                        'company:id,company_name,account_code,email,mobile,billing_address,city,zip',
                        'payments'
                    ])
                        ->select('*')
                        ->where('payment_status', 'Paid')
                        ->whereHas('payments', function ($query) {
                            $query->where('payment_type', '=', 'Added to Wallet');
                        });

                    if ($fromDate) {
                        $getinvoicedata->where('updated_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $getinvoicedata->where('updated_at', '<=', $toDate);
                    }
                    $getinvoicedata->where(function ($query) use ($params) {
                        $query->orWhere('invoice_id', 'LIKE', "%$params%")
                            ->orWhere('invoice_amount', 'LIKE', "%$params%")
                            ->orWhere('updated_at', 'LIKE', "%$params%")
                            ->orWhereHas('company', function ($subQuery) use ($params) {
                                $subQuery->where('company_name', 'LIKE', "%$params%")
                                    ->orWhere('email', 'LIKE', "%{$params}%");
                            })
                            ->orWhereHas('payments', function ($subQuery) use ($params) {
                                $subQuery->where('payment_type', 'LIKE', "%{$params}%");
                            });
                    });
                    $getinvoicedata = $getinvoicedata->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('payments')
                        ->select('*')
                        ->where('payment_status', 'Paid')
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        } else {
            if ($invoice_get_id) {
                $getinvoicedata = Invoice::with('invoice_items')
                    ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                    ->with('states:id,country_id,state_name')
                    ->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                    ->with('payments')
                    ->select('*')
                    ->whereHas('payments', function ($query) {
                        $query->where('payment_type', '=', 'Added to Wallet');
                    })
                    ->where('company_id', $user->company_id)
                    ->where('payment_status', 'Paid')
                    ->where('id', $invoice_get_id);
            } else {
                if ($params !== "" || $request->has('from_date') || $request->has('to_date')) {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('payments')
                        ->select('*')
                        ->where('company_id', $user->company_id)
                        ->where('payment_status', 'Paid')
                        ->whereHas('payments', function ($query) {
                            $query->where('payment_type', '=', 'Added to Wallet');
                        });
                    if ($fromDate) {
                        $getinvoicedata->where('updated_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $getinvoicedata->where('updated_at', '<=', $toDate);
                    }
                    $getinvoicedata->where(function ($query) use ($params) {
                        $query->orWhere('invoice_id', 'LIKE', "%$params%")
                            ->orWhere('invoice_amount', 'LIKE', "%$params%")
                            ->orWhere('updated_at', 'LIKE', "%$params%")
                            ->orWhereHas('company', function ($subQuery) use ($params) {
                                $subQuery->where('company_name', 'LIKE', "%$params%")
                                    ->orWhere('email', 'LIKE', "%{$params}%");
                            })
                            ->orWhereHas('payments', function ($subQuery) use ($params) {
                                $subQuery->where('payment_type', 'LIKE', "%{$params}%");
                            });
                    });
                    $getinvoicedata = $getinvoicedata->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getinvoicedata = Invoice::with('invoice_items')
                        ->with('countries:id,country_name,phone_code,currency,currency_symbol')
                        ->with('states:id,country_id,state_name')
                        ->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('payments')
                        ->select('*')
                        ->whereHas('payments', function ($query) {
                            $query->where('payment_type', '=', 'Added to Wallet');
                        })
                        ->where('company_id', $user->company_id)
                        ->where('payment_status', 'Paid')
                        ->orderBy('id', 'DESC')
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

    public function getRechargehistoryInvoiceData(Request $request)
    {
        $user  = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params      = $request->params ?? "";
        $recharge_id = $request->id ?? NULL;
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        if ($fromDate) {
            $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        }
        if ($toDate) {
            $toDate = \Carbon\Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        }
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($recharge_id) {
                $getrechargehistorydata = RechargeHistory::with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                    ->with('user:id,name,email')
                    ->select('*')
                    ->where('id', $recharge_id)->first();
            } else {
                if ($params !== "" || $request->has('from_date') || $request->has('to_date')) {
                    $getrechargehistorydata = RechargeHistory::with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('user:id,name,email')
                        ->select('*')
                        ->where('recharged_by', 'LIKE', "%$params%");

                    if ($fromDate) {
                        $getrechargehistorydata->where('updated_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $getrechargehistorydata->where('updated_at', '<=', $toDate);
                    }
                    $getrechargehistorydata->orWhereHas('company', function ($subQuery) use ($params) {
                        $subQuery->where('company_name', 'LIKE', "%$params%")
                            ->orWhere('email', 'LIKE', "%{$params}%");
                    });
                    $getrechargehistorydata->orWhereHas('user', function ($subQuery) use ($params) {
                        $subQuery->where('name', 'LIKE', "%$params%")
                            ->orWhere('email', 'LIKE', "%{$params}%");
                    });
                    $getrechargehistorydata = $getrechargehistorydata->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getrechargehistorydata = RechargeHistory::select('*')->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('user:id,name,email')
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        } else {
            if ($recharge_id) {
                $getrechargehistorydata = RechargeHistory::with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                    ->with('user:id,name,email')
                    ->select('*')
                    ->where('id', $recharge_id)
                    ->where('company_id', $user->company->id)->first();
            } else {
                if ($params !== "" || $request->has('from_date') || $request->has('to_date')) {
                    $getrechargehistorydata = RechargeHistory::with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('user:id,name,email')
                        ->select('*')->where('recharged_by', 'LIKE', "%$params%");

                    if ($fromDate) {
                        $getrechargehistorydata->where('updated_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $getrechargehistorydata->where('updated_at', '<=', $toDate);
                    }
                    $getrechargehistorydata->orWhereHas('company', function ($subQuery) use ($params) {
                        $subQuery->where('company_name', 'LIKE', "%$params%")
                            ->orWhere('email', 'LIKE', "%{$params}%");
                    });
                    $getrechargehistorydata->orWhereHas('user', function ($subQuery) use ($params) {
                        $subQuery->where('name', 'LIKE', "%$params%")
                            ->orWhere('email', 'LIKE', "%{$params}%");
                    });
                    $getrechargehistorydata = $getrechargehistorydata->where('company_id', $user->company->id)->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getrechargehistorydata = RechargeHistory::select('*')->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                        ->with('user:id,name,email')
                        ->where('company_id', $user->company->id)
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        }

        if ($getrechargehistorydata->isNotEmpty()) {
            $response = $getrechargehistorydata->toArray();
            return $this->output(true, 'Success', $response, 200);
        } else {
            return $this->output(true, 'No Record Found');
        }
    }
}
