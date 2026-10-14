<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mensualidad de un alumno para un período (AAAA-MM). Montos en CLP.
 *
 * @property int $id
 * @property int $athlete_id
 * @property string $period
 * @property int $amount
 * @property string $due_date
 * @property string $status
 * @property int|null $paid_amount
 * @property-read Athlete $athlete
 */
class Charge extends Model
{
    /** @var list<string> */
    protected $fillable = ['athlete_id', 'period', 'amount', 'due_date', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): int
    {
        return (int) ($this->paid_amount ?? $this->payments()->sum('amount'));
    }

    public function balance(): int
    {
        return max(0, $this->amount - $this->paidAmount());
    }
}
