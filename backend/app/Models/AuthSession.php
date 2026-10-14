<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesión de un usuario en un navegador/dispositivo (revocable en servidor).
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $last_activity_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 */
class AuthSession extends Model
{
    /** Motivos de cierre (se informan al usuario en el 401). */
    public const REASON_LOGOUT = 'logout';

    public const REASON_REPLACED = 'replaced';

    public const REASON_PERMISSIONS = 'permissions_changed';

    public const REASON_ADMIN = 'revoked_by_admin';

    public const REASON_REUSE = 'token_reuse';

    public const REASON_IDLE = 'idle';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'user_id', 'ip_address', 'user_agent', 'last_activity_at', 'expires_at', 'revoked_at', 'revoked_reason'];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RefreshToken, $this> */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'session_id');
    }

    /**
     * @param  Builder<AuthSession>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isIdle(): bool
    {
        return $this->last_activity_at->copy()->addSeconds((int) config('jwt.idle_ttl'))->isPast();
    }

    /** Mensaje para el usuario según por qué ya no es válida la sesión. */
    public function closedMessage(): string
    {
        return match ($this->revoked_reason) {
            self::REASON_REPLACED => 'Se inició sesión en otra pestaña o dispositivo. Esta sesión fue cerrada.',
            self::REASON_PERMISSIONS => 'Sus permisos cambiaron. Inicie sesión nuevamente.',
            self::REASON_ADMIN => 'Un administrador cerró su sesión.',
            self::REASON_IDLE => 'La sesión se cerró por inactividad.',
            self::REASON_LOGOUT => 'La sesión fue cerrada.',
            default => $this->revoked_at === null && $this->expires_at->isPast()
                ? 'La sesión ha expirado. Inicie sesión nuevamente.'
                : 'Sesión inválida. Inicie sesión nuevamente.',
        };
    }
}
