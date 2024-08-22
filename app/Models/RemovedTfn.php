<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemovedTfn extends Model
{
    use HasFactory;

    protected $fillable = [
        'tfn_number', 'country_id', 'deleted_by', 'company_id', 'status',
    ];
}
