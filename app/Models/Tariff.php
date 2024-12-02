<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;
    protected $fillable = [
        'tariff_name',
        'status' 
    ];

    /**
     * Get the outboundcallrate for the blog post.
     */
    public function outboundcallrates()
    {
        return $this->hasMany(OutboundCallRate::class);
    }
}
