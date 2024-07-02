<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Queue extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'country_id',
        'company_id',
        'name',
        'queue_name',
        'description',
        'musiconhold',
        'announce',
        'context',
        'timeout',
        'monitor_join',
        'monitor_format',
        'queue_youarenext',
        'queue_thereare',
        'queue_callswaiting',
        'queue_holdtime',
        'queue_minutes',
        'queue_seconds',
        'queue_lessthan',
        'queue_thankyou',
        'queue_reporthold',
        'announce_frequency',
        'announce_round_seconds',
        'announce_holdtime',
        'retry',
        'wrapuptime',
        'maxlen',
        'servicelevel',
        'strategy',
        'joinempty',
        'leavewhenempty',
        'eventmemberstatus',
        'eventwhencalled',
        'reportholdtime',
        'memberdelay',
        'weight',
        'timeoutrestart',
        'periodic_announce',
        'periodic_announce_frequency',
        'ringinuse',
        'setinterfacevar',
        'monitor_type',
        'status'        
    ];

    /**
     * Get the user that owns the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
