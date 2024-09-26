<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RechargeHistory;
use App\Models\ResellerRechargeHistories;
use App\Models\ResellerWallet;
use App\Models\User;
use Str;
use Carbon\Carbon;
use Validator;
use Illuminate\Http\Request;

class RechargeHistoryController extends Controller
{
    public function RechargeHistoryForSuperAdmin(Request $request)
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
                    $getrechargehistorydata = $getrechargehistorydata->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $getrechargehistorydata = RechargeHistory::select('*')->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')->with('user:id,name,email')
                        ->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        } else {
            if ($recharge_id) {
                $getrechargehistorydata = RechargeHistory::with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')
                    ->select('*')->with('user:id,name,email')
                    ->where('id', $recharge_id)->where('company_id', $user->company->id)->first();
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
                    $getrechargehistorydata = RechargeHistory::select('*')->with('company:id,company_name,account_code,email,mobile,billing_address,city,zip')->with('user:id,name,email')->where('company_id', $user->company->id)
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


    public function AddbalanceForResellerBySuperAdmin(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|numeric',
                'amount' => 'required|numeric',
            ],  [
                'user_id.required' => 'User name is Required.',
            ]);

            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $userdataforbalanceupdate = User::where('id', '=', $request->user_id)->first();
                $resellerwellat = ResellerWallet::where('user_id', $request->user_id)->first();
                if (is_null($resellerwellat)) {
                    return $this->output(false, 'Something Went Wrong!! User and Wallet is not exist with us', []);
                }
                if (is_null($userdataforbalanceupdate)) {
                    return $this->output(false, 'Something Went Wrong!! User is not exist with us', []);
                } else {

                    $rechargeHistory_data = ResellerRechargeHistories::create([
                        'user_id'    => $resellerwellat->user_id,
                        'old_balance' => $resellerwellat->balance,
                        'added_balance'   => $request->amount,
                        'total_balance'   => $resellerwellat->balance + $request->amount,
                        'currency'        => 'USD',
                        'transaction_id' => Str::random(30),
                        'recharged_by'    => 'Admin',
                        'status'          => 1,
                    ]);
                    if (!$rechargeHistory_data) {
                        return $this->output(false, 'Failed to Create Recharge History!!.', 400);
                    } else {
                        $resellerwellat->balance += $request->amount;
                        $resuserBalance = $resellerwellat->save();
                        if ($resuserBalance) {
                            return $this->output(true, 'Amount Added successfully!', $rechargeHistory_data, 200);
                        } else {
                            return $this->output(false, 'Error occurred While adding Amount. Please try again!.', [], 200);
                        }
                    }
                }
            }
        } else {
            return $this->output(false, 'Unauthorized action.', [], 403);
        }
    }


    public function getALLresellerRechargeHistory(Request $request)
    {
        $user  = \Auth::user();
        $perPageNo = $request->filled('perpage') ? $request->perpage : 10;
        $params      = $request->params ?? "";
        $res_recharge_id = $request->id ?? NULL;

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate) {
            $fromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        }
        if ($toDate) {
            $toDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        }

        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            if ($res_recharge_id) {
                $getrechargehistorydata = ResellerRechargeHistories::with('user:id,name,email')
                    ->select('*')
                    ->where('id', $res_recharge_id)->first();
            } else {
                $getrechargehistorydata = ResellerRechargeHistories::with('user:id,name,email')
                    ->select('*');

                if ($fromDate) {
                    $getrechargehistorydata->where('updated_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $getrechargehistorydata->where('updated_at', '<=', $toDate);
                }

                if ($params !== "") {
                    $getrechargehistorydata->where(function ($query) use ($params) {
                        $query->orWhere('recharged_by', 'LIKE', "%$params%")->orWhere('payment_type', 'LIKE', "%$params%")
                            ->orWhereHas('user', function ($subQuery) use ($params) {
                                $subQuery->where('name', 'LIKE', "%$params%")
                                    ->orWhere('email', 'LIKE', "%{$params}%");
                            });
                    });
                }

                $getrechargehistorydata = $getrechargehistorydata->orderBy('id', 'DESC')
                    ->paginate($perPageNo, ['*'], 'page');
            }
        } else {
            if ($res_recharge_id) {
                $getrechargehistorydata = ResellerRechargeHistories::select('*')->with('user:id,name,email')
                    ->where('id', $res_recharge_id)->where('user_id', $user->id)->first();
            } else {
                $getrechargehistorydata = ResellerRechargeHistories::with('user:id,name,email')
                    ->select('*')->where('user_id', $user->id);

                if ($fromDate) {
                    $getrechargehistorydata->where('updated_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $getrechargehistorydata->where('updated_at', '<=', $toDate);
                }

                if ($params !== "") {
                    $getrechargehistorydata->where(function ($query) use ($params) {
                        $query->orWhere('recharged_by', 'LIKE', "%$params%")
                            ->orWhereHas('user', function ($subQuery) use ($params) {
                                $subQuery->where('name', 'LIKE', "%$params%")
                                    ->orWhere('email', 'LIKE', "%{$params}%");
                            });
                    });
                }

                $getrechargehistorydata = $getrechargehistorydata->orderBy('id', 'DESC')
                    ->paginate($perPageNo, ['*'], 'page');
            }
        }

        if ($getrechargehistorydata->isNotEmpty()) {
            $response = $getrechargehistorydata->toArray();
            return $this->output(true, 'Success', $response, 200);
        } else {
            return $this->output(false, 'No Record Found', []);
        }
    }
}
