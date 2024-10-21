<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getAllcompanyListforSuperAdminDashboard(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            try {
                $companyCounts = Company::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN parent_id = 1 THEN 1 ELSE 0 END) as direct,
            SUM(CASE WHEN parent_id > 1 THEN 1 ELSE 0 END) as reseller
        ')->first();


                if ($companyCounts->total > 0) {
                    $percentDirectCompany = number_format(($companyCounts->direct / $companyCounts->total) * 100, 2) . '%';
                    $percentResellerCompany = number_format(($companyCounts->reseller / $companyCounts->total) * 100, 2) . '%';
                    return response()->json([
                        'total_company' => $companyCounts->total,
                        'reseller' => $companyCounts->reseller,
                        'company' => $companyCounts->direct,
                        'percentage_company' => $percentDirectCompany,
                        'percentage_reseller' => $percentResellerCompany,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching user counts: ' . $e->getMessage());
                return $this->output(false, 'An error occurred while fetching data.', [], 500);
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }


    public function getAllResellerUserCountforSuperAdminDashboard(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            try {
                $userCounts = User::selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN company_id IS NULL THEN 1 ELSE 0 END) as reseller,
                    SUM(CASE WHEN company_id > 0 THEN 1 ELSE 0 END) as users
                ')->first();
                if ($userCounts->total > 0) {
                    $percentUserCounts = number_format(($userCounts->users / $userCounts->total) * 100, 2) . '%';
                    $percentResellerUserCounts = number_format(($userCounts->reseller / $userCounts->total) * 100, 2) . '%';

                    return response()->json([
                        'total_users' => $userCounts->total,
                        'reseller' => $userCounts->reseller,
                        'users' => $userCounts->users,
                        'percentage_company' => $percentUserCounts,
                        'percentage_reseller' => $percentResellerUserCounts,
                    ]);
                } else {
                    return $this->output(true, 'No Record Found', []);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching user counts: ' . $e->getMessage());
                return $this->output(false, 'An error occurred while fetching data.', [], 500);
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }


    public function getAllTfnforSuperAdminDashboard(Request $request)
    {
        $user = \Auth::user();
        //if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            try {
                $query = DB::table('tfns')
                    ->leftJoin('carts', 'tfns.id', '=', 'carts.item_id')
                    ->select(
                        DB::raw('COUNT(*) AS total'),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '1' AND tfns.company_id = 0 AND carts.item_type = 'TFN' THEN 1 ELSE 0 END) AS reserved"),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '0' AND tfns.company_id = 0 THEN 1 ELSE 0 END) AS free"),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '1' AND tfns.company_id > 0 AND tfns.activated = '1' THEN 1 ELSE 0 END) AS Purchase"),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '1' AND tfns.company_id > 0 AND tfns.activated = '0' AND tfns.status = 1 THEN 1 ELSE 0 END) AS Expired")
                    );
                    if (in_array($user->roles->first()->slug, ['admin', 'user'])) {
                        $query->where('tfns.company_id', $user->company_id);
                    }
                    $tfnCounts = $query->first();

                if ($tfnCounts->total > 0) {
                    $percentReservedTfnCounts = number_format(($tfnCounts->reserved / $tfnCounts->total) * 100, 2) . '%';
                    $percentFreeTfnCounts = number_format(($tfnCounts->free / $tfnCounts->total) * 100, 2) . '%';
                    $percentPurchaseTfnCounts = number_format(($tfnCounts->Purchase / $tfnCounts->total) * 100, 2) . '%';
                    $percentExpiredTfnCounts = number_format(($tfnCounts->Expired / $tfnCounts->total) * 100, 2) . '%';


                    return response()->json([
                        'total' => $tfnCounts->total,
                        'cart' => $tfnCounts->reserved,
                        'free' => $tfnCounts->free,
                        'active' => $tfnCounts->Purchase,
                        'expired' => $tfnCounts->Expired,
                        'percentage_cart' => $percentReservedTfnCounts,
                        'percentage_free' => $percentFreeTfnCounts,
                        'percentage_active' => $percentPurchaseTfnCounts,
                        'percentage_expired' => $percentExpiredTfnCounts,
                    ]);
                } else {
                    return $this->output(true, 'No Record Found', []);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching user counts: ' . $e->getMessage());
                return $this->output(false, 'An error occurred while fetching data.', [], 500);
            }
       /*  } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        } */
    }


    public function getAllExtensionforSuperAdminDashboard(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            try {
                $extensionCounts = DB::table('extensions')
                    ->leftJoin('carts', 'extensions.id', '=', 'carts.item_id')
                    ->select(
                        DB::raw('COUNT(*) AS total'),
                        DB::raw("SUM(CASE WHEN extensions.host IS NULL AND extensions.status = 0 AND carts.item_type = 'Extension' THEN 1 ELSE 0 END) AS cart"),
                        DB::raw("SUM(CASE WHEN extensions.host = 'static' AND extensions.status = 0 THEN 1 ELSE 0 END) AS Expired"),
                        DB::raw("SUM(CASE WHEN extensions.host = 'dynamic' AND extensions.status = 1 THEN 1 ELSE 0 END) AS Active")
                    )
                    ->first();

                if ($extensionCounts->total > 0) {
                    $percentCartExtensionCounts = number_format(($extensionCounts->cart / $extensionCounts->total) * 100, decimals: 2) . '%';
                    $percentExpiredExtensionCounts = number_format(($extensionCounts->Expired / $extensionCounts->total) * 100, 2) . '%';
                    $percentActiveExtensionCounts = number_format(($extensionCounts->Active / $extensionCounts->total) * 100, 2) . '%';

                    return response()->json([
                        'total' => $extensionCounts->total,
                        'active' => $extensionCounts->Active,
                        'cart' => $extensionCounts->cart,
                        'expired' => $extensionCounts->Expired,
                        'percentage_cart' => $percentCartExtensionCounts,
                        'percentage_expired' => $percentExpiredExtensionCounts,
                        'percentage_active' => $percentActiveExtensionCounts,
                    ]);
                } else {
                    return $this->output(true, 'No Record Found', []);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching user counts: ' . $e->getMessage());
                return $this->output(false, 'An error occurred while fetching data.', [], 500);
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }

    public function getALLTfnExtensionPriceforlastSevendays()
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            try {
                $invoiceAndinvoiceItemsCounts = DB::table('invoices')
                    ->leftJoin('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
                    ->select(
                        DB::raw('COUNT(*) AS total'),
                        DB::raw("SUM(invoice_items.item_price) AS totalprice"),
                        DB::raw("SUM(CASE WHEN invoice_items.item_type = 'Extension' THEN 1 ELSE 0 END) AS extension"),
                        DB::raw("SUM(CASE WHEN invoice_items.item_type = 'TFN' THEN 1 ELSE 0 END) AS tfn"),
                        DB::raw("SUM(CASE WHEN invoice_items.item_type = 'Extension' THEN invoice_items.item_price ELSE 0 END) AS extensionprice"),
                        DB::raw("SUM(CASE WHEN invoice_items.item_type = 'TFN' THEN invoice_items.item_price ELSE 0 END) AS tfnprice"),

                    )
                    ->where('invoices.updated_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 200 DAY)'))
                    ->first();


                if ($invoiceAndinvoiceItemsCounts->total > 0) {
                    $percentNumberofextension = number_format(($invoiceAndinvoiceItemsCounts->extension / $invoiceAndinvoiceItemsCounts->total) * 100, decimals: 2) . '%';
                    $percentNumberoftfn = number_format(($invoiceAndinvoiceItemsCounts->tfn / $invoiceAndinvoiceItemsCounts->total) * 100, 2) . '%';
                    $percentNumberofextensionprice = number_format(($invoiceAndinvoiceItemsCounts->extensionprice / $invoiceAndinvoiceItemsCounts->totalprice) * 100, 2) . '%';
                    $percentNumberoftfnprice = number_format(($invoiceAndinvoiceItemsCounts->tfnprice / $invoiceAndinvoiceItemsCounts->totalprice) * 100, 2) . '%';


                    return response()->json([ 
                        'total_price' => $invoiceAndinvoiceItemsCounts->totalprice,  
                        'number_of_extensionprice' => $invoiceAndinvoiceItemsCounts->extensionprice,
                        'number_of_tfnprice' => $invoiceAndinvoiceItemsCounts->tfnprice,
                    ]);
                } else {
                    return $this->output(true, 'No Record Found', []);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching user counts: ' . $e->getMessage());
                return $this->output(false, 'An error occurred while fetching data.', [], 500);
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }


    public function getCdrReports(Request $request, $dayCount)
    {
        $user = \Auth::user();
        try { 
            

                /* $cdrCounts = DB::table('cdrs')
                ->select(
                    DB::raw('COUNT(*) AS total'),
                    DB::raw("SUM(CASE WHEN cdrs.disposition= 'ANSWER' THEN 1 ELSE 0 END)  AS answer"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'BUSY' THEN 1 ELSE 0 END) as busy"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'CANCEL' THEN 1 ELSE 0 END)  as cancel"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'CHANUNAVAIL' THEN 1 ELSE 0 END)  as chanunavail"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'NOANSWER' THEN 1 ELSE 0 END)  as noanswer")
                )
                ->where('call_date', '>=',  DB::raw('DATE_SUB(NOW(), INTERVAL '.$dayCount.' DAY)'))
                ->first(); */
                $query = DB::table('cdrs')
                    ->select(
                        DB::raw('COUNT(*) AS total'),
                        DB::raw("SUM(CASE WHEN cdrs.disposition = 'ANSWER' THEN 1 ELSE 0 END) AS answer"),
                        DB::raw("SUM(CASE WHEN cdrs.disposition = 'BUSY' THEN 1 ELSE 0 END) AS busy"),
                        DB::raw("SUM(CASE WHEN cdrs.disposition = 'CANCEL' THEN 1 ELSE 0 END) AS cancel"),
                        DB::raw("SUM(CASE WHEN cdrs.disposition = 'CHANUNAVAIL' THEN 1 ELSE 0 END) AS chanunavail"),
                        DB::raw("SUM(CASE WHEN cdrs.disposition = 'NOANSWER' THEN 1 ELSE 0 END) AS noanswer")
                    )
                    ->where('call_date', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL ' . $dayCount . ' DAY)'));
                    if (in_array($user->roles->first()->slug, ['admin', 'user'])) {
                        $query->where('company_id', $user->company_id);
                    }

                    if ($dayCount == 1) {
                        // If dayCount is 1, group by hour
                        $query->groupBy(DB::raw('HOUR(call_date)'))
                            ->selectRaw('HOUR(call_date) as time_interval');
                    } else {
                        // If dayCount is 7 or 30, group by day
                        $query->groupBy(DB::raw('DATE(call_date)'))
                            ->selectRaw('DATE(call_date) as time_interval');
                    }
                
                return $cdrCounts = $query->get();
            
                /* if ($cdrCounts->total > 0) { 
                    return response()->json([
                        'total' => $cdrCounts->total,
                        'answer' => $cdrCounts->answer,
                        'busy' => $cdrCounts->busy,
                        'cancel' => $cdrCounts->cancel,
                        'chanunavail' => $cdrCounts->chanunavail,
                        'noanswer' => $cdrCounts->noanswer,
                    ]);
                } else {
                    return $this->output(true, 'No Record Found', []);
                } */
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in getting CDR Reposrts for deshboard : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }


    public function getCompanyUserCount(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, ['admin'])) {
            $data = 
            $totalUsers = DB::table('users')
                        ->where('company_id', $user->company_id)
                        ->where('role_id', 6)
                        ->count();            
            if ($data) {
                return $this->output(true, 'Success', $totalUsers);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        }else{
            return $this->output(false, 'Sorry! You are not authorized.', [], 403);  
        }
    }
    
}
