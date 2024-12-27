<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'event_id',
        'captain_match_player_id',
        'name',
        'status',
        'points_scored',
        'rank'
    ];

    /**
     * Get the user that owns the team.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the players that belong to the team.
     */
    public function players()
    {
        return $this->hasMany(TeamPlayer::class);
    }

    public function userTransaction()
    {
        return $this->hasOne(UserTransaction::class, 'team_id')
            ->where('transaction_type', 'credit');
    }
}
