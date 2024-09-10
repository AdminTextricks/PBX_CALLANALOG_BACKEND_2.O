<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeGroup extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',       
        'name',
        'time_to_start',
        'time_to_finish',
        'week_day_start',
        'week_day_finish',
        'month_day_start',
        'month_day_finish',
        'month_start',
        'month_finish',      
    ];
}
