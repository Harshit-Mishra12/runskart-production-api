<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTransaction extends Model
{
    use HasFactory;

    // Table associated with the model
    protected $table = 'user_transactions';

    // Primary key
    protected $primaryKey = 'id';

    // Disable auto-incrementing if necessary (Laravel defaults to this)
    public $incrementing = true;

    // Fields that are mass assignable
    protected $fillable = [
        'user_id',
        'team_id',
        'amount',
        'transaction_type',  // 'credit' or 'debit'
        'description',
        'transaction_id',
        'status',
        'transaction_usecase'
    ];

    // Define relationship: One user can have many transactions
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Define optional relationship to teams, if needed
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
    public function userBankDetails()
    {
        return $this->hasOne(UserBankDetails::class, 'user_id', 'user_id');
    }
}
