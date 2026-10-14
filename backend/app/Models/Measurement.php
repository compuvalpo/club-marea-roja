<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marca de desempeño (tiempo, distancia, repeticiones…).
 *
 * @property int $athlete_id
 * @property string $metric
 * @property float $value
 * @property string $unit
 * @property Carbon $recorded_at
 */
class Measurement extends Model
{
    /** @var list<string> */
    protected $fillable = ['athlete_id', 'practice_id', 'metric', 'value', 'unit', 'notes', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['value' => 'float', 'recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
