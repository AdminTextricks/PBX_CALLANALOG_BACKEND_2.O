<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ResellerCommissionOfCalls;
use App\Models\User;
use Carbon\Carbon;
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
        //if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
        try {
            $query = DB::table('extensions')
                ->leftJoin('carts', 'extensions.id', '=', 'carts.item_id')
                ->select(
                    DB::raw('COUNT(*) AS total'),
                    DB::raw("SUM(CASE WHEN extensions.host IS NULL AND extensions.status = 0 AND carts.item_type = 'Extension' THEN 1 ELSE 0 END) AS cart"),
                    DB::raw("SUM(CASE WHEN extensions.host = 'static' AND extensions.status = 0 THEN 1 ELSE 0 END) AS Expired"),
                    DB::raw("SUM(CASE WHEN extensions.host = 'dynamic' AND extensions.status = 1 THEN 1 ELSE 0 END) AS Active")
                );
            if (in_array($user->roles->first()->slug, ['admin', 'user'])) {
                $query->where('extensions.company_id', $user->company_id);
            }
            $extensionCounts = $query->first();

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
        /* } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        } */
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
            // If $dayCount is 1, we generate hourly data, otherwise, we generate daily data.
            if ($dayCount == 1) {
                $query = DB::table('cdrs')
                ->select(
                    DB::raw('COUNT(*) AS total'),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'ANSWER' THEN 1 ELSE 0 END) AS answer"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'BUSY' THEN 1 ELSE 0 END) AS busy"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'CANCEL' THEN 1 ELSE 0 END) AS cancel"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'CHANUNAVAIL' THEN 1 ELSE 0 END) AS chanunavail"),
                    DB::raw("SUM(CASE WHEN cdrs.disposition = 'NOANSWER' THEN 1 ELSE 0 END) AS noanswer"),
                    DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'UNAVAILABLE' THEN 1 ELSE 0 END), 0) AS unavailable")
                )
                ->where('call_date', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL ' . $dayCount . ' DAY)'));
            if (in_array($user->roles->first()->slug, ['admin', 'user'])) {
                $query->where('company_id', $user->company_id);
            }
            $query->groupBy(DB::raw('HOUR(call_date)'))
                    ->selectRaw('HOUR(call_date) as time_interval');
            $cdrCounts = $query->get();
            /*
                // Generate hours for the current day (0 to 23)
                $cdrCounts = DB::table(DB::raw("(SELECT 0 AS hour UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL 
                        SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL 
                        SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL 
                        SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL 
                        SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL 
                        SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23) AS hours"))
                    ->leftJoin('cdrs', function($join) use ($user) {
                        $join->on(DB::raw('HOUR(cdrs.call_date)'), '=', 'hours.hour');
                        if (in_array($user->roles->first()->slug, ['admin', 'user'])) {
                            $join->where('cdrs.company_id', '=', $user->company_id);
                        }
                    })
                    ->where(DB::raw('DATE(cdrs.call_date)'), '=', DB::raw('CURDATE()')) // Only today's data
                    ->select(
                        'hours.hour as time_interval',
                        DB::raw('COALESCE(COUNT(cdrs.id), 0) AS total'),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'ANSWER' THEN 1 ELSE 0 END), 0) AS answer"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'BUSY' THEN 1 ELSE 0 END), 0) AS busy"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'CANCEL' THEN 1 ELSE 0 END), 0) AS cancel"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'CHANUNAVAIL' THEN 1 ELSE 0 END), 0) AS chanunavail"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'NOANSWER' THEN 1 ELSE 0 END), 0) AS noanswer"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'UNAVAILABLE' THEN 1 ELSE 0 END), 0) AS unavailable")
                    )
                    ->groupBy('hours.hour')
                    ->orderBy('hours.hour', 'ASC')
                    ->get();
                */
            } else {
                // Generate days for the given $dayCount interval
                $cdrCounts = DB::table(DB::raw('(SELECT CURDATE() - INTERVAL seq DAY as date FROM seq_0_to_99 WHERE seq <= ' . $dayCount . ') AS dates'))
                    ->leftJoin('cdrs', function($join) use ($user) {
                        $join->on(DB::raw('DATE(cdrs.call_date)'), '=', 'dates.date');
                        
                        // Apply filtering by company if needed
                        if (in_array($user->roles->first()->slug, ['admin', 'user'])) {
                            $join->where('cdrs.company_id', '=', $user->company_id);
                        }
                    })
                    ->select(
                        'dates.date as time_interval',
                        DB::raw('COALESCE(COUNT(cdrs.id), 0) AS total'),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'ANSWER' THEN 1 ELSE 0 END), 0) AS answer"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'BUSY' THEN 1 ELSE 0 END), 0) AS busy"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'CANCEL' THEN 1 ELSE 0 END), 0) AS cancel"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'CHANUNAVAIL' THEN 1 ELSE 0 END), 0) AS chanunavail"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'NOANSWER' THEN 1 ELSE 0 END), 0) AS noanswer"),
                        DB::raw("COALESCE(SUM(CASE WHEN cdrs.disposition = 'UNAVAILABLE' THEN 1 ELSE 0 END), 0) AS unavailable")
                    )
                    ->groupBy('dates.date')
                    ->orderBy('dates.date', 'ASC')
                    ->get();
            }
            return $cdrCounts;
      /*      $query = DB::table('cdrs')
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
*/
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
            Log::error('Error occurred in getting CDR Reposrts for deshboard : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
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
        } else {
            return $this->output(false, 'Sorry! You are not authorized.', [], 403);
        }
    }




    /// Reseller Dashboard Section
    public function getAllcompanyListforResellerDashboard(Request $request)
    {
        $user = \Auth::user();

        if ($user->roles->first()->slug == 'reseller') {
            try {
                $companyCounts = Company::selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN parent_id = ' . $user->id . ' THEN 1 ELSE 0 END) as reseller_company
                     ')->first();
                if ($companyCounts->total > 0) {
                    $percentResellerCompany = number_format(($companyCounts->reseller_company / $companyCounts->total) * 100, 2) . '%';
                    return response()->json([
                        'total_company' => $companyCounts->total,
                        'reseller_company' => $companyCounts->reseller_company,
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


    public function getAllcompanyCallCommissionListforResellerDashboard(Request $request)
    {
        $user = \Auth::user();

        if ($user->roles->first()->slug == 'reseller') {
            try {
                $resellercommissionofcalls = ResellerCommissionOfCalls::selectRaw('COUNT(*) as total,
                    SUM( commission_amount ) as commission_amount')->first();
                if ($resellercommissionofcalls->total > 0) {
                    return response()->json([
                        'commission_amount' => $resellercommissionofcalls->commission_amount,
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


    public function getAllcompanyItemsCommissionListforResellerDashboard(Request $request)
    {
        $user = \Auth::user();

        if ($user->roles->first()->slug == 'reseller') {
            try {
                return $resellerCountsItems = DB::table('reseller_commission_of_items')
                    ->leftJoin('invoice_items', 'reseller_commission_of_items.invoice_id', '=', 'invoice_items.invoice_id')
                    ->select(
                        DB::raw('COUNT(*) AS total'),
                        DB::raw("SUM( DISTINCT reseller_commission_of_items.no_of_items ) AS total_number"),
                        DB::raw("SUM(CASE WHEN invoice_items.item_type = 'Extension' THEN 1 ELSE 0 END) AS extension"),
                        DB::raw("SUM(CASE WHEN invoice_items.item_type = 'TFN' THEN 1 ELSE 0 END) AS tfn"),
                        DB::raw("SUM( DISTINCT reseller_commission_of_items.commission_amount ) AS commission_amount"),
                    )->where('reseller_commission_of_items.reseller_id', '=', $user->id)
                    ->toRawSql();


                if ($resellerCountsItems->total > 0) {
                    return response()->json([
                        'total_number' => $resellerCountsItems->total_number,
                        'extension_number' => $resellerCountsItems->extension,
                        'tfn_number' => $resellerCountsItems->tfn,
                        'commission_amount' => $resellerCountsItems->commission_amount
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


    public function getResellerGraphCommissionDashboard(Request $request, $options)
    {
        $user = \Auth::user();
        if ($user->roles->first()->slug == 'reseller') {
            try {
                $days = $options; // assuming $options contains the number of days
                $resellerItemsCommission = DB::table(DB::raw('(SELECT CURDATE() - INTERVAL seq DAY as date FROM seq_0_to_99 WHERE seq <= ' . $days . ') AS dates'))
                ->leftJoin('reseller_commission_of_items as rci', function($join) use ($user) {
                    $join->on(DB::raw('DATE(rci.created_at)'), '=', 'dates.date')
                         ->where('rci.reseller_id', '=', $user->id);
                })
                ->select(
                    'dates.date as time_interval',
                    DB::raw('COALESCE(SUM(rci.commission_amount), 0) as items_commission_amount')
                )
                ->groupBy('dates.date')
                ->orderBy('dates.date', 'ASC')
                ->get();
                /**
                 * Call commision start.
                 */
                $resellerCallsCommission = DB::table(DB::raw('(SELECT CURDATE() - INTERVAL seq DAY as date FROM seq_0_to_99 WHERE seq <= ' . $days . ') AS dates'))
                ->leftJoin('reseller_commission_of_calls as rcc', function($join) use ($user) {
                    $join->on(DB::raw('DATE(rcc.created_at)'), '=', 'dates.date')
                         ->where('rcc.reseller_id', '=', $user->id);
                })
                ->select(
                    'dates.date as time_interval',
                    DB::raw('COALESCE(SUM(rcc.commission_amount), 0) as calls_commission_amount')
                )
                ->groupBy('dates.date')
                ->orderBy('dates.date', 'ASC')
                ->get();

               /* $query = DB::table('reseller_commission_of_items')                   
                    ->select(
                        //DB::raw('COUNT(id) AS total'),
                        DB::raw("SUM(commission_amount) AS items_commission_amount")  
                    )
                    ->where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL ' . $options . ' DAY)'))
                    ->where('reseller_id', '=', $user->id);
               
                $query->groupBy(DB::raw('DATE(created_at)'))
                        ->selectRaw('DATE(created_at) as time_interval');
                $resellerItemsCommission = $query->get();
                /**
                 * Call commision start.
                 */
               /* $query = DB::table('reseller_commission_of_calls')                   
                    ->select(
                        //DB::raw('COUNT(id) AS total'),
                        DB::raw("SUM(commission_amount) AS calls_commission_amount")  
                    )
                    ->where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL ' . $options . ' DAY)'))
                    ->where('reseller_id', '=', $user->id);
               
                $query->groupBy(DB::raw('DATE(created_at)'))
                        ->selectRaw('DATE(created_at) as time_interval');
                $resellerCallsCommission = $query->get();
                */
                return response()->json([
                    'resellerItemsCommission' => $resellerItemsCommission,
                    'resellerCallsCommission' => $resellerCallsCommission,
                ]);

            } catch (\Exception $e) {
                Log::error('Error fetching reseller item commission data: ' . $e->getMessage());
                return $this->output(false, 'An error occurred while fetching data.', [], 500);
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }
}
