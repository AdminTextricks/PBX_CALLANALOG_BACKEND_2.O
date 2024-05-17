<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\State;
//use App\Models\Country;
use Illuminate\Validation\ValidationException;
use Validator;

class StateController extends Controller
{
	public function __construct(){
		
	}
	
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStates(Request $request, $country_id=NULL){		
		
        $data = State::select('id','country_id', 'state_name', 'iso3')->where('country_id', $country_id)->get();
		if($data->isNotEmpty()){
            return $this->output(true, 'Success', $data->toArray(), 200);
        }else{
            return $this->output(true, 'No Record Found', []);
        }
    }
}
