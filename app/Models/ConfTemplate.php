<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfTemplate extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'template_id',
        'template_name',
        'template_contents',
        'user_group',
        'status',
    ];
}
