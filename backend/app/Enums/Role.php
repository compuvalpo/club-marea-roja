<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles del sistema. El valor se persiste en base de datos y viaja en el JWT.
 */
enum Role: string
{
    case Admin = 'admin';
    case Coach = 'coach';
    case Guardian = 'guardian';
    case Athlete = 'athlete';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Coach => 'Entrenador',
            self::Guardian => 'Apoderado',
            self::Athlete => 'Alumno',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }
}
