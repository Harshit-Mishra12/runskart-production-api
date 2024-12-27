<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class EventMatch
 *
 * @package App\Models
 */
class EventMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'match_id'
    ];

    /**
     * Get the event that owns the event match.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the match that is associated with the event match.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function match()
    {
        return $this->belongsTo(Matches::class);
    }
}
