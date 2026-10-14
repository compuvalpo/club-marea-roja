<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property string|null $phone
 * @property bool $active
 */
class User extends Authenticatable
{
    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'active'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** @return HasMany<Athlete, $this> */
    public function wards(): HasMany
    {
        return $this->hasMany(Athlete::class, 'guardian_id');
    }

    /** @return HasOne<Athlete, $this> */
    public function athleteProfile(): HasOne
    {
        return $this->hasOne(Athlete::class, 'user_id');
    }

    /** @return HasMany<TrainingGroup, $this> */
    public function coachedGroups(): HasMany
    {
        return $this->hasMany(TrainingGroup::class, 'coach_id');
    }
}
