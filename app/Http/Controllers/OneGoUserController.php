<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Mail;
use Carbon\Carbon;

class OneGoUserController extends Controller
{
    public function manageApplication(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id'	=> 'required|numeric|exists:companies,id',
            'user_id'       => 'required|numeric|exists:users,id',
            'country_id'    => 'required|numeric|exists:countries,id',
            
            
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $Company = Company::find($request->company_id);

        $reseller_id = '';
        if ($user->company->parent_id > 1) {
            $price_for = 'Reseller';
            $reseller_id = $user->company->parent_id;
        } else {
            $price_for = 'Company';
        }
        $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $reseller_id, 'TFN');
        if ($item_price_arr['Status'] == 'true') {        
            $item_price = $item_price_arr['TFN_price'];
            $company = Company::where('id', $user->company->id)->first();
            $inbound_trunk = explode(',', $company->inbound_permission);

            $searchQry = Tfn::select('id', 'tfn_number')
                        ->where('country_id', $country_id)
                        ->where('company_id', 0)
                        ->where('assign_by', 0)
                        ->whereIn('tfn_provider', $inbound_trunk)
                        ->where('activated', '0')
                        ->where('reserved', '0')
                        ->where('status', 1);
            
            if ($type == 'Local') {
                $data = $searchQry->paginate($perPageNo);
            } else {
                $data = $searchQry->where('tfn_number', 'like', "%$starting_digits%")->paginate(
                    $perPage = $perPageNo,
                    $columns = ['*'],
                    $pageName = 'page'
                );
            }
        
        
    }
    
}
