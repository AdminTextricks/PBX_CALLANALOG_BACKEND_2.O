<?php

namespace App\Http\Controllers;

use App\Models\MainPlan;
use Illuminate\Http\Request;

class MainPlansController extends Controller
{
    public function getAllActivePlans(Request $request)
    {
        $mainplans = MainPlan::select()->where('status', 1)->get();
        if ($mainplans->isNotEmpty()) {
            return $this->output(true, 'success', $mainplans->toArray());
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }


    public function getAllPlans(Request $request)
    {
        $mainplans = MainPlan::select()->get();
        if ($mainplans->isNotEmpty()) {
            return $this->output(true, 'success', $mainplans->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }
}
