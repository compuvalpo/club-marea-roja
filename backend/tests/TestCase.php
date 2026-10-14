<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * Base de pruebas de la API: SQLite en memoria, datos demo y reloj congelado.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public const TODAY = '2026-10-05 10:00:00';

    /** Ejecuta DemoSeeder tras cada migración de prueba. */
    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::TODAY, 'America/Santiago'));
        config(['jwt.secret' => str_repeat('t', 64), 'jwt.cookie.secure' => false]);
        $this->seed(DemoSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function login(string $email, string $password = DemoSeeder::PASSWORD): string
    {
        $response = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        $response->assertOk();

        return (string) $response->json('data.access_token');
    }

    protected function admin(): string
    {
        return $this->login('admin@marea.cl');
    }

    protected function coach(): string
    {
        return $this->login('entrenador@marea.cl');
    }

    protected function guardian(): string
    {
        return $this->login('apoderado@marea.cl');
    }

    protected function athlete(): string
    {
        return $this->login('alumno@marea.cl');
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $cookies
     * @param  array<string, string>  $headers
     */
    protected function api(string $method, string $uri, ?array $body = null, ?string $token = null, array $cookies = [], array $headers = []): TestResponse
    {
        // Cada llamada simula un navegador nuevo (sin estado de autenticación previo).
        $this->app['auth']->forgetGuards();
        $request = $this->withHeaders($headers + ($token ? ['Authorization' => 'Bearer '.$token] : []));
        foreach ($cookies as $name => $value) {
            $request = $request->withCredentials()->withUnencryptedCookie($name, $value);
        }

        $response = $request->json($method, $uri, $body ?? []);
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;

        return $response;
    }

    protected function refreshCookie(TestResponse $response): string
    {
        return (string) $response->getCookie('refresh_token', false)?->getValue();
    }
}
