<?php

namespace App\Http\Controllers;

use App\Models\Tfn;
use Validator;
use Illuminate\Http\Request;

class TfnController extends Controller
{
    public function addAdminTfns(Request $request)
    {

        if ($request->user()->hasRole('super-admin')) {
            $validator = Validator::make($request->all(), [
                'tfn_number'                => 'required|numeric|unique:tfns',
                'tfn_provider'              => 'required|numeric',
                'tfn_group_id'              => 'required|numeric',
                'country_id'                => 'required|numeric',
                'monthly_rate'              => 'required',
                'connection_charge'         => 'required',
                'selling_rate'              => 'required',
                'aleg_retail_min_duration'  => 'required|numeric',
                'aleg_billing_block'        => 'required|numeric',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $addTfns = Tfn::where('tfn_number', $request->tfn_number)->first();
                if (!$addTfns) {
                    $addTfns = Tfn::create([
                        'user_id'                  => $request->user()->id,
                        'tfn_number'               => $request->tfn_number,
                        'tfn_provider'             => $request->tfn_provider,
                        'tfn_group_id'             => $request->tfn_group_id,
                        'country_id'               => $request->country_id,
                        'activated'                => 1,
                        'monthly_rate'             => $request->monthly_rate,
                        'connection_charge'        => $request->connection_charge,
                        'selling_rate'             => $request->selling_rate,
                        'aleg_retail_min_duration' => $request->aleg_retail_min_duration,
                        'aleg_billing_block'       => $request->aleg_billing_block,
                        'status'                   => isset($request->status) ? $request->status : 1
                    ]);
                    $response = $addTfns->toArray();
                    return $this->output(true, 'Tfn Number Added Successfully!', $response);
                } else {
                    return $this->output(false, 'This Tfn Number is Already exist with us. Please choose another name to add Tfn Number.');
                }
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized to add Tfn Number.', [], 209);
        }
    }


    public function updateTfns(Request $request, $id)
    {
        $user = \Auth::user();
        $updateTfns = Tfn::find($id);
        // return $updateTfns->id;
        if (is_null($updateTfns)) {
            return $this->output(false, 'This Number not exist with us. Please try again!.', [], 404);
        } else {
            $validator = Validator::make($request->all(), [
                'tfn_number'                => 'required|numeric|unique:tfns,tfn_number,' . $updateTfns->id,
                'tfn_provider'              => 'required|numeric',
                'tfn_group_id'              => 'required|numeric',
                'country_id'                => 'required|numeric',
                'monthly_rate'              => 'required',
                'connection_charge'         => 'required',
                'selling_rate'              => 'required',
                'aleg_retail_min_duration'  => 'required|numeric',
                'aleg_billing_block'        => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $user = $request->user();
                $updateTfns->user_id                   = $user->id;
                $updateTfns->tfn_number               = $request->tfn_number;
                $updateTfns->tfn_provider             = $request->tfn_provider;
                $updateTfns->tfn_group_id             = $request->tfn_group_id;
                $updateTfns->country_id               = $request->country_id;
                $updateTfns->activated                = 1;
                $updateTfns->monthly_rate             = $request->monthly_rate;
                $updateTfns->connection_charge        = $request->connection_charge;
                $updateTfns->selling_rate             = $request->selling_rate;
                $updateTfns->aleg_retail_min_duration = $request->aleg_retail_min_duration;
                $updateTfns->aleg_billing_block       = $request->aleg_billing_block;
                $updateTfnsRes                        = $updateTfns->save();
                if ($updateTfnsRes) {
                    $updateTfns = Tfn::where('id', $id)->first();
                    $response = $updateTfns->toArray();
                    return $this->output(true, 'Tfn Number Updated Successfully!', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in Tfn Number Updating. Please try again!.', [], 200);
                }
            }
        }
    }

    public function changeTfnsStatus(Request $request, $id)
    {
        $tfnsStatus = Tfn::find($id);
        if (is_null($tfnsStatus)) {
            return $this->output(false, 'This Tfn Number not exist with us. Please try again!.', [], 200);
        } else {
            $validator = Validator::make($request->all(), [
                'status'  => 'required'
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $tfnsStatus->status = $request->status;
                $tfnsStatusRes      =  $tfnsStatus->save();
                if ($tfnsStatus) {
                    $tfnsStatus = Tfn::where('id', $id)->first();
                    $response   = $tfnsStatus->toArray();
                    return $this->output(true, 'Tfn Number updated successfully.', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in Tfn Numbe Updating. Please try again!.', [], 200);
                }
            }
        }
    }


    public function deleteTfn(Request $request, $id)
    {
        $tfnnumberdelete = Tfn::where('id', $id)->first();
        if ($tfnnumberdelete) {
            $tfndelete = $tfnnumberdelete->delete();
            if ($tfndelete) {
                return $this->output(true, 'Tfn Number Deleted successfully!', 200);
            } else {
                return $this->output(false, 'Error occurred in Tfn Number removing. Please try again!.', [], 209);
            }
        } else {
            return $this->output(false, 'Tfn Number not exist with us.', [], 409);
        }
    }


    public function getAllTfn(Request $request)
    {
        $user        = \Auth::user();
        $perPageNo   = isset($request->perpage) ? $request->perpage : 10;
        $params      = $request->params ?? "";

        if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
            $tfn_id = $request->id ?? NULL;
            if ($tfn_id) {
                $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                    ->select('*')->where('id', $tfn_id)->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_type_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->select('*')->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->select('*')->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        } else {

            $tfn_id = $request->id ?? NULL;
            if ($tfn_id) {
                $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                    ->select('*')
                    ->where('company_id', '=', $user->company_id)
                    ->where('id', $tfn_id)->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_type_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->select('*')
                        ->where('company_id', '=', $user->company_id)
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->select('*')
                        ->where('company_id', '=', $user->company_id)
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        }

        if ($tfngetAll->isNotEmpty()) {
            $tfngetAll_data = $tfngetAll->toArray();
            unset($tfngetAll_data['links']);
            return $this->output(true, 'Success', $tfngetAll_data, 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }

    public function getAllActiveTfns(Request $request)
    {
        $user = \Auth::user();
        if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
            $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                ->select('id', 'tfn_number', 'company_id', 'user_id', 'country_id')
                ->where('status', "=", 1)
                ->get();
        } else {
            $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                ->select('id', 'tfn_number', 'company_id', 'user_id', 'country_id')
                ->where('company_id', '=',  $user->company_id)
                ->where('status', "=", 1)
                ->get();
        }
        if ($tfngetAll->isNotEmpty()) {
            return $this->output(true, 'Success', $tfngetAll->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }
}
