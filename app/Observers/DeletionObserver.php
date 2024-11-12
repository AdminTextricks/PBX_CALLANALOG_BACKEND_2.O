<?php

namespace App\Observers;

use App\Models\DeletedHistory;
use Illuminate\Support\Facades\Auth;

class DeletionObserver
{
    public function deleting($model)
    {
        $user = Auth::user();

        $deletedData = null;

        if ($model instanceof \App\Models\Extension) {
            $deletedData = ['name' => $model->name, 'company_id' => $model->company_id, 'country_id' => $model->country_id, 'expirationdate' => $model->expirationdate];
        }

        if ($model instanceof \App\Models\Ivr) {
            $deletedData = ['name' => $model->name, 'company_id' => $model->company_id, 'country_id' => $model->country_id];
        }

        if ($model instanceof \App\Models\Trunk) {
            $deletedData = ['type' => $model->type, 'name' => $model->name, 'prefix' => $model->prefix, 'ip' => $model->ip];
        }

        if ($model instanceof \App\Models\Tfn) {
            $deletedData = ['tfn_number' => $model->tfn_number, 'tfn_provider' => $model->tfn_provider, 'company_id' => $model->company_id, 'country_id' => $model->country_id];
        }
        
        if ($model instanceof \App\Models\BlockNumber) {
            $deletedData = ['blocknumber_data' => $model];
        }

        if ($model instanceof \App\Models\RingGroup) {
            $deletedData = ['ringno' => $model->ringno,'company_id' => $model->company_id, 'country_id' => $model->country_id];
        }

        if ($model instanceof \App\Models\Queue) {
            $deletedData = ['name' => $model->name,'company_id' => $model->company_id, 'country_id' => $model->country_id];
        }

        if ($model instanceof \App\Models\Conference) {
            $deletedData = ['conf_name' => $model->conf_name,'company_id' => $model->company_id, 'country_id' => $model->country_id];
        }

        if ($model instanceof \App\Models\OneGoUser) {
            $deletedData = ['parent_id' => $model->parent_id, 'user_id' => $model->user_id,'company_id' => $model->company_id, 'country_id' => $model->country_id];
        }

        if ($model instanceof \App\Models\VoiceMail) {
            $deletedData = ['mailbox' => $model->mailbox, 'company_id' => $model->company_id,'email' => $model->email];
        }

        if ($model instanceof \App\Models\TimeCondition) {
            $deletedData = ['company_id' => $model->company_id, 'country_id' => $model->country_id,'name' => $model->name, 'time_group_id' => $model->time_group_id];
        }

        DeletedHistory::create([
            'deleted_id' => $model->id,
            'model_name' => get_class($model),
            'deleted_data' => $deletedData,
            'deleted_by' => $user ? $user->id : null,
            'company_id' => $user ? $user->company_id : null,
        ]);
    }
}
