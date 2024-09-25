<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\DB;
use App\Models\Ivr;
use App\Models\IvrOption;
use App\Models\IvrDirectDestination;
use Validator;
use Illuminate\Support\Collection;
class IvrController extends Controller
{
    public function __construct(){

    }

    public function addIvr(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'country_id'    => 'required|numeric|exists:countries,id',
				'company_id'	=> 'required|numeric|exists:companies,id',
                'name'          => 'required|string|max:255|unique:ivrs',
                'description'   => 'nullable|string',
                'ivr_media_id'  => 'required|numeric',
                'timeout'       => 'nullable|string',
                'direct_destination'    => 'required|numeric|in:0,1',
                'destination_type_id'   => 'required_if:direct_destination,1',
                'destination_id'        => 'required_with:destination_type_id',
                'authentication'        => 'required|in:0,1',
                'authentication_type'   => 'required_if:authentication,1',
                'authentication_digit'  => 'required_if:authentication_type,1,2',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            
            $Ivr = Ivr::where('name', $request->name)->first();
            if (!$Ivr) {
                $Ivr = Ivr::create([
                    'company_id'    => $request->company_id,
                    'country_id'    => $request->country_id,
                    'name'          => $request->name,
                    'description'   => $request->description,
                    'ivr_media_id'  => $request->ivr_media_id,
                    'timeout'       => $request->timeout,
                    'direct_destination'    => $request->direct_destination,
                ]);

                if ($request->get('direct_destination')) {
                    $direct_destination = [
                        'ivr_id'                => $Ivr->id,                        
                        'destination_type_id'   => $request->destination_type_id,
                        'destination_id'        => $request->destination_id,
                        'authentication'        => $request->authentication,
                    ];
                    if($request->get('authentication')){
                        //$direct_destination['authentication']        = $request->authentication;
                        $direct_destination['authentication_type']   = $request->authentication_type;
                        $direct_destination['authentication_digit']  = $request->authentication_digit;
                    }
                    IvrDirectDestination::create($direct_destination);
                }                
                
                $response = $Ivr->toArray();                
                return $this->output(true, 'IVR added successfully.', $response, 200);            
            }else{
                return $this->output(false, 'IVR with the same name is already exists. please choose other name.', [], 409);
            }   
        } catch (\Exception $e) {
            Log::error('Error in IVR Media Inserting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }             
    }


    public function changeIVRStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}
		$Ivr = Ivr::find($id);
		if (is_null($Ivr)) {
			return $this->output(false, 'This Ivr not exist with us. Please try again!.', [], 200);
		} else {
			if($Ivr->company_id == $request->user()->company_id || $request->user()->hasRole('super-admin')){
				$Ivr->status = $request->status;
				$IvrRes = $Ivr->save();
				if ($IvrRes) {
					$Ivr = Ivr::where('id', $id)->first();
					$response = $Ivr->toArray();
					return $this->output(true, 'Ivr status updated successfully.', $response, 200);
				} else {
					return $this->output(false, 'Error occurred in Ivr Updating. Please try again!.', [], 409);
				}
			}else{
				return $this->output(false, 'Sorry! You are not authorized to change status.', [], 209);
			}
		}
	}

    public function updateIvr(Request $request, $id)
	{
		$Ivr = Ivr::find($id);
		if (is_null($Ivr)) {
			return $this->output(false, 'This Ivr not exist with us. Please try again!.', [], 404);
		} else {
            $validator = Validator::make($request->all(), [
                'name'          => 'required|string|max:255|unique:ivrs,name,' . $Ivr->id, 
                'country_id'    => 'required|numeric',
                'description'   => 'nullable|string',
                'ivr_media_id'  => 'required|numeric',
                'timeout'       => 'nullable|string',
                'direct_destination'    => 'required|numeric|in:0,1',
                'destination_type_id'   => 'required_if:direct_destination,1',
                'destination_id'        => 'required_with:destination_type_id',
                'authentication'        => 'required|in:0,1',
                'authentication_type'   => 'required_if:authentication,1',
                'authentication_digit'  => 'required_if:authentication_type,1,2',
            ]);
			if ($validator->fails()) {
				return $this->output(false, $validator->errors()->first(), [], 409);
			}else{

                $Ivr->name  = $request->name;
                $Ivr->country_id  = $request->country_id;
                $Ivr->description = $request->description;
                $Ivr->ivr_media_id = $request->ivr_media_id;
                $Ivr->timeout = $request->timeout;
                $Ivr->direct_destination = $request->direct_destination;
                $IvrRes = $Ivr->save();
                if ($IvrRes) {
                    if ($request->get('direct_destination')) {
                        $direct_destination = [
                            'ivr_id'                => $id, 
                            'destination_type_id'   => $request->destination_type_id,
                            'destination_id'        => $request->destination_id,
                            'authentication'        => $request->authentication,
                        ];
                        if($request->get('authentication')){
                            $direct_destination['authentication_type']   = $request->authentication_type;
                            $direct_destination['authentication_digit']  = $request->authentication_digit;
                        }
                        IvrDirectDestination::where('ivr_id', $id)->update($direct_destination);
                    }

                    $Ivr = Ivr::where('id', $id)->first();
                    $response = $Ivr->toArray();
                    return $this->output(true, 'Ivr updated successfully.', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in Ivr Updating. Please try again!.', [], 209);
                }
            }
        }
	} 

    public function getAllActiveIvrList(Request $request)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $Ivr = Ivr::where('status',   1)->get();
        } else {
            $Ivr = Ivr::where('status',   1)->where('company_id', $request->user()->company_id)->get();
        }
        if (is_null($Ivr)) {
            return $this->output(false, 'No Recode found', [], 200);   
        } else {
            $IvrRes = $Ivr->toArray();
            return $this->output(true, 'Success',   $IvrRes, 200);
        }
                        
    }


    public function getAllIvrList(Request $request)
    {
        $user = \Auth::user();
		$perPageNo = isset($request->perpage) ? $request->perpage : 10;
		$params = $request->params ?? "";
        $Ivr_id = $request->id ?? NULL;
        
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$Ivr_id = $request->id ?? NULL;
			if ($Ivr_id) {
				$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->with('IvrMedia:id,name,media_file,file_ext')
                            ->with('ivrDirectDestination')
                            ->with('ivrDirectDestination.AuthenticationType:id,name')
                            ->with('ivrDirectDestination.destination_type:id,destination_type')
				    	    ->select()->where('id', $Ivr_id)->orderBy('id', 'DESC')->get();
			} else {
				if ($params != "") {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->with('IvrMedia:id,name,media_file,file_ext')
                            ->with('ivrDirectDestination')
                            ->with('ivrDirectDestination.AuthenticationType:id,name')
                            ->with('ivrDirectDestination.destination_type:id,destination_type')
                            ->where('name', 'LIKE', "%$params%")
                            ->orWhereHas('IvrMedia', function ($query) use ($params) {
                                $query->where('name', 'like', "%{$params}%");
                            })
		    				->orWhereHas('company', function ($query) use ($params) {
                                $query->where('company_name', 'like', "%{$params}%");
                            })
                            ->orWhereHas('company', function ($query) use ($params) {
                                $query->where('email', 'like', "%{$params}%");
                            })
                            ->orWhereHas('country', function ($query) use ($params) {
                                $query->where('country_name', 'like', "%{$params}%");
                            })
                            ->orderBy('id', 'DESC')
			    			->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->with('IvrMedia:id,name,media_file,file_ext')
                            ->with('ivrDirectDestination')
                            ->with('ivrDirectDestination.AuthenticationType:id,name')
                            ->with('ivrDirectDestination.destination_type:id,destination_type')
					    	->select()->orderBy('id', 'DESC')->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
				}
			}
		} else {
			$Ivr_id = $request->id ?? NULL;
			if ($Ivr_id) {
				$data = Ivr::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->with('IvrMedia:id,name,media_file,file_ext')
                        ->with('ivrDirectDestination')
                        ->with('ivrDirectDestination.AuthenticationType:id,name')
                        ->with('ivrDirectDestination.destination_type:id,destination_type')
					    ->select()->where('id', $Ivr_id)->orderBy('id', 'DESC')->get();
			} else {
				if ($params != "") {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->with('IvrMedia:id,name,media_file,file_ext')
                            ->with('ivrDirectDestination')
                            ->with('ivrDirectDestination.AuthenticationType:id,name')
                            ->with('ivrDirectDestination.destination_type:id,destination_type')
                            ->where('company_id', '=',  $user->company_id)                            
                            ->where(function($query) use($params) {
                                $query->orWhere('name', 'LIKE', "%$params%")
                                ->orWhereHas('IvrMedia', function ($query) use ($params) {
                                    $query->where('name', 'like', "%{$params}%");
                                })
                                ->orWhereHas('country', function ($query) use ($params) {
                                    $query->where('country_name', 'like', "%{$params}%");
                                });
                            })
                            ->orderBy('id', 'DESC')
                            ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = Ivr::with('company:id,company_name,email,mobile')
                            ->with('country:id,country_name')
                            ->with('IvrMedia:id,name,media_file,file_ext')
                            ->with('ivrDirectDestination')
                            ->with('ivrDirectDestination.AuthenticationType:id,name')
                            ->with('ivrDirectDestination.destination_type:id,destination_type')
                            ->where('company_id', '=',  $user->company_id)
                            ->select()->orderBy('id', 'DESC')->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
				}
			}
		}

        $data->each(function ($data) {
            if (isset($data->ivrDirectDestination) && $data->ivrDirectDestination) {
                switch ($data->ivrDirectDestination->destination_type_id) {
                    case 1:
                        $data->ivrDirectDestination->load('queue:id,name as value');
                        break;
                    case 2:
                        $data->ivrDirectDestination->load('extension:id,name as value');
                        break;
                    case 3:
                        $data->ivrDirectDestination->load('voiceMail:id,mailbox as value,email');
                        break;
                    case 5:
                        $data->ivrDirectDestination->load('conference:id,confno as value');
                        break;
                    case 6:
                        $data->ivrDirectDestination->load('ringGroup:id,ringno as value');
                        break;
                    case 8:
                        $data->ivrDirectDestination->load('Ivr:id,name as value');
                        break;
                    case 9:
                        $destina = $this->getDestinationName($data->ivrDirectDestination->destination_type_id);
                        $data->ivrDirectDestination[strtolower(str_replace(' ', '_', $destina))] = array('id' => $data->ivrDirectDestination->destination_type_id, 'value' => $destina);
                        break;
                    case 10:
                        $data->ivrDirectDestination->load('timeCondition:id,name as value');
                        break;
                    default:
                        $destina = $this->getDestinationName($data->ivrDirectDestination->destination_type_id);
                        $data->ivrDirectDestination[strtolower(str_replace(' ', '_', $destina))] = array('id' => $data->ivrDirectDestination->destination_type_id, 'value' => $data->ivrDirectDestination->destination_id);
                }
            }
        }); 
       
		if ($data->isNotEmpty()) {
			$dd = $data->toArray();
			unset($dd['links']);
			return $this->output(true, 'Success', $dd, 200);
		} else {
			return $this->output(true, 'No Record Found', []);
		}
	}

    public function getDestinationName($id)
    {
        $data = DB::table('destination_types')->where('id', $id)->first();
        return $data->destination_type;
    }

    public function getIvrListByCompanyAndCountry(Request $request, $country_id, $company_id)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $Ivr = Ivr::select()
                    ->where('country_id', $country_id)
					->where('company_id', $company_id)
                    ->where('status',   1)->get();
        } else {
            $Ivr = Ivr::where('status',   1)
                    ->where('country_id', $country_id)
                    ->where('company_id', $request->user()->company_id)->get();
        }
        if (is_null($Ivr)) {
            return $this->output(false, 'No Recode found', [], 200);   
        } else {
            $IvrRes = $Ivr->toArray();
            return $this->output(true, 'Success',   $IvrRes, 200);
        }

    }

    public function deleteIvr(Request $request, $id)
    {
        try {  
            DB::beginTransaction();
            $Ivr = Ivr::where('id', $id)->first();
            if($Ivr){
                IvrDirectDestination::where('ivr_id', $id)->delete();
                IvrOption::where('ivr_id', $id)->delete();
				$resdelete = $Ivr->delete();
                if ($resdelete) {                   
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in IVR deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'IVR not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in IVR Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function getDirectDestination(Request $request)
    {
        $data = IvrDirectDestination::select()->with('AuthenticationType:id,name')->orderBy('id', 'DESC')->get();

        if (is_null($data)) {
            return $this->output(false, 'No Recode found', [], 200);   
        } else {
            $authdata = $data->toArray();
            return $this->output(true, 'Success',   $authdata, 200);
        }
    }
}
