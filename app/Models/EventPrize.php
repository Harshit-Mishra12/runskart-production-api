<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class EventPrize
 *
 * @package App\Models
 */
class EventPrize extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'rank_from',
        'rank_to',
        'prize_amount',
        'type'
    ];

    /**
     * Get the event that owns the prize.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    public function winners()
{
    return $this->hasMany(Winner::class);
}

}
