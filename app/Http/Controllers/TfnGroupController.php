<?php

namespace App\Http\Controllers;

use App\Models\TfnGroups;
use Illuminate\Http\Request;

class TfnGroupController extends Controller
{
    public function getAllActiveTfngroup(Request $request)
    {
        $user = \Auth::user();
        $tfngroup = TfnGroups::select()->where('status', 1)->get();
        if ($tfngroup->isNotEmpty()) {
            return $this->output(true, 'success', $tfngroup->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }

    public function getAllTfngroup(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";

        $tfngroup_id = $request->id ?? NULL;
        if ($tfngroup_id) {
            $tfngroupData = TfnGroups::select()->where('id', $tfngroup_id)->get();
        } else {
            if ($params !== "") {
                $tfngroupData = TfnGroups::select()->where('tfngroup_name', 'LIKE', "%$params%")->paginate($perpage = $perPageNo, $column = ['*'], $pageName = 'page');
            } else {
                $tfngroupData = TfnGroups::select()->paginate($perpage = $perPageNo, $column = ['*'], $pageName = 'page');
            }
        }
        if ($tfngroupData->isNotEmpty()) {
            $response = $tfngroupData->toArray();
            unset($tfngetAll_data['links']);
            return $this->output(true, 'success', $response, 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }
}
