<?php

namespace App\Http\Controllers;

use App\Models\ResellerCommissionOfItems;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\MainPrice;
use App\Models\ResellerPrice;

class Controller extends BaseController
{
    
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	
	public function output($status = false, $message = '', $data = [], $httpcode = 200){
        if($status){
            $response = ["status" => $status,"message" => $message, 'code'=>$httpcode, 'data' => $data];
        }else{
            $response = ["status" => $status,"message" => $message, 'code'=>$httpcode];
        }
        return response()->json($response, $httpcode);
    }



    public function generateStrongPassword($length = 8, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = array();
        if (strpos($available_sets, 'l') !== false)
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        if (strpos($available_sets, 'u') !== false)
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        if (strpos($available_sets, 'd') !== false)
            $sets[] = '23456789';
        if (strpos($available_sets, 's') !== false)
            $sets[] = '!@#$%&*?';

        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[$this->tweak_array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++)
            $password .= $all[$this->tweak_array_rand($all)];

        $password = str_shuffle($password);

        if (!$add_dashes){
            return $this->output(true, 'Password created successfully.', $password);
            //return $password;
        }

        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while (strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;
        //$response[] = $dash_str;
        return $this->output(true, 'Password created successfully.', $dash_str);
        //return $dash_str;
    }
    //take a array and get random index, same function of array_rand, only diference is
    // intent use secure random algoritn on fail use mersene twistter, and on fail use defaul array_rand
    protected function tweak_array_rand($array)
    {
        if (function_exists('random_int')) {
            return random_int(0, count($array) - 1);
        } elseif (function_exists('mt_rand')) {
            return mt_rand(0, count($array) - 1);
        } else {
            return array_rand($array);
        }
    }


    public function validateDomain($value): bool
    {
        if (stripos($value, 'localhost') !== false) {
            return true;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-æøå]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/isu', $value);
    }


    public function getItemPrice($company_id, $country_id, $price_for, $reseller_id, $product){

        $MainPrice = MainPrice::select()
                    ->where('country_id',$country_id)
                    ->where('price_for',$price_for);
                    if($price_for == 'Reseller'){
                        $MainPrice = $MainPrice->where('reseller_id',$reseller_id);
                    }
                    $MainPrice = $MainPrice->where('status','1')->first();
                    
        $MainPriceArr = $MainPrice ? $MainPrice->toArray() : [];
        if(count($MainPriceArr) > 0 ){
            $itemPrice = ($product == 'Extension') ? $MainPriceArr['extension_price'] : $MainPriceArr['tfn_price'];

            $ResellerPrice = ResellerPrice::select()
                            ->where('company_id',$company_id)
                            ->where('country_id',$country_id)
                            //->where('product',$product)
                            ->where('status','1')->first();

            $ResellerCommission = $ResellerPrice ? $ResellerPrice->toArray() : [];

            if(count($ResellerCommission) > 0 ){
                if($product == 'Extension'){
                    $commission_type = trim($ResellerCommission['extension_commission_type']);
                    $commissionPrice = $ResellerCommission['extension_price'];
                    
                }else{
                    $commission_type = trim($ResellerCommission['tfn_commission_type']);
                    $commissionPrice = $ResellerCommission['tfn_price'];
                }
                if($commission_type == 'Fixed Amount'){
                    $itemPrice += $commissionPrice;
                }

                if(trim($commission_type) == 'Percentage'){
                    $pricePercentage = $itemPrice * $commissionPrice/100;
                    $itemPrice += $pricePercentage;
                }
            }
            return array('Status' => 'true', $product.'_price' => $itemPrice);
        }else{
            return array('Status' => 'false', 'Message' => 'Price not available for this country. Please contact with support team.');
        }
    }

    public function ResellerCommissionCalculate($user, $total_items, $invoice_id, $totalPrice)
    {

        $companyId = $user->company->id;
        $parentID  = $user->company->parent_id;
        $planId    = $user->company->plan_id;
        $noofItems = count($total_items);
        $total_price_ext_items = 0;
        $total_price_tfn_items = 0;
        $resellerPrice = ResellerPrice::where('company_id', $companyId)->first();
        if ($resellerPrice && $parentID > 1) {
            foreach ($total_items as $itemData) {
                $mainPrice = MainPrice::where('reseller_id', $parentID)->where('country_id', $itemData['country_id'])->first();
                if (is_null($mainPrice)) {
                    return $this->output(false, 'No price found for ' . $itemData['country_id'], 400);
                }
                if ($planId == 1) {
                    if ($itemData['item_type'] == "Extension") {
                        if ($resellerPrice->extension_commission_type == "Fixed Amount") {
                            $total_price_ext_items += $resellerPrice->extension_price;
                        } else {
                            $total_price_ext_items +=  $mainPrice->extension_price * $resellerPrice->extension_price / 100;
                        }
                    } else {
                        if ($resellerPrice->tfn_commission_type == "Fixed Amount") {
                            $total_price_tfn_items += $resellerPrice->tfn_price;
                        } else {
                            $total_price_tfn_items += $mainPrice->tfn_price * $resellerPrice->tfn_price / 100;
                        }
                    }
                } else {
                    if ($itemData['item_type'] == "TFN") {
                        if ($resellerPrice->tfn_commission_type == "Fixed Amount") {
                            $total_price_tfn_items += $resellerPrice->tfn_price;
                        } else {
                            $total_price_tfn_items += $mainPrice->tfn_price * $resellerPrice->tfn_price / 100;
                        }
                    }
                }
            }
            $total_price_items_Commission = $total_price_ext_items + $total_price_tfn_items;
        } else {
            $total_price_items_Commission = 0;
        }

        $resellercommissionofitems = ResellerCommissionOfItems::create([
            'company_id' => $companyId,
            'reseller_id' => $parentID,
            'invoice_id'  => $invoice_id,
            'no_of_items' => $noofItems,
            'total_amount' => $totalPrice,
            'commission_amount' => $total_price_items_Commission,
        ]);

        if (!is_null($resellercommissionofitems)) {
            return array('Status' => 'true', 'resellerCommissionOfItems' => $resellercommissionofitems);
        } else {
            return array('Status' => 'false', 'Message' => 'No Commission Found.');
        }
    }
}
