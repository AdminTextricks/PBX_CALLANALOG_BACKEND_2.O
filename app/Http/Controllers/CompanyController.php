<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Company;
use App\Models\User;
use Validator;

class CompanyController extends Controller
{

    public function __construct()
    {
    }

    public function getAllCompany(Request $request)
    {
        $company_id = $request->id ?? "";
        $perPageNo = isset($request->perpage) ? $request->perpage : 5;
        if ($company_id) {
            $data = Company::select('*')->where('id', $company_id)->first();
            //->where('status', 1)         
        } else {
            $data = Company::select()
                ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
        }

        if (!is_null($data)) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllActiveCompany(Request $request)
    {
        $company_id = $request->id ?? "";
        $perPageNo = isset($request->perpage) ? $request->perpage : 5;
        $data = Company::select()->where('status', 1)
            ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');

        if (!is_null($data)) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    /**
     * Change User Status.
     *
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
        $Company = Company::find($id);
        if (is_null($Company)) {
            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 200);
        } else {
            $Company->status = $request->status;
            $companyRes = $Company->save();
            if ($companyRes) {
                if ($request->status == '0') {
                    $User = User::where('company_id', $id)
                        ->update(['status' => '0']);
                    return $this->output(true, 'Company and all User has been disabled successfully.');
                }
                return $this->output(true, 'Company status has been updated successfully.');
            } else {
                return $this->output(false, 'Error occurred in company status updating. Please try again!.', [], 409);
            }
        }
    }

    public function updateCompany(Request $request, $id)
    {
        $Company = Company::find($id);
        if (is_null($Company)) {
            return $this->output(false, 'This Company not exist with us. Please try again!.', [], 404);
        } else {
            $validator = Validator::make($request->all(), [
                'billing_address'   => 'required|string|max:255',
                'country_id'    => 'required|numeric',
                'state_id'      => 'required|numeric',
                'city'          => 'required|string|max:150',
                'zip'           => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:6',
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            $Company->billing_address     = $request->billing_address;
            $Company->country_id = $request->country_id;
            $Company->state_id  = $request->state_id;
            $Company->city      = $request->city;
            $Company->zip       = $request->zip;
            $CompanysRes         = $Company->save();

            if ($CompanysRes) {
                $Company = Company::where('id', $id)->first();
                $response = $Company->toArray();
                return $this->output(true, 'Company updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in Company Updating. Please try again!.', [], 409);
            }
        }
    }


    public function getBalance(Request $request)
    {
        $user = \Auth::user();
        $balance = Company::where('id', $user->company_id)->first();
        if ($balance !== null) {
            $balance_result = Company::select('balance')->where('id', $user->company_id)->first();
            return $this->output(true, 'success', $balance_result->toArray(), 200);
        } else {
            return $this->output(false, 'Balance not found', 404);
        }
    }
    
}
