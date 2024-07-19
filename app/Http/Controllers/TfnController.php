<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\DestinationType;
use App\Models\MainPrice;
use App\Models\RemovedTfn;
use App\Models\ResellerPrice;
use App\Models\RingGroup;
use App\Models\Tfn;
use App\Models\TfnDestination;
use App\Models\User;
use Illuminate\Auth\Events\Validated;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;

class TfnController extends Controller
{
    public function addAdminTfns(Request $request)
    {
        $user = \Auth::user();
        //if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
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
        //if ($request->user()->hasRole('super-admin') || $user->company_id == 0) {
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
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
                //$updateTfns->tfn_number               = $request->tfn_number;
                $updateTfns->tfn_provider             = $request->tfn_provider;
                $updateTfns->tfn_group_id             = $request->tfn_group_id;
                $updateTfns->country_id               = $request->country_id;
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
        $tfnnumbermove = Tfn::find($id);

        if (!$tfnnumbermove) {
            return $this->output(false, 'This TFN Number does not exist with us. Please try again!', [], 200);
        }

        try {
            DB::beginTransaction();

            $removedTfn = RemovedTfn::where('tfn_number', $tfnnumbermove->tfn_number)->first();
            $cartTfn = Cart::where('item_number', $tfnnumbermove->tfn_number)->first();

            if ($removedTfn) {
                DB::rollBack();
                return $this->output(true, 'This TFN Number is already removed!', [], 200);
            }

            RemovedTfn::create([
                'tfn_number' => $tfnnumbermove->tfn_number,
                'country_id' => $tfnnumbermove->country_id,
                'status'     => $tfnnumbermove->status ?? 1,
            ]);

            $tfnnumbermove->forcedelete();

            if ($cartTfn) {
                $cartTfn->delete();
            }

            DB::commit();
            return $this->output(true, 'This TFN Number was removed successfully!', [], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->output(false, 'An error occurred: ' . $e->getMessage(), [], 500);
        }
    }


    public function getAllTfn(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $tfn_id = $request->id ?? NULL;
            if ($tfn_id) {
                $tfngetAll = Tfn::with([
                    'countries:id,country_name,phone_code,currency_symbol',
                    'trunks:id,type,name',
                    'company:id,company_name,email',
                    'tfn_groups:id,tfngroup_name',
                    'main_plans:id,name',
                    'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                    'tfn_destinations.destinationType:id,destination_type'
                ])
                    ->select('*')->where('id', $tfn_id)->withTrashed()->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::select('tfns.id', 'tfns.company_id', 'tfns.assign_by', 'tfns.tfn_number', 'tfns.tfn_provider', 'tfns.tfn_group_id', 'tfns.country_id', 'tfns.activated', 'tfns.reserved')
                        ->with([
                            'countries:id,country_name,phone_code,currency_symbol',
                            'trunks:id,type,name',
                            'company:id,company_name,email',
                            'tfn_groups:id,tfngroup_name',
                            'main_plans:id,name',
                            'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                            'tfn_destinations.destinationType:id,destination_type'
                        ])
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->orWhere('activated', 'LIKE', "%$params%")
                        ->orWhere('reserved', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('trunks', function ($query) use ($params) {
                            $query->where('name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('tfn_destinations.destinationType', function ($query) use ($params) {
                            $query->where('destination_type', 'like', "%{$params}%");
                        })
                        ->orWhereHas('tfn_destinations', function ($query) use ($params) {
                            $query->where('destination_id', 'like', "%{$params}%")
                                ->orWhereHas('queues', function ($subQuery) use ($params) {
                                    $subQuery->where('name', 'like', "%{$params}%");
                                })
                                ->orWhereHas('extensions', function ($subQuery) use ($params) {
                                    $subQuery->where('name', 'like', "%{$params}%");
                                })
                                ->orWhereHas('voice_mail', function ($subQuery) use ($params) {
                                    $subQuery->where('fullname', 'like', "%{$params}%")
                                        ->orWhere('email', 'like', "%{$params}%");
                                })
                                ->orWhereHas('conferences', function ($subQuery) use ($params) {
                                    $subQuery->where('confno', 'like', "%{$params}%");
                                })
                                ->orWhereHas('ringGroups', function ($subQuery) use ($params) {
                                    $subQuery->where('ringno', 'like', "%{$params}%");
                                })
                                ->orWhereHas('ivrs', function ($subQuery) use ($params) {
                                    $subQuery->where('name', 'like', "%{$params}%");
                                });
                        })
                        ->withTrashed()
                        ->paginate($perPageNo, ['*'], 'page');
                } else {
                    $tfngetAll = Tfn::with([
                        'countries:id,country_name,phone_code,currency_symbol',
                        'trunks:id,type,name',
                        'company:id,company_name,email',
                        'tfn_groups:id,tfngroup_name',
                        'main_plans:id,name',
                        'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                        'tfn_destinations.destinationType:id,destination_type'
                    ])
                        ->withTrashed()
                        ->paginate($perPageNo, ['*'], 'page');
                }
            }
        } else {
            $tfn_id = $request->id ?? NULL;
            if ($tfn_id) {
                $tfngetAll = Tfn::with([
                    'countries:id,country_name,phone_code,currency_symbol',
                    'trunks:id,type,name',
                    'company:id,company_name,email',
                    'tfn_groups:id,tfngroup_name',
                    'main_plans:id,name',
                    'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                    'tfn_destinations.destinationType:id,destination_type'
                ])
                    ->where('company_id', '=', $user->company_id)
                    ->where('id', $tfn_id)->withTrashed()->get();
            } else {
                if ($params !== "") {
                    $tfngetAll = Tfn::select('tfns.id', 'tfns.company_id', 'tfns.assign_by', 'tfns.tfn_number', 'tfns.tfn_provider', 'tfns.tfn_group_id', 'tfns.country_id', 'tfns.activated', 'tfns.reserved')
                        ->with([
                            'countries:id,country_name,phone_code,currency_symbol',
                            'trunks:id,type,name',
                            'company:id,company_name,email',
                            'tfn_groups:id,tfngroup_name',
                            'main_plans:id,name',
                            'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                            'tfn_destinations.destinationType:id,destination_type'
                        ])
                        ->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->orWhere('activated', 'LIKE', "%$params%")
                        ->orWhere('reserved', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('trunks', function ($query) use ($params) {
                            $query->where('name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('tfn_destinations.destinationType', function ($query) use ($params) {
                            $query->where('destination_type', 'like', "%{$params}%");
                        })
                        ->orWhereHas('tfn_destinations', function ($query) use ($params) {
                            $query->where('destination_id', 'like', "%{$params}%")
                                ->orWhereHas('queues', function ($subQuery) use ($params) {
                                    $subQuery->where('name', 'like', "%{$params}%");
                                })
                                ->orWhereHas('extensions', function ($subQuery) use ($params) {
                                    $subQuery->where('name', 'like', "%{$params}%");
                                })
                                ->orWhereHas('voice_mail', function ($subQuery) use ($params) {
                                    $subQuery->where('fullname', 'like', "%{$params}%")
                                        ->orWhere('email', 'like', "%{$params}%");
                                })
                                ->orWhereHas('conferences', function ($subQuery) use ($params) {
                                    $subQuery->where('confno', 'like', "%{$params}%");
                                })
                                ->orWhereHas('ringGroups', function ($subQuery) use ($params) {
                                    $subQuery->where('ringno', 'like', "%{$params}%");
                                })
                                ->orWhereHas('ivrs', function ($subQuery) use ($params) {
                                    $subQuery->where('name', 'like', "%{$params}%");
                                });
                        })
                        ->where('company_id', '=', $user->company_id)
                        ->withTrashed()
                        ->paginate($perPageNo, ['*'], 'page');
                } else {
                    $tfngetAll = Tfn::with([
                        'countries:id,country_name,phone_code,currency_symbol',
                        'trunks:id,type,name',
                        'company:id,company_name,email',
                        'tfn_groups:id,tfngroup_name',
                        'main_plans:id,name',
                        'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                        'tfn_destinations.destinationType:id,destination_type'
                    ])
                        ->where('company_id', '=', $user->company_id)
                        ->paginate($perPageNo, ['*'], 'page');
                }
            }
        }

        $tfngetAll->each(function ($tfn) {
            $tfn->tfn_destinations->each(function ($destination) {
                switch ($destination->destination_type_id) {
                    case 1:
                        $destination->load('queues:id,name');
                        break;
                    case 2:
                        $destination->load('extensions:id,name');
                        break;
                    case 3:
                        $destination->load('voice_mail:id,mailbox,fullname,email');
                        break;
                    case 4:
                        // $externalNumber = $destination->getExternalNumber($destination->destination_id);
                        // $destination['External Number'] = $externalNumber;
                        break;
                    case 5:
                        $destination->load('conferences:id,conf_name,confno');
                        break;
                    case 6:
                        $destination->load('ringGroups:id,ringno');
                        break;
                    case 7:
                        // $pbxIP = $destination->getPbxIP($destination->destination_id); 
                        // $destination['PbxIP'] = $pbxIP;
                        break;
                    case 8:
                        $destination->load('ivrs:id,name');
                        break;
                }
            });
        });

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
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $tfngetAll = Tfn::with([
                'countries:id,country_name,phone_code,currency_symbol',
                'trunks:id,type,name',
                'company:id,company_name,email',
                'tfn_groups:id,tfngroup_name',
                'main_plans:id,name',
                'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                'tfn_destinations.destinationType:id,destination_type'
            ])
                // ->where('tfn_destinations.destination_id', '=', 'destination_types.id')
                ->where('tfns.status', "=", 1)
                ->get();
        } else {

            $tfngetAll = Tfn::with([
                'countries:id,country_name,phone_code,currency_symbol',
                'trunks:id,type,name',
                'company:id,company_name,email',
                'tfn_groups:id,tfngroup_name',
                'main_plans:id,name',
                'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
                'tfn_destinations.destinationType:id,destination_type'
            ])
                ->where('company_id', $user->company_id)
                ->where('status', 1)
                ->get();
        }

        $tfngetAll->each(function ($tfn) {
            $tfn->tfn_destinations->each(function ($destination) {
                switch ($destination->destination_type_id) {
                    case 1:
                        $destination->load('queues:id,name');
                        break;
                    case 2:
                        $destination->load('extensions:id,name');
                        break;
                    case 3:
                        $destination->load('voiceMail:id,mailbox,fullname,email');
                        break;
                    case 4:
                        // $externalNumber = $destination->getExternalNumber($destination->destination_id);
                        // $destination['External Number'] = $externalNumber;
                        break;
                    case 5:
                        $destination->load('conferences:id,conf_name,confno');
                        break;
                    case 6:
                        $destination->load('ringGroups:id,ringno');
                        break;
                    case 7:
                        // $pbxIP = $destination->getPbxIP($destination->destination_id); 
                        // $destination['PbxIP'] = $pbxIP;
                        break;
                    case 8:
                        $destination->load('ivrs:id,name');
                        break;
                }
            });
        });
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
    public function assignTfnMain(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|numeric',
            'tfn_number' => 'required|array',
            'tfn_type'   => 'required',
        ], [
            'company_id' => 'The company is required.',
            'tfn_type' => 'Payment Type Field is required.',
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
            $tfn = Tfn::where('tfn_number', $tfn_number)->first();

            if ($tfn && $tfn->company_id != 0) {
                return $this->output(false, "TFN Number ($tfn_number) is already purchased. Please try another TFN number.", [], 409);
            } elseif ($tfn && $tfn->reserved == "1") {
                return $this->output(false, "TFN Number ($tfn_number) is already in Cart. Please try another TFN number.", [], 409);
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
                    return $this->output(false, 'Insufficient balance. Please Add balance in Wallet First.', null, 400);
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

        $tfn = Tfn::where('tfn_number', $tfn_number)->first();

        if ($tfn && $tfn->company_id == 0) {
            if (in_array($tfn->tfn_provider, $inbound_trunk)) {
                $tfn->update([
                    'company_id' => $company->id,
                    'assign_by' => $user->id,
                    // 'plan_id' => $company->plan_id,
                    'activated' => '1',
                    'reserved' => '1',
                    'reserveddate' => date('Y-m-d H:i:s'),
                    'reservedexpirationdate' => NULL,
                    'startingdate' => date('Y-m-d H:i:s'),
                    'expirationdate' => date('Y-m-d H:i:s', strtotime('+29 days')),
                ]);
                Cart::where('item_number', $tfn_number)->delete();
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => "Inbound Trunk Permission not found for this TFN Number ($tfn_number)"];
            }
        } else {
            return ['success' => false, 'message' => "TFN Number ($tfn_number) is already Purchased!!"];
        }
    }


    public function assignDestinationType(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'company_id'              => 'required|numeric',
            'tfn_id'                  => 'required|numeric',
            'destination_type_id'     => 'required',
            'destination_id'          => 'required'
        ], [
            'company_id' => 'Company field is Required',
            'tfn_id' => 'Please Select Tfn First.',
            'destination_type_id' => 'Please Select Destination First',
            'destination_id' => 'Please Select Destination Number First'
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        try {
            DB::beginTransaction();
            $isAdmin = in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'));
            $tfnnumberDataQuery = Tfn::where('id', $request->tfn_id);
            if ($isAdmin) {
                $tfnnumberDataQuery->where('company_id', '!=', 0);
            }

            $tfnnumberData = $tfnnumberDataQuery->first();

            if (!$tfnnumberData) {
                DB::rollBack();
                return $this->output(false, "Tfn Number is not Purchased! Please assign Tfn Number First!", [], 400);
            }

            if ($isAdmin && $tfnnumberData->company_id != $request->company_id) {
                DB::rollBack();
                return $this->output(false, "This Tfn Number does not belongs to the designated company!", [], 400);
            }

            if ($tfnnumberData->country_id != $request->country_id) {
                DB::rollBack();
                return $this->output(false, "This Destination number does not belongs to the designated country!", [], 400);
            }
            if ($tfnnumberData->id == $request->tfn_id) {
                $tfn_destinations = TfnDestination::select("*")->where('tfn_id', '=', $request->tfn_id)->first();
                if (!$tfn_destinations) {
                    $tfn_destinations = TfnDestination::create([
                        'company_id'                => $request->company_id,
                        'tfn_id'                    => $request->tfn_id,
                        'destination_type_id'        => $request->destination_type_id,
                        'destination_id'             => $request->destination_id,
                        'country_id'                 => $request->country_id,
                        'priority'                   => $request->priority,
                    ]);
                    $response = $tfn_destinations->toArray();
                    DB::commit();
                    return $this->output(true, 'Destination Assigned successfully.', $response);
                } else {
                    $tfn_destinations->destination_type_id = $request->destination_type_id;
                    $tfn_destinations->destination_id = $request->destination_id;
                    $tfn_destinations->destination_id = $request->destination_id;
                    $tfn_destinations->priority      = $request->priority;

                    $response = $tfn_destinations->save();
                    DB::commit();
                    return $this->output(true, 'Destination Assigned Successfully!', $response);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->output(false, 'An error occurred while assigning the destination. Please try again!', [], 500);
        }
    }


    public function destinationType(Request $request)
    {
        $user = \Auth::user();
        $destinationtypes = DestinationType::get();
        if ($destinationtypes->isNotEmpty()) {
            return $this->output(true, 'success', $destinationtypes->toArray(), 200);
        } else {
            return $this->output(false, "No price record found!", 200);
        }
    }
    public function getAllActiveTFNByCompanyAndCountry(Request $request, $country_id, $company_id)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $data = Tfn::select('id', 'tfn_number', 'country_id', 'company_id')
                /*->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')*/
                ->where('country_id', $country_id)
                ->where('company_id', $company_id)
                ->where('status', 1)
                ->where('activated', '1')->get();
        } else {
            $data = Tfn::select('id', 'tfn_number', 'country_id', 'company_id')
                /*->with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')*/
                ->where('company_id', '=',  $user->company_id)
                ->where('country_id', $country_id)
                ->where('status', 1)
                ->where('activated', '1')->get();
        }

        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function callScreenAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tfn_id'             => 'required',
            'call_screen_action' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        try {
            DB::beginTransaction();
            $tfnData = Tfn::select('*')->where('id', $request->tfn_id)->first();
            if (!$tfnData) {
                return $this->output(false, 'This Tfn Number is not exist with us. Please try again!.', [], 404);
            } else {
                $tfnData->call_screen_action = $request->call_screen_action;
                if ($tfnData->save()) {
                    DB::commit();
                    $response = $tfnData->toArray();
                    return $this->output(true, "TFN Call Screen Status Updated Successfully!", $response, 200);
                } else {
                    DB::rollBack();
                    return $this->output(false, "Failed to update TFN Call Screen Status.", [], 500);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage(), [], 500);
        }
    }
}
