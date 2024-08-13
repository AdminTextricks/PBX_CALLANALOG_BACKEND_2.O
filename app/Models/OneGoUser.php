<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OneGoUser extends Model
{
    use HasFactory;
    protected $table = 'one_go_user_steps';
    protected $fillable = [
        'id',
        'country_id',
        'company_id',
        'user_id',
        'tfn_id',
        'extension_id',
        'ring_id',
        'invoice_id',
        'payment_id',
        'step_no',
    ];

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
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
