<?php

namespace App\Http\Controllers;

use App\Models\ResellerPaymentHistories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
class ResellerPaymentHistoryController extends Controller
{
    
    public function getAllResellerPaymentHistory(Request $request)
    {
        $user  = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params      = $request->params ?? "";
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate) {
            $fromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        }
        if ($toDate) {
            $toDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        }
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) 
        {
            $ResellerPaymentHistories = ResellerPaymentHistories::with('user:id,name,email')
                ->with('company:id,company_name,email')
                ->select('*');

            if ($fromDate) {
                $ResellerPaymentHistories->where('updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $ResellerPaymentHistories->where('updated_at', '<=', $toDate);
            }

            if ($params !== "") {
                $ResellerPaymentHistories->where(function ($query) use ($params) {
                    $query->orWhere('payment_type', 'LIKE', "%$params%")
                        ->orWhere('payment_by', 'LIKE', "%$params%")
                        ->orWhere('item_numbers', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($subQuery) use ($params) {
                            $subQuery->where('company_name', 'LIKE', "%$params%")
                                ->orWhere('email', 'LIKE', "%{$params}%");
                        })
                        ->orWhereHas('user', function ($subQuery) use ($params) {
                            $subQuery->where('name', 'LIKE', "%$params%")
                                ->orWhere('email', 'LIKE', "%{$params}%");
                        });
                });
            }
            $ResellerPaymentHistories = $ResellerPaymentHistories->orderBy('id', 'DESC')
                ->paginate($perPageNo, ['*'], 'page');
        } else {
           
            $ResellerPaymentHistories = ResellerPaymentHistories::with('user:id,name,email')
                ->with('company:id,company_name,email')
                ->select('*')->where('user_id', $user->id);

            if ($fromDate) {
                $ResellerPaymentHistories->where('updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $ResellerPaymentHistories->where('updated_at', '<=', $toDate);
            }

            if ($params !== "") {
                $ResellerPaymentHistories->where(function ($query) use ($params) {
                    $query->orWhere('payment_type', 'LIKE', "%$params%")
                        ->orWhere('payment_by', 'LIKE', "%$params%")
                        ->orWhere('item_numbers', 'LIKE', "%$params%")
                        ->orWhereHas('user', function ($subQuery) use ($params) {
                            $subQuery->where('name', 'LIKE', "%$params%")
                                ->orWhere('email', 'LIKE', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($subQuery) use ($params) {
                            $subQuery->where('company_name', 'LIKE', "%$params%")
                                ->orWhere('email', 'LIKE', "%{$params}%");
                        });
                });
            }
            $ResellerPaymentHistories = $ResellerPaymentHistories->orderBy('id', 'DESC')
                ->paginate($perPageNo, ['*'], 'page');           
        }

        if ($ResellerPaymentHistories->isNotEmpty()) {
            $response = $ResellerPaymentHistories->toArray();
            return $this->output(true, 'Success', $response, 200);
        } else {
            return $this->output(false, 'No Record Found', []);
        }
    }
}
