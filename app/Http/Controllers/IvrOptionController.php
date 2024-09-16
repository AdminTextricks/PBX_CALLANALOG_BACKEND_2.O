<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IvrOption;
use App\Models\Ivr;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\DB;
use Validator;

class IvrOptionController extends Controller
{
    public function __construct(){

    }
    
    public function addIvrOption(Request $request)
    {
        try{
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'company_id'        => 'required|numeric|exists:companies,id',
                'ivr_id'            => 'required|numeric|exists:ivrs,id',
                'input_digit'       => 'required|numeric',
                'destination_type_id'  => 'required|string',
                'destination_id'    => 'required|numeric',
                'parent_id'         => 'nullable',
            ]);
            if ($validator->fails()) {
                DB::commit();
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {            
                $IvrOption = IvrOption::where('company_id', $request->company_id)
                            ->where('ivr_id', $request->ivr_id)
                            ->where('input_digit', $request->input_digit);
                            if(isset($request->parent_id)){
                                $IvrOption = $IvrOption->where('parent_id', $request->parent_id);
                            }
                            $IvrOption = $IvrOption->first();
                if (!$IvrOption) {
                    $IvrOption = IvrOption::create([
                        'company_id'   => $request->company_id,
                        'ivr_id'        => $request->ivr_id,
                        'input_digit'   => $request->input_digit,                            
                        'destination_type_id'=> $request->destination_type_id,
                        'destination_id'  => $request->destination_id,
                        'parent_id'       => isset($request->parent_id) ? $request->parent_id : 0,
                    ]);   
                    $response = $IvrOption->toArray();   
                    DB::commit();                 
                    return $this->output(true, 'Ivr Option added successfully.', $response, 200); 
                } else {
                    DB::commit();      
                    return $this->output(false, 'This Ivr Option is already added.');
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in creating IVR Options : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function editIvrOption(Request $request, $id)
    {
        try{
            DB::beginTransaction();
            $IvrOption = IvrOption::find($id);
            if (is_null($IvrOption)) {
                DB::commit(); 
                return $this->output(false, 'This Ivr option not exist with us. Please try again!.', [], 404);
            } else {
                $validator = Validator::make($request->all(), [
                    'company_id'        => 'required|numeric|exists:companies,id',
                    'ivr_id'            => 'required|numeric|exists:ivrs,id',
                    'input_digit'       => 'required|numeric',
                    'destination_type_id'  => 'required|string',
                    'destination_id'    => 'required|numeric',
                ]);
                if ($validator->fails()) {
                    DB::commit();      
                    return $this->output(false, $validator->errors()->first(), [], 409);
                } else {  
                    $IvrOptionExist = IvrOption::where('company_id', $request->company_id)
                                    ->where('ivr_id', $request->ivr_id)
                                    ->where('input_digit', $request->input_digit)
                                    ->first();
                    if (!$IvrOptionExist || $IvrOptionExist->id == $id) {
                        
                        $IvrOption->company_id  = $request->company_id;
                        $IvrOption->ivr_id      = $request->ivr_id;
                        $IvrOption->input_digit = $request->input_digit;
                        $IvrOption->destination_type_id= $request->destination_type_id;
                        $IvrOption->destination_id  = $request->destination_id;
                        $IvrOption->parent_id       = isset($request->parent_id) ? $request->parent_id : 0;
                        $IvrOptionRes = $IvrOption->save();
                        if($IvrOptionRes){
                            $response = $IvrOption->toArray();   
                            DB::commit();                       
                            return $this->output(true, 'Ivr Option added successfully.', $response, 200);
                        }else{
                            DB::commit();
                            return $this->output(false, 'Error occurred in Ivr option updating. Please try again!.', [], 200);
                        } 
                    } else {                   
                        DB::commit();       
                        return $this->output(false, 'This Ivr Option is already assigned.');
                    }
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error occurred in creating IVR Options : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }

    public function removeIvrOption(Request $request, $id)
    {
        $IvrOption = IvrOption::where('id', $id)->first();
       // $Ivr = Ivr::where('id', $IvrOption->ivr_id)->first();
        if($IvrOption){            
            $resdelete = $IvrOption->delete();
            if ($resdelete) {
                return $this->output(true,'Deleted Successfully!!',200);
            } else {
                return $this->output(false, 'Error occurred in Ivr Option removing. Please try again!.', [], 209);
            }            
        }else{
            return $this->output(false,'Ivr Option not exist with us.', [], 409);
        }        
    }

    public function getIvroptionsByCompanyId(Request $request, $company_id){
        $user = \Auth::user();
        $ivrGetall = IvrOption::select('*')
                    ->with('ivr:id,name')
                    ->where('company_id', $company_id)->get();
        $ivrOptionArr = $ivrGetall->toArray();
        /*foreach($ivrOptionArr as $key => $ivrOption){            
            if($ivrOption['destination_type_id'] == 'Ivr'){
                $targetDetails = Ivr::select('name')->where('id', $ivrOption['destination_id'])->get();
            }
            $data = json_decode($targetDetails, true);
            $ivrOptionArr[$key]['destination_name'] = $data[0]['name'];
        }*/
        //return $ivrOptionArr;
        if ($ivrGetall->isNotEmpty()) {
            return $this->output(true, 'success', $ivrOptionArr, 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }

    }
}
