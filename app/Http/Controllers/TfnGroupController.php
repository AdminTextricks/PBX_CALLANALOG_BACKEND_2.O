<?php

namespace App\Http\Controllers;

use App\Models\TfnGroups;
use Illuminate\Http\Request;

class TfnGroupController extends Controller
{
    public function getAllActiveTfngroup(Request $request)
    {
        $tfngroup = TfnGroups::select()->where('status', 1)->get();
        if ($tfngroup->isNotEmpty()) {
            return $this->output(true, 'success', $tfngroup->toArray(), 200);
        } else {
            return $this->output(true, 'No Record Found', [], 200);
        }
    }
}
