<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\JwtService;
use Firebase\JWT\JWT;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JwtServiceTest extends TestCase
{
    private const SECRET = 'tttttttttttttttttttttttttttttttttttttttttttttttttttttttttttttttt';

    private JwtService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtService(self::SECRET, 'marea-roja-api', 900, 'marea-roja-web');
    }

    public function test_issues_and_verifies_token_with_session_and_type(): void
    {
        $user = User::query()->where('email', 'entrenador@marea.cl')->firstOrFail();
        $token = $this->jwt->issue($user, 'sesion-1');

        self::assertSame(['user_id' => $user->id, 'session_id' => 'sesion-1'], $this->jwt->verify($token));
        $header = json_decode((string) base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true);
        self::assertSame(['typ' => 'at+jwt', 'alg' => 'HS256'], $header);
    }

    public function test_rejects_expired_token(): void
    {
        $token = $this->jwt->issue(User::query()->firstOrFail(), 's');
        Carbon::setTestNow(now()->addMinutes(16));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('expirado');
        $this->jwt->verify($token);
    }

    public function test_rejects_tampered_token(): void
    {
        [$h, , $s] = explode('.', $this->jwt->issue(User::query()->firstOrFail(), 's'));
        $payload = rtrim(strtr(base64_encode((string) json_encode(['sub' => '1', 'sid' => 's', 'role' => 'admin', 'iss' => 'marea-roja-api', 'aud' => 'marea-roja-web', 'exp' => PHP_INT_MAX])), '+/', '-_'), '=');

        $this->expectException(AuthenticationException::class);
        $this->jwt->verify("{$h}.{$payload}.{$s}");
    }

    public function test_rejects_alg_none(): void
    {
        $enc = static fn (array $a): string => rtrim(strtr(base64_encode((string) json_encode($a)), '+/', '-_'), '=');
        $token = $enc(['typ' => 'at+jwt', 'alg' => 'none']).'.'.$enc(['sub' => '1', 'sid' => 's', 'iss' => 'marea-roja-api', 'aud' => 'marea-roja-web', 'exp' => now()->addHour()->getTimestamp()]).'.';

        $this->expectException(AuthenticationException::class);
        $this->jwt->verify($token);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function invalidClaims(): iterable
    {
        yield 'otro emisor' => [['iss' => 'otro'], ['typ' => 'at+jwt']];
        yield 'otra audiencia' => [['aud' => 'otra-app'], ['typ' => 'at+jwt']];
        yield 'sin sesión' => [['sid' => null], ['typ' => 'at+jwt']];
        yield 'tipo JWT genérico' => [[], ['typ' => 'JWT']];
    }

    /**
     * @param  array<string, mixed>  $override
     * @param  array<string, string>  $head
     */
    #[DataProvider('invalidClaims')]
    public function test_rejects_tokens_with_invalid_claims(array $override, array $head): void
    {
        $claims = array_filter($override + ['iss' => 'marea-roja-api', 'aud' => 'marea-roja-web', 'sub' => '1', 'sid' => 's', 'exp' => now()->addHour()->getTimestamp()], static fn ($v) => $v !== null);
        $token = JWT::encode($claims, self::SECRET, 'HS256', null, $head);

        $this->expectException(AuthenticationException::class);
        $this->jwt->verify($token);
    }

    public function test_requires_strong_secret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JwtService('corto', 'x', 900);
    }
}
