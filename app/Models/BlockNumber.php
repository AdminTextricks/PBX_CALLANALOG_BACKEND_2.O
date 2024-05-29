<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockNumber extends Model
{
    use HasFactory;
    protected $fillable = [
        'digits',
        'company_id',
        'transfer_number',
        'subject',
        'ruletype',
        'blocktype',
        'status',
    ];

    /**
     * Get the user that owns the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
