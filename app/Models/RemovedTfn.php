<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemovedTfn extends Model
{
    use HasFactory;

    protected $fillable = [
        'tfn_number',
        'country_id',
        'deleted_by',
        'company_id',
        'status',
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'deleted_by', 'id');
    }

    public function countries()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
    public function Company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
}
