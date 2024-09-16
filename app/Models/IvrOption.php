<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrOption extends Model
{
    use HasFactory;
		
    protected $fillable = [
        'company_id',
        'ivr_id',
        'input_digit',
        'destination_type_id',
        'destination_id',
        'parent_id',
    ];

    /**
     * Get the user that owns the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ivr()
    {
        return $this->belongsTo(Ivr::class);
    }
}
