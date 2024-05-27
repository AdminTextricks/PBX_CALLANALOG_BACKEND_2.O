<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class ExtensionController extends Controller
{
    public function __construct(){

    }

    public function genrateExtensions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'extension_digit'    => 'nullable|numeric|between:5,10',
            'extension_number'   => 'required|numeric|max:50',
        ],[
            'extension_number'  => 'Total number of extention required.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        try { 
            DB::beginTransaction();
            $extension_digit = !empty($request->extension_digit) ? $request->extension_digit : 7;
            $extension_number = $request->extension_number;
            $number_array = $final_array =  array();
            $number_array[] = '10230605';
            $number_array[] = '44092960';
            while(1) {
                // generate unique random number 
                $numberArr = array('5'=>'99999','6'=>'999999','7'=>'9999999','8'=>'99999999','9'=>'999999999','10'=>'9999999999');
                $randomNumber = rand(1000, $numberArr[$extension_digit]);
                // pad the number with zeros (if needed)
                $paded = str_pad($randomNumber, $extension_digit, '0', STR_PAD_RIGHT);   
                $number_array[] = $paded;
                $number_array = array_unique($number_array);               
                
                if(count($number_array) > $extension_number-1) 
                {
                    $final_array = $this->checkExtensionsInDB($number_array);
                    if(count($final_array) > $extension_number-1) {   
                        break;
                    }else{                     
                        $number_array = $final_array;
                        continue;
                    }
                }
            }
            //print_r($number_array);            
            //print_r($final_array);exit;
            $response = implode(',',$final_array);
            DB::commit();
            return $this->output(true, 'Extensions genreted successfully.', $response);
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }

    protected function checkExtensionsInDB(array $number_array)
    {
        $existExtension = Extension::select(['name'])->whereIn('name', $number_array)->get();            
        $exitArray = $existExtension->toArray();  
        $exitArray = array_column($exitArray, 'name');
        //print_r($exitArray);
        return $final_array = array_diff($number_array,$exitArray);

    }

    public function addExtensions(Request $request)
    {
        $failover_trunk = $request->failover_trunk;
        $validator = Validator::make($request->all(), [
            'trunk_type'        => 'required|in:Inbound,Outbound',
            'trunk_name'        => 'required|unique:trunks',
            'trunk_prefix'      => 'required|max:250',
            'tech'              => 'required|max:250',
            'trunk_ip'          => 'required|ip',
            'remove_prefix'     => 'required',
            'failover_trunk'    => 'nullable|exists:trunks,id',
            'max_use'           => 'required',
            'if_max_use'        => 'required',
            'trunk_username'    => 'required|max:250',
            'trunk_password'    => 'required|max:250',
        ],[
            'trunk_name.unique'  => 'This Trunk name is already registered. Please try with different trunk.',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();
            $Trunk = Extension::where('trunk_name', $request->trunk_name)->first();        
            if(!$Trunk){
                $Trunk = Extension::create([
                    'trunk_type'    => $request->trunk_type,
                    'trunk_name'    => $request->trunk_name,
                    'trunk_prefix'	=> $request->trunk_prefix,
                    'tech'          => $request->tech,
                    'trunk_ip'      => $request->trunk_ip,
                    'remove_prefix' => $request->remove_prefix,
                    'failover_trunk'=> $request->failover_trunk,
                    'max_use' 	    => $request->max_use,
                    'if_max_use' 	=> $request->if_max_use,
                    'trunk_username'=> $request->trunk_username,
                    'trunk_password'=> $request->trunk_password,
                    'status' 	    => $request->status,
                ]);
                
                $response 	= $Trunk->toArray();               
                DB::commit();
                return $this->output(true, 'Trunk added successfully.', $response);
            }else{
                DB::commit();
                return $this->output(false, 'This Trunk is already register with us.');
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }
}
