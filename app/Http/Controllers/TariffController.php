<?php

namespace App\Http\Controllers;

use App\Models\Tariff;
use Illuminate\Http\Request;
use Validator;

class TariffController extends Controller
{
    public function createTariff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tariff_name' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $TariffData = Tariff::where('tariff_name', $request->tariff_name)->first();
        if (!$TariffData) {
            $TariffData = Tariff::create([
                'tariff_name' => $request->tariff_name,
                'status' => isset($request->status) ? $request->status : 1,
            ]);
            $response = $TariffData->toArray();
            return $this->output(true, 'Tariff name added successfully.', $response, 200);
        } else {
            return $this->output(false, 'This Tariff name is already exist with us. Please choose another name to add some Tariff');
        }
    }


    public function updateTariff(Request $request, $id)
    {
        $TariffData = Tariff::find($id);
        if (is_null($TariffData)) {
            return $this->output(false, 'This Tariff is not belong with us. Please try again!.', [], 404);
        } else {
            $validator = Validator::make($request->all(), [
                'tariff_name' => "required|max:255",
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            } else {
                $TariffData->tariff_name = $request->tariff_name;
                $TariffData->status = isset($request->status) ? $request->status : 1;
                $TariffDataRes =  $TariffData->save();
                if ($TariffDataRes) {
                    $TariffDatamain = Tariff::where('id', $id)->first();
                    $response = $TariffDatamain->toArray();
                    return $this->output(true, 'Tariff name updated successfully!', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in Tariff name Updating. Please try again!.', [], 200);
                }
            }
        }
    }


    public function changeTariffStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        } else {
            $TariffData = Tariff::find($id);
            if (is_null($TariffData)) {
                return $this->output(false, 'This Tariff is not belong with us. Please try again!.', [], 404);
            } else {
                $TariffData->status = $request->status;
                $TariffDataRes = $TariffData->save();
                if ($TariffData) {
                    $response  = $TariffData->toArray();
                    return $this->output(true, 'Tariff updated successfully.', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in Tariff Updating. Please try again!.', [], 200);
                }
            }
        }
    }


    public function getAllActiveTariff(Request $request)
    {
        $TariffData = Tariff::select()->where('status', 1)->get();
        if ($TariffData->isNotEmpty()) {
            return $this->output(true, 'success', $TariffData->toArray());
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllTariff(Request $request)
    {
        $user  = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $tariff_id = $request->id ?? NULL;
        if ($tariff_id) {
            $tariff_data = Tariff::select()->where('id', $tariff_id)->get();
        } else {
            if ($params !== "") {
                $tariff_data = Tariff::select('*')->where('tariff_name', 'LIKE', "%$params%")->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
            } else {
                $tariff_data = Tariff::select('*')->paginate($perPage = $perPageNo, $column = ['*'], $pageName = 'page');
            }
        }
        if ($tariff_data->isNotEmpty()) {
            $response = $tariff_data->toArray();
            unset($tariff_data['links']);
            return $this->output(true, 'success', $response, 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }
}
