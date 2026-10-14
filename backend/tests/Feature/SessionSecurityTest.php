<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sesiones, revocación de permisos, expiración, CSRF/Origin, inyección SQL y RBAC.
 */
final class SessionSecurityTest extends TestCase
{
    private function loginResponse(string $email, array $headers = []): TestResponse
    {
        return $this->api('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'Marea2026!'], headers: $headers);
    }

    public function test_new_login_closes_the_previous_session_of_the_same_user(): void
    {
        $first = $this->loginResponse('entrenador@marea.cl');
        $second = $this->loginResponse('entrenador@marea.cl');
        self::assertNotSame($first->json('data.session_id'), $second->json('data.session_id'));

        $this->api('GET', '/api/v1/auth/me', token: $first->json('data.access_token'))
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'Se inició sesión en otra pestaña o dispositivo. Esta sesión fue cerrada.');
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $this->refreshCookie($first)])->assertStatus(401);
        $this->api('GET', '/api/v1/auth/me', token: $second->json('data.access_token'))->assertOk();
    }

    public function test_sessions_of_different_users_do_not_interfere(): void
    {
        $coach = $this->loginResponse('entrenador@marea.cl');
        $guardian = $this->loginResponse('apoderado@marea.cl');

        $this->api('GET', '/api/v1/auth/me', token: $coach->json('data.access_token'))->assertOk()->assertJsonPath('data.email', 'entrenador@marea.cl');
        $this->api('GET', '/api/v1/auth/me', token: $guardian->json('data.access_token'))->assertOk()->assertJsonPath('data.email', 'apoderado@marea.cl');
    }

    public function test_logout_invalidates_the_access_token_immediately(): void
    {
        $login = $this->loginResponse('alumno@marea.cl');
        $token = (string) $login->json('data.access_token');

        $this->api('POST', '/api/v1/auth/logout', token: $token, cookies: ['refresh_token' => $this->refreshCookie($login)])->assertNoContent();
        $this->api('GET', '/api/v1/auth/me', token: $token)->assertStatus(401);
    }

    public function test_token_scoped_logout_keeps_the_cookie_of_another_tab(): void
    {
        $coach = $this->loginResponse('entrenador@marea.cl');
        $guardian = $this->loginResponse('apoderado@marea.cl'); // la otra pestaña reemplazó la cookie

        $response = $this->api('POST', '/api/v1/auth/logout', ['scope' => 'token'], $coach->json('data.access_token'), ['refresh_token' => $this->refreshCookie($guardian)]);
        $response->assertNoContent();
        self::assertNull($response->getCookie('refresh_token', false));

        $this->api('GET', '/api/v1/auth/me', token: $coach->json('data.access_token'))->assertStatus(401);
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $this->refreshCookie($guardian)])->assertOk();
    }

    public function test_changing_role_or_deactivating_revokes_permissions_immediately(): void
    {
        $admin = $this->admin();
        $coach = $this->loginResponse('entrenador@marea.cl');
        $coachId = (int) $coach->json('data.user.id');

        $this->api('PATCH', "/api/v1/users/{$coachId}", ['role' => 'guardian'], $admin)->assertOk();
        $this->api('GET', '/api/v1/practices?from=2026-10-01&to=2026-10-31', token: $coach->json('data.access_token'))
            ->assertStatus(401)->assertJsonPath('error.message', 'Sus permisos cambiaron. Inicie sesión nuevamente.');
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $this->refreshCookie($coach)])->assertStatus(401);

        $relogged = $this->loginResponse('entrenador@marea.cl');
        self::assertSame('guardian', $relogged->json('data.user.role'));
        self::assertNotContains('practices.manage', $relogged->json('data.user.permissions'));

        $this->api('PATCH', "/api/v1/users/{$coachId}", ['active' => false], $admin)->assertOk();
        $this->api('GET', '/api/v1/auth/me', token: $relogged->json('data.access_token'))->assertStatus(401);
        $this->loginResponse('entrenador@marea.cl')->assertStatus(401);
    }

    public function test_admin_can_close_all_sessions_of_a_user(): void
    {
        $guardian = $this->loginResponse('apoderado@marea.cl');
        $id = (int) $guardian->json('data.user.id');

        $this->api('POST', "/api/v1/users/{$id}/revoke-sessions", token: $this->admin())->assertOk()->assertJsonPath('data.revoked', 1);
        $this->api('GET', '/api/v1/auth/me', token: $guardian->json('data.access_token'))
            ->assertStatus(401)->assertJsonPath('error.message', 'Un administrador cerró su sesión.');
        $this->api('POST', "/api/v1/users/{$id}/revoke-sessions", token: $this->coach())->assertForbidden();
    }

    public function test_expired_access_token_is_rejected_and_renewed_with_refresh(): void
    {
        $login = $this->loginResponse('apoderado@marea.cl');
        Carbon::setTestNow(now()->addMinutes(16));

        $this->api('GET', '/api/v1/auth/me', token: $login->json('data.access_token'))
            ->assertStatus(401)->assertJsonPath('error.message', 'El token ha expirado.');

        $refreshed = $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $this->refreshCookie($login)]);
        $refreshed->assertOk()->assertJsonPath('data.session_id', $login->json('data.session_id'));
        $this->api('GET', '/api/v1/auth/me', token: $refreshed->json('data.access_token'))->assertOk();
    }

    public function test_session_closes_after_inactivity(): void
    {
        $login = $this->loginResponse('apoderado@marea.cl');
        Carbon::setTestNow(now()->addMinutes(31));

        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $this->refreshCookie($login)])
            ->assertStatus(401)->assertJsonPath('error.message', 'La sesión se cerró por inactividad.');
    }

    public function test_session_has_an_absolute_lifetime_even_with_activity(): void
    {
        $login = $this->loginResponse('apoderado@marea.cl');
        $cookie = $this->refreshCookie($login);

        // Actividad cada 10 minutos durante 7 h 50 min: la sesión sigue viva.
        for ($i = 0; $i < 47; $i++) {
            Carbon::setTestNow(now()->addMinutes(10));
            $response = $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $cookie]);
            $response->assertOk();
            $cookie = $this->refreshCookie($response);
        }

        Carbon::setTestNow(now()->addMinutes(11)); // supera las 8 horas
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $cookie])
            ->assertStatus(401)->assertJsonPath('error.message', 'La sesión ha expirado. Inicie sesión nuevamente.');
    }

    public function test_cookie_endpoints_reject_foreign_origins(): void
    {
        $this->loginResponse('apoderado@marea.cl', ['Origin' => 'https://evil.example'])->assertForbidden();
        $this->loginResponse('apoderado@marea.cl', ['Origin' => 'null'])->assertForbidden();
        $this->loginResponse('apoderado@marea.cl', ['Sec-Fetch-Site' => 'cross-site'])->assertForbidden();
        $this->loginResponse('apoderado@marea.cl', ['Origin' => 'http://localhost:5173', 'Sec-Fetch-Site' => 'same-origin'])->assertOk();
    }

    public function test_cors_never_allows_unknown_origins_even_on_preflight(): void
    {
        $preflight = $this->api('OPTIONS', '/api/v1/auth/refresh', headers: ['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST']);
        // Con un único origen permitido se responde siempre ese origen: el navegador bloquea al resto.
        self::assertNotSame('https://evil.example', $preflight->headers->get('Access-Control-Allow-Origin'));
        self::assertNotSame('*', $preflight->headers->get('Access-Control-Allow-Origin'));

        $ok = $this->api('OPTIONS', '/api/v1/auth/refresh', headers: ['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST']);
        self::assertSame('http://localhost:5173', $ok->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $ok->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_login_is_limited_per_ip_against_credential_stuffing(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->api('POST', '/api/v1/auth/login', ['email' => "nadie{$i}@marea.cl", 'password' => 'x']);
        }

        $this->loginResponse('apoderado@marea.cl')->assertStatus(429);
    }

    public function test_sql_injection_attempts_are_rejected_or_harmless(): void
    {
        $this->api('POST', '/api/v1/auth/login', ['email' => "admin@marea.cl' OR '1'='1", 'password' => "' OR '1'='1"])->assertStatus(422);
        $this->api('POST', '/api/v1/auth/login', ['email' => 'admin@marea.cl', 'password' => "' OR '1'='1' --"])->assertStatus(401);

        $admin = $this->admin();
        $this->api('GET', "/api/v1/users?role=admin' OR '1'='1", token: $admin)->assertStatus(422);
        $this->api('GET', "/api/v1/practices?from=2026-10-01' OR 1=1 --&to=2026-10-31", token: $admin)->assertStatus(422);
        $this->api('GET', '/api/v1/athletes/1%20OR%201=1', token: $this->guardian())->assertNotFound();
        self::assertSame(1, User::query()->where('role', 'admin')->count());
    }

    public function test_permissions_are_returned_to_the_frontend_per_role(): void
    {
        $coach = $this->loginResponse('entrenador@marea.cl')->json('data.user.permissions');
        self::assertContains('practices.manage', $coach);
        self::assertNotContains('finance.manage', $coach);
        self::assertNotContains('practices.plan', $coach);

        $admin = $this->loginResponse('admin@marea.cl')->json('data.user.permissions');
        self::assertContains('users.manage', $admin);
        self::assertNotContains('finance.own', $admin);
    }

    public function test_admin_cannot_demote_or_deactivate_himself(): void
    {
        $token = $this->admin();
        $this->api('PATCH', '/api/v1/users/1', ['role' => 'coach'], $token)->assertStatus(409);
        $this->api('PATCH', '/api/v1/users/1', ['active' => false], $token)->assertStatus(409);
        $this->api('PATCH', '/api/v1/users/1', ['phone' => '+56 9 0000 0000'], $token)->assertOk();
    }

    public function test_referenced_users_must_have_the_right_role(): void
    {
        $token = $this->admin();
        $coachId = (int) User::query()->where('email', 'entrenador@marea.cl')->value('id');
        $guardianId = (int) User::query()->where('email', 'apoderado@marea.cl')->value('id');

        $this->api('POST', '/api/v1/athletes', ['first_name' => 'Ana', 'last_name' => 'Pérez', 'monthly_fee' => 30000, 'guardian_id' => $coachId], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['guardian_id'], 'error.errors');
        $this->api('POST', '/api/v1/groups', ['name' => 'Grupo X', 'coach_id' => $guardianId], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['coach_id'], 'error.errors');
    }

    public function test_each_role_is_limited_to_its_permissions(): void
    {
        $matrix = [
            'entrenador@marea.cl' => ['GET /api/v1/users' => 403, 'GET /api/v1/finance/charges' => 403, 'POST /api/v1/practices' => 403, 'GET /api/v1/me/charges' => 403],
            'apoderado@marea.cl' => ['GET /api/v1/users' => 403, 'GET /api/v1/schedules' => 403, 'PATCH /api/v1/practices/1' => 403, 'GET /api/v1/me/charges' => 200],
            'alumno@marea.cl' => ['POST /api/v1/events' => 403, 'POST /api/v1/venues' => 403, 'GET /api/v1/me/charges' => 200],
        ];

        foreach ($matrix as $email => $checks) {
            $token = (string) $this->loginResponse($email)->json('data.access_token');
            foreach ($checks as $call => $status) {
                [$method, $uri] = explode(' ', $call);
                $this->api($method, $uri, [], $token)->assertStatus($status);
            }
        }
    }
}
