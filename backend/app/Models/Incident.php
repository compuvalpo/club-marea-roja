<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lesión u otra anomalía registrada en una práctica.
 */
class Incident extends Model
{
    /** @var list<string> */
    protected $fillable = ['practice_id', 'athlete_id', 'type', 'severity', 'description', 'recorded_by', 'created_at'];

    public const TYPES = ['injury', 'behavior', 'equipment', 'other'];

    public const SEVERITIES = ['low', 'medium', 'high'];

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
