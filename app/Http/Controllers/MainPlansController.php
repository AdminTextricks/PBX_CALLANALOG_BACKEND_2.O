<?php

namespace App\Http\Controllers;

use App\Models\MainPlan;
use Illuminate\Http\Request;

class MainPlansController extends Controller
{
    public function getAllActivePlans(Request $request)
    {
        $user = \Auth::user();
        $mainplans = MainPlan::select()->where('status', 1)->get();
        if ($mainplans->isNotEmpty()) {
            return $this->output(true, 'success', $mainplans->toArray());
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }


    public function getAllPlans(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $mainplan_id = $request->id ?? NULL;
        if ($mainplan_id) {
            $mainplans = MainPlan::slecet()->where('id', $request->id)->get();
        } else {
            if ($params !== "") {
                $mainplans = MainPlan::select()->where('name', 'LIKE', "%$params%")->paginate($perpage = $perPageNo, $column = ['*'], $pageName = 'page');
            } else {
                $mainplans = MainPlan::select()->paginate($perpage = $perPageNo, $column = ['*'], $pageName = 'page');
            }
        }

        if ($mainplans->isNotEmpty()) {
            $dd = $mainplans->toArray();
			unset($dd['links']);
            return $this->output(true, 'success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }
}
