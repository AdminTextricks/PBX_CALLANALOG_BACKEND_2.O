<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'extension_name',
        'extension_ip'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
