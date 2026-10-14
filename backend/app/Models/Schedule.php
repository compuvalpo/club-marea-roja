<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regla semanal recurrente a partir de la cual se generan prácticas.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $coach_id
 * @property int|null $venue_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 * @property string $valid_from
 * @property string|null $valid_to
 */
class Schedule extends Model
{
    /** @var list<string> */
    protected $fillable = ['group_id', 'coach_id', 'venue_id', 'weekday', 'start_time', 'end_time', 'valid_from', 'valid_to', 'active'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'active' => 'boolean'];
    }

    /** @return BelongsTo<TrainingGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
