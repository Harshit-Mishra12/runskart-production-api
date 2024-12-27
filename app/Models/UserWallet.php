<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWallet extends Model
{
    use HasFactory;

    // Table associated with the model
    protected $table = 'user_wallets';

    // Primary key
    protected $primaryKey = 'id';

    // Disable auto-incrementing if necessary (Laravel defaults to this)
    public $incrementing = true;

    // Fields that are mass assignable
    protected $fillable = [
        'user_id',
        'balance',
    ];

    // Define relationship: One user has one wallet
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Wallet can have multiple transactions
    public function transactions()
    {
        return $this->hasMany(UserTransaction::class, 'user_id', 'user_id');
    }
}
