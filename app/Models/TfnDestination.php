<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TfnDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'tfn_id', 'destination_type_id', 'destination_id', 'priority'
    ];

    public function destination_types()
    {
        return $this->belongsTo(DestinationType::class, 'destination_type_id', 'id');
    }

    public function tfns()
    {
        return $this->belongsTo(Tfn::class, 'tfn_id', 'id');
    }

    public function tfn()
    {
        return $this->belongsTo(Tfn::class, 'tfn_id', 'id');
    }

    public function destinationType()
    {
        return $this->belongsTo(DestinationType::class, 'destination_type_id', 'id');
    }
    public function ringGroups()
    {
        return $this->belongsTo(RingGroup::class, 'destination_id', 'id');
    }

    public function extensions()
    {
        return $this->belongsTo(Extension::class, 'destination_id', 'id');
    }

    public function voice_mail()
    {
        return $this->belongsTo(VoiceMail::class, 'destination_id', 'id');
    }

    public function queues()
    {
        return $this->belongsTo(Queue::class, 'destination_id', 'id');
    }

    // public function ivrs()
    // {
    //     return $this->belongsTo(IVR::class, 'destination_id', 'id');
    // }

    // public function conferences()
    // {
    //     return $this->belongsTo(Conference::class, 'destination_id', 'id');
    // }

    public function getExternalNumber($destinationId)
    {
        if ($destinationId == 4) {
            return;
        }
    }

    public function getPbxIP($destinationId)
    {
        if ($destinationId == 7) {
            return;
        }
    }
}
