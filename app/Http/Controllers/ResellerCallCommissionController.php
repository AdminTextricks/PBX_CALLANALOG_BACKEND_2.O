<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use App\Models\ResellerCallCommission;
use Validator;
use Carbon\Carbon;
class ResellerCallCommissionController extends Controller
{
    public function __construct(){

    }

    public function addResellerCallCommission(Request $request)
    {
		try {
			DB::beginTransaction(); 
			$validator = Validator::make($request->all(), [
                'reseller_id'   => 'required|numeric|exists:users,id', 
				'country_id'    => 'required|numeric|exists:countries,id', 
				'company_id'    => 'required|numeric|exists:companies,id',
				'inbound_call_commission'   => 'required',
				'outbound_call_commission'  => 'required',
			],[
                'country_id'    => 'The country field is required.',
                'company_id'    => 'The company field is required.',
            ]);
			if ($validator->fails()){
				return $this->output(false, $validator->errors()->first(), [], 409);
			}
			
			$user = \Auth::user();
			if(!is_null($user)){
				$ResellerCallCommission = ResellerCallCommission::where('reseller_id', $request->reseller_id)
							->where('country_id', $request->country_id)
							->where('company_id', $request->company_id)
							->first();
				if(!$ResellerCallCommission){
					$ResellerCallCommission = ResellerCallCommission::create([
                            'reseller_id'   => $request->reseller_id,
							'country_id'    => $request->country_id,
							'company_id'    => $request->company_id,						
							'inbound_call_commission'   => $request->inbound_call_commission,
							'outbound_call_commission'	=> $request->outbound_call_commission,	
						]);
					$response = $ResellerCallCommission->toArray();
					DB::commit();
					return $this->output(true, 'Reseller Call Commission added successfully.', $response);
				}else{
					DB::commit();
					return $this->output(false, 'This Reseller Call Commission already exist with us.');
				}
			}else{
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
            Log::error('Error occurred in Adding Reseller Call Commission : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }


    public function getAllResellerCallCommission(Request $request)
	{
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";
        $user = \Auth::user();
		if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
			$ResellerCallCommission_id = $request->id ?? NULL;
			if ($ResellerCallCommission_id) {
				$data = ResellerCallCommission::select('id','company_id','country_id','reseller_id','inbound_call_commission','outbound_call_commission','status')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
                        ->where('id', $ResellerCallCommission_id)->orderBy('id', 'DESC')->get();
			} else {
                $data = ResellerCallCommission::select('id','company_id','country_id','reseller_id','inbound_call_commission','outbound_call_commission','status')
                        ->with('reseller:id,name,email')
                        ->with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->orderBy('id', 'DESC')
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
			}
		} else {
            $ResellerCallCommission_id = $request->id ?? NULL;
			if ($ResellerCallCommission_id) {
				$data = ResellerCallCommission::with('company:id,company_name,email,mobile')
                    ->with('country:id,country_name')
					->select('id','company_id','country_id','reseller_id','inbound_call_commission','outbound_call_commission','status')
					->where('id', $ResellerCallCommission_id)
					->where('company_id', '=',  $user->company_id)
					->orderBy('id', 'DESC')
					->get();
			} else {
				if ($params != "") {
					$data = ResellerCallCommission::select('id','company_id','country_id','reseller_id','inbound_call_commission','outbound_call_commission','status')
						->with('company:id,company_name,email,mobile')	
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->orderBy('id', 'DESC')
						//->orWhere('did_number', 'LIKE', "%$params%")
						->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
				} else {
					$data = ResellerCallCommission::with('company:id,company_name,email,mobile')
                        ->with('country:id,country_name')
						->where('company_id', '=',  $user->company_id)
						->select('id','company_id','country_id','reseller_id','inbound_call_commission','outbound_call_commission','status')
						->orderBy('id', 'DESC')
						->paginate(
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
}
