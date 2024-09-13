<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoiceMail extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'company_id',
        'context',
        'mailbox',
        'password',
        'fullname',
        'email',
        'pager',        
        'timezone',        
        'attach',        
        'saycid',        
        'dialout',        
        'callback',        
        'review',        
        'operator',        
        'envelope',        
        'sayduration',        
        'saydurationm',        
        'sendvoicemail',        
        'nextaftercmd',        
        'forcename',        
        'forcegreetings',        
        'hidefromdir',        
        'audio_id',
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

    public function audio()
    {
        return $this->belongsTo(IvrMedia::class, 'audio_id', 'id');
    }
}
