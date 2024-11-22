<?php

namespace App\Http\Controllers;

use App\Models\ResellerCommissionOfItems;
use App\Models\TfnsHistory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\MainPrice;
use App\Models\ResellerPrice;
use App\Models\Server;
use Request;
use Illuminate\Support\Facades\Log;

class Controller extends BaseController
{

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function output($status = false, $message = '', $data = [], $httpcode = 200)
    {
        if ($status) {
            $response = ["status" => $status, "message" => $message, 'code' => $httpcode, 'data' => $data];
        } else {
            $response = ["status" => $status, "message" => $message, 'code' => $httpcode];
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

        if (!$add_dashes) {
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


    public function getItemPrice($company_id, $country_id, $price_for, $reseller_id, $product)
    {

        $MainPrice = MainPrice::select()
            ->where('country_id', $country_id)
            ->where('price_for', $price_for);
        if ($price_for == 'Reseller') {
            $MainPrice = $MainPrice->where('reseller_id', $reseller_id);
        }
        $MainPrice = $MainPrice->where('status', '1')->first();

        $MainPriceArr = $MainPrice ? $MainPrice->toArray() : [];
        if (count($MainPriceArr) > 0) {
            $itemPrice = ($product == 'Extension') ? $MainPriceArr['extension_price'] : $MainPriceArr['tfn_price'];

            $ResellerPrice = ResellerPrice::select()
                ->where('company_id', $company_id)
                ->where('country_id', $country_id)
                //->where('product',$product)
                ->where('status', '1')->first();

            $ResellerCommission = $ResellerPrice ? $ResellerPrice->toArray() : [];

            if (count($ResellerCommission) > 0) {
                if ($product == 'Extension') {
                    $commission_type = trim($ResellerCommission['extension_commission_type']);
                    $commissionPrice = $ResellerCommission['extension_price'];
                } else {
                    $commission_type = trim($ResellerCommission['tfn_commission_type']);
                    $commissionPrice = $ResellerCommission['tfn_price'];
                }
                if ($commission_type == 'Fixed Amount') {
                    $itemPrice += $commissionPrice;
                }

                if (trim($commission_type) == 'Percentage') {
                    $pricePercentage = $itemPrice * $commissionPrice / 100;
                    $itemPrice += $pricePercentage;
                }
            }
            return array('Status' => 'true', $product . '_price' => $itemPrice);
        } else {
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

    public function sipReload()
    {
        $add_extension_script = config('app.add_extension_script');
        $result2 = shell_exec('sudo ' . $add_extension_script);
        Log::error('Opensips add extension command exe : ' . $result2);
        
        $Server_arr = Server::where('status',1)->get();
        if ($Server_arr->isNotEmpty()) {
            foreach($Server_arr as $server){
                $server_ip  = $server['ip'];
                $ami_port   = $server['ami_port'];
                $user_name  = $server['user_name'];
                $secret     = $server['secret'];
                //$socket = @fsockopen($server_ip, 5038);
                $socket = fsockopen($server_ip, $ami_port, $errno, $errstr, 60);
                Log::error('fsockopen command load : ' . $socket);
                $response = "";
                if (!is_resource($socket)) {
                // echo "conn failed in Engconnect ";
                    Log::error('conn failed in Engconnect');
                    return false;
                //  exit;
                }
                fputs($socket, "Action: Login\r\n");
                fputs($socket, "UserName: ".$user_name."\r\n");
                fputs($socket, "Secret: ".$secret."\r\n\r\n");
                fputs($socket, "Action: Command\r\n");
                fputs($socket, "Command: sip reload\r\n\r\n");
                fputs($socket, "Action: Logoff\r\n\r\n");
                while (!feof($socket))
                    $response .= fread($socket, $ami_port);
                fclose($socket);
                Log::error('fsockopen command run for : ' . $server_ip);
            }
            return true;
        }else{
            Log::error('Server details not available to reload sip');
            return false;
        }
    }
    public function addExtensionInConfFile($extensionName, $conf_file_path, $secret, $account_code, $template_contents)
    {
        // Add new user section
        $register_string = "\n[$extensionName]\nusername=$extensionName\nsecret=$secret\naccountcode=$account_code\n$template_contents\n";
        //$webrtc_conf_path = "/var/www/html/callanalog/admin/webrtc_template.conf";
        file_put_contents($conf_file_path, $register_string, FILE_APPEND | LOCK_EX);
        //echo "Registration successful. The SIP user $nname has been added to the webrtc_template.conf file.";        
    }

    public function removeExtensionFromConfFile($extensionName, $conf_file_path)
    {
        // Remove user section
        //$conf_file_path = "webrtc_template.conf";
        $lines = file($conf_file_path);
        $output = '';
        $found = false;
        foreach ($lines as $line) {
            if (strpos($line, "[$extensionName]") !== false) {
                $found = true;
                continue;
            }
            if ($found && strpos($line, "[") === 0) {
                $found = false;
            }
            if (!$found) {
                $output .= $line;
            }
        }
        file_put_contents($conf_file_path, $output, LOCK_EX);
        //echo "Registration removed. The SIP user $nname has been removed from the webrtc_template.conf file.";
    }

    public function TfnHistories($company_id, $assign_id, $number, $payment_for, $msg)
    {
        $insert_tfn_histories = TfnsHistory::create([
            'company_id'  => $company_id,
            'assign_by'   => $assign_id,
            'tfn_number'  => $number,
            'payment_for' => $payment_for,
            'message'     => "Tfn Number is {$msg} Successfully!",
        ]);
        if (!is_null($insert_tfn_histories)) {
            return array('Status' => 'true', 'insert_tfn_historiesData' => $insert_tfn_histories);
        } else {
            return array('Status' => 'false', 'Message' => 'No Commission Found.');
        }
    }
}
