<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeletedHistory extends Model
{
    use HasFactory;

    protected $fillable = ['deleted_id', 'model_name', 'deleted_data', 'deleted_by', 'company_id'];

    protected $casts = [
        'deleted_data' => 'array',
    ];
}
