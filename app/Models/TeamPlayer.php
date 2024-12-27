<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPlayer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'team_id',
        'match_player_id',
    ];

    /**
     * Get the team that owns the player.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the match player associated with the team player.
     */
    public function matchPlayer()
    {
        return $this->belongsTo(MatchPlayer::class);
    }
}
