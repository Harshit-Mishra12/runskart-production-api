<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password',
        'profile_picture',
        'is_verified',
        'role',
        'status',
        'otp',
        'verification_uid',
        'is_bank_details_verified',
        'activate_status'
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the user's bank details.
     */
    public function bankDetails()
    {
        return $this->hasOne(UserBankDetails::class, 'user_id');
    }

    /**
     * Get the user's documents.
     */
    public function documents()
    {
        return $this->hasMany(UserDocuments::class, 'user_id');
    }
    public function winners()
    {
        return $this->hasMany(Winner::class);
    }
    public function userBankDetails()
    {
        return $this->hasOne(UserBankDetails::class, 'user_id');
    }

    // Define relationship with UserDocuments
    public function userDocuments()
    {
        return $this->hasMany(UserDocuments::class, 'user_id');
    }
    public function teams()
    {
        return $this->hasMany(Team::class, 'user_id');
    }
}
