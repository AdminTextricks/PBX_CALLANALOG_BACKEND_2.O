<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;
    protected $table = 'countries';

    /**
     * Get all of the numbers for the Number
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function numbers()
    {
        return $this->hasMany(Number::class);
    }

    public function createinvoice(){
        return $this->hasMany(CreateInvoice::class);
    }


}
