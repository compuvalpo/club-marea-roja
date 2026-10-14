<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriz de permisos por rol (RBAC) y visibilidad por fila.
 */
final class AuthorizationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function matrix(): iterable
    {
        yield 'entrenador no administra usuarios' => ['entrenador@marea.cl', 'GET', '/api/v1/users', 403];
        yield 'apoderado no ve finanzas' => ['apoderado@marea.cl', 'GET', '/api/v1/finance/summary', 403];
        yield 'alumno no ve morosos' => ['alumno@marea.cl', 'GET', '/api/v1/finance/delinquents', 403];
        yield 'entrenador no ve finanzas' => ['entrenador@marea.cl', 'GET', '/api/v1/finance/charges', 403];
        yield 'entrenador no crea eventos' => ['entrenador@marea.cl', 'POST', '/api/v1/events', 403];
        yield 'apoderado no edita horarios' => ['apoderado@marea.cl', 'GET', '/api/v1/schedules', 403];
        yield 'admin ve usuarios' => ['admin@marea.cl', 'GET', '/api/v1/users', 200];
        yield 'apoderado ve sus cargos' => ['apoderado@marea.cl', 'GET', '/api/v1/me/charges', 200];
        yield 'entrenador no usa me/charges' => ['entrenador@marea.cl', 'GET', '/api/v1/me/charges', 403];
    }

    #[DataProvider('matrix')]
    public function test_role_matrix(string $email, string $method, string $uri, int $expected): void
    {
        $this->api($method, $uri, $method === 'POST' ? [] : null, $this->login($email))->assertStatus($expected);
    }

    public function test_guardian_only_sees_own_children(): void
    {
        $names = $this->api('GET', '/api/v1/athletes', token: $this->guardian())->json('data.*.full_name');

        self::assertEqualsCanonicalizing(['Matías Herrera', 'Sofía Herrera'], $names);
    }

    public function test_athlete_cannot_see_other_athlete_stats(): void
    {
        $token = $this->athlete();
        $this->api('GET', '/api/v1/athletes/1/stats', token: $token)->assertOk();
        $this->api('GET', '/api/v1/athletes/4/stats', token: $token)->assertForbidden();
    }

    public function test_coach_sees_athletes_of_his_groups(): void
    {
        $names = $this->api('GET', '/api/v1/athletes', token: $this->coach())->json('data.*.full_name');

        self::assertContains('Sofía Herrera', $names);     // Infantil (grupo propio)
        self::assertContains('Catalina Reyes', $names);    // Máster (grupo propio)
        self::assertContains('Matías Herrera', $names);    // Juvenil (prácticas de sábado asignadas)
    }

    public function test_stats_include_attendance_and_metrics(): void
    {
        $stats = $this->api('GET', '/api/v1/athletes/1/stats', token: $this->guardian())->json('data');

        self::assertGreaterThan(0, $stats['attendance']['total']);
        self::assertContains('50 m libre', array_column($stats['metrics'], 'metric'));
    }

    public function test_user_management_validates_and_protects_self(): void
    {
        $token = $this->admin();
        $this->api('POST', '/api/v1/users', ['name' => 'X', 'email' => 'admin@marea.cl', 'password' => 'corta', 'role' => 'jefe'], $token)
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['message', 'errors' => ['name', 'email', 'password', 'role']]]);

        $this->api('POST', '/api/v1/users', ['name' => 'Nuevo Apoderado', 'email' => 'NUEVO@marea.cl', 'password' => 'Frase-segura-2026', 'role' => 'guardian'], $token)
            ->assertCreated()->assertJsonPath('data.email', 'nuevo@marea.cl');

        $this->api('DELETE', '/api/v1/users/1', token: $token)->assertStatus(409);
    }
}
