<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Match
 *
 * @package App\Models
 */
class Matches extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_match_id',
        'team1',
        'team2',
        'date_time',
        'venue',
        'status',
        'team1_url',
        'team2_url',
        'is_squad_announced'
    ];
    /**
     * Get the event that owns the match.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
