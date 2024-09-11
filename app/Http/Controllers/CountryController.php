<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
	public function __construct(){

    }
	
	/**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCountries(Request $request){
        $data = Country::select('id', 'country_name', 'iso3', 'phone_code','currency','currency_symbol')->get();
        if($data->isNotEmpty()){
            return $this->output(true, 'Success', $data->toArray(), 200);
        }else{
            return $this->output(true, 'No Record Found', []);
        }        
    }

    public function getCountriesTimeZones(Request $request, $country_id){
        $data = DB::table('country_time_zones')->select('id','country_id','iso3','time_zone')->where('country_id', $country_id)->get();        
        if($data->isNotEmpty()){
            return $this->output(true, 'Success', $data->toArray(), 200);
        }else{
            return $this->output(true, 'No Record Found', []);
        } 
    }
}
