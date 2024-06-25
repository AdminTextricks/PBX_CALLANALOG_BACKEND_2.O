<?php

namespace App\Http\Controllers;

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
                            ->where('product',$product)
                            ->where('status','1')->first();

            $ResellerCommission = $ResellerPrice->toArray();

            if(count($ResellerCommission) > 0 ){

                if(trim($ResellerCommission['commission_type']) == 'Fixed Amount'){
                    $itemPrice = $itemPrice + $ResellerCommission['price'];
                }

                if(trim($ResellerCommission['commission_type']) == 'Percentage'){
                    $pricePercentage = $itemPrice * $ResellerCommission['price']/100;
                    $itemPrice = $itemPrice + $pricePercentage;
                }                
            }
            return array('Status' => 'true', $product.'_price' => $itemPrice);
        }else{
            return array('Status' => 'false', 'Message' => 'Price not available for this country. Please contact with support team.');
        }
    }
}
