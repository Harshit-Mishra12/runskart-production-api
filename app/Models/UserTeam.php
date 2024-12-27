<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'total_points',
    ];

    /**
     * Get the user that owns the team.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event that the team is associated with.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the players for the team.
     */
    // public function players()
    // {
    //     return $this->belongsToMany(Player::class, 'player_teams')
    //                 ->withPivot('position', 'is_leader')
    //                 ->withTimestamps();
    // }
}


