<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $event_id
 * @property int $athlete_id
 * @property string $rsvp
 * @property-read Athlete $athlete
 */
class EventParticipant extends Model
{
    /** @var list<string> */
    protected $fillable = ['event_id', 'athlete_id', 'rsvp'];

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
