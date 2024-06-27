<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\MainPrice;
use App\Models\RemovedTfn;
use App\Models\ResellerPrice;
use App\Models\Tfn;
use App\Models\User;
use Illuminate\Auth\Events\Validated;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\FacadesDB;

use function Laravel\Prompts\select;

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
                try {
                    DB::beginTransaction();

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

                        DB::commit();

                        $response = $addTfns->toArray();
                        return $this->output(true, 'Tfn Number Added Successfully!', $response);
                    } else {
                        DB::rollBack();
                        return $this->output(false, 'This Tfn Number already exists. Please choose another number.');
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    return $this->output(false, 'An error occurred while adding the TFN number. Please try again.', [], 500);
                }
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized to add TFN Number.', [], 209);
        }
    }



    public function updateTfns(Request $request, $id)
    {
        $user = \Auth::user();
        if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
            $updateTfns = Tfn::find($id);

            if (is_null($updateTfns)) {
                return $this->output(false, 'This Number does not exist with us. Please try again!', [], 404);
            }

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
            }

            try {
                DB::beginTransaction();

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

                if ($updateTfns->save()) {
                    DB::commit();
                    $response = $updateTfns->fresh()->toArray();
                    return $this->output(true, 'TFN Number Updated Successfully!', $response, 200);
                } else {
                    DB::rollBack();
                    return $this->output(false, 'Error occurred in updating TFN Number. Please try again!', [], 200);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->output(false, 'An error occurred while updating the TFN number. Please try again!', [], 500);
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized to update the TFN Number.', [], 209);
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
                    ->with('trunks:id,type,name')
                    ->with('company:id,company_name')
                    ->with('tfn_groups:id,tfngroup_name')
                    ->with('main_plans:id,name')
                    ->select('*')->where('id', $tfn_id)->withTrashed()->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->with('trunks:id,type,name')
                        ->with('company:id,company_name')
                        ->with('tfn_groups:id,tfngroup_name')
                        ->with('main_plans:id,name')
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_type_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->select('*')->withTrashed()->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
                } else {
                    $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                        ->with('trunks:id,type,name')
                        ->with('company:id,company_name')
                        ->with('tfn_groups:id,tfngroup_name')
                        ->with('main_plans:id,name')
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
                        ->with('trunks:id,type,name')
                        ->with('company:id,company_name')
                        ->with('tfn_groups:id,tfngroup_name')
                        ->with('main_plans:id,name')
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
                ->with('trunks:id,type,name')
                ->with('company:id,company_name')
                ->with('tfn_groups:id,tfngroup_name')
                ->with('main_plans:id,name')
                ->select('id', 'tfn_number', 'tfn_provider', 'tfn_group_id', 'plan_id', 'company_id', 'country_id')
                ->where('status', "=", 1)
                ->get();
        } else {
            $tfngetAll = Tfn::with('countries:id,country_name,phone_code,currency_symbol')
                ->with('trunks:id,type,name')
                ->with('company:id,company_name')
                ->with('tfn_groups:id,tfngroup_name')
                ->with('main_plans:id,name')
                ->select('id', 'tfn_number', 'tfn_provider', 'tfn_group_id', 'plan_id', 'company_id', 'country_id')
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


    public function assignTfn(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|numeric',
            'tfn_number' => 'required|array',
            'tfn_type'   => 'required',
        ], [
            'company_id' => 'The company is required.',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {
            $company_details = Company::select('*')->where('id', '=', $request->company_id)->first();
            $company_user_details = User::select('*')->where('company_id', '=', $request->company_id)->first();

            if ($company_details) {
                $total_price = 0;

                foreach ($request->tfn_number as $TfnNumber) {
                    $tfnassign = Tfn::select('*')->where('tfn_number', '=', $TfnNumber)->where('company_id', '=', 0)->where('plan_id', '=', 0)->first();

                    if (!$tfnassign) {
                        return $this->output(false, 'This TFN Number ( ' . $TfnNumber . ' ) is already Purchased!!. Please try  again with another Tfn number!', [], 409);
                    }

                    $countryIID = Tfn::select('*')->where('tfn_number', '=', $TfnNumber)->where('company_id', '=', 0)->where('plan_id', '=', 0)->first();
                    if ($company_details->parent_id != "1") {
                        $main_price = MainPrice::select('*')
                            ->where('price_for', 'Reseller')
                            ->where('product', 'TFN')
                            ->where('country_id', $countryIID->country_id)
                            ->where('reseller_id', $company_details->parent_id)
                            ->first();

                        $reseller_price = ResellerPrice::select('*')
                            ->where('product', 'TFN')
                            ->where('country_id', $countryIID->country_id)
                            ->where('company_id', $company_details->id)
                            ->first();

                        if ($main_price && $reseller_price) {
                            if ($reseller_price->commission_type == 'Percentage') {
                                $total_price += $main_price->price + ($main_price->price * $reseller_price->price) / 100;
                            } else {
                                $total_price += $main_price->price + $reseller_price->price;
                            }
                        } else {
                            return $this->output(false, "No Record Found!", 200);
                        }
                    } else {
                        $main_price = MainPrice::select('*')
                            ->where('price_for', 'Company')
                            ->where('product', 'TFN')
                            ->where('country_id', $countryIID->country_id)
                            ->first();

                        if ($main_price) {
                            $total_price += $main_price->price;
                        }
                    }
                }

                if ($request->tfn_type == "Free") {
                    foreach ($request->tfn_number as $TfnNumber) {
                        $tfnassign = Tfn::where('tfn_number', '=', $TfnNumber)->whrer('company_id', '=', 0)->first();
                        if ($tfnassign) {
                            $tfnassign->company_id = $company_details->id;
                            $tfnassign->assign_by = $user->id;
                            $tfnassign->plan_id = $company_details->plan_id;
                            $tfnassign->reserved = 1;
                            $tfnassign->reserveddate = date('Y-m-d H:i:s');
                            $tfnassign->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));
                            $tfnassign->startingdate = date('Y-m-d H:i:s');
                            $tfnassign->expirationdate = date('Y-m-d H:i:s', strtotime('+30 days'));
                            $tfnassign->save();
                        } else {
                            return $this->output(false, 'This TFN Number does not exist with us. Please try again!', [], 409);
                        }
                    }
                } else {
                    if ($total_price > 0 && $company_details->balance > $total_price) {
                        $company_details->balance -= $total_price;
                        $company_detect_balance = $company_details->save();

                        if ($company_detect_balance) {
                            foreach ($request->tfn_number as $TfnNumber) {
                                $tfnassign = Tfn::where('tfn_number', '=', $TfnNumber)->whrer('company_id', '=', 0)->first();
                                if ($tfnassign) {
                                    $tfnassign->company_id = $company_details->id;
                                    $tfnassign->assign_by = $user->id;
                                    $tfnassign->plan_id = $company_details->plan_id;
                                    $tfnassign->reserved = 1;
                                    $tfnassign->reserveddate = date('Y-m-d H:i:s');
                                    $tfnassign->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));
                                    $tfnassign->startingdate = date('Y-m-d H:i:s');
                                    $tfnassign->expirationdate = date('Y-m-d H:i:s', strtotime('+30 days'));
                                    $tfnassign->save();
                                } else {
                                    return $this->output(false, 'TFN Number ' . $TfnNumber . ' not found.', 400);
                                }
                            }
                        } else {
                            return $this->output(false, 'Something Went Wrong! Balance not Credited!!', null, 400);
                        }
                    } else {
                        return $this->output(false, 'You have insufficient balance in your wallet. Please choose the Pay Now option.', null, 400);
                    }
                }

                return $this->output(true, 'TFN Numbers assigned successfully.', null, 200);
            } else {
                return $this->output(false, 'Company Not Found. Please try again!', [], 409);
            }
        }
    }



    public function assignTfnMain(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|numeric',
            'tfn_number' => 'required|array',
            'tfn_type'   => 'required',
        ], [
            'company_id' => 'The company is required.',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $company = Company::find($request->company_id);
        if (!$company) {
            return $this->output(false, 'Company Not Found. Please try again!', [], 409);
        }

        $total_price = 0;
        foreach ($request->tfn_number as $tfn_number) {
            $tfn = Tfn::where('tfn_number', $tfn_number)
                ->where('company_id', 0)
                ->where('plan_id', 0)
                ->where('reserved', 0)
                ->first();

            if (!$tfn) {
                return $this->output(false, "TFN Number ($tfn_number) is already purchased. Please try another TFN number.", [], 409);
            }

            $main_price = MainPrice::where('country_id', $tfn->country_id)
                ->where('price_for', $company->parent_id != 1 ? 'Reseller' : 'Company')
                ->when($company->parent_id != 1, function ($query) use ($company) {
                    return $query->where('reseller_id', $company->parent_id);
                })
                ->first();

            $reseller_price = null;
            if ($company->parent_id != 1) {
                $reseller_price = ResellerPrice::where('product', 'TFN')
                    ->where('country_id', $tfn->country_id)
                    ->where('company_id', $company->id)
                    ->first();
            }

            if ($main_price) {
                $price = $main_price->tfn_price;
                if ($reseller_price) {
                    $price += $reseller_price->commission_type == 'Percentage'
                        ? ($main_price->tfn_price * $reseller_price->price) / 100
                        : $reseller_price->price;
                }
                $total_price += $price;
            } else {
                return $this->output(false, "No price record found!", 200);
            }
        }

        DB::beginTransaction();
        try {
            if ($request->tfn_type == "Free") {
                foreach ($request->tfn_number as $tfn_number) {
                    $result = $this->assignTfnToCompany($tfn_number, $company, $user);
                    if (!$result['success']) {
                        DB::rollBack();
                        return $this->output(false, $result['message'], null, 400);
                    }
                }
            } else {
                if ($total_price > 0 && $company->balance > $total_price) {
                    $company->balance -= $total_price;
                    if ($company->save()) {
                        foreach ($request->tfn_number as $tfn_number) {
                            $result = $this->assignTfnToCompany($tfn_number, $company, $user);
                            if (!$result['success']) {
                                DB::rollBack();
                                return $this->output(false, $result['message'], null, 400);
                            }
                        }
                    } else {
                        DB::rollBack();
                        return $this->output(false, 'Something went wrong! Balance not credited.', null, 400);
                    }
                } else {
                    DB::rollBack();
                    return $this->output(false, 'Insufficient balance. Please choose the Pay Now option.', null, 400);
                }
            }

            DB::commit();
            return $this->output(true, 'TFN numbers assigned successfully.', null, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->output(false, 'An error occurred. Please try again.', null, 500);
        }
    }

    private function assignTfnToCompany($tfn_number, $company, $user)
    {
        $inbound_trunk = explode(',', $company->inbound_permission);

        $tfn = Tfn::where('tfn_number', $tfn_number)
            ->where('company_id', 0)
            ->where('plan_id', 0)
            ->where('reserved', 0)
            ->first();

        if ($tfn) {
            if (in_array($tfn->tfn_provider, $inbound_trunk)) {
                $tfn->update([
                    'company_id' => $company->id,
                    'assign_by' => $user->id,
                    'plan_id' => $company->plan_id,
                    'reserved' => 1,
                    'reserveddate' => date('Y-m-d H:i:s'),
                    'reservedexpirationdate' => date('Y-m-d H:i:s', strtotime('+1 day')),
                    'startingdate' => date('Y-m-d H:i:s'),
                    'expirationdate' => date('Y-m-d H:i:s', strtotime('+30 days')),
                ]);
                Cart::where('item_number', $tfn_number)->delete();
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => "Inbound Trunk Permission not found for this TFN Number ($tfn_number)"];
            }
        } else {
            return ['success' => false, 'message' => "TFN Number ($tfn_number) not found"];
        }
    }
}
