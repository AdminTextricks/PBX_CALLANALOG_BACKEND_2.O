<?php

namespace App\Http\Controllers;

use App\Models\MainPrice;
use App\Models\ResellerPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class MainPriceController extends Controller
{
    public function __construct(){

    }
    public function addSuperAdminPrice(Request $request)
    {
        $validator = Validator::make($request->all(), [            
            'country_id'    => 'required',
            'price_for'     => 'required|max:500|in:Reseller,Company',
            'reseller_id'   => 'bail|required_if:price_for,Reseller,exists:users,id',
            //'product'     => 'required|max:500|in:TFN,Extension',
            'tfn_price'     => 'required|max:255',
            'extension_price'   => 'required|max:255',
        ]/*,[
            'user_id'   => 'The user field is required when user type is reseller and user should be registered with us!',           
        ]*/);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();
            $MainPrice = MainPrice::where('price_for', $request->price_for)                            
                            ->where('country_id', $request->country_id);
                            if($request->price_for == 'Reseller'){
                                $MainPrice = $MainPrice->where('reseller_id', $request->reseller_id);
                            }
                            $MainPrice = $MainPrice->first();
            if(!$MainPrice){
                $MainPrice = MainPrice::create([
                    'country_id'=> $request->country_id,
                    'price_for' => $request->price_for,
                    'reseller_id'	=> ($request->price_for == 'Reseller') ? $request->reseller_id : null,
                    'tfn_price'     => $request->tfn_price,
                    'extension_price'   => $request->extension_price,
                    'status'    => isset($request->status) ? $request->status : 1,
                ]);
                
                $response 	= $MainPrice->toArray();               
                DB::commit();
                return $this->output(true, 'Price added successfully.', $response);
            }else{
                DB::commit();
                return $this->output(false, 'This Price for this '.$request->price_for.' is already added with us.');
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }

    public function getPriceList(Request $request, $price_for)
    {
        
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        //$price_fo = $request->price_fo ?? "";

        $MainPrice_data = MainPrice::select('*') 
                            ->with(['user:id,name,email,mobile']) 
                            ->with('country:id,country_name')
                            ->where('price_for', $price_for)
                            ->paginate(
                            $perPage = $perPageNo,
                            $columns = ['*'],
                            $pageName = 'page'
                        );

        if ($MainPrice_data->isNotEmpty()) {
            $MainPrice_dd = $MainPrice_data->toArray();
            unset($MainPrice_dd['links']);
            return $this->output(true, 'Success', $MainPrice_dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllPriceList(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $price_id = $request->id ?? NULL;
        if($price_id){
            $MainPrice_data = MainPrice::select('*') 
                            ->with(['user:id,name,email,mobile']) 
                            ->with('country:id,country_name')
                            ->where('id', $price_id)->get();
        }else{
            $MainPrice_data = MainPrice::select('*') 
                                ->with(['user:id,name,email,mobile']) 
                                ->with('country:id,country_name')
                                ->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
        }        

        if ($MainPrice_data->isNotEmpty()) {
            $MainPrice_dd = $MainPrice_data->toArray();
            unset($MainPrice_dd['links']);
            return $this->output(true, 'Success', $MainPrice_dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }


    public function changeMainPriceStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}

		$MainPrice = MainPrice::find($id);
		if (is_null($MainPrice)) {
			return $this->output(false, 'This Price details not exist with us. Please try again!.', [], 200);
		} else {
            $MainPrice->status = $request->status;
            $MainPriceRes = $MainPrice->save();
            if ($MainPriceRes) {
                $MainPrice = MainPrice::where('id', $id)->first();
                $response = $MainPrice->toArray();
                return $this->output(true, 'Price details updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in Price details updating. Please try again!.', [], 200);
            }
		} 
	}

    public function updatePrice(Request $request, $id)
	{
		$MainPrice = MainPrice::find($id);
		if (is_null($MainPrice)) {
			return $this->output(false, 'This Price details not exist with us. Please try again!.', [], 404);
		} else {
			$validator = Validator::make($request->all(), [              
                'tfn_price'         => 'required|max:255',
                'extension_price'   => 'required|max:255',
            ]);
            if ($validator->fails()){
                return $this->output(false, $validator->errors()->first(), [], 409);
            }		
			$MainPrice->tfn_price       = $request->tfn_price;
            $MainPrice->extension_price = $request->extension_price;
			$MainPriceRes 			    = $MainPrice->save();
			if ($MainPriceRes) {
				$MainPrice = MainPrice::where('id', $id)->first();
				$response = $MainPrice->toArray();
				return $this->output(true, 'Price details updated successfully.', $response, 200);
			} else {
				return $this->output(false, 'Error occurred in price updating. Please try again!.', [], 200);
			}
		}
	}

    public function deletePrice(Request $request, $id){
        try {  
            DB::beginTransaction();            
            $MainPrice = MainPrice::where('id', $id)->first();
            if($MainPrice){
                $resdelete = $MainPrice->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in price details removing. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'price details not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }



    /**********************************   Reseller  commissions functions   ********************** */
    /**********************************   Reseller  commissions functions   ********************** */
    /**********************************   Reseller  commissions functions   ********************** */

    public function addResellerPrice(Request $request)
    {
        $validator = Validator::make($request->all(), [            
            'country_id'    => 'required|numeric',
            'company_id'    => 'required|numeric|exists:companies,id',          
            'tfn_commission_type'       => 'required|max:500|in:Fixed Amount,Percentage',             
            'extension_commission_type' => 'required|max:500|in:Fixed Amount,Percentage',
            //'product'         => 'required|max:500|in:TFN,Extension',
            'tfn_price'         => 'required|max:255',
            'extension_price'   => 'required|max:255',
        ],[
            'company_id'   => 'The company field is required and company should be registered with us!',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        // Start transaction!
        try { 
            DB::beginTransaction();
            $ResellerPrice = ResellerPrice::where('company_id', $request->company_id)
                            //->where('product', $request->product)
                            ->where('country_id', $request->country_id)
                            ->first();
            if(!$ResellerPrice){
                $ResellerPrice = ResellerPrice::create([
                    'country_id'        => $request->country_id,
                    'company_id'	    => $request->company_id,
                    'tfn_commission_type'   => $request->tfn_commission_type,
                    'tfn_price'             => $request->tfn_price,
                    'extension_commission_type' => $request->extension_commission_type,
                    'extension_price'           => $request->extension_price,
                    'status'    => isset($request->status) ? $request->status : 1,
                ]);
                
                $response 	= $ResellerPrice->toArray();               
                DB::commit();
                return $this->output(true, 'Price added successfully.', $response);
            }else{
                DB::commit();
                return $this->output(false, 'This Price for this user is already added with us.');
            }
        } catch(\Exception $e)
        {
            DB::rollback();
            //return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
            return $this->output(false, $e->getMessage());
            //throw $e; 
        }
    }


    public function getResellerPriceList(Request $request)
    {
        //$user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $price_id = $request->id ?? NULL;
        if($price_id){            
            $ResellerPrice_data = ResellerPrice::select('*')
                            ->with(['company:id,company_name,email,mobile'])
                            ->with('country:id,country_name') 
                            ->where('id', $price_id)->get();;
        }else{
            $ResellerPrice_data = ResellerPrice::select('*') 
                                ->with(['company:id,company_name,email,mobile']) 
                                ->with('country:id,country_name')
                                ->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
        }
        if ($ResellerPrice_data->isNotEmpty()) {
            $ResellerPrice_dd = $ResellerPrice_data->toArray();
            unset($ResellerPrice_dd['links']);
            return $this->output(true, 'Success', $ResellerPrice_dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function changeResellerPriceStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}

		$ResellerPrice = ResellerPrice::find($id);
		if (is_null($ResellerPrice)) {
			return $this->output(false, 'This Price details not exist with us. Please try again!.', [], 200);
		} else {			
            $ResellerPrice->status = $request->status;
            $ResellerPriceRes = $ResellerPrice->save();
            if ($ResellerPriceRes) {
                $ResellerPrice = ResellerPrice::where('id', $id)->first();
                $response = $ResellerPrice->toArray();
                return $this->output(true, 'Price details updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in Price details updating. Please try again!.', [], 200);
            }
		} 
	}


    public function updateResellerPrice(Request $request, $id)
	{
		$ResellerPrice = ResellerPrice::find($id);
		if (is_null($ResellerPrice)) {
			return $this->output(false, 'This Price details not exist with us. Please try again!.', [], 404);
		} else {
			$validator = Validator::make($request->all(), [              
                'tfn_price'             => 'required|max:255',                
                'tfn_commission_type'   => 'required|max:255',
                'extension_price'             => 'required|max:255',                
                'extension_commission_type'   => 'required|max:255',                
            ]);
            if ($validator->fails()){
                return $this->output(false, $validator->errors()->first(), [], 409);
            }		
			$ResellerPrice->tfn_price           = $request->tfn_price;		
			$ResellerPrice->tfn_commission_type = $request->tfn_commission_type;
            $ResellerPrice->extension_price           = $request->extension_price;		
			$ResellerPrice->extension_commission_type = $request->extension_commission_type;		
			$ResellerPriceRes 			    = $ResellerPrice->save();
			if ($ResellerPriceRes) {
				$ResellerPrice = ResellerPrice::where('id', $id)->first();
				$response = $ResellerPrice->toArray();
				return $this->output(true, 'Price details updated successfully.', $response, 200);
			} else {
				return $this->output(false, 'Error occurred in price updating. Please try again!.', [], 200);
			}
		}
	}

    public function deleteResellerPrice(Request $request, $id){
        try {  
            DB::beginTransaction();            
            $ResellerPrice = ResellerPrice::where('id', $id)->first();
            if($ResellerPrice){
                $resdelete = $ResellerPrice->delete();
                if ($resdelete) {
                    DB::commit();
                    return $this->output(true,'Success',200);
                } else {
                    DB::commit();
                    return $this->output(false, 'Error occurred in price details removing. Please try again!.', [], 209);                    
                }
            }else{
                DB::commit();
                return $this->output(false,'price details not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            DB::rollback();
            //return $this->output(false, $e->getMessage());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
    
}
