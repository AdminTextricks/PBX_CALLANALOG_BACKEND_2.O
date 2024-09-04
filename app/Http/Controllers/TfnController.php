<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\Country;
use App\Models\DestinationType;
use App\Models\Invoice;
use App\Models\InvoiceItems;
use App\Models\MainPrice;
use App\Models\Payments;
use App\Models\RemovedTfn;
use App\Models\ResellerPrice;
use App\Models\RingGroup;
use App\Models\Tfn;
use App\Models\TfnDestination;
use App\Models\TfnGroups;
use App\Models\Trunk;
use App\Models\User;
use Illuminate\Auth\Events\Validated;
use Validator;
use Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Laravel\Prompts\select;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

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
        $user = \Auth::user();
        $tfnnumbermove = Tfn::find($id);
        if (!$tfnnumbermove) {
            return $this->output(false, 'This TFN Number does not exist with us. Please try again!', [], 200);
        }
        try {
            DB::beginTransaction();
            $removedTfn = RemovedTfn::where('tfn_number', $tfnnumbermove->tfn_number)->first();
            $cartTfn = Cart::where('item_number', $tfnnumbermove->tfn_number)->first();
            // if ($removedTfn) {
            //     DB::rollBack();
            //     return $this->output(true, 'This TFN Number is already removed!', [], 200);
            // }
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $companyID = 0;
            } else {
                $companyID = $user->company_id;
            }
            RemovedTfn::create([
                'tfn_number' => $tfnnumbermove->tfn_number,
                'country_id' => $tfnnumbermove->country_id,
                'deleted_by' => $user->id,
                'company_id' => $companyID,
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
        $perPageNo = $request->get('perpage', 10);
        $params = $request->get('params', "");
        $tfn_id = $request->get('id', null);
        $options = $request->get('options', null);
        $query = Tfn::with([
            'countries:id,country_name,phone_code,currency_symbol',
            'trunks:id,type,name',
            'company:id,company_name,email,balance',
            'tfn_groups:id,tfngroup_name',
            'main_plans:id,name',
            'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
            'tfn_destinations.destinationType:id,destination_type'
        ])

            ->orderBy('id', 'DESC');

        if (in_array($user->roles->first()->slug, ['super-admin', 'support', 'noc'])) {
            $query->withTrashed();
            if ($tfn_id) {
                $query->where('id', $tfn_id);
            }
            if (!empty($params)) {
                $query->where(function ($query) use ($params) {
                    $query->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhere('company_id', 'LIKE', "%$params%")
                        //->orWhere('tfn_provider', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($subQuery) use ($params) {
                            $subQuery->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($subQuery) use ($params) {
                            $subQuery->where('email', 'like', "%{$params}%");
                        })
                        ->orWhereHas('countries', function ($subQuery) use ($params) {
                            $subQuery->where('country_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('trunks', function ($subQuery) use ($params) {
                            $subQuery->where('name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('tfn_destinations', function ($subQuery) use ($params) {
                            $subQuery->whereHas('destinationType', function ($nestedQuery) use ($params) {
                                $nestedQuery->where('destination_type', 'like', "%{$params}%");
                            })
                                ->orWhere('destination_id', 'like', "%{$params}%");
                        });
                });
            }
            if (!empty($options)) {
                if ($options == 5) {
                    $query->where('company_id', '>', 0)->where('reserved', '=', '1')->where('activated', '=', '0')->where('status', '=', 0)->where('expirationdate', '<', Carbon::now());
                } elseif ($options == 1) {
                    $query->where('reserved', '=', '0')->where('activated', '=', '0')->where('status', '=', 1);
                } elseif ($options == 2) {
                    $query->where('company_id', '>', 0)->where('reserved', '=', '1')->where('activated', '=', '1')->where('status', '=', 1);
                } elseif ($options == 3) {
                    $query->where('company_id', '=', 0)->where('reserved', '=', '1')->where('activated', '=', '0')->where('status', '=', 1);
                } elseif ($options == 4) {
                    $query->where('reserved', '=', '1')->whereBetween('expirationdate', [Carbon::now(), Carbon::now()->addDays(3)]);
                }
            }
        } else {
            $query->where('company_id', $user->company_id);

            if ($tfn_id) {
                $query->where('id', $tfn_id);
            }
            if (!empty($params)) {
                $query->where(function ($query) use ($params, $user) {
                    $query->where('tfn_number', 'LIKE', "%$params%")
                        //->orWhere('tfn_type_number', 'LIKE', "%$params%")
                        //->orWhere('tfn_provider', 'LIKE', "%$params%")
                        /* ->orWhereHas('company', function ($subQuery) use ($params) {
                            $subQuery->where('company_name', 'like', "%{$params}%");
                        }) */
                        ->orWhereHas('countries', function ($subQuery) use ($params) {
                            $subQuery->where('country_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('trunks', function ($subQuery) use ($params) {
                            $subQuery->where('name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('tfn_destinations', function ($subQuery) use ($params, $user) {
                            $subQuery->where('company_id', '=', $user->company_id)
                                ->whereHas('destinationType', function ($nestedQuery) use ($params) {
                                    $nestedQuery->where('destination_type', 'like', "%{$params}%");
                                })
                                ->orWhere('destination_id', 'like', "%{$params}%");
                        });
                });
            }
            if (!empty($options)) {
                if ($options == 5) {
                    $query->where('company_id', '>', 0)->where('reserved', '=', '1')->where('activated', '=', '0')->where('status', '=', 0)->where('expirationdate', '<', Carbon::now());
                } elseif ($options == 1) {
                    $query->where('reserved', '=', '0')->where('activated', '=', '0')->where('status', '=', 1);
                } elseif ($options == 2) {
                    $query->where('company_id', '>', 0)->where('reserved', '=', '1')->where('activated', '=', '1')->where('status', '=', 1);
                } elseif ($options == 3) {
                    $query->where('company_id', '=', 0)->where('reserved', '=', '1')->where('activated', '=', '0')->where('status', '=', 1);
                } elseif ($options == 4) {
                    $query->whereBetween('expirationdate', [Carbon::now(), Carbon::now()->addDays(3)]);
                }
            }
        }

        $tfngetAll = $query->paginate($perPageNo);

        // Load additional relationships based on destination type
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
        $perPageNo   = isset($request->perpage) ? $request->perpage : 10;
        $params      = $request->params ?? "";
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
                ->orderBy('id', 'DESC')
                ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
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
                ->orderBy('id', 'DESC')
                ->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
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
            'import_csv' => 'required|file|mimes:csv,xlsx',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $file = $request->file('import_csv');
        $fileExtension = $file->getClientOriginalExtension();

        $dataCSV = ['Status' => 'true', 'Message' => 'File data has been processed successfully.', 'data' => [], 'code' => 200];
        $errors = [];
        $chunkdata = [];
        $chunksize = 25;
        if ($fileExtension === 'csv') {
            $reader = new CsvReader();
        } else {
            $reader = new XlsxReader();
        }

        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            if ($rowIndex === 1) {
                continue;
            }

            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $rowData[] = mb_convert_encoding($cell->getValue(), 'UTF-8', 'auto');
            }

            $chunkdata[] = $rowData;

            if (count($chunkdata) === $chunksize) {
                $dataCSV = $this->getchunkdata($chunkdata);
                $chunkdata = [];
            }
        }

        if (!empty($chunkdata)) {
            $dataCSV = $this->getchunkdata($chunkdata);
        }
        if (!empty($errors)) {
            return $this->output(false, 'Some errors occurred during processing: ' . implode(', ', $errors), 409);
        }

        return $this->output($dataCSV['Status'], $dataCSV['Message'], $dataCSV['data'], $dataCSV['code']);
    }
    public function getchunkdata($chunkdata)
    {
        foreach ($chunkdata as $column) {
            // return count($column);
            if (count($column) < 9) {
                continue;
            }

            foreach ($column as &$value) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
            }

            $tfn_number = trim($column[0]);
            $tfn_provider = trim($column[1]);
            $tfn_group_id = trim($column[2]);
            $country_id = trim($column[3]);
            $countryData = Country::select('*')->where('iso3', $country_id)->first();
            $tfn_providerData = Trunk::select('*')->where('type', "Inbound")->where('name', $tfn_provider)->first();
            $tfn_group_idData = TfnGroups::select('*')->where('tfngroup_name', $tfn_group_id)->first();
            $tfncsv = Tfn::where('tfn_number', $tfn_number)->first();

            if ($tfncsv) {
                return ['Status' => false, 'Message' => 'This TFN number ' . $tfn_number . ' already exists!', 'data' => [], 'code' => 400];
            } else {
                $tfncsv = new Tfn();
            }

            $tfncsv->tfn_number = trim($column[0]);
            $tfncsv->tfn_provider = $tfn_providerData->id;
            $tfncsv->tfn_group_id = $tfn_group_idData->id;
            $tfncsv->country_id = $countryData->id;
            $tfncsv->activated = '0';
            $tfncsv->monthly_rate = trim($column[4]);
            $tfncsv->connection_charge = trim($column[5]);
            $tfncsv->selling_rate = trim($column[6]);
            $tfncsv->aleg_retail_min_duration = trim($column[7]);
            $tfncsv->aleg_billing_block = trim($column[8]);
            $tfncsv->status = 1;
            $response = $tfncsv->save();

            if (!$response) {
                return ['Status' => false, 'Message' => 'Error occurred while processing TFN ' . $tfn_number, 'data' => [], 'code' => 409];
            }
        }
        return ['Status' => true, 'Message' => 'CSV Uploaded successfully', 'data' => [], 'code' => 200];
    }


    public function assignTfnMainOLD(Request $request)
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
                    $result = $this->assignTfnToCompanyOLD($tfn_number, $company, $user);
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
                            $result = $this->assignTfnToCompanyOLD($tfn_number, $company, $user);
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

    private function assignTfnToCompanyOLD($tfn_number, $company, $user)
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


    ///// updated Assign Tfn Api:::::
    public function assignTfnMain(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|numeric',
            'country_id' => 'required|array',
            'tfn_number' => 'required|array',
            'tfn_type'   => 'required',
        ], [
            'company_id.required' => 'The company is required.',
            'tfn_type.required' => 'Payment Type Field is required.',
            'country_id.required' => 'Country Field is required.',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $company = Company::find($request->company_id);
        if (!$company) {
            return $this->output(false, 'Company Not Found. Please try again!', [], 409);
        }
        DB::beginTransaction();
        try {
            $transaction_id = Str::random(10);
            $stripe_charge_id = Str::random(30);
            $transaction_id_pf = $request->tfn_type == "Free" ? "FREE" . $transaction_id : "PAID" . $transaction_id;
            $stripe_charge_id_pf = $request->tfn_type == "Free" ? "FREE" . $stripe_charge_id : "PAID" . $stripe_charge_id;
            $payment_assign_type = $request->tfn_type == "Free" ? "Free" : "Paid";
            $invoice_payments_status = $request->tfn_type == "Free" ? "Free" : "Paid";

            $total_price = 0;
            $item_types = [];
            $prices = [];

            foreach ($request->tfn_number as $key => $tfn_number) {
                $tfn = Tfn::where('tfn_number', $tfn_number)->first();

                if ($tfn && $tfn->company_id != 0) {
                    return $this->output(false, "TFN Number ($tfn_number) is already purchased. Please try another TFN number.", [], 409);
                } elseif ($tfn && $tfn->reserved == "1") {
                    return $this->output(false, "TFN Number ($tfn_number) is already in Cart. Please try another TFN number.", [], 409);
                }

                $reseller_id = '';
                if ($company->parent_id > 1) {
                    $price_for = 'Reseller';
                    $reseller_id = $company->parent_id;
                } else {
                    $price_for = 'Company';
                }

                $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id[$key], $price_for, $reseller_id, 'TFN');

                if ($item_price_arr['Status'] == 'true') {
                    $price = $item_price_arr['TFN_price'];
                    $prices[] = $price;
                    $total_price += $price;
                } else {
                    DB::rollBack();
                    return $this->output(false, $item_price_arr['Message']);
                }

                $item_types[] = 'TFN';
            }

            if ($request->tfn_type == "Free") {
                // Invoice Data
                // $invoice_amount_assign = 0;
                foreach ($request->tfn_number as $tfn_number) {
                    $result = $this->assignTfnToCompany($tfn_number, $company, $user);
                    if (!$result['success']) {
                        DB::rollBack();
                        return $this->output(false, $result['message'], null, 400);
                    }
                }
            } else {
                if ($total_price > 0 && $company->balance > $total_price) {
                    //Company Balance 
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

            $invoicetable_id = DB::table('invoices')->max('id');
            if (!$invoicetable_id) {
                $invoice_id = '#INV/' . date('Y') . '/00001';
            } else {
                $invoice_id = "#INV/" . date('Y') . "/000" . ($invoicetable_id + 1);
            }
            // return $company;
            $createInvoices = Invoice::create([
                'company_id' => $company->id,
                'country_id' => $company->country_id,
                'state_id' => $company->state_id,
                'invoice_id' => $invoice_id,
                'invoice_currency' => 'USD',
                'invoice_subtotal_amount' => $total_price,
                'invoice_amount' => $total_price,
                'payment_status' => $invoice_payments_status,

            ]);
            $itemsData = [];
            foreach ($request->tfn_number as $key => $tfn_number) {
                $ind_data = InvoiceItems::create([
                    'country_id' => $request->country_id[$key],
                    'invoice_id' => $createInvoices->id,
                    'item_type'  => 'TFN',
                    'item_number' => $tfn_number,
                    'item_price' => $prices[$key],
                ]);
                if ($ind_data) {
                    $itemsData[] = $ind_data->toArray();
                }
            }

            if (!empty($itemsData)) {
                $user_data = User::select('*')->where('company_id', $company->id)->first();
                if ($company->parent_id > 1 && $request->tfn_type == "Paid") {
                    $this->ResellerCommissionCalculate($user_data, $itemsData, $createInvoices->id, $total_price);
                }
            }
            $payments = Payments::create([
                'company_id' => $company->id,
                'invoice_id'  => $createInvoices->id,
                'ip_address' => $request->ip(),
                'invoice_number'  => $createInvoices->invoice_id,
                'order_id'        => $createInvoices->invoice_id . '-UID-' . $company->id,
                'item_numbers'    => implode(',', $request->tfn_number),
                'payment_type'    => $payment_assign_type,
                'payment_currency' => 'USD',
                'payment_price' => $total_price,
                'stripe_charge_id' => $stripe_charge_id_pf,
                'transaction_id'  => $transaction_id_pf,
                'status' => 1,
            ]);

            $this->pdfmailSend($company, $request->tfn_number, $total_price, $createInvoices->id, $createInvoices->invoice_id, $item_types);

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
            ->where('reserved', '0')
            ->first();

        if ($tfn) {
            if (in_array($tfn->tfn_provider, $inbound_trunk)) {
                $tfn->update([
                    'company_id' => $company->id,
                    'assign_by' => $user->id,
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
            return ['success' => false, 'message' => "TFN Number ($tfn_number) is already Assigned!!"];
        }
    }


    public function assignTfnMainRenew(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|numeric',
            'country_id' => 'required|array',
            'tfn_number' => 'required|array',
            'tfn_type'   => 'required',
        ], [
            'company_id.required' => 'The company is required.',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $company = Company::find($request->company_id);
        if (!$company) {
            return $this->output(false, 'Company Not Found. Please try again!', [], 409);
        }
        DB::beginTransaction();
        try {
            $transaction_id = Str::random(10);
            $stripe_charge_id = Str::random(30);
            $transaction_id_pf = $request->tfn_type == "Free" ? "FREE" . $transaction_id : "PAID" . $transaction_id;
            $stripe_charge_id_pf = $request->tfn_type == "Free" ? "FREE" . $stripe_charge_id : "PAID" . $stripe_charge_id;
            $payment_assign_type = $request->tfn_type == "Free" ? "Free" : "Paid";
            $invoice_payments_status = $request->tfn_type == "Free" ? "Free" : "Paid";

            $total_price = 0;
            $item_types = [];
            $prices = [];

            foreach ($request->tfn_number as $key => $tfn_number) {
                $tfn = Tfn::where('tfn_number', $tfn_number)->first();

                if ($tfn && $tfn->company_id == 0 && $tfn->reserved == 0) {
                    return $this->output(false, "TFN Number ($tfn_number) is already purchased. Please try another TFN number.", [], 409);
                } elseif ($tfn->company_id != $request->company_id) {
                    return $this->output(false, "Company information does not match the provided TFN Number ($tfn_number).", [], 409);
                }

                $reseller_id = '';
                if ($company->parent_id > 1) {
                    $price_for = 'Reseller';
                    $reseller_id = $company->parent_id;
                } else {
                    $price_for = 'Company';
                }
                $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id[$key], $price_for, $reseller_id, 'TFN');

                if ($item_price_arr['Status'] == 'true') {
                    $price = $item_price_arr['TFN_price'];
                    $prices[] = $price;
                    $total_price += $price;
                } else {
                    DB::rollBack();
                    return $this->output(false, $item_price_arr['Message']);
                }

                $item_types[] = 'TFN';
            }

            if ($request->tfn_type == "Free") {
                // Invoice Data
                // $invoice_amount_assign = 0;
                foreach ($request->tfn_number as $tfn_number) {
                    $result = $this->assignTfnToCompanyRenew($tfn_number, $company, $user);
                    if (!$result['success']) {
                        DB::rollBack();
                        return $this->output(false, $result['message'], null, 400);
                    }
                }
            } else {
                if ($total_price > 0 && $company->balance > $total_price) {
                    //Company Balance 
                    $company->balance -= $total_price;
                    if ($company->save()) {
                        foreach ($request->tfn_number as $tfn_number) {
                            $result = $this->assignTfnToCompanyRenew($tfn_number, $company, $user);
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

            $invoicetable_id = DB::table('invoices')->max('id');
            if (!$invoicetable_id) {
                $invoice_id = '#INV/' . date('Y') . '/00001';
            } else {
                $invoice_id = "#INV/" . date('Y') . "/000" . ($invoicetable_id + 1);
            }
            // return $company;
            $createInvoices = Invoice::create([
                'company_id' => $company->id,
                'country_id' => $company->country_id,
                'state_id' => $company->state_id,
                'invoice_id' => $invoice_id,
                'invoice_currency' => 'USD',
                'invoice_subtotal_amount' => $total_price,
                'invoice_amount' => $total_price,
                'payment_status' => $invoice_payments_status,

            ]);
            $itemsData = [];
            foreach ($request->tfn_number as $key => $tfn_number) {
                $ind_data = InvoiceItems::create([
                    'country_id' => $request->country_id[$key],
                    'invoice_id' => $createInvoices->id,
                    'item_type'  => 'TFN',
                    'item_number' => $tfn_number,
                    'item_price' => $prices[$key],
                ]);
                if ($ind_data) {
                    $itemsData[] = $ind_data->toArray();
                }
            }

            if (!empty($itemsData)) {
                $user_data = User::select('*')->where('company_id', $company->id)->first();
                if ($company->parent_id > 1 && $request->tfn_type == "Paid") {
                    $this->ResellerCommissionCalculate($user_data, $itemsData, $createInvoices->id, $total_price);
                }
            }
            $payments = Payments::create([
                'company_id' => $company->id,
                'invoice_id'  => $createInvoices->id,
                'ip_address' => $request->ip(),
                'invoice_number'  => $createInvoices->invoice_id,
                'order_id'        => $createInvoices->invoice_id . '-UID-' . $company->id,
                'item_numbers'    => implode(',', $request->tfn_number),
                'payment_type'    => $payment_assign_type,
                'payment_currency' => 'USD',
                'payment_price' => $total_price,
                'stripe_charge_id' => $stripe_charge_id_pf,
                'transaction_id'  => $transaction_id_pf,
                'status' => 1,
            ]);

            $this->pdfmailSend($company, $request->tfn_number, $total_price, $createInvoices->id, $createInvoices->invoice_id, $item_types);

            DB::commit();
            return $this->output(true, 'TFN numbers have been successfully renewed..', null, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->output(false, 'An error occurred. Please try again.', null, 500);
        }
    }

    private function assignTfnToCompanyRenew($tfn_number, $company, $user)
    {
        $inbound_trunk = explode(',', $company->inbound_permission);

        $tfn = Tfn::where('tfn_number', $tfn_number)
            ->where('company_id', '>', 0)
            ->where('reserved', '1')
            ->first();


        $currentDate = Carbon::now();
        $targetDate = Carbon::parse($tfn->expirationdate);
        $daysDifference = $currentDate->diffInDays($targetDate, false);

        if ($tfn) {
            if ($daysDifference <= 3 && $daysDifference >= 1) {
                $newDate = date('Y-m-d H:i:s', strtotime('+' . (29 + $daysDifference) . ' days'));
            } elseif ($daysDifference > 3) {
                $newDate = date('Y-m-d H:i:s', strtotime('+' . (29 + $daysDifference) . ' days'));
            } else {
                $newDate = date('Y-m-d H:i:s', strtotime('+29 days'));
            }
            if (in_array($tfn->tfn_provider, $inbound_trunk)) {
                $tfn = $tfn->update([
                    'company_id'     => $company->id,
                    'assign_by'      => $user->id,
                    'activated'      => '1',
                    'startingdate'   => date('Y-m-d H:i:s'),
                    'expirationdate' => $newDate,
                    'status'         => 1,
                ]);
                Cart::where('item_number', $tfn_number)->delete();
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => "Inbound Trunk Permission not found for this TFN Number ($tfn_number)"];
            }
        } else {
            return ['success' => false, 'message' => "TFN Number ($tfn_number) is already Assigned!!"];
        }
    }
    public function pdfmailSend($user, $item_numbers, $price_mail, $invoice_id, $invoice_number, $itemTpyes)
    {
        $email = $user->email;
        $data = [
            'title' => 'Invoice From Callanalog',
            'item_numbers' => $item_numbers,
            'item_types' => $itemTpyes,
            'price' => $price_mail,
            'invoice_number' => $invoice_number
        ];

        if ($invoice_id) {
            try {
                Mail::send('invoice', ['data' => $data], function ($message) use ($data, $email) {
                    $message->to($email)->subject($data['title']);
                });

                $invoice_update_email = Invoice::find($invoice_id);
                if ($invoice_update_email) {
                    $invoice_update_email->email_status = 1;
                    $invoice_update_email->save();
                }
                return $this->output(true, 'Email sent successfully!');
            } catch (\Exception $e) {
                \Log::error('Error sending email: ' . $e->getMessage());
                return $this->output(false, 'Error occurred while sending the email.');
            }
        } else {
            return $this->output(false, 'Error occurred in Invoice creation. The PDF file does not exist or the path is incorrect.');
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
                ->where('activated', '1')
                ->orderBy('id', 'DESC')->get();
        } else {
            $data = Tfn::select('id', 'tfn_number', 'country_id', 'company_id')
                /*->with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')*/
                ->where('company_id', '=',  $user->company_id)
                ->where('country_id', $country_id)
                ->where('status', 1)
                ->where('activated', '1')
                ->orderBy('id', 'DESC')->get();
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
                    return $this->output(true, "TFN Dial Status Updated Successfully!", $response, 200);
                } else {
                    DB::rollBack();
                    return $this->output(false, "Failed to update TFN Dial Status.", [], 500);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage(), [], 500);
        }
    }

    public function getAllTfnOrByCompany(Request $request)
    {
        //dd('dsfcdsfadf');
        $query = Tfn::select('id', 'tfn_number')
            ->where('company_id', '<>', 0)
            ->where('company_id', '<>', '')
            ->where('company_id', '<>', null);

        if ($request->get('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        $data = $query->orderBy('id', 'DESC')->get();
        //return $query->ddRawSql();   
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getALLRemovedTfn(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = $request->get('perpage', 10);
        $params = $request->get('params', "");
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate) {
            $fromDate = \Carbon\Carbon::createFromFormat('d-m-y', $fromDate)->startOfDay();
        }
        if ($toDate) {
            $toDate = \Carbon\Carbon::createFromFormat('d-m-y', $toDate)->endOfDay();
        }
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $query = RemovedTfn::select('*')
                ->with('company:id,company_name,email')
                ->with('countries:id,country_name,phone_code,currency_symbol')
                ->with('users:id,name,email')
                ->where('status', 1)->orderBy('id', 'DESC');
            if ($fromDate) {
                $query->where('updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->where('updated_at', '<=', $toDate);
            }
            if (!empty($params)) {
                $query->where(function ($q) use ($params) {
                    $q->where('tfn_number', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($subQuery) use ($params) {
                            $subQuery->where('company_name', 'like', "%{$params}%")
                                ->orWhere('email', 'like', "%{$params}%");
                        })
                        ->orWhereHas('countries', function ($subQuery) use ($params) {
                            $subQuery->where('country_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('users', function ($subQuery) use ($params) {
                            $subQuery->where('name', 'like', "%{$params}%");
                        });
                });
            }
        } else {
            return $this->output(false, 'Sorry! You are not authorized to add TFN Number.', [], 209);
        }
        $data =  $query->paginate($perPageNo);
        if ($data->isNotEmpty()) {
            return $this->output(true, 'Success', $data->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }
}
