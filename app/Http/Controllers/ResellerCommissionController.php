<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\Invoice;
use App\Models\MainPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResellerCommissionController extends Controller
{
    public function getCommissionExtensionOrTfnForReseller(Request $request)
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
}
