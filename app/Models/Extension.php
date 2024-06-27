<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'country_id',
        'company_id',
        'intercom',
        'name',
        'account_code',
        'regexten',
        'amaflags',
        'callgroup',
        'callerid',
        'canreinvite',
        'context',
        'defaultip',
        'dtmfmode',
        'fromuser',
        'fromdomain',
        'host',
        'insecure',
        'language',
        'mailbox',
        'barge',
        'outbound_call',
        'call_recording',
        'voice_mail',
        'nat',
        'permit',
        'deny',
        'mask',
        'pickupgroup',
        'port',
        'qualify',
        'restrictcid',
        'rtptimeout',
        'rtpholdtimeout',
        'secret',
        'type',
        'username',
        'useragent',
        'disallow',
        'allow',
        'musiconhold',
        'regseconds',
        'ipaddr',
        'cancallforward',
        'fullcontact',
        'setvar',
        'regserver',
        'lastms',
        'callbackextension',
        'status'        
    ];

    /**
     * Get the user that owns the Company
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
