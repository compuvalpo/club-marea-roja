<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Evento ocasional; el lugar puede ser un recinto registrado o texto libre.
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property int|null $venue_id
 * @property string|null $location_text
 * @property string $start_at
 * @property string $end_at
 * @property-read Venue|null $venue
 */
class Event extends Model
{
    /** @var list<string> */
    protected $fillable = ['title', 'description', 'type', 'venue_id', 'location_text', 'start_at', 'end_at', 'created_by'];

    public const TYPES = ['competition', 'meeting', 'social', 'other'];

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<EventParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }
}
