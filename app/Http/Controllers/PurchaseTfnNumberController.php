<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\MainPrice;
use App\Models\ResellerPrice;
use App\Models\Tfn;
use App\Models\User;
use Validator;
use Illuminate\Http\Request;

class PurchaseTfnNumberController extends Controller
{
    public function searchTfn(Request $request)
    {
        $perPageNo = isset($request->perpage) ? $request->perpage : 50;
        $user = \Auth::user();
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

        if ($user->role_id == 6) {
            $main_price = MainPrice::select('*')
                ->where('user_type', 'Reseller')
                ->where('product', 'TFN')
                ->where('country_id', $country_id)
                ->where('company_id', $user->company_id)
                ->first();
            $reseller_price = ResellerPrice::select('*')
                ->where('product', 'TFN')
                ->where('country_id', $country_id)
                ->where('company_id', $user->company_id)
                ->first();

            if ($main_price && $reseller_price) {
                if ($reseller_price->commission_type == 'Percentage') {
                    $total_price = $main_price->price + ($main_price->price * $reseller_price->price) / 100;
                } else {
                    $total_price = $main_price->price + $reseller_price->price;
                }
            }
        } else {
            $main_price = MainPrice::select('*')
                ->where('user_type', 'Company')
                ->where('product', 'TFN')
                ->where('country_id', $country_id)
                ->first();

            if ($main_price) {
                $total_price = $main_price->price;
            }
        }

        $searchQry = \DB::table('tfns')
            ->select('tfns.id', 'tfns.tfn_number')
            // ->where('tfns.tfn_number', 'like', "%$starting_digits%")
            ->where('tfns.country_id', $country_id)
            ->where('tfns.company_id', '0')
            ->where('tfns.assign_by', '0')
            ->where('tfns.activated', '1')
            ->where('tfns.reserved', '0')
            ->where('tfns.status', '1')
            ->distinct();

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
            return $this->output(true, 'No Record Found', []);
        }
    }


    public function addtocart(Request $request)
    {
        $user = \Auth::user();
        $validator = Validator::make($request->all(), [
            'item_id'     => 'required|numeric',
            'item_number' => 'required|numeric',
            'item_price'  => 'required',
            'item_type'   => 'required',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {
            if ($request->item_type == 'TFN') {
                $tfnNumber = Tfn::select()->where('tfn_number', '=', $request->item_number)->first();
                if ($tfnNumber && $tfnNumber->tfn_number == $request->item_number) {
                    $cart = Cart::select()->where('item_number', '=', $request->item_number)
                        ->where('company_id', '=', $user->company_id)
                        ->first();
                    if (!$cart) {
                        $addCart = Cart::create([
                            'company_id'  => $user->company_id,
                            'item_id'     => $request->item_id,
                            'item_number' => $request->item_number,
                            'item_type'   => $request->item_type,
                            'item_price'  => $request->item_price
                        ]);

                        if ($addCart) {
                            $tfnNumber->reserved   = 1;
                            $tfnNumber->reserveddate = date('Y-m-d H:i:s');
                            $tfnNumber->reservedexpirationdate = date('Y-m-d H:i:s', strtotime('+1 day'));
                            $tfnNumber->save();
                        } else {
                            return $this->output(false, 'Tfn Number not found.');
                        }
                        return $this->output(true, 'Tfn Number added to cart successfully', 200);
                    } else {
                        return $this->output(false, 'Tfn Number is already in the cart', 409);
                    }
                } else {
                    return $this->output(false, 'Oops! Something Went Wrong Tfn number does not exist with us or values mismatch', 409);
                }
            } else {
                return $this->output(true, 'Please Check Item Type First', 200);
            }
        }
    }


    public function removeFromCart(Request $request, $id)
    {
        $user = \Auth::user();
        $cart = Cart::find($id);
        if (is_null($cart)) {
            return $this->output(false, 'This Cart Number not exist with us. Please try again!.', [], 404);
        } else {
            $tfnNumber = Tfn::select()->where('tfn_number', '=', $cart->item_number)->first();
            if ($cart) {
                $tfnNumber->reserved   = 0;
                $tfnNumber->reserveddate = null;
                $tfnNumber->reservedexpirationdate = null;
                $tfnNumber->save();
            } else {
                return $this->output(false, 'Tfn Number not found.');
            }
            $cart = Cart::where('id', '=', $id)->where('company_id', '=', $user->company_id)->delete();
            if ($cart) {
                return $this->output(true, 'Cart item deleted successfully', 200);
            } else {
                return $this->output(false, 'Error occurred in Deleting Cart Number. Please try again!.', [], 209);
            }
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
