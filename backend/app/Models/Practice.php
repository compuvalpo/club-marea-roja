<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ocurrencia concreta de entrenamiento.
 *
 * @property int $id
 * @property int|null $schedule_id
 * @property int $group_id
 * @property int|null $coach_id
 * @property int|null $venue_id
 * @property string $date
 * @property string $start_time
 * @property string $end_time
 * @property string|null $objectives
 * @property string|null $notes
 * @property string $status
 * @property-read TrainingGroup $group
 * @property-read User|null $coach
 * @property-read Venue|null $venue
 */
class Practice extends Model
{
    /** @var list<string> */
    protected $fillable = ['schedule_id', 'group_id', 'coach_id', 'venue_id', 'date', 'start_time', 'end_time', 'objectives', 'notes', 'status'];

    public const STATUSES = ['scheduled', 'done', 'cancelled'];

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

    /** @return HasMany<Attendance, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<Measurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->coach_id === $user->id;
    }
}
