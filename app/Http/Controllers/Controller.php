<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	
	public function output($status = false, $message = '', $data = [], $httpcode = 200){
        if($status){
            $response = ["status" => $status,"message" => $message, 'data' => $data];
        }else{
            $response = ["status" => $status,"message" => $message];
        }
        return response()->json($response, $httpcode);
    }
}
