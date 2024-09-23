<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TfnsHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'assign_by',
        'tfn_number',
        'payment_for',
        'message',
        'status',
    ];
}
