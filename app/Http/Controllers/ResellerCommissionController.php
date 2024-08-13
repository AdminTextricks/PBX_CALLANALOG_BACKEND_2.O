<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\MainPrice;
use App\Models\ResellerCommissionOfItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResellerCommissionController extends Controller
{
    public function getCommissionExtensionOrTfnForResellerOLD(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $getinvoicedata = Invoice::with([
                'invoice_items',
                'countries:id,country_name,phone_code,currency,currency_symbol',
                'states:id,country_id,state_name',
                'company:id,parent_id,company_name,account_code,email,mobile,billing_address,city,zip',
                'company.main_prices',
                'payments',
                'reseller_prices'
            ])
                ->select('invoices.*')
                ->where('payment_status', 'Paid')
                ->leftJoin('companies', 'companies.id', '=', 'invoices.company_id')
                ->leftJoin('main_prices', 'main_prices.reseller_id', '=', 'companies.parent_id')
                ->leftJoin('reseller_prices', 'reseller_prices.company_id', '=', 'companies.id')
                ->whereHas('company', function ($query) {
                    $query->where('parent_id', '>', 1);
                })
                ->whereHas('payments', function ($query) {
                    $query->where('payment_type', '!=', "Added to Wallet");
                })
                ->orderBy('invoices.id', 'DESC')
                ->distinct()
                ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');

            if ($getinvoicedata) {
                foreach ($getinvoicedata as $invoice) {
                    $total_price_ext_items = 0;
                    $total_price_tfn_items = 0;

                    foreach ($invoice->reseller_prices as $reseller_data) {
                        if ($reseller_data->commission_type == "Fixed Amount") {
                            if ($reseller_data->product == "Extension") {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "Extension") {
                                        $total_price_ext_items += $reseller_data->price;
                                    }
                                }
                            } else {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "TFN") {
                                        $total_price_tfn_items += $reseller_data->price;
                                    }
                                }
                            }
                        } else {
                            if ($reseller_data->product == "Extension") {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "Extension") {
                                        $total_price_ext_items +=  $invoice->company->main_prices->extension_price * $reseller_data->price / 100;
                                    }
                                }
                            } else {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "TFN") {
                                        $total_price_tfn_items += $invoice->company->main_prices->tfn_price * $reseller_data->price / 100;
                                    }
                                }
                            }
                        }
                    }

                    $invoice->total_price_items_Commission = $total_price_ext_items + $total_price_tfn_items;
                    $invoice->numberofItems = count($invoice->invoice_items);
                }
                return $this->output(true, 'Success', $getinvoicedata, 200);
            } else {
                return $this->output(false, 'No data found', null, 404);
            }
        } elseif ($request->user()->hasRole('reseller')) {
            $getinvoicedata = Invoice::with([
                'invoice_items',
                'countries:id,country_name,phone_code,currency,currency_symbol',
                'states:id,country_id,state_name',
                'company:id,parent_id,company_name,account_code,email,mobile,billing_address,city,zip',
                'company.main_prices',
                'payments',
                'reseller_prices'
            ])
                ->select('invoices.*')
                ->where('payment_status', 'Paid')
                ->leftJoin('companies', 'companies.id', '=', 'invoices.company_id')
                ->leftJoin('main_prices', 'main_prices.reseller_id', '=', 'companies.parent_id')
                ->leftJoin('reseller_prices', 'reseller_prices.company_id', '=', 'companies.id')
                ->whereHas('company', function ($query) use ($user) {
                    $query->where('parent_id', '=', $user->id);
                })
                ->whereHas('payments', function ($query) {
                    $query->where('payment_type', '!=', "Added to Wallet");
                })
                ->orderBy('invoices.id', 'DESC')
                ->distinct()
                ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');

            if ($getinvoicedata) {
                foreach ($getinvoicedata as $invoice) {
                    $total_price_ext_items = 0;
                    $total_price_tfn_items = 0;

                    foreach ($invoice->reseller_prices as $reseller_data) {
                        if ($reseller_data->commission_type == "Fixed Amount") {
                            if ($reseller_data->product == "Extension") {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "Extension") {
                                        $total_price_ext_items += $reseller_data->price;
                                    }
                                }
                            } else {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "TFN") {
                                        $total_price_tfn_items += $reseller_data->price;
                                    }
                                }
                            }
                        } else {
                            if ($reseller_data->product == "Extension") {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "Extension") {
                                        $total_price_ext_items +=  $invoice->company->main_prices->extension_price * $reseller_data->price / 100;
                                    }
                                }
                            } else {
                                foreach ($invoice->invoice_items as $invoice_item) {
                                    if ($invoice_item->item_type == "TFN") {
                                        $total_price_tfn_items += $invoice->company->main_prices->tfn_price * $reseller_data->price / 100;
                                    }
                                }
                            }
                        }
                    }

                    $invoice->total_price_items_Commission = $total_price_ext_items + $total_price_tfn_items;
                    $invoice->numberofItems = count($invoice->invoice_items);
                }
                return $this->output(true, 'Success', $getinvoicedata, 200);
            } else {
                return $this->output(false, 'No data found', null, 404);
            }
        } else {
            return $this->output(false, 'No data found', null, 404);
        }
    }


    public function getCommissionExtensionOrTfnForResellerOLD2(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params      = $request->params ?? "";
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $invoice_get_id = $request->id ?? NULL;
        $queryInvoiceData = Invoice::with([
            'invoice_items',
            'countries:id,country_name,phone_code,currency,currency_symbol',
            'states:id,country_id,state_name',
            'company:id,parent_id,company_name,account_code,email,mobile,billing_address,city,zip',
            'company.main_prices',
            'payments',
            'reseller_prices'
        ])
            ->select('invoices.*')
            ->where('payment_status', 'Paid')
            ->leftJoin('companies', 'companies.id', '=', 'invoices.company_id')
            ->leftJoin('main_prices', 'main_prices.reseller_id', '=', 'companies.parent_id')
            ->leftJoin('reseller_prices', 'reseller_prices.company_id', '=', 'companies.id')
            ->whereHas('payments', function ($query) {
                $query->where('payment_type', '!=', "Added to Wallet");
            });

        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            $queryInvoiceData->whereHas('company', function ($query) {
                $query->where('parent_id', '>', 1);
            });
        } elseif ($request->user()->hasRole('reseller')) {
            $queryInvoiceData->whereHas('company', function ($query) use ($user) {
                $query->where('parent_id', '=', $user->id);
            });
        }
        if ($invoice_get_id) {
            $queryInvoiceData
                ->where('invoices.id', '=', $invoice_get_id);
            // ->orderBy('invoices.id', 'DESC');
        } elseif ($params !== "" || $request->has('from_date') || $request->has('to_date')) {
            if ($fromDate) {
                $queryInvoiceData->where('invoices.updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $queryInvoiceData->where('invoices.updated_at', '<=', $toDate);
            }
            $queryInvoiceData->where(function ($dataquery) use ($params) {
                $dataquery->orWhere('invoices.invoice_id', 'LIKE', "%$params%")
                    ->orWhere('invoices.invoice_amount', 'LIKE', "%$params%")
                    ->orWhere('invoices.updated_at', 'LIKE', "%$params%")
                    ->orWhereHas('company', function ($subQuery) use ($params) {
                        $subQuery->where('company_name', 'LIKE', "%$params%")
                            ->orWhere('email', 'LIKE', "%{$params}%");
                    })
                    ->orWhereHas('payments', function ($subQuery) use ($params) {
                        $subQuery->where('payment_type', 'LIKE', "%{$params}%");
                    });
            });
            $queryInvoiceData->orderBy('invoices.id', 'DESC');
        }

        $distinctInvoiceIds = $queryInvoiceData->distinct()->pluck('invoices.id');
        $paginatedInvoiceIds = $distinctInvoiceIds->forPage($request->page ?? 1, $perPageNo);

        $getinvoicedata = Invoice::with([
            // 'invoice_items',
            'countries:id,country_name,phone_code,currency,currency_symbol',
            'states:id,country_id,state_name',
            'company:id,parent_id,company_name,account_code,email,mobile,billing_address,city,zip',
            'company.main_prices',
            'payments',
            'reseller_prices'
        ])
            ->whereIn('id', $paginatedInvoiceIds)->orderBy('id', 'DESC')->get();
        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $getinvoicedata,
            $distinctInvoiceIds->count(),
            $perPageNo,
            $request->page ?? 1,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Calculate commission and number of items
        foreach ($paginatedData as $invoice) {
            $total_price_ext_items = 0;
            $total_price_tfn_items = 0;

            foreach ($invoice->reseller_prices as $reseller_data) {
                if ($reseller_data->commission_type == "Fixed Amount") {
                    if ($reseller_data->product == "Extension") {
                        foreach ($invoice->invoice_items as $invoice_item) {
                            if ($invoice_item->item_type == "Extension") {
                                $total_price_ext_items += $reseller_data->price;
                            }
                        }
                    } else {
                        foreach ($invoice->invoice_items as $invoice_item) {
                            if ($invoice_item->item_type == "TFN") {
                                $total_price_tfn_items += $reseller_data->price;
                            }
                        }
                    }
                } else {
                    if ($reseller_data->product == "Extension") {
                        foreach ($invoice->invoice_items as $invoice_item) {
                            if ($invoice_item->item_type == "Extension") {
                                $total_price_ext_items +=  $invoice->company->main_prices->extension_price * $reseller_data->price / 100;
                            }
                        }
                    } else {
                        foreach ($invoice->invoice_items as $invoice_item) {
                            if ($invoice_item->item_type == "TFN") {
                                $total_price_tfn_items += $invoice->company->main_prices->tfn_price * $reseller_data->price / 100;
                            }
                        }
                    }
                }
            }

            $invoice->total_price_items_Commission = $total_price_ext_items + $total_price_tfn_items;
            $invoice->numberofItems = count($invoice->invoice_items);
            $paginatedData->makeHidden(['invoice_items']);
        }

        return $this->output(true, 'Success', $paginatedData, 200);
    }


    public function getCommissionExtensionOrTfnForReseller(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params = $request->params ?? "";
        $fromDate = $request->get('from_date') ? \Carbon\Carbon::createFromFormat('Y-m-d', $request->get('from_date'))->startOfDay() : null;
        $toDate = $request->get('to_date') ? \Carbon\Carbon::createFromFormat('Y-m-d', $request->get('to_date'))->endOfDay() : null;
        $commission_id = $request->id ?? null;

        $query = ResellerCommissionOfItems::select('*')->with('company:id,company_name,email');

        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            if ($commission_id) {
                $query->where('id', $commission_id);
            }

            if ($params !== "") {
                $query->where('updated_at', 'LIKE', "%$params%")
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('company_name', 'like', "%{$params}%")
                            ->orWhere('email', 'like', "%{$params}%");
                    });
            }
        } elseif ($request->user()->hasRole('reseller')) {
            $query->where('reseller_id', $user->id);

            if ($commission_id) {
                $query->where('id', $commission_id)->where('reseller_id', $user->id);
            }

            if ($params !== "") {
                $query->where('updated_at', 'LIKE', "%$params%")
                    ->orWhereHas('company', function ($query) use ($params) {
                        $query->where('company_name', 'like', "%{$params}%")
                            ->orWhere('email', 'like', "%{$params}%");
                    });
            }
        } else {
            return $this->output(false, 'Unauthorized Action');
        }

        if ($fromDate) {
            $query->where('updated_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('updated_at', '<=', $toDate);
        }

        $getAllResellercommission = $query->orderBy('id', 'DESC')->paginate($perPageNo);

        if ($getAllResellercommission->isNotEmpty()) {
            $response = $getAllResellercommission->toArray();
            return $this->output(true, 'Success', $response, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function numberofItemsforResellerCommission(Request $request, $id)
    {
        $user = \Auth::user();
        // $invoice_id = $request->invoice_id ?? NULL;
        if (is_null($id)) {
            return $this->output(false, 'Invoice Not Found!', 400);
        }
        $getNumberData = InvoiceItems::where('invoice_id', '=', $id)->get();
        if (is_null($getNumberData)) {
            return $this->output(false, 'No Record Found!', 400);
        } else {
            return $this->output(true, 'Success', $getNumberData, 200);
        }
    }
}
