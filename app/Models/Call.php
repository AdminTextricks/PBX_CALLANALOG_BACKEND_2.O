<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'uniqueid',
        'company_id',
        'account_code',
        'country_id',
        'call_date',
        'call_start_time',
        'answer_time',
        'hangup_time',
        'agent_channel',
        'agent_name',
        'agent_number',
        'duration',
        'billsec',
        'disposition',
        'call_type',
        'Recording',
        'caller_num',
        'tfn',
        'destination_type',
        'destination',
        'hangup_cause',
        'receive_ip',
        'codec',
        'cost',
    ];
    
    /**
     * Get the user that owns the Company
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class,'company_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
