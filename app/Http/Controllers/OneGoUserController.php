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
            'type'          => 'required',
            'starting_digits'   => 'nullable', 
            
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $Company = Company::find($request->company_id);

        return $PurchaseTfnNumber = app(PurchaseTfnNumberController::class)->searchTfn($request);
        
        
    }
    
}
