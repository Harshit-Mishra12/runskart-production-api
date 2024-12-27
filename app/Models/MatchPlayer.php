<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchPlayer extends Model
{
    use HasFactory;

    // Specify the table associated with the model
    protected $table = 'match_players';

    // Specify the fillable attributes for mass assignment
    protected $fillable = [
        'player_id',
        'match_id',
        'event_id',
        'name',
        'role',
        'country',
        'image_url',
        'external_player_id',
        'status',
        'team',
    ];

    /**
     * Define a relationship with the Player model.
     */
    // public function player()
    // {
    //     return $this->belongsTo(Player::class);
    // }

    // /**
    //  * Define a relationship with the Match model.
    //  */
    // public function match()
    // {
    //     return $this->belongsTo(Match::class);
    // }
}
