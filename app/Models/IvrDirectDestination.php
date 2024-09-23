<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrDirectDestination extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'ivr_id',
        'authentication',
        'authentication_type',
        'authentication_digit',
        'destination_type_id',
        'destination_id',
    ];


    public function Ivr()
    {
        return $this->belongsTo(Ivr::class, 'ivr_id', 'id');
    }

    public function AuthenticationType()
    {
        return $this->belongsTo( AuthenticationOption::class,'authentication_type');
    }
    
    public function destination_type()
    {
        return $this->belongsTo(DestinationType::class, 'destination_type_id', 'id');
    }


    
    /*******  */
    
    /**
     * Get the user that owns the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ivr_()
    {
        return $this->belongsTo(Ivr::class, 'destination_id', 'id');
    }

    public function ringGroup()
    {
        return $this->belongsTo(RingGroup::class, 'destination_id','id');
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'destination_id', 'id');
    }

    public function voiceMail()
    {
        return $this->belongsTo(VoiceMail::class, 'destination_id', 'id');
    }

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'destination_id', 'id');
    }

    public function conference()
    {
        return $this->belongsTo(Conference::class, 'destination_id','id');
    }
   
    public function timeCondition()
    {
        return $this->belongsTo(TimeCondition::class, 'destination_id','id');
    }
   
}
