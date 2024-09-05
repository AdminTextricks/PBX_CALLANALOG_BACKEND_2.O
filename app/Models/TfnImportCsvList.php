<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TfnImportCsvList extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'tfn_import_csv',
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
        return $this->belongsTo(Company::class);
    }
}
