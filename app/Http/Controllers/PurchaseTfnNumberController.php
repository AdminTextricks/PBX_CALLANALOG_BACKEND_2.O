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

class PurchaseTfnNumberController extends Controller
{
    public function searchTfn(Request $request)
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
            'items' => 'required|array',
            'items.*.item_id' => 'required|numeric',
            'items.*.item_number' => 'required|numeric',
            'items.*.item_price' => 'required',
            'items.*.item_type' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {
            $responses = [];
            try {
                DB::beginTransaction();
                foreach ($request->items as $item) {
                    if ($item['item_type'] == 'TFN') {
                        $tfnNumber = Tfn::where('id', $item['item_id'])->where('reserved', 0)->first();
                        if ($tfnNumber && $tfnNumber->id == $item['item_id']) {
                            $cart = Cart::where('item_number', $item['item_number'])
                                ->where('company_id', $user->company_id)
                                ->first();
                            if (!$cart) {
                                $addCart = Cart::create([
                                    'company_id' => $user->company_id,
                                    'item_id' => $item['item_id'],
                                    'item_number' => $item['item_number'],
                                    'item_type' => $item['item_type'],
                                    'item_price' => $item['item_price']
                                ]);

                                if ($addCart) {
                                    $tfnNumber->company_id = $user->company_id;
                                    $tfnNumber->reserved = 1;
                                    $tfnNumber->reserveddate = date('Y-m-d H:i:s');
                                    $tfnNumber->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));
                                    $tfnNumber->save();
                                    $responses[] = $addCart->toArray();
                                } else {
                                    DB::rollBack();
                                    return $this->output(false, 'Tfn Number not found.');
                                }
                            } else {
                                DB::rollBack();
                                return $this->output(false, 'Tfn Number is already in the cart', 409);
                            }
                        } else {
                            DB::rollBack();
                            return $this->output(false, 'Oops! Something Went Wrong Tfn number does not exist with us or values mismatch', 409);
                        }
                    } else {
                        $extNumber = Extension::where('id', $item['item_id'])->where('status', 0)->first();
                        if ($extNumber && $extNumber->id == $item['item_id']) {
                            $cart = Cart::where('item_number', $item['item_number'])
                                ->where('company_id', $user->company_id)
                                ->first();
                            if (!$cart) {
                                $addCart = Cart::create([
                                    'company_id' => $user->company_id,
                                    'item_id' => $item['item_id'],
                                    'item_number' => $item['item_number'],
                                    'item_type' => $item['item_type'],
                                    'item_price' => $item['item_price']
                                ]);

                                if ($addCart) {
                                    $responses[] = $addCart->toArray();
                                } else {
                                    DB::rollBack();
                                    return $this->output(false, 'Extension Number not found.');
                                }
                            } else {
                                DB::rollBack();
                                return $this->output(false, 'Extension Number is already in the cart', 409);
                            }
                        } else {
                            DB::rollBack();
                            return $this->output(false, 'Oops! Something Went Wrong Extension number does not exist with us or values mismatch', 409);
                        }
                    }
                }
                DB::commit();
                return $this->output(true, 'Items added to cart successfully', $responses, 200);
            } catch (\Exception $e) {
                DB::rollBack();
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
