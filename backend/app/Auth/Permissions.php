<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\Role;
use App\Models\User;

/**
 * Matriz única de permisos por rol (RBAC).
 *
 * - El backend la aplica en las rutas (middleware "can:permiso", ver AppServiceProvider) y es la fuente de verdad.
 * - El frontend la recibe en el login/refresh (campo "permissions") y la usa para mostrar menús, rutas y botones,
 *   de modo que ambas capas validan con la misma regla.
 * - La autorización por fila (qué alumno o práctica) la resuelven las Policies y AccessScope.
 */
final class Permissions
{
    private const ALL = [Role::Admin, Role::Coach, Role::Guardian, Role::Athlete];

    /** @var array<string, list<Role>> */
    private const MATRIX = [
        'dashboard.view' => self::ALL,
        'users.manage' => [Role::Admin],
        'athletes.view' => self::ALL,
        'athletes.list' => [Role::Admin, Role::Coach, Role::Guardian],
        'athletes.manage' => [Role::Admin],
        'catalog.manage' => [Role::Admin],
        'schedules.manage' => [Role::Admin],
        'practices.view' => self::ALL,
        'practices.plan' => [Role::Admin],
        'practices.manage' => [Role::Admin, Role::Coach],
        'practices.rsvp' => [Role::Admin, Role::Guardian, Role::Athlete],
        'events.view' => self::ALL,
        'events.manage' => [Role::Admin],
        'events.rsvp' => [Role::Admin, Role::Guardian, Role::Athlete],
        'finance.manage' => [Role::Admin],
        'finance.own' => [Role::Guardian, Role::Athlete],
        'stats.own' => [Role::Athlete],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::MATRIX);
    }

    public static function allows(User $user, string $permission): bool
    {
        return $user->active && in_array($user->role, self::MATRIX[$permission] ?? [], true);
    }

    /** @return list<string> */
    public static function for(User $user): array
    {
        return array_values(array_filter(self::all(), static fn (string $p): bool => self::allows($user, $p)));
    }
}
