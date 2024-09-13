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

    public function match_destination_type()
    {
        return $this->belongsTo(DestinationType::class, 'tc_match_destination_type');
    }

    public function non_match_destination_type()
    {
        return $this->belongsTo(DestinationType::class, 'tc_non_match_destination_type');
    }

    public function ringGroup()
    {
        return $this->belongsTo(RingGroup::class, 'tc_match_destination_id');
    }
    public function ringGroup_()
    {
        return $this->belongsTo(RingGroup::class, 'tc_non_match_destination_id');
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'tc_match_destination_id');
    }
    public function extension_()
    {
        return $this->belongsTo(Extension::class, 'tc_non_match_destination_id');
    }

    public function voiceMail()
    {
        return $this->belongsTo(VoiceMail::class, 'tc_match_destination_id');
    }
    public function voiceMail_()
    {
        return $this->belongsTo(VoiceMail::class, 'tc_non_match_destination_id');
    }

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'tc_match_destination_id');
    }
    public function queue_()
    {
        return $this->belongsTo(Queue::class, 'tc_non_match_destination_id');
    }

    public function ivr()
    {
        return $this->belongsTo(Ivr::class, 'tc_match_destination_id');
    }
    public function ivr_()
    {
        return $this->belongsTo(Ivr::class, 'tc_non_match_destination_id');
    }

    public function conference()
    {
        return $this->belongsTo(Conference::class, 'tc_match_destination_id');
    }
    public function conference_()
    {
        return $this->belongsTo(Conference::class, 'tc_non_match_destination_id');
    }
}
