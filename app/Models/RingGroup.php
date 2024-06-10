<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'country_id',
        'company_id',
        'ringno',
        'strategy',
        'ringtime',
        'description',
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

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
