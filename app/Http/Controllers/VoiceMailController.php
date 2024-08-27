<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\VoiceMail;
use App\Models\Extension;
use Validator;
use Carbon\Carbon;

class VoiceMailController extends Controller
{
    public function __construct(){

    }

    public function addVoiceMail(Request $request)
    {
		try {
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
				'company_id'=> 'required|numeric|exists:companies,id',
				'mailbox'   => 'required|numeric|unique:voice_mails|exists:extensions,name',
				'context'   => 'required|string|max:200',
				'password'  => 'required|string|max:200',
				'fullname'  => 'required|string|max:20',
				'email'     => 'required|email|max:255',
                'dialout'   => 'required|numeric',
			]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$user = \Auth::user();
			if(!is_null($user)){
                $VoiceMail = VoiceMail::create([
                        'company_id'    => $request->company_id,
                        'mailbox'	    => $request->mailbox,
                        'context'	    => $request->context,
                        'password' 	    => $request->password,
                        'fullname' 	    => $request->fullname,
                        'email' 	    => $request->email,
                        'dialout' 	    => $request->dialout,
                    ]);
                if($VoiceMail){
                    Extension::where('name', $request->mailbox)->update([
                        'mailbox' => '1',                       
                        'updated_at' => Carbon::now(),
                    ]);

                    $response = $VoiceMail->toArray();
                    DB::commit();
                    return $this->output(true, 'VoiceMail added successfully.', $response);		
                }else{
                    DB::commit();
				    return $this->output(false, 'Error occurred in Adding VoiceMail.');
                }
			}else{
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Adding VoiceMail : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function getAllVoiceMail(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$VoiceMail_id = $request->id ?? NULL;
			if ($VoiceMail_id) {
				$data = VoiceMail::select()
                        ->with('company:id,company_name,email,mobile')
                        ->where('id', $VoiceMail_id)->orderBy('id', 'DESC')->get();
			} else {
				if ($params != "") {
					$data = VoiceMail::select()
							->with('company:id,company_name,email,mobile')
							->orWhere('mailbox', 'LIKE', "%$params%")
                            ->orWhere('email', 'like', "%{$params}%")
							->orWhereHas('company', function ($query) use ($params) {
								$query->where('company_name', 'like', "%{$params}%");
							})
							->orWhereHas('company', function ($query) use ($params) {
								$query->where('email', 'like', "%{$params}%");
							})
							->orderBy('id', 'DESC')
							->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');				
				}else{
					$data = VoiceMail::select()
							->with('company:id,company_name,email,mobile')
							->orderBy('id', 'DESC')
							->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				}                
			}
		} else {
            $VoiceMail_id = $request->id ?? NULL;
			if ($VoiceMail_id) {
				$data = VoiceMail::with('company:id,company_name,email,mobile')
                    ->select()
					->where('id', $VoiceMail_id)
					->where('company_id', '=',  $user->company_id)
					->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = VoiceMail::with('company:id,company_name,email,mobile')	
                        ->where('company_id', '=',  $user->company_id)
						->where(function($query) use($params) {
							$query->where('mailbox', 'like', "%{$params}%")
                                    ->orWhere('email', 'like', "%{$params}%")
                                    ->orWhereHas('country', function ($query) use ($params) {
                                        $query->where('country_name', 'like', "%{$params}%");
                                    });
						    })
						->orderBy('id', 'DESC')
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = VoiceMail::with('company:id,company_name,email,mobile')                        
                            ->where('company_id', '=',  $user->company_id)
                            ->orderBy('id', 'DESC')
                            ->select()->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
				}
			}			
		}
		if ($data->isNotEmpty()) {
			$dd = $data->toArray();
			unset($dd['links']);
			return $this->output(true, 'Success', $dd, 200);
		} else {
			return $this->output(true, 'No Record Found', []);
		}
	}


    public function getAllVoiceMailByCompany(Request $request, $company_id)
    {
		$user = \Auth::user();
        $Company = Company::find($company_id);		
        if(is_null($Company)){
            DB::commit();
            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 409);
        }
        else
        {
        $data = VoiceMail::select()
                ->with('company:id,company_name,email,mobile')
                ->where('company_id', $company_id)
                ->get();    
        }
		if($data->isNotEmpty()){
			return $this->output(true, 'Success', $data->toArray());
		}else{
			return $this->output(true, 'No Record Found', []);
		}
	}


    public function deleteVoiceMail(Request $request, $id)
    {
        try {  
            DB::beginTransaction();            
            $VoiceMail = VoiceMail::where('id', $id)->first();
            if($VoiceMail){
                $resdelete = $VoiceMail->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in VoiceMail deleting. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'VoiceMail not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in VoiceMail Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

}
