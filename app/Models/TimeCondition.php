<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeCondition extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',       
        'country_id',
        'company_id',
        'name',
        'time_zone',
        'time_group_id',
        'tc_match_destination_type',
        'tc_match_destination_id',
        'tc_non_match_destination_type',
        'tc_non_match_destination_id',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function timeGroup()
    {
        return $this->belongsTo(TimeGroup::class, 'time_group_id');
    }
    public function ringGroups()
    {
        return $this->belongsTo(RingGroup::class, 'tc_match_destination_id');
    }

    public function extensions()
    {
        return $this->belongsTo(Extension::class, 'tc_match_destination_id');
    }

    public function voiceMail()
    {
        return $this->belongsTo(VoiceMail::class, 'tc_match_destination_id');
    }

    public function queues()
    {
        return $this->belongsTo(Queue::class, 'tc_match_destination_id');
    }

    public function ivrs()
    {
        return $this->belongsTo(Ivr::class, 'tc_match_destination_id');
    }

    public function conferences()
    {
        return $this->belongsTo(Conference::class, 'tc_match_destination_id');
    }
}
