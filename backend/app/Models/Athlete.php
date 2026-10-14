<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $guardian_id
 * @property int|null $group_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $birth_date
 * @property int $monthly_fee
 * @property bool $active
 * @property-read string $full_name
 */
class Athlete extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'guardian_id', 'group_id', 'first_name', 'last_name', 'birth_date', 'monthly_fee', 'active'];

    protected function casts(): array
    {
        return ['monthly_fee' => 'integer', 'active' => 'boolean'];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return BelongsTo<TrainingGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * @param  Builder<Athlete>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
