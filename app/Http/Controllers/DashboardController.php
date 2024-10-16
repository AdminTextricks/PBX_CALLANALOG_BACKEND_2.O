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
        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            try {
                $tfnCounts = DB::table('tfns')
                    ->leftJoin('carts', 'tfns.id', '=', 'carts.item_id')
                    ->select(
                        DB::raw('COUNT(*) AS total'),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '1' AND tfns.company_id = 0 AND carts.item_type = 'TFN' THEN 1 ELSE 0 END) AS reserved"),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '0' AND tfns.company_id = 0 THEN 1 ELSE 0 END) AS free"),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '1' AND tfns.company_id != 0 THEN 1 ELSE 0 END) AS Purchase"),
                        DB::raw("SUM(CASE WHEN tfns.reserved = '1' AND tfns.company_id != 0 AND tfns.status = 1 THEN 1 ELSE 0 END) AS Expired")
                    )
                    ->first();

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
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
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
                        DB::raw("SUM(CASE WHEN extensions.host = 'static' AND extensions.status = 0 THEN 1 ELSE 0 END) AS Expired")
                    )
                    ->first();

                if ($extensionCounts->total > 0) {
                    $percentCartExtensionCounts = number_format(($extensionCounts->cart / $extensionCounts->total) * 100, decimals: 2) . '%';
                    $percentExpiredExtensionCounts = number_format(($extensionCounts->Expired / $extensionCounts->total) * 100, 2) . '%';


                    return response()->json([
                        'total' => $extensionCounts->total,
                        'cart' => $extensionCounts->cart,
                        'expired' => $extensionCounts->Expired,
                        'percentage_cart' => $percentCartExtensionCounts,
                        'percentage_expired' => $percentExpiredExtensionCounts,
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
}
