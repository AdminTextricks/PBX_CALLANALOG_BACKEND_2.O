<?php

namespace App\Http\Controllers;

use App\Models\OutboundCallRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class OutboundCallRateController extends Controller
{
    public function __construct(){

    }
    public function addOutboundCallRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tariff_id'     => 'required|numeric|exists:tariffs,id',
            'trunk_id'      => 'required|numeric|exists:trunks,id',
            'country_prefix'=> 'required',
            'selling_rate'  => 'required',
            'init_block'    => 'required',
            'billing_block' => 'required',
            'start_date'    => 'required',
            'stop_date'     => 'required',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();
            /*$OutboundCallRate = OutboundCallRate::where('tariff_id', $request->tariff_id)
                    ->where('trunk_id', $request->trunk_id)->first();        
            if(!$OutboundCallRate){*/
                $OutboundCallRate = OutboundCallRate::create([
                    'tariff_id'     => $request->tariff_id,
                    'trunk_id'      => $request->trunk_id,
                    'country_prefix'=> $request->country_prefix,
                    'selling_rate'  => $request->selling_rate,
                    'init_block'    => $request->init_block,
                    'billing_block' => $request->billing_block,
                    'start_date'    => $request->start_date,
                    'stop_date' 	=> $request->stop_date,
                    'status' 	    => isset($request->status) ? $request->status : 1,
                ]);
                
                $response 	= $OutboundCallRate->toArray();               
                DB::commit();
                return $this->output(true, 'Outbound Call Rate added successfully.', $response);
            /*}else{
                DB::commit();
                return $this->output(false, 'This Outbound Call Rate is already register with us.');
            }*/
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }


    public function getAllOutboundCallRate(Request $request)
    {
        //$user = \Auth::user();
        try { 
            DB::beginTransaction();
            $perPageNo = isset($request->perpage) ? $request->perpage : 10;
            $params = $request->params ?? "";

            $OutboundCallRate_id = $request->id ?? NULL;
            if($OutboundCallRate_id){            
                $OutboundCallRate_data = OutboundCallRate::select('*') 
                                ->with(['tariff','trunk'])
                                ->where('id', $OutboundCallRate_id)->get();
            }else{
                $OutboundCallRate_data = OutboundCallRate::select('*') 
                                        ->with(['tariff','trunk'])
                                    ->paginate(
                                    $perPage = $perPageNo,
                                    $columns = ['*'],
                                    $pageName = 'page'
                                );
            }        

            if ($OutboundCallRate_data->isNotEmpty()) {
                $OutboundCallRate_dd = $OutboundCallRate_data->toArray();
                unset($OutboundCallRate_dd['links']);
                DB::commit();
                return $this->output(true, 'Success', $OutboundCallRate_dd, 200);
            } else {
                DB::commit();
                return $this->output(true, 'No Record Found', []);
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }

    public function getAllActiveOutboundCallRate(Request $request)
    {
        try { 
            DB::beginTransaction();
            //$user = \Auth::user();        
            $OutboundCallRate = OutboundCallRate::select()->where('status', '=', 1)->get();        
            if ($OutboundCallRate->isNotEmpty()) {
                DB::commit();
                return $this->output(true, 'Success', $OutboundCallRate->toArray(), 200);
            } else {
                DB::commit();
                return $this->output(true, 'No Record Found', [], 200);
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }


    public function changeOutboundCallRateStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}
        try { 
            DB::beginTransaction();
            $OutboundCallRate = OutboundCallRate::find($id);
            if (is_null($OutboundCallRate)) {
                DB::commit();
                return $this->output(false, 'This Outbound Call Rate not exist with us. Please try again!.', [], 200);
            } else {			
                $OutboundCallRate->status = $request->status;
                $OutboundCallRateRes = $OutboundCallRate->save();
                if ($OutboundCallRateRes) {
                    $OutboundCallRate = OutboundCallRate::where('id', $id)->first();
                    $response = $OutboundCallRate->toArray();
                    DB::commit();
                    return $this->output(true, 'Outbound Call Rate updated successfully.', $response, 200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Outbound Call Rate Updating. Please try again!.', [], 200);
                }			
            } 
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
	}


    public function updateOutboundCallRate(Request $request, $id)
	{
        try { 
            DB::beginTransaction();
            $OutboundCallRate = OutboundCallRate::find($id);
            if (is_null($OutboundCallRate)) {
                DB::commit();
                return $this->output(false, 'This Outbound Call Rates not exist with us. Please try again!.', [], 404);
            } else {
                $validator = Validator::make($request->all(), [
                    'tariff_id'     => 'required|numeric|exists:tariffs,id',
                    'trunk_id'      => 'required|numeric|exists:trunks,id',
                    'country_prefix'=> 'required',
                    'selling_rate'  => 'required',
                    'init_block'    => 'required',
                    'billing_block' => 'required',
                    'start_date'    => 'required',
                    'stop_date'     => 'required',
                ]);
                if ($validator->fails()){
                    return $this->output(false, $validator->errors()->first(), [], 409);
                }
                /* $checkOutboundCallRate = OutboundCallRate::where('tariff_id', $request->tariff_id)
                                    ->where('trunk_id', $request->trunk_id)
                                    ->where('id', '!=', $id)->first();  
                //$user = $request->user();
                if(!$checkOutboundCallRate){  */
                    $OutboundCallRate->tariff_id        = $request->tariff_id;
                    $OutboundCallRate->trunk_id 	    = $request->trunk_id;
                    $OutboundCallRate->country_prefix	= $request->country_prefix;
                    $OutboundCallRate->selling_rate	    = $request->selling_rate;
                    $OutboundCallRate->init_block       = $request->init_block;
                    $OutboundCallRate->billing_block    = $request->billing_block;
                    $OutboundCallRate->start_date 	    = $request->start_date;			
                    $OutboundCallRate->stop_date 	    = $request->stop_date;		
                    $OutboundCallRateRes 			    = $OutboundCallRate->save();

                    if ($OutboundCallRateRes) {
                        $OutboundCallRate = OutboundCallRate::where('id', $id)->first();
                        $response = $OutboundCallRate->toArray();
                        DB::commit();
                        return $this->output(true, 'Outbound Call Rate updated successfully.', $response, 200);
                    } else {
                        DB::commit();
                        return $this->output(false, 'Error occurred in Outbound Call Rate Updating. Please try again!.', [], 200);
                    }
                /* }else{
                    DB::commit();
                    return $this->output(false, 'This Outbound Call Rate is already register with us.');
                } */
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
	}

    public function deleteOutboundCallRate(Request $request, $id){
        try {  
            DB::beginTransaction();            
            $Trunk = OutboundCallRate::where('id', $id)->first();
            if($Trunk){
                $resdelete = $Trunk->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in Outbound Call Rate removing. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'Outbound Call Rate not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
