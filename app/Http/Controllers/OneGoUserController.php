<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Company;
use App\Models\User;
use App\Models\Tfn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Mail;
use Carbon\Carbon;

class OneGoUserController extends Controller
{
    public function manageApplication(Request $request)
    {
        $perPageNo = isset($request->perpage) ? $request->perpage : 25;
        $validator = Validator::make($request->all(), [
            'company_id'	=> 'required|numeric|exists:companies,id',
            'user_id'       => 'required|numeric|exists:users,id',
            'country_id'    => 'required|numeric|exists:countries,id',
            'user_type'     => 'required|string|in:Reseller,Company',
            'reseller_id'   => 'required_if:user_type,Reseller,',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        //$Company = Company::find($request->company_id);
        $reseller_id = '';        
        $price_for   = $request->user_type;
        $reseller_id = $request->reseller_id;
        $type = 'Toll Free';
        $starting_digits = '';
        $item_price_arr = $this->getItemPrice($request->company_id, $request->country_id, $price_for, $reseller_id, 'TFN');
        if ($item_price_arr['Status'] == 'true') {
            $item_price = $item_price_arr['TFN_price'];
            $company = Company::where('id', $request->company_id)->first();
            $inbound_trunk = explode(',', $company->inbound_permission);

            $tfnNumber = Tfn::select('id', 'tfn_number')
                        ->where('country_id', $request->country_id)
                        ->where('company_id', 0)
                        ->where('assign_by', 0)
                        ->whereIn('tfn_provider', $inbound_trunk)
                        ->where('activated', '0')
                        ->where('reserved', '0')
                        ->where('status', 1)
                        ->limit(1)->first();
                
            if ($tfnNumber->isNotEmpty()) {
                $tfnNumber->reserved = '1';
                $tfnNumber->reserveddate = date('Y-m-d H:i:s');
                $tfnNumber->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));
                if($tfnNumber->save()){
                    
                    return $this->output(true, 'Success', $tfnNumber, 200);
                }else{
                    return $this->output(false, 'Error occurred in reserving TFN for you. Please try after some time.');
                }
                
                
            } else {
                return $this->output(true, 'TFN not available for this country', []);
            }
        }else{
            return $this->output(false, $item_price_arr['Message']);
        }
    }
    
}