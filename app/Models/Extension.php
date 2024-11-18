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
        'startingdate',
        'expirationdate',
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
        'agent_name',
        'barge',
        'recording',
        'sip_temp',
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
        return $this->belongsTo(Company::class,'company_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function userRegisteredServer(){
        return $this->hasOne(UserRegisteredServer::class, 'company_id', 'company_id');
    }
}
