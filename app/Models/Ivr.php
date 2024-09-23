<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ivr extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'company_id',
        'country_id',
        'name',
        'description',
        'ivr_media_id',
        'timeout',
        'direct_destination',
        'status',
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

    public function IvrMedia()
    {
        return $this->belongsTo(IvrMedia::class,'ivr_media_id');
    }

    public function ivrDirectDestination()
    {
        return $this->hasOne(IvrDirectDestination::class,'ivr_id', 'id');
    }
    
    
    
}
