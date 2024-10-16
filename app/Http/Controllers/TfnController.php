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
//use App\Models\TfnAuthentication;
use App\Models\TfnGroups;
use App\Models\TfnImportCsvList;
use App\Models\Trunk;
use App\Models\User;
use Auth;
use Illuminate\Auth\Events\Validated;
use Validator;
use Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
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
                'prefix'                    => 'required|numeric',
                // 'tfn_group_id'              => 'required|numeric',
                'country_id'                => 'required|numeric',
                // 'monthly_rate'              => 'required',
                // 'connection_charge'         => 'required',
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
                            'tfn_number'               => $request->prefix . $request->tfn_number,
                            'tfn_provider'             => $request->tfn_provider,
                            // 'tfn_group_id'             => $request->tfn_group_id,
                            'country_id'               => $request->country_id,
                            // 'monthly_rate'             => $request->monthly_rate,
                            // 'connection_charge'        => $request->connection_charge,
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
                    Log::error('Error occurred in Add Tfn Number   : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
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
                // 'tfn_group_id'              => 'required|numeric',
                'country_id'                => 'required|numeric',
                // 'monthly_rate'              => 'required',
                // 'connection_charge'         => 'required',
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
                // $updateTfns->tfn_group_id             = $request->tfn_group_id;
                $updateTfns->country_id               = $request->country_id;
                // $updateTfns->monthly_rate             = $request->monthly_rate;
                // $updateTfns->connection_charge        = $request->connection_charge;
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
                Log::error('Error occurred in Updating Tfn Number   : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
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
        $AuthUser = Auth::user();
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
                        $subject = 'TFN Deleted'; 
                        $message = 'TFN '.$tfn->tfn_number.' has been deleted by ' .$AuthUser->name. '.';
                        $resReturn = 'TFN has been deleted successfully.';
                    } else {
                        $subject = 'TFN Restore.'; 
                        $message = 'TFN '.$tfn->tfn_number.' has been restoreby by ' .$AuthUser->name. '.'; 
                        $resReturn =  'TFN has been restore successfully.';
                    }

                    /**
                     *  Notification code
                     */
                    $type = 'info';
                    $notifyUserType = ['super-admin', 'support', 'noc'];
                    $notifyUser = array();
                    if($AuthUser->role_id == 6){
                        $notifyUserType[] = 'admin';
                        $notifyUser['admin'] = $AuthUser->company_id;
                    }
                    if($AuthUser->role_id == 4 ){
                        $Company = Company::where('id', $AuthUser->company_id)->first();
                        if($Company->parent_id > 1){
                            $notifyUserType[] = 'reseller';
                            $notifyUser['reseller'] = $Company->parent_id;
                        }                        
                    }

                    $res = $this->addNotification($AuthUser, $subject, $message, $type, $notifyUserType, $notifyUser);
                    if(!$res){
                        Log::error('Notification not created when user role '.$AuthUser->role_id.' update company status.');
                    }
                    /**
                     * End of Notification code
                     */

                    return $this->output(true, $resReturn);
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
            Log::error('Error occurred in Delete or Remove Tfn  : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
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
            // 'tfn_groups:id,tfngroup_name',
            'main_plans:id,name',
            'tfn_destinations:id,company_id,tfn_id,destination_type_id,destination_id,priority',
            'tfn_destinations.destinationType:id,destination_type'
        ]);

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
                            $subQuery->where(function ($q) use ($params) {
                                $q->where('destination_id', 'LIKE', "%$params%")
                                    ->orWhereHas('destinationType', function ($nestedQuery) use ($params) {
                                        $nestedQuery->where('destination_type', 'LIKE', "%$params%");
                                    });
                                $q->orWhere(function ($q2) use ($params) {
                                    $q2->where('destination_type_id', '=', 10) // Time Conditions
                                        ->whereHas('timeConditions', function ($nestedQuery) use ($params) {
                                            $nestedQuery->where('name', 'LIKE', "%$params%")
                                                ->orWhere('time_zone', 'LIKE', "%$params%")
                                                ->orWhere('tc_match_destination_type', 'LIKE', "%$params%")
                                                ->orWhere('tc_match_destination_id', 'LIKE', "%$params%")
                                                ->orWhere('tc_non_match_destination_type', 'LIKE', "%$params%")
                                                ->orWhere('tc_non_match_destination_id', 'LIKE', "%$params%");
                                        });
                                })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 8) // IVR
                                            ->whereHas('ivrs', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('name', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 6) // Ring Groups
                                            ->whereHas('ringGroups', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('ringno', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 5) // Conferences
                                            ->whereHas('conferences', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('confno', 'LIKE', "%$params%")->orWhere('conf_name', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 3) // Voice Mail
                                            ->whereHas('voiceMail', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('mailbox', 'LIKE', "%$params%")->orWhere('fullname', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 2) // Extensions
                                            ->whereHas('extensions', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('name', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 1) // Queues
                                            ->whereHas('queues', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('name', 'LIKE', "%$params%");
                                            });
                                    });
                            });
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
                            $subQuery->where(function ($q) use ($params, $user) {
                                $q->where('destination_id', 'LIKE', "%$params%")->where('company_id', '=', $user->company_id)
                                    ->orWhereHas('destinationType', function ($nestedQuery) use ($params) {
                                        $nestedQuery->where('destination_type', 'LIKE', "%$params%");
                                    });
                                $q->orWhere(function ($q2) use ($params) {
                                    $q2->where('destination_type_id', '=', 10) // Time Conditions
                                        ->whereHas('timeConditions', function ($nestedQuery) use ($params) {
                                            $nestedQuery->where('name', 'LIKE', "%$params%")
                                                ->orWhere('time_zone', 'LIKE', "%$params%")
                                                ->orWhere('tc_match_destination_type', 'LIKE', "%$params%")
                                                ->orWhere('tc_match_destination_id', 'LIKE', "%$params%")
                                                ->orWhere('tc_non_match_destination_type', 'LIKE', "%$params%")
                                                ->orWhere('tc_non_match_destination_id', 'LIKE', "%$params%");
                                        });
                                })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 8) // IVR
                                            ->whereHas('ivrs', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('name', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 6) // Ring Groups
                                            ->whereHas('ringGroups', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('ringno', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 5) // Conferences
                                            ->whereHas('conferences', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('confno', 'LIKE', "%$params%")->orWhere('conf_name', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 3) // Voice Mail
                                            ->whereHas('voiceMail', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('mailbox', 'LIKE', "%$params%")->orWhere('fullname', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 2) // Extensions
                                            ->whereHas('extensions', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('name', 'LIKE', "%$params%");
                                            });
                                    })
                                    ->orWhere(function ($q2) use ($params) {
                                        $q2->where('destination_type_id', '=', 1) // Queues
                                            ->whereHas('queues', function ($nestedQuery) use ($params) {
                                                $nestedQuery->where('name', 'LIKE', "%$params%");
                                            });
                                    });
                            });
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

        $tfngetAll = $query->orderBy('updated_at', 'DESC')->paginate($perPageNo);

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
                    case 9:
                        // $terminate = $destination->terminate($destination->destination_id); 
                        // $destination['Terminate'] = $terminate;
                        break;
                    case 10:
                        $destination->load('timeConditions:id,name,time_zone,tc_match_destination_type,tc_match_destination_id,tc_non_match_destination_type,tc_non_match_destination_id');
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
                // 'tfn_groups:id,tfngroup_name',
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
                // 'tfn_groups:id,tfngroup_name',
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
                    case 9:
                        // $terminate = $destination->terminate($destination->destination_id); 
                        // $destination['Terminate'] = $terminate;
                        break;
                    case 10:
                        $destination->load('timeConditions:id,name,time_zone,tc_match_destination_type,tc_match_destination_id,tc_non_match_destination_type,tc_non_match_destination_id');
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

        try {
            DB::beginTransaction();

            $file = $request->file('import_csv');
            $fileExtension = $file->getClientOriginalExtension();
            $originalFilename = pathinfo($fileExtension, PATHINFO_FILENAME);
            $filename = $file ? $originalFilename . date("Ymdhis") . '.' . $fileExtension : '';
            $chunkdata = [];
            $chunksize = 200;
            $hasData = false;
            $result = ['Status' => true, 'Message' => 'File data has been processed successfully.', 'data' => [], 'code' => 200];

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
                    $value = trim($cell->getValue());
                    if ($value !== null && $value !== '') {
                        $rowData[] = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                }
                if (empty($rowData)) {
                    continue;
                }
                $hasData = true;
                $chunkdata[] = $rowData;

                if (count($chunkdata) === $chunksize) {
                    $result = $this->getchunkdata($chunkdata, $file, $filename);
                    if (!$result['Status']) {
                        DB::rollback();
                        return $this->output($result['Status'], $result['Message'], [], $result['code']);
                    }
                    $chunkdata = [];
                }
            }

            if (!$hasData) {
                return $this->output(false, 'Uploaded file is empty or contains no data.', [], 400);
            }

            if (!empty($chunkdata)) {
                $result = $this->getchunkdata($chunkdata, $file, $filename);
                if (!$result['Status']) {
                    DB::rollback();
                    return $this->output($result['Status'], $result['Message'], [], $result['code']);
                }
            }

            DB::commit();
            return $this->output($result['Status'], $result['Message'], [], $result['code']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in While Uploading CSV or Xlsx: ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, $e->getMessage());
        }
    }


    private function getchunkdata($chunkdata, $file, $filename)
    {
        $user = \Auth::user();

        foreach ($chunkdata as $column) {
            if (count($column) < 6) {
                continue;
            }

            foreach ($column as &$value) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
            }
            if (is_null($column)) {
                return ['Status' => false, 'Message' => 'No Record Found!', 'data' => [], 'code' => 404];
            }

            $tfn_number = trim($column[0]);
            $tfn_provider = trim($column[1]);
            $country_id = trim($column[2]);

            $countryData = Country::select('*')->where('country_name', $country_id)->first();
            if (is_null($countryData)) {
                return ['Status' => false, 'Message' => 'No Country ' . $country_id . ' Record Found!', 'data' => [], 'code' => 404];
            }
            $tfn_providerData = Trunk::select('*')->where('type', "Inbound")->where('name', $tfn_provider)->first();
            if (is_null($tfn_providerData)) {
                return ['Status' => false, 'Message' => 'No Inbound Trunk ' . $tfn_provider . ' Record Found!', 'data' => [], 'code' => 404];
            }
            $tfncsv = Tfn::select('*')->where('tfn_number', $countryData->phone_code . $tfn_number)->first();
            if (!is_null($tfncsv)) {
                Log::info('Duplicate TFN found and skipped: ' . $tfn_number);
                continue;
            }
            $tfncsv = new Tfn();
            $tfncsv->tfn_number = $countryData->phone_code . trim($column[0]);
            $tfncsv->tfn_provider = $tfn_providerData->id;
            $tfncsv->country_id = $countryData->id;
            $tfncsv->activated = '0';
            $tfncsv->selling_rate = trim($column[3]);
            $tfncsv->aleg_retail_min_duration = trim($column[4]);
            $tfncsv->aleg_billing_block = trim($column[5]);
            $tfncsv->status = 1;

            $response = $tfncsv->save();

            if (!$response) {
                return ['Status' => false, 'Message' => 'Error occurred while processing TFN ' . $tfn_number, 'data' => [], 'code' => 409];
            }
        }

        $sanitizedFilename = str_replace(' ', '_', $filename);
        $filePath = public_path('Tfn_uploadData/');
        if (!file_exists($filePath)) {
            mkdir($filePath, 0755, true);
        }
        $fileMoved = $file->move($filePath, $sanitizedFilename);
        if (!$fileMoved) {
            return ['Status' => false, 'Message' => 'File could not be moved to the specified directory.', 'data' => [], 'code' => 500];
        }

        $TfnCsvData = TfnImportCsvList::create([
            'uploaded_by' => $user->id,
            'tfn_import_csv' => $sanitizedFilename,
            'status' => 1
        ]);

        if (!$TfnCsvData) {
            return ['Status' => false, 'Message' => 'Failed to save file information to the database.', 'data' => [], 'code' => 500];
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
                    $value = "Free";
                    $result = $this->assignTfnToCompany($tfn_number, $company, $user, $value);
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
                            $value = "Paid";
                            $result = $this->assignTfnToCompany($tfn_number, $company, $user, $value);
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
                    'item_category' => 'Purchase',
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
                'payment_by'      => 'Super Admin',
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
            Log::error('Error occurred in Assign Tfn Number   : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'An error occurred. Please try again.', null, 500);
        }
    }

    private function assignTfnToCompany($tfn_number, $company, $user, $value)
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
                $insert_tfn_histories = $this->TfnHistories($company->id, $user->id, $tfn_number,  $value, "Assigned By Admin");

                if (!$insert_tfn_histories['Status'] == 'true') {
                    DB::rollback();
                    return $this->output(false, 'Oops Somthing went wrong!!. Failed to insert data into Tfns History Table.', 400);
                }
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
                    $value = "Free";
                    $result = $this->assignTfnToCompanyRenew($tfn_number, $company, $user, $value);
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
                            $value = "Paid";
                            $result = $this->assignTfnToCompanyRenew($tfn_number, $company, $user, $value);
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
                    'item_category' => 'Renew',
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
                'payment_by'      => 'Super Admin',
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
            Log::error('Error occurred in Renew TFN number   : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'An error occurred. Please try again.', null, 500);
        }
    }

    private function assignTfnToCompanyRenew($tfn_number, $company, $user, $value)
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
                $newDate = date('Y-m-d H:i:s', strtotime('+' . (30 + $daysDifference) . ' days'));
                $startDate = date('Y-m-d H:i:s', strtotime('+' . $daysDifference . ' days'));
            } elseif ($daysDifference > 3) {
                $newDate = date('Y-m-d H:i:s', strtotime('+' . (30 + $daysDifference) . ' days'));
                $startDate = date('Y-m-d H:i:s', strtotime('+' . $daysDifference . ' days'));
            } else {
                $newDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                $startDate = date('Y-m-d H:i:s');
            }
            if (in_array($tfn->tfn_provider, $inbound_trunk)) {
                $tfn = $tfn->update([
                    'company_id'     => $company->id,
                    'assign_by'      => $user->id,
                    'activated'      => '1',
                    'startingdate'   => $startDate,
                    'expirationdate' => $newDate,
                    'status'         => 1,
                ]);
                $insert_tfn_histories = $this->TfnHistories($company->id, $user->id, $tfn_number,  $value, "Assigned By Admin");

                if (!$insert_tfn_histories['Status'] == 'true') {
                    DB::rollback();
                    return $this->output(false, 'Oops Somthing went wrong!!. Failed to insert data into Tfns History Table.', 400);
                }
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
            Log::error('Error occurred in Assign Destination  : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
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
            Log::error('Error occurred in Update Dail Tfn Status  : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, $e->getMessage(), [], 500);
        }
    }

    public function getAllTfnOrByCompany(Request $request)
    {
        //dd('dsfcdsfadf');
        $query = Tfn::select('tfn_number as id', 'tfn_number as name')
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
/*
    public function setTfnAuthentication(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'tfn_auth'          => 'required',
            'tfn_id'            => 'required|numeric|exists:tfns,id',
            'authentication_type' => 'required_if:tfn_auth,1',
            'auth_digit'        => 'required_if:authentication_type,1,2',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        try {
            DB::beginTransaction();
            $tfn_auth = $request->get('tfn_auth');
            if ($tfn_auth) {
                $TfnAuthentication = TfnAuthentication::select('*')
                    ->where('tfn_id', $request->tfn_id)
                    ->first();
                if (!$TfnAuthentication) {
                    $TfnAuthentication = TfnAuthentication::create([
                        'tfn_id'                => $request->tfn_id,
                        'authentication_type'   => $request->authentication_type,
                        'auth_digit'            => $request->auth_digit,
                    ]);
                    Tfn::where('id', $request->tfn_id)->update(['tfn_auth' => $tfn_auth]);
                    $response = $TfnAuthentication->toArray();
                    DB::commit();
                    return $this->output(true, 'Tfn Authentication set successfully.', $response);
                } else {
                    $TfnAuthentication->tfn_id      = $request->tfn_id;
                    $TfnAuthentication->authentication_type = $request->authentication_type;
                    $TfnAuthentication->auth_digit  = $request->auth_digit;
                    if ($TfnAuthentication->save()) {
                        Tfn::where('id', $request->tfn_id)->update(['tfn_auth' => $tfn_auth]);
                        DB::commit();
                        $response = $TfnAuthentication->toArray();
                        return $this->output(true, "TFN Authentication Updated Successfully!", $response, 200);
                    } else {
                        DB::rollBack();
                        return $this->output(false, "Failed to update TFN Authentication.", [], 409);
                    }
                }
            } else {
                $TfnAuthentication = TfnAuthentication::where('tfn_id', $request->tfn_id)->delete();
                Tfn::where('id', $request->tfn_id)->update(['tfn_auth' => '0']);
                DB::commit();
                return $this->output(true, "TFN Authentication Updated Successfully!", [], 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->output(false, $e->getMessage(), [], 500);
        }
    }

    public function getTfnAuthentication(Request $request, $tfn_id)
    {
        try {
            $TfnAuthentication = TfnAuthentication::where('tfn_id', $tfn_id)->first();
            if ($TfnAuthentication) {
                $response = $TfnAuthentication->toArray();
                return $this->output(true, 'Success', $response, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in geting TFN Authentication : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
*/
    public function ReplaceTfnNumber(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'tfn_number' => 'required|numeric',
            'replace_tfn_number' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        try {
            DB::beginTransaction();
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $tfn = Tfn::where('tfn_number', $request->tfn_number)->first();
                $Replacetfn = Tfn::where('tfn_number', $request->replace_tfn_number)->first();
                $companyData = Company::where('id', $tfn->company_id)->first();
                if (is_null($tfn)) {
                    DB::rollback();
                    return $this->output(true, 'This Tfn Number ' . $request->tfn_number . ' dose not belongs to us or is currently in process.');
                }
                if (is_null($Replacetfn)) {
                    DB::rollback();
                    return $this->output(true, 'This Tfn Number ' . $request->replace_tfn_number . ' dose not belongs to us or is currently in process.');
                } else {
                    $inbound_trunk = explode(',', $companyData->inbound_permission);
                    if ($Replacetfn->company_id != 0) {
                        DB::rollback();
                        return $this->output(false, "This TFN Number ($request->replace_tfn_number) is already Purchased!!", 400);
                    }
                    if (!in_array($Replacetfn->tfn_provider, $inbound_trunk)) {
                        DB::rollback();
                        return $this->output(false, "Inbound Trunk Permission not found for this TFN Number ($request->replace_tfn_number)", 400);
                    }
                    $replaceTfnData = $Replacetfn->update([
                        'company_id' => $companyData->id,
                        'assign_by' => $user->id,
                        'activated' => '1',
                        'reserved' => '1',
                        'reserveddate' => $tfn->reserveddate,
                        'reservedexpirationdate' => $tfn->reservedexpirationdate,
                        'startingdate' => $tfn->startingdate,
                        'expirationdate' => $tfn->expirationdate,
                    ]);

                    if ($replaceTfnData) {
                        $tfn_destinationData = TfnDestination::where('tfn_id', $tfn->id)->first();
                        if ($tfn_destinationData) {
                            $tfn_destinationData->tfn_id =  $Replacetfn->id;
                            $tfn_destinationData->save();
                        }
                        RemovedTfn::create([
                            'tfn_number' => $tfn->tfn_number,
                            'country_id' => $tfn->country_id,
                            'deleted_by' => $user->id,
                            'company_id' => 0,
                            'status'     => 1,
                        ]);
                        $tfn->forcedelete();
                    }
                    DB::commit();
                    return $this->output(true, "TFN number ($request->replace_tfn_number) Replaced successfully.", [], 200);
                }
            } else {
                DB::rollback();
                return $this->output(false, 'Sorry! You are not authorized.', [], 209);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in Tfn Replacement  : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, $e->getMessage());
        }
    }
    public function getALLCsvUploadedList(Request $request)
    {
        $user = \Auth::user();
        $params = $request->get('params', "");
        $perPageNo = $request->get('perpage', 10);
        try {
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc', 'admin'))) {

                if (!empty($params)) {
                    $query = TfnImportCsvList::with('users:id,name,email')->orderBy('id', 'DESC')
                        ->where(function ($subquery) use ($params) {
                            $subquery->where('tfn_import_csv', 'LIKE', "%{$params}%")
                                ->orWhereHas('users', function ($subQueryMain) use ($params) {
                                    $subQueryMain->where('name', 'LIKE', "%{$params}%")
                                        ->orWhere('email', 'LIKE', "%{$params}%");
                                });
                        });
                } else {
                    $query = TfnImportCsvList::with('users:id,name,email')->orderBy('id', 'DESC');
                }
                $getAllCsvList = $query->paginate($perPageNo);
                if ($getAllCsvList->isNotEmpty()) {
                    $tfngetAllCSV_data = $getAllCsvList->toArray();
                    unset($tfngetAllCSV_data['links']);
                    return $this->output(true, 'Success', $tfngetAllCSV_data, 200);
                } else {
                    return $this->output(true, 'No Record Found', [], 200);
                }
            } else {
                return $this->output(false, 'Sorry! You are not authorized.', [], 403);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in getting Uploaded Tfn CSV List : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function getALLTfnNumberofCompany(Request $request, $company_id)
    {
        $user = \Auth::user();
        try {
            $tfnNumber = Tfn::where('company_id', $company_id)->where('activated', '1')->where('status', 1)->get();
            if ($tfnNumber) {
                $response = $tfnNumber->toArray();
                return $this->output(true, 'Success', $response, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in getting TFN Authentication : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function getAllTfnNumberFreebyCountry(Request $request, $country_id)
    {
        $user = \Auth::user();
        try {
            $tfnNumber = Tfn::where('country_id', $country_id)->where('company_id', '=', '0')->where('activated', '=', '0')->where('reserved', '=', '0')->where('status', '=', 1)->get();
            if ($tfnNumber) {
                $response = $tfnNumber->toArray();
                return $this->output(true, 'Success', $response, 200);
            } else {
                return $this->output(true, 'No Record Found', []);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in getting TFN Authentication : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function tfnexpDateUpdate(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'tfn_number' => 'required|numeric',
            'expirationdate' => 'required',
        ], [
            'tfn_number.required' => 'Tfn Number is Required',
            'expirationdate.required' => 'Expiration Date is Required'
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 400);
        }
        try {
            if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
                $dataChangeTfns = Tfn::where('tfn_number', $request->tfn_number)->first();
                if (is_null($dataChangeTfns)) {
                    return $this->output(false, 'This Number does not exist with us. Please try again!', [], 404);
                }
                // $dataChangeTfns->startingdate = Carbon::now();
                $requestExpirationDate =  \Carbon\Carbon::createFromFormat('Y-m-d', $request->expirationdate);
                if ($dataChangeTfns->expirationdate > $requestExpirationDate) {
                    $dataChangeTfns->expirationdate = $requestExpirationDate;
                    $dataChangeTfns->activated = '0';
                    $dataChangeTfns->status = 0;
                } else {
                    $dataChangeTfns->expirationdate = $requestExpirationDate;
                }
                $dateData = $dataChangeTfns->save();
                if ($dateData) {
                    $response = $dataChangeTfns->toArray();
                    return $this->output(true, "Tfn Date update Successfully!.", $response, 200);
                } else {
                    return $this->output(false, "Somthing went wrong. While Tfn Date update", [], 400);
                }
            } else {
                return $this->output(false, 'Sorry! You are not authorized.', [], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in Tfn Number Date change : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
