<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Event
 *
 * @package App\Models
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'go_live_date',
        'event_start_time',
        'team_size',
        'batsman_limit',
        'bowler_limit',
        'all_rounder_limit',
        'wicketkeeper_limit',
        'team_creation_cost',
        'user_participation_limit',
        'team_limit_per_user',
        'winners_limit',
        'status',

    ];

    /**
     * Get the matches for the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function matches()
    {
        return $this->hasMany(Matches::class);
    }

    /**
     * Get the prizes for the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function prizes()
    {
        return $this->hasMany(EventPrize::class);
    }

    /**
     * Get the event matches.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventMatches()
    {
        return $this->hasMany(EventMatch::class);
    }

    public function userTeams()
    {
        return $this->hasMany(UserTeam::class);
    }
    public function winners()
    {
        return $this->hasMany(Winner::class);
    }
    public function teams()
    {
        return $this->hasMany(Team::class, 'event_id');
    }
}
