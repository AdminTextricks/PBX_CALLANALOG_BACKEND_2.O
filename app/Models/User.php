<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasPermissionsTrait;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasPermissionsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
      protected $fillable = [
		'company_id',
		'account_code',
        'name',
        'email',
        'password',
		'mobile',
        'address',
        'country_id',
        'state_id',
        'city',
        'zip',    
        'role_id',
        'is_verified',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

	public function company()
    {
        return $this->belongsTo(Company::class);
    }
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function user_role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the user that owns the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the user that owns the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function tfn()
    {
        return $this->hasMany(Tfn::class);
    }

    /**
     * Get the resellerPrices for the blog post.
     */
    public function resellerPrices()
    {
        return $this->hasMany(ResellerPrice::class);
    }

    public function mainPrices()
    {
        return $this->hasMany(MainPrice::class, 'user_id');
    }
}
