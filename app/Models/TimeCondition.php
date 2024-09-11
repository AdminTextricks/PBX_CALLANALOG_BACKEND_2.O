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
}
