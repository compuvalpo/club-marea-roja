<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function test_login_returns_access_token_and_http_only_refresh_cookie(): void
    {
        $response = $this->api('POST', '/api/v1/auth/login', ['email' => 'ADMIN@marea.cl', 'password' => 'Marea2026!']);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonMissingPath('data.user.password');
        $cookie = $response->getCookie('refresh_token', false);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/v1/auth', $cookie->getPath());
    }

    public function test_invalid_credentials_return_401_with_generic_message(): void
    {
        $this->api('POST', '/api/v1/auth/login', ['email' => 'admin@marea.cl', 'password' => 'incorrecta'])
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'Credenciales inválidas.');
    }

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/api/v1/auth/login', ['email' => 'admin@marea.cl', 'password' => 'mala-'.$i]);
        }

        $this->api('POST', '/api/v1/auth/login', ['email' => 'admin@marea.cl', 'password' => 'Marea2026!'])->assertStatus(429);
    }

    public function test_refresh_rotates_token_and_detects_reuse(): void
    {
        $old = $this->refreshCookie($this->api('POST', '/api/v1/auth/login', ['email' => 'apoderado@marea.cl', 'password' => 'Marea2026!']));

        $refreshed = $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $old]);
        $refreshed->assertOk();
        $new = $this->refreshCookie($refreshed);
        self::assertNotSame($old, $new);

        // Pasado el margen de concurrencia, reutilizar el token anterior se trata como robo: se cierra la sesión.
        Carbon::setTestNow(now()->addSeconds(30));
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $old])->assertStatus(401);
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $new])->assertStatus(401);
        $this->api('GET', '/api/v1/auth/me', token: (string) $refreshed->json('data.access_token'))->assertStatus(401);
    }

    public function test_simultaneous_refresh_from_two_tabs_does_not_close_the_session(): void
    {
        $old = $this->refreshCookie($this->api('POST', '/api/v1/auth/login', ['email' => 'apoderado@marea.cl', 'password' => 'Marea2026!']));
        $new = $this->refreshCookie($this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $old]));

        // La otra pestaña usó el token anterior un instante después: 401 para reintentar, sin revocar.
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $old])
            ->assertStatus(401)->assertJsonPath('error.message', 'La sesión se renovó en otra pestaña. Reintente.');
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $new])->assertOk();
    }

    public function test_logout_revokes_refresh_token(): void
    {
        $token = $this->refreshCookie($this->api('POST', '/api/v1/auth/login', ['email' => 'alumno@marea.cl', 'password' => 'Marea2026!']));

        $this->api('POST', '/api/v1/auth/logout', cookies: ['refresh_token' => $token])->assertNoContent();
        $this->api('POST', '/api/v1/auth/refresh', cookies: ['refresh_token' => $token])->assertStatus(401);
    }

    public function test_protected_routes_require_bearer_token(): void
    {
        $this->api('GET', '/api/v1/auth/me')->assertStatus(401);
        $this->api('GET', '/api/v1/auth/me', token: 'basura')->assertStatus(401);
        $this->api('GET', '/api/v1/auth/me', token: $this->coach())->assertOk()->assertJsonPath('data.email', 'entrenador@marea.cl');
    }

    public function test_responses_include_security_and_cors_headers(): void
    {
        $preflight = $this->api('OPTIONS', '/api/v1/auth/login', headers: ['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST']);
        self::assertSame('http://localhost:5173', $preflight->headers->get('Access-Control-Allow-Origin'));

        $evil = $this->api('GET', '/api/health', headers: ['Origin' => 'https://evil.example']);
        self::assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('nosniff', $evil->headers->get('X-Content-Type-Options'));
    }
}
