<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\MainPrice;
use App\Models\ResellerPrice;
use App\Models\Tfn;
use App\Models\Extension;
use App\Models\User;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;

class PurchaseTfnNumberController extends Controller
{

    public function searchTfn(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 50;
        $validator = Validator::make($request->all(), [
            'country_id'        => 'required',
            'type'              => 'required',
            'starting_digits'   => 'nullable', 
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $country_id = $request->country_id;
        $type       = $request->type;
        $starting_digits = $request->starting_digits;
        
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
            if ($data->isNotEmpty()) {
                $datanew = $data->toArray();
                $datanew = ['item_price' => $item_price] + $datanew;
                unset($datanew['links']);
                return $this->output(true, 'success', $datanew, 200);
            } else {
                return $this->output(true, 'No Record Found!!', []);
            }
        } else {
            DB::commit();
            return $this->output(false, $item_price_arr['Message']);
        }
    }

    public function searchTfn_old(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 50;
        $validator = Validator::make($request->all(), [
            'country_id' => 'required',
            'type'       => 'required',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $country_id = $request->country_id;
        $type = $request->type;
        $starting_digits = $request->starting_digits;
        $total_price = 0;

        if ($user->company->parent_id != "1") {
            $main_price = MainPrice::select('*')
                ->where('price_for', 'Reseller')
                ->where('country_id', $country_id)
                ->where('reseller_id', $user->company->parent_id)
                ->first();
            // return $main_price;
            $reseller_price = ResellerPrice::select('*')
                ->where('product', 'TFN')
                ->where('country_id', $country_id)
                ->where('company_id', $user->company_id)
                ->first();

            if ($main_price && $reseller_price) {
                if ($reseller_price->commission_type == 'Percentage') {
                    $total_price = $main_price->tfn_price + ($main_price->tfn_price * $reseller_price->price) / 100;
                } else {
                    $total_price = $main_price->tfn_price + $reseller_price->price;
                }
            } else {
                return $this->output(false, "No Record Found!", 200);
            }
        } else {
            $main_price = MainPrice::select('*')
                ->where('price_for', 'Company')
                ->where('country_id', $country_id)
                ->first();

            if ($main_price) {
                $total_price = $main_price->tfn_price;
            }
        }
        // return $total_price;
        $company = Company::where('id', $user->company->id)->first();
        $inbound_trunk = explode(',', $company->inbound_permission);

        $searchQry = DB::table('tfns')
            ->select('tfns.id', 'tfns.tfn_number')
            ->where('tfns.country_id', $country_id)
            ->where('tfns.company_id', '0')
            ->where('tfns.assign_by', '0')
            ->where('tfns.plan_id', '0')
            ->whereIn('tfns.tfn_provider', $inbound_trunk)
            ->where('tfns.activated', '1')
            ->where('tfns.reserved', '0')
            ->where('tfns.status', '1')
            ->distinct();
        // return $trunk;
        if ($type == 'Local') {
            $data = $searchQry->paginate($perPageNo);
        } else {
            $data = $searchQry->where('tfns.tfn_number', 'like', "%$starting_digits%")
                ->paginate($perPageNo);
        }
        foreach ($data as $result) {
            $result->total_price = $total_price;
            $result->item_type = 'TFN';
        }

        if ($data->isNotEmpty()) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found!!', []);
        }
    }


    public function addtocart(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'item_id'       => 'required|numeric',
            'item_number'   => 'required|numeric',
            'item_price'    => 'required',
            'item_type'     => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {            
            try {                
                DB::beginTransaction();                
                $cart = Cart::where('item_number', $request->item_number)
                            ->where('company_id', $user->company_id)->get();
                if ($cart->count() > 0) {
                    DB::rollBack();
                    return $this->output(false, 'Tfn Number is already in the cart', 409);
                }else{
                    if (strtoupper($request->item_type) == 'TFN') {
                        $tfnNumber = Tfn::where('id', $request->item_id)->where('reserved', '0')->first();
                        if ($tfnNumber) {
                            $tfnNumber->reserved = '1';
                            $tfnNumber->reserveddate = date('Y-m-d H:i:s');
                            $tfnNumber->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));
                            if($tfnNumber->save()){
                                $addCart = Cart::create([
                                    'company_id'    => $user->company_id,
                                    'item_id'       => $request->item_id,
                                    'item_number'   => $request->item_number,
                                    'item_type'     => $request->item_type,
                                    'item_price'    => $request->item_price,
                                ]);
                                if ($addCart) {
                                    $response = $addCart->toArray();                                   
                                    DB::commit();
                                    return $this->output(true, 'Item has been added into cart successfully.', $response);
                                } else {
                                    DB::rollBack();
                                    return $this->output(false, 'Error occurred in add to cart process.');
                                }
                            } else {
                                return $this->output(false, 'Error occurred in reserving this TFN for you.');
                            }
                        } else {
                            DB::rollBack();
                            return $this->output(false, 'This TFN number is reserved by someone OR not exist with us.', 409);
                        }
                    } else {
                        $extNumber = Extension::where('id', $request->item_id)->where('status', 0)->first();
                        if ($extNumber) {
                            $addCart = Cart::create([
                                'company_id'    => $user->company_id,
                                'item_id'       => $request->item_id,
                                'item_number'   => $request->item_number,
                                'item_type'     => $request->item_type,
                                'item_price'    => $request->item_price,
                            ]);
                            if ($addCart) {
                                $response = $addCart->toArray();
                                DB::commit();
                                return $this->output(true, 'Item has been added into cart successfully.', $response);
                            } else {
                                DB::rollBack();
                                return $this->output(false, 'Error occurred in add to cart process.');
                            }
                        } else {
                            DB::rollBack();
                            return $this->output(false, 'This Extension number not exist with us OR in running process.', 409);
                        }
                    }
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error in add to cart Inserting : ' . $e->getMessage() . ' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
                return $this->output(false, 'An error occurred while adding items to the cart. Please try again!', [], 500);
            }
        }
    }


    public function removeFromCart(Request $request, $id)
    {
        $user = \Auth::user();
        try {
            DB::beginTransaction();

            $cart = Cart::find($id);
            if (is_null($cart)) {
                return $this->output(false, 'This Cart Number does not exist with us. Please try again!', [], 404);
            }

            if ($cart->company_id != $user->company_id) {
                return $this->output(false, 'Unauthorized action.', [], 403);
            }

            if ($cart->item_type == "TFN") {
                $tfnNumber = Tfn::find($cart->item_id);
                if ($tfnNumber) {
                    $tfnNumber->company_id = 0;
                    $tfnNumber->reserved = 0;
                    $tfnNumber->reserveddate = null;
                    $tfnNumber->reservedexpirationdate = null;
                    $tfnNumber->save();
                } else {
                    return $this->output(false, 'TFN Number not found.', [], 404);
                }
            } else {
                $extNumber = Extension::find($cart->item_id);
                if (!$extNumber) {
                    return $this->output(false, 'Extension Number not found.', [], 404);
                }
            }

            $cartDeleted = Cart::where('id', $id)->where('company_id', $user->company_id)->delete();
            if ($cartDeleted) {
                DB::commit();
                return $this->output(true, 'Cart item deleted successfully.', [], 200);
            } else {
                DB::rollBack();
                return $this->output(false, 'Error occurred while deleting cart item. Please try again!', [], 209);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->output(false, 'An error occurred. Please try again!', [], 500);
        }
    }


    public function allCartList(Request $request)
    {
        $user = \Auth::user();
        $cart = Cart::select('*')->where('company_id', '=', $user->company_id)->get();
        if ($cart->isNotEmpty()) {
            return $this->output(true, 'success', $cart->toArray(), 200);
        } else {
            return $this->output(true, 'No record Found', []);
        }
    }
}
