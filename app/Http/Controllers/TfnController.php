<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\RemovedTfn;
use App\Models\Tfn;
use Illuminate\Auth\Events\Validated;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TfnController extends Controller
{
    public function addAdminTfns(Request $request)
    {
        $user = \Auth::user();
        if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
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
        if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
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
        } else {
            return $this->output(false, 'Sorry! You are not authorized to add Tfn Number.', [], 209);
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
        $validator = Validator::make($request->all(), [
            'is_delete' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {
            $tfn = Tfn::withTrashed()->find($id);
            if (is_null($tfn)) {
                return $this->output(false, 'This Tfn is not exist with us. Please try again!.', [], 404);
            } else {
                if ($request->is_delete == '1') {
                    $tfn->status = 0;
                    $tfn_res = $tfn->save();
                    $tfn_result = Tfn::where('id', $id)->delete();
                } else {
                    $tfn_result = Tfn::onlyTrashed()->whereId($id)->restore();
                }
                if ($tfn_result) {
                    if ($request->is_delete == '1') {
                        return $this->output(true, 'TFN has been deleted successfully.');
                    } else {
                        return $this->output(true, 'TFN has been restore successfully.',);
                    }
                } else {
                    return $this->output(false, 'Error occurred in TFN deleting. Please try again!.', [], 209);
                }
            }
        }
    }


    // ReMove TFN from one table to another table (removed_tnfs)
    public function removeTfnfromTable(Request $request, $id)
    {
        $tfnnumbermove = Tfn::select('*')->where('id', $id)->first();

        try {
            if ($tfnnumbermove) {
                $removedTfn = RemovedTfn::select()->where('tfn_number', '=', $tfnnumbermove->tfn_number)->first();
                $cartTfn    = Cart::select()->where('item_number', '=', $tfnnumbermove->tfn_number)->first();
                DB::beginTransaction();
                if (!$removedTfn) {
                    $removedTfn = RemovedTfn::create([
                        'tfn_number' => $tfnnumbermove->tfn_number,
                        'country_id' => $tfnnumbermove->country_id,
                        'status'     => isset($tfnnumbermove->status) ? $tfnnumbermove->status : 1
                    ]);
                    // return $tfnnumbermove->tfn_number;
                    $tfndelete = $tfnnumbermove->delete();
                    if ($cartTfn) {
                        $cartTfn1   = $cartTfn->delete();
                    }
                    DB::commit();
                    return $this->output(true, 'This Tfn Number Removed Successfully!.', 200);
                } else {
                    DB::rollback();
                    return $this->output(true, 'This Tfn Number already removed!.', 200);
                }
            } else {
                DB::rollback();
                return $this->output(false, 'This Tfn Number not exist with us. Please try again!.', [], 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage());
            //throw $e;
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
                    ->with('tfn_groups:id,tfngroup_name')
                    ->with('truks:id,name')
                    ->select('*')->where('id', $tfn_id)->withTrashed()->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->with('tfn_groups:id,tfngroup_name')
                        ->with('truks:id,name')
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_type_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->select('*')->withTrashed()->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->with('tfn_groups:id,tfngroup_name')
                        ->with('truks:id,name')
                        ->select('*')->withTrashed()->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                }
            }
        } else {

            $tfn_id = $request->id ?? NULL;
            if ($tfn_id) {
                $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                    ->select('*')
                    ->where('company_id', '=', $user->company_id)
                    ->where('id', $tfn_id)->withTrashed()->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_type_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->select('*')
                        ->where('company_id', '=', $user->company_id)
                        ->withTrashed()
                        ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->select('*')
                        ->where('company_id', '=', $user->company_id)
                        // ->withTrashed()
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
                ->select('id', 'tfn_number', 'company_id', 'country_id')
                ->where('status', "=", 1)
                ->get();
        } else {
            $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                ->select('id', 'tfn_number', 'company_id', 'country_id')
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

    public function uploadCSVfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'import_csv' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $file = $request->file('import_csv');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle !== FALSE) {
            fgetcsv($handle); // Skip the first row (header)
            $chunksize = 25;

            while (!feof($handle)) {
                $chunkdata = [];

                for ($i = 0; $i < $chunksize; $i++) {
                    $data = fgetcsv($handle);
                    if ($data === false) {
                        break;
                    }
                    $chunkdata[] = $data;
                }

                // Process chunk data
                $this->getchunkdata($chunkdata);
            }

            fclose($handle);
        }

        return $this->output(true, 'CSV data has been processed successfully.', [], 200);
    }

    public function getchunkdata($chunkdata)
    {
        foreach ($chunkdata as $column) {
            if (count($column) < 12) {
                continue; 
            }

            $tfn_number = $column[0];
            $tfn_provider = $column[1];
            $tfn_group_id = $column[2];
            $country_id = $column[3];
            $tfn_type_id = $column[4];
            $activated = $column[5];
            $monthly_rate = $column[6];
            $connection_charge = $column[7];
            $selling_rate = $column[8];
            $aleg_retail_min_duration = $column[9];
            $aleg_billing_block = $column[10];
            $status = $column[11];

            // Create new Tfn record
            $tfncsv = new Tfn();
            $tfncsv->tfn_number = $tfn_number;
            $tfncsv->tfn_provider = $tfn_provider;
            $tfncsv->tfn_group_id = $tfn_group_id;
            $tfncsv->country_id = $country_id;
            $tfncsv->tfn_type_id = $tfn_type_id;
            $tfncsv->activated = $activated;
            $tfncsv->monthly_rate = $monthly_rate;
            $tfncsv->connection_charge = $connection_charge;
            $tfncsv->selling_rate = $selling_rate;
            $tfncsv->aleg_retail_min_duration = $aleg_retail_min_duration;
            $tfncsv->aleg_billing_block = $aleg_billing_block;
            $tfncsv->status = $status;

            $tfncsv->save();
        }
    }
}
