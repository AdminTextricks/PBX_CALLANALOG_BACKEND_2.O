<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrOption extends Model
{
    use HasFactory;
		
    protected $fillable = [
        'company_id',
        'ivr_id',
        'input_digit',
        'destination_type_id',
        'destination_id',
        'parent_id',
    ];

    /**
     * Get the user that owns the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ivr()
    {
        return $this->belongsTo(Ivr::class);
    }

    public function childrenRecursive()
    {
        return $this->hasMany(IvrOption::class, 'parent_id')->with('childrenRecursive');
    }

    public function destination_type()
    {
        return $this->belongsTo(DestinationType::class, 'destination_type_id');
    }


    public function ringGroup()
    {
        return $this->belongsTo(RingGroup::class, 'destination_id');
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'destination_id');
    }

    public function voiceMail()
    {
        return $this->belongsTo(VoiceMail::class, 'destination_id');
    }

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'destination_id');
    }

    public function conference()
    {
        return $this->belongsTo(Conference::class, 'destination_id');
    }
   
    public function timeCondition()
    {
        return $this->belongsTo(TimeCondition::class, 'destination_id');
    }
}
